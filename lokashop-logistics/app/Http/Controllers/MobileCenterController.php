<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};
use App\Services\{ApiToken, Municipalities};

/** Native center DTOs. The web controllers remain the single workflow authority. */
class MobileCenterController
{
    private function user(Request $r) {
        $u = ApiToken::user($r->bearerToken());
        abort_unless(MobileWorkspaceController::allowed($u) && $u->role === 'sorting_center', 401, 'Sign in with an approved sorting center account.');
        Auth::setUser($u); $r->setUserResolver(fn() => $u);
        abort_unless(DB::table('sorting_centers')->where('user_id', $u->id)->exists(), 403);
        DB::table('users')->where('id', $u->id)->update(['last_seen_at' => now()]);
        return $u;
    }
    private function data($view): array { return $view->getData(); }
    private function options($rows, string $label = 'name'): array {
        return collect($rows)->map(fn($x) => ['value' => (string)$x->id, 'label' => (string)($x->$label ?? '#'.$x->id)])->values()->all();
    }
    private function field(string $name, string $label, string $type = 'text', array $extra = []): array {
        return array_merge(['name'=>$name,'label'=>$label,'type'=>$type,'required'=>true], $extra);
    }
    private function action(string $key, string $label, int $id = 0, array $fields = [], array $fixed = []): array {
        return compact('key','label','id','fields','fixed');
    }
    private function card(string $title, array $facts = [], array $actions = [], string $status = ''): array {
        return compact('title','facts','actions','status');
    }
    private function facts($row, array $labels): array {
        $row = (array)$row; $out = [];
        foreach ($labels as $key=>$label) if (isset($row[$key]) && is_scalar($row[$key])) $out[] = ['label'=>$label,'value'=>(string)$row[$key]];
        return $out;
    }
    private function page($rows, callable $card, string $parameter = 'page'): array {
        $paged = $rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;
        return ['records'=>collect($paged?$rows->items():$rows)->map($card)->values()->all(),
            'current_page'=>$paged?$rows->currentPage():1,'last_page'=>$paged?$rows->lastPage():1,'page_parameter'=>$parameter];
    }
    private function reason(): array { return $this->field('reason','Reason (at least 10 characters)','text',['min_length'=>10,'max_length'=>300,'multiline'=>true]); }
    private function decision(int $id, string $key): array {
        return [$this->action($key,'Approve',$id,[],['decision'=>'approved']),
            $this->action($key,'Reject',$id,[$this->reason()],['decision'=>'rejected'])];
    }
    private function docs(int $user): array {
        return DB::table('registration_documents')->where('user_id',$user)->get(['id','kind'])->map(fn($d)=>[
            'label'=>'Save '.\App\Services\DocumentLabels::label($d->kind), 'download'=>'/api/center/documents/'.$d->id])->all();
    }
    private function parcel($p, array $actions = []): array {
        return $this->card($p->tracking_code ?? 'Parcel #'.$p->id,
            $this->facts($p,['order_id'=>'Order','status'=>'Parcel status','city'=>'Municipality','province'=>'Province','updated_at'=>'Updated']),$actions,$p->status??'');
    }
    public function index(Request $r, string $module) {
        $u = $this->user($r);
        $r->validate(['page'=>'nullable|integer|min:1','trips_page'=>'nullable|integer|min:1','legacy_page'=>'nullable|integer|min:1','q'=>'nullable|string|max:150']);
        $out = ['title'=>'Sorting center','records'=>[],'actions'=>[],'current_page'=>1,'last_page'=>1,'page_parameter'=>'page'];
        $L = app(LogisticsController::class);
        switch ($module) {
        case 'overview':
            $d=$this->data(app(LogisticsWorkspaceController::class)->overview($r));
            $out['title']=$d['center']->name ?? 'Sorting center';
            $out['charts']=\App\Services\OverviewCharts::data();$out['metrics']=$d['cards'];$out['metrics'][]=['label'=>'Unread notifications','value'=>DB::table('notifications')->where('user_id',$u->id)->whereNull('read_at')->count()];
            $out['metrics'][]=['label'=>'Delivery areas','value'=>DB::table('delivery_areas')->where('center_id',$d['center']->id)->count()];
            $out['metrics'][]=['label'=>'Incoming transfers','value'=>$d['incomingTransfers']];
            $out['metrics'][]=['label'=>'Unremitted COD','value'=>'PHP '.number_format($d['unremittedCod'],2)];
            $out['records']=collect($d['recent'])->map(fn($p)=>$this->parcel($p))->all();
            break;
        case 'pickups':
            $d=$this->data($L->pickups()); $out['title']='Pickups';
            $out['notice']='Assign an active local area rider. Seller requests expire after 12 hours; rider offers expire after 3 hours.';
            $out['tabs']=['request'=>'Pickup requests','ongoing'=>'Ongoing pickup','completed'=>'Completed pickup'];
            $out=array_merge($out,$this->page($d['requests'],function($p)use($d){
                $actions=[];$eligible=$d['eligible'][$p->id]??collect();
                if(in_array($p->status,['requested','expired','declined']) && !($p->status==='expired' && $p->rider_id===null) && count($eligible))
                    $actions[]=$this->action('pickup-assign','Assign pickup rider',$p->id,[$this->field('rider_id','Area rider','select',['options'=>$this->options($eligible)])]);
                $c=$this->parcel($p,$actions);$origin=$d['origins'][$p->id]??null;
                if($origin)$c['facts']=array_merge($c['facts'],$this->facts($origin,['barangay'=>'Seller barangay','city'=>'Seller municipality']));
                return $c;
            })); break;
        case 'deliveries':
            $d=$this->data($L->deliveries());$out['title']='Deliveries';$out['notice']='Buyer addresses match service areas automatically. If no rider is available, check Areas and courier availability.';$out['tabs']=['request'=>'Delivery requests','ongoing'=>'Ongoing delivery','completed'=>'Completed delivery'];
            $out=array_merge($out,$this->page($d['parcels'],function($p)use($d){
                $actions=[];$riders=$d['riders'][$p->id]??collect();
                if(in_array($p->status,['AT_SORTING_CENTER','SORTED','DELIVERY_FAILED','ASSIGNED_TO_RIDER']) && !($d['offers'][$p->id]??null) && count($riders))
                    $actions[]=$this->action('delivery-assign','Assign delivery rider',$p->id,[$this->field('rider_id','Active area rider','select',['options'=>$this->options($riders)])]);
                $c=$this->parcel($p,$actions);$a=$d['addresses'][$p->id]??null;
                if($a)$c['facts']=array_merge($c['facts'],$this->facts($a,['recipient'=>'Recipient','barangay'=>'Buyer barangay','city'=>'Buyer municipality']));
                return $c;
            }));break;
        case 'transfers':
            $d=$this->data(app(TransferController::class)->index());$out['title']='Bulk transfers';$out['notice']='Create a trip from eligible parcels and an active driver. The driver must hand over before the destination center confirms receipt.';$out['tabs']=['create'=>'Create transfer','active'=>'Active transfers','incoming'=>'Incoming transfers','completed'=>'Completed transfers'];
            $tab=$r->input('tab','create');
            if($tab==='create' && count($d['drivers']) && count($d['bulkSelectable'])) {
                $choices=collect($d['bulkSelectable'])->map(fn($p)=>['value'=>(string)$p->id,'label'=>$p->tracking_code.' → '.($d['destinations'][$p->id]->name??'Destination center')])->values()->all();
                $out['actions'][]=$this->action('trip-create','Create bulk transfer',0,[$this->field('driver_id','Active driver','select',['options'=>$this->options($d['drivers'])]),$this->field('parcel_ids','Parcels for this trip','multi',['options'=>$choices])]);
            }
            if($tab==='incoming') {
                $out['records']=collect($d['bulkIncoming'])->map(fn($t)=>$this->trip($t,true))->values()->all();
                foreach($d['incoming'] as $p){$actions=[];if($p->status==='IN_TRANSIT_CENTER')$actions[]=$this->action('transfer-receive','Confirm parcel receipt',$p->id,[],['confirm_receipt'=>true]);$out['records'][]=$this->parcel($p,$actions);}
            } else {
                $out=array_merge($out,$this->page($d['trips'],fn($t)=>$this->trip($t,false),'trips_page'));
                if(in_array($tab,['create','active']))$out['secondary']=$this->page($d['parcels'],fn($p)=>$this->parcel($p));
                if($tab==='completed'){
                    $out['secondary']=$this->page($d['completedLegacy'],fn($p)=>$this->card($p->tracking_code,$this->facts($p,['order_id'=>'Order','name'=>'Driver','updated_at'=>'Received'])),'legacy_page');
                }
            }
            break;
        case 'areas':
            $d=$this->data($L->areas());$out['title']='Pickup and delivery areas';$riders=$this->options($d['riders']);$c=$d['registeredCenter'];
            $out['actions'][]=$this->action('area-create','Add area',0,[$this->field('name','Area name'),$this->field('address','Service location','place',['initial'=>['city'=>$c->city,'province'=>$c->province]]),$this->field('rider_id','Local rider','select',['options'=>$riders])]);
            $out['records']=collect($d['areas'])->map(fn($a)=>$this->card($a->name,array_merge($this->facts($a,['barangay'=>'Barangay','city'=>'Municipality','province'=>'Province']),[['label'=>'Assigned rider','value'=>DB::table('users')->where('id',$a->rider_id)->where('company_id',auth()->id())->value('name')??'No rider']]),[
                $this->action('area-manage','Change rider',$a->id,[$this->field('rider_id','Local rider','select',['options'=>$riders])],['action'=>'assign']),
                $this->action('area-manage','Delete area',$a->id,[],['action'=>'delete'])]))->values()->all();break;
        case 'couriers':
            $d=$this->data(app(CourierSetupController::class)->team($r));$out['title']='Couriers';$out['tabs']=['rider'=>'Riders','driver'=>'Drivers'];$out['tab_parameter']='type';
            $out['actions'][]=$this->invite();
            $out=array_merge($out,$this->page($d['members'],function($m){
                $actions=[['label'=>'View courier details','module'=>'courier','query'=>['id'=>(string)$m->id]]];
                if($m->approval_status==='approved')$actions[]=$this->action('courier-status',$m->active?'Suspend courier':'Reactivate courier',$m->id,[$this->reason()],['active'=>!$m->active]);
                if($m->approval_status==='pending_activation')$actions[]=$this->action('courier-resend','Resend activation',$m->id);
                return $this->card($m->name,$this->facts($m,['email'=>'Email','phone'=>'Phone','vehicle'=>'Vehicle','approval_status'=>'Approval','account_status'=>'Account','online'=>'Online']),$actions);
            }));break;
        case 'courier':
            $id=$r->validate(['id'=>'required|integer|min:1'])['id'];$d=$this->data(app(CenterManagementController::class)->show($id));$out['title']=$d['member']->name;
            $out['records'][]=$this->card('Courier details',$this->facts($d['member'],['name'=>'Name','email'=>'Email','phone'=>'Phone','courier_type'=>'Role','vehicle'=>'Vehicle','plate_number'=>'Plate','license_number'=>'License','license_expires_at'=>'License expiry','approval_status'=>'Approval','account_status'=>'Account']),$this->docs($id));
            if($d['address'])$out['records'][]=$this->card('Registered address',$this->facts($d['address'],['street'=>'Street','barangay'=>'Barangay','city'=>'Municipality','province'=>'Province']));
            foreach($d['jobs'] as $status=>$count)$out['records'][]=$this->card(ucwords(strtolower(str_replace('_',' ',$status))),[['label'=>'Jobs','value'=>(string)$count]]);
            break;
        case 'applications':
            $d=$this->data(app(LogisticsAdminController::class)->approvals());$out['title']='Courier applications';$out['tabs']=['pending'=>'Pending review','approved'=>'Approved','rejected'=>'Rejected'];$out['tab_parameter']='status';
            $out=array_merge($out,$this->page($d['users'],fn($m)=>$this->card($m->name,$this->facts($m,['email'=>'Email','phone'=>'Phone','courier_type'=>'Role','vehicle'=>'Vehicle','approval_status'=>'Approval']),array_merge($this->docs($m->id),$m->approval_status==='pending'?$this->decision($m->id,'application-review'):($m->approval_status==='pending_activation'?[$this->action('courier-resend','Resend activation',$m->id)]:[])))));break;
        case 'appeals':
            $d=$this->data(app(CenterManagementController::class)->appeals());$out['title']='Courier appeals';
            $out=array_merge($out,$this->page($d['appeals'],fn($a)=>$this->card($a->name,$this->facts($a,['message'=>'Appeal','status'=>'Status','review_reason'=>'Review reason','created_at'=>'Submitted']),$a->status==='pending'?[
                $this->action('appeal-review','Approve appeal',$a->id,[$this->reason()],['decision'=>'approved']),$this->action('appeal-review','Reject appeal',$a->id,[$this->reason()],['decision'=>'rejected'])]:[])));break;
        case 'rates': case 'cod': case 'returns': case 'tracking':
            $d=$this->data(app(OperationsController::class)->panel($r,$module));$out['title']=['rates'=>'Shipping rates','cod'=>'COD remittances','returns'=>'Returned parcels','tracking'=>'Parcel tracking'][$module];
            if($module==='rates'){$c=DB::table('sorting_centers')->where('user_id',$u->id)->first();$out['actions'][]=$this->rate(null,$c);}
            $out=array_merge($out,$this->page(\App\Services\ProcessRouting::paginate(collect($d['records'])->values()),function($x)use($module,$u){
                $actions=[];
                if($module==='cod' && $x->status!=='confirmed')$actions[]=$this->action('cod-confirm','Confirm remittance',$x->id);
                if($module==='returns')$actions[]=$this->action('parcel-return','Confirm returned to seller',$x->id);
                if($module==='tracking' && !in_array($x->status,['COMPLETED','CANCELLED','REFUNDED','RETURN_RECEIVED','DELIVERED'])){
                    $actions[]=$this->action('parcel-exception','Report lost / damaged',$x->id,[$this->field('status','Incident','select',['options'=>[['value'=>'LOST','label'=>'Lost'],['value'=>'DAMAGED','label'=>'Damaged']]]),$this->reason(),$this->field('proof','Evidence photo (required for damage)','file',['required'=>false])]);
                    $actions[]=$this->action('parcel-override','Correct operational status',$x->id,[$this->field('status','Status','select',['options'=>array_map(fn($s)=>['value'=>$s,'label'=>ucwords(strtolower(str_replace('_',' ',$s)))],['AT_SORTING_CENTER','SORTED','DELIVERY_FAILED','RETURN_TO_SENDER'])]),$this->reason()]);
                }
                if($module==='rates')$actions[]=$this->rate($x,DB::table('sorting_centers')->where('user_id',$u->id)->first());
                return $this->card($x->tracking_code??($module==='rates'?($x->city.' shipping rate'):('Remittance #'.$x->id)),$this->facts($x,['status'=>'Status','buyer'=>'Buyer','amount'=>'Amount (PHP)','expected'=>'Expected (PHP)','reference'=>'Reference','base_fee'=>'Base fee (PHP)','extra_kg_fee'=>'Extra kg (PHP)','active'=>'Active','city'=>'Municipality','province'=>'Province','updated_at'=>'Updated']),$actions,$x->status??'');
            }));break;
        case 'reports': case 'finance':
            $r->query->remove('format');$r->request->remove('format');
            $d=$this->data($module==='finance'?app(CenterFinancialController::class)->index($r):app(ReportController::class)->index($r));$out['title']=$module==='finance'?'Financial report':'Operations report';
            if($module==='finance'){$out['metrics']=[['label'=>'Wallet (PHP)','value'=>number_format($d['balance'],2)],['label'=>'Legacy funding owed (PHP)','value'=>number_format($d['owed'],2)]];foreach($this->facts($d['stats'],['income'=>'Shipping income','courier_cost'=>'Courier cost','settled'=>'Settlements','cod_collected'=>'COD collected','cod_received'=>'COD received']) as $stat)$out['metrics'][]=$stat;}
            $out['export']='/api/center/export/'.$module;
            $rows=$module==='finance'?$d['rows']:\App\Services\ProcessRouting::paginate(collect($d['records'])->values());
            $out=array_merge($out,$this->page($rows,fn($x)=>$this->card($x->tracking_code??($x->party??'Record #'.($x->source_id??$x->id)),$this->facts($x,['order_id'=>'Order','status'=>'Status','attempts'=>'Attempts','type'=>'Type','recorded_at'=>'Date','created_at'=>'Created','reference'=>'Reference','amount'=>'Amount (PHP)','expected'=>'Expected (PHP)']))));break;
        case 'address':
            $a=Municipalities::address($u->id);$out['title']='Registered address';
            if($a)$out['records'][]=$this->card('Service address',$this->facts($a,['recipient'=>'Recipient','phone'=>'Phone','street'=>'Street','barangay'=>'Barangay','city'=>'Municipality','province'=>'Province','postal_code'=>'Postal code']));
            $out['actions'][]=$this->action('address-save','Edit registered address',0,[
                $this->field('recipient','Recipient','text',['initial'=>$a->recipient??$u->name]),$this->field('phone','Phone','text',['initial'=>$a->phone??$u->phone]),
                $this->field('location','Address location','place',['initial'=>['province'=>$a->province??'','city'=>$a->city??'','barangay'=>$a->barangay??'']]),
                $this->field('street','Street','text',['initial'=>$a->street??'']),$this->field('postal_code','Postal code','text',['initial'=>$a->postal_code??''])]);break;
        case 'notifications':
            $out['title']='Notifications';$out['actions'][]=$this->action('notifications-read','Mark all as read');
            $out=array_merge($out,$this->page(DB::table('notifications')->where('user_id',$u->id)->orderByDesc('id')->paginate(12),fn($n)=>$this->card($n->body,$this->facts($n,['created_at'=>'Received','read_at'=>'Read']))));break;
        default: abort(404);
        }
        return response()->json($out)->header('Cache-Control','private, no-store');
    }
    private function trip(array $t, bool $incoming): array {
        $facts=[['label'=>'Driver','value'=>$t['driver_name']??'Assigned driver'],['label'=>'Origin','value'=>$t['origin_name']??'Origin center'],['label'=>'Status','value'=>$t['status']]];$actions=[];
        if(!$incoming && $t['status']==='offered')$actions[]=$this->action('trip-cancel','Cancel unaccepted trip',$t['id']);
        foreach($t['stops'] as $s){
            $facts[]=['label'=>'Stop','value'=>$s['center_name'].' · '.$s['city']];
            foreach($s['parcels'] as $p)$facts[]=['label'=>$p->tracking_code,'value'=>$p->received_at?'Received':($s['handed_over_at']?'Driver handed over':'Awaiting driver handover')];
            if($incoming && $s['handed_over_at'] && !$s['received_at']){
                $choices=collect($s['parcels'])->filter(fn($p)=>!$p->received_at)->map(fn($p)=>['value'=>(string)$p->parcel_id,'label'=>$p->tracking_code])->values()->all();
                if($choices)$actions[]=$this->action('stop-receive','Confirm received parcels',$s['id'],[$this->field('parcel_ids','Parcels physically received','multi',['options'=>$choices])],['confirm_receipt'=>true]);
            }
        }
        return $this->card('Bulk trip #'.$t['id'],$facts,$actions,$t['status']);
    }
    private function rate($x,$c): array {
        return $this->action('rate-save',$x?'Edit rate':'Add shipping rate',$x->id??0,[
            $this->field('origin_province','Origin province (optional)','text',['required'=>false,'initial'=>$x->origin_province??'']),
            $this->field('province','Destination province','text',['initial'=>$c->province,'readonly'=>true]),$this->field('city','Destination municipality','text',['initial'=>$c->city,'readonly'=>true]),
            $this->field('base_fee','Base fee (PHP)','money',['initial'=>(string)($x->base_fee??50)]),$this->field('extra_kg_fee','Extra kg fee (PHP)','money',['initial'=>(string)($x->extra_kg_fee??0)]),
            $this->field('active','Active','select',['options'=>[['value'=>'1','label'=>'Yes'],['value'=>'0','label'=>'No']],'initial'=>(string)($x->active??1)]),$this->reason()]);
    }
    private function invite(): array {
        $fields=[];foreach(['first_name'=>'First name','last_name'=>'Last name','email'=>'Email','phone'=>'Phone','date_of_birth'=>'Date of birth','license_number'=>'License number','license_expires_at'=>'License expiry','plate_number'=>'Plate number'] as $k=>$label)$fields[]=$this->field($k,$label,in_array($k,['date_of_birth','license_expires_at'])?'date':'text');
        $fields[]=$this->field('courier_type','Courier role','select',['options'=>[['value'=>'rider','label'=>'Rider'],['value'=>'driver','label'=>'Driver']]]);
        $fields[]=$this->field('vehicle','Vehicle','select',['options'=>array_map(fn($s)=>['value'=>$s,'label'=>ucfirst($s)],['motorcycle','tricycle','4-wheel truck','6-wheel truck','8-wheel truck','10-wheel truck'])]);
        $fields[]=$this->field('id_document','ID document','document');$fields[]=$this->field('permit_document','Permit document','document');
        return $this->action('courier-invite','Invite courier',0,$fields);
    }
    public function mutate(Request $r,string $action,int $id=0) {
        $this->user($r);
        $r->headers->set('Accept','application/json');
        // Explicit targets prevent method/path injection. Each handler rechecks ownership and workflow state.
        $result=match($action){
            'pickup-assign'=>app(LogisticsController::class)->verifyPickup($r,$id),
            'delivery-assign'=>app(LogisticsController::class)->assignDelivery($r,$id),
            'trip-create'=>app(DriverTripController::class)->create($r),
            'trip-cancel'=>app(DriverTripController::class)->cancel($r,$id),
            'stop-receive'=>app(DriverTripController::class)->receive($r,$id),
            'transfer-receive'=>app(TransferController::class)->receive($id),
            'area-create'=>$this->createArea($r),
            'area-manage'=>app(CenterManagementController::class)->area($r,$id),
            'courier-status'=>$this->courierStatus($r,$id),
            'courier-resend'=>$this->resend($r,$id),
            'courier-invite'=>app(OperationsController::class)->rider($r),
            'application-review'=>app(ReviewController::class)->approve($r,$id),
            'appeal-review'=>app(CenterManagementController::class)->review($r,$id),
            'rate-save'=>$this->saveRate($r,$id),
            'cod-confirm'=>$this->confirmCod($r,$id),
            'parcel-return'=>$this->parcelAction($r,$id,'returned'),
            'parcel-exception'=>$this->parcelAction($r,$id,'exception'),
            'parcel-override'=>$this->parcelAction($r,$id,'override'),
            'address-save'=>app(AccountController::class)->address($r),
            'notifications-read'=>app(MobileRiderController::class)->read($r),
            default=>abort(404),
        };
        return $result instanceof \Illuminate\Http\JsonResponse?$result:response()->json(['message'=>'Saved. Refreshing your sorting center.']);
    }
    private function createArea(Request $r){\App\Services\Support::once($r,'native-center-area',function()use($r){app(LogisticsController::class)->area($r);return true;});return response()->json(['message'=>'Area saved.']);}
    private function courierStatus(Request $r,int $id){$r->merge(['id'=>$id]);return app(CenterManagementController::class)->status($r);}
    private function resend(Request $r,int $id){$r->merge(['id'=>$id]);return app(OperationsController::class)->resend($r);}
    private function saveRate(Request $r,int $id){$r->merge(['id'=>$id?:null]);return app(OperationsController::class)->rate($r);}
    private function confirmCod(Request $r,int $id){$r->merge(['id'=>$id]);return app(OperationsController::class)->confirm($r);}
    private function parcelAction(Request $r,int $id,string $method){$r->merge(['parcel_id'=>$id]);return app(OperationsController::class)->$method($r);}
    public function export(Request $r,string $kind){$this->user($r);abort_unless(in_array($kind,['reports','finance']),404);$r->merge(['format'=>'pdf']);return $kind==='finance'?app(CenterFinancialController::class)->index($r):app(ReportController::class)->index($r);}
    public function document(Request $r,int $id){$this->user($r);$response=app(LogisticsAdminController::class)->document($id);$ext=pathinfo($response->getFile()->getPathname(),PATHINFO_EXTENSION);$response->setContentDisposition('attachment','courier-document-'.$id.'.'.$ext);return $response;}
    public function messages(Request $r){$this->user($r);$response=app(MobileRiderController::class)->messages($r);$d=$response->getData(true);foreach($d['messages'] as &$m)if($m['attachment_url'])$m['attachment_url']=str_replace('/api/rider/messages/','/api/center/messages/',$m['attachment_url']);return response()->json($d)->header('Cache-Control','private, no-store');}
    public function sendMessage(Request $r){$this->user($r);return app(CommonController::class)->sendMessage($r);}
    public function attachment(Request $r,int $id){$this->user($r);return app(MobileRiderController::class)->attachment($id);}
    public function address(Request $r){$this->user($r);return app(AccountController::class)->address($r);}
}
