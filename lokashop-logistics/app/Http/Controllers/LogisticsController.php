<?php

namespace App\Http\Controllers;

use App\Services\Workflow;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogisticsController extends Controller
{
    public function landing()
    {
        if (auth()->check()) {
            return redirect('/dashboard');
        }

        return view('landing', [
            'stats' => [
                'delivered' => DB::table('parcels')
                    ->whereIn('status', ['DELIVERED', 'COMPLETED'])
                    ->count(),

                'riders' => DB::table('users')
                    ->where('role', 'rider')
                    ->where('approval_status', 'approved')
                    ->where('active', 1)
                    ->count(),

                'registeredCenter' => DB::table('sorting_centers')
                    ->find($this->centerId()),

                'areas' => DB::table('delivery_areas')->count(),

                'centers' => DB::table('sorting_centers')->count(),
            ],
        ]);
    }

    public function track(Request $r)
    {
        $code = trim((string) $r->query('code'));
        $parcel = null;
        $events = collect();

        if ($code !== '') {
            $parcel = DB::table('parcels as p')
                ->join('orders as o', 'o.id', '=', 'p.order_id')
                ->join('addresses as a', 'a.id', '=', 'o.address_id')
                ->where('p.tracking_code', $code)
                ->select(
                    'p.id',
                    'p.tracking_code',
                    'p.status',
                    'a.city',
                    'a.province'
                )
                ->first();

            if ($parcel) {
                $events = DB::table('status_history')
                    ->where('parcel_id', $parcel->id)
                    ->orderBy('id')
                    ->get(['to_status', 'created_at']);
            }
        }

        return view('track', compact('code', 'parcel', 'events'));
    }

    private function listing($title, $q)
    {
        return view('list', [
            'title' => $title,
            'links' => app(CommonController::class)->links(),
            'records' => $q->orderByDesc('id')->paginate(30),
        ]);
    }

    private function centerId()
    {
        return DB::table('sorting_centers')
            ->where('user_id', auth()->id())
            ->value('id');
    }

    public function areas()
    {
        $registeredCenter = DB::table('sorting_centers')
            ->where('user_id', auth()->id())
            ->first();

        abort_unless(
            $registeredCenter,
            403,
            'No sorting center is linked to your account.'
        );

        return view('center_areas', [
            'links' => app(CommonController::class)->links(),

            'registeredCenter' => $registeredCenter,

            'areas' => DB::table('delivery_areas')
                ->where('center_id', $registeredCenter->id)
                ->get(),

            'riders' => DB::table('users')
                ->where('role', 'rider')
                ->where('courier_type', 'rider')
                ->where('active', 1)
                ->where('account_status', 'active')
                ->where('approval_status', 'approved')
                ->where('company_id', auth()->id())
                ->get(),
        ]);
    }

    public function area(Request $r)
    {
        $v = $r->validate([
            'name' => 'required|max:150',
            'barangay' => 'required|string|max:120',
            'city' => 'required|max:120',
            'province' => 'required|max:120',
            'rider_id' => 'required|exists:users,id',
        ]);

        abort_unless(
            DB::table('users')
                ->where('id', $v['rider_id'])
                ->where('role', 'rider')
                ->where('approval_status', 'approved')
                ->where('active', 1)
                ->where('company_id', auth()->id())
                ->exists(),
            422
        );

        \App\Services\Geography::validate($v);
        $c = DB::table('sorting_centers')->find($this->centerId());

        if (!\App\Services\Municipalities::matches($c, (object) $v)) {
            \App\Services\Support::fail(
                'city',
                'Areas must belong to your registered municipality.'
            );
        }

        if (
            DB::table('users')
                ->where('id', $v['rider_id'])
                ->value('courier_type') !== 'rider'
        ) {
            \App\Services\Support::fail(
                'rider_id',
                'Choose a local rider, not a driver.'
            );
        }

        DB::table('delivery_areas')->insert($v + [
            'center_id' => $this->centerId(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Area added.');
    }

    public function pickups()
    {
        \App\Services\PickupRequests::refresh();
        $requests=DB::table('assignments as s')->join('parcels as p','p.id','=','s.parcel_id')->join('orders as o','o.id','=','p.order_id')->join('addresses as a','a.id','=','o.address_id')->where('s.kind','pickup')->where('p.center_id',$this->centerId())->whereIn('p.status',['PREPARING','READY_FOR_PICKUP','PICKED_UP'])->whereRaw("s.id = (select max(last_a.id) from assignments last_a where last_a.parcel_id=s.parcel_id and last_a.kind='pickup')")->select('s.*','p.tracking_code','p.status as parcel_status','a.city','a.province')->paginate(20);
        $eligible=[];$origins=[];
        foreach($requests as $a){$p=DB::table('parcels')->find($a->parcel_id);$eligible[$a->id]=\App\Services\AreaRouting::riders($p,'pickup')->reject(fn($u)=>in_array($a->status,['expired','declined'])&&$u->id==$a->rider_id);$o=DB::table('orders')->find($p->order_id);$origins[$a->id]=\App\Services\Municipalities::address($o->seller_id);}
        return view('center_pickups',['links'=>app(CommonController::class)->links(),'requests'=>$requests,'eligible'=>$eligible,'origins'=>$origins]);
    }
    public function verifyPickup(Request $r,int $id)
    {
        $v=$r->validate(['rider_id'=>'required|integer']);
        \App\Services\PickupRequests::offer($id,auth()->id(),(int)$v['rider_id']);
        return back()->with('success','Pickup assigned. The rider has 3 hours to accept.');
    }
    public function deliveries()
    {
        \App\Services\DeliveryOffers::refresh();
        $parcels=DB::table('parcels')->where('center_id',$this->centerId())->whereIn('status',['AT_SORTING_CENTER','SORTED','ASSIGNED_TO_RIDER','OUT_FOR_DELIVERY','DELIVERY_FAILED','DELIVERED','COMPLETED'])->orderByDesc('id')->paginate(20);
        $matches=[];$riders=[];$addresses=[];$offers=[];
        foreach($parcels as $p){$matches[$p->id]=\App\Services\AreaRouting::areas($p);$riders[$p->id]=\App\Services\AreaRouting::riders($p);$addresses[$p->id]=\App\Services\AreaRouting::address($p);$offers[$p->id]=DB::table('assignments')->where('parcel_id',$p->id)->where('kind','delivery')->whereIn('status',['offered','accepted'])->orderByDesc('id')->first();}
        return view('center_deliveries',['links'=>app(CommonController::class)->links(),'parcels'=>$parcels,'matches'=>$matches,'riders'=>$riders,'addresses'=>$addresses,'offers'=>$offers]);
    }
    public function assignDelivery(Request $r,int $id)
    {
        $v=$r->validate(['rider_id'=>'required|integer']);
        abort_unless(DB::table('parcels')->where('id',$id)->where('center_id',$this->centerId())->exists(),403);
        \App\Services\Dispatch::offer($id,auth()->id(),(int)$v['rider_id']);
        return back()->with('success','Delivery area matched automatically and offer sent to the area rider.');
    }

    public function parcels()
    {
        \App\Services\DeliveryOffers::refresh();
        return view('center_parcels', [
            'links' => app(CommonController::class)->links(),

            'parcels' => DB::table('parcels as p')
                ->join('orders as o', 'o.id', '=', 'p.order_id')
                ->join('addresses as a', 'a.id', '=', 'o.address_id')
                ->where(function ($q) {
                    $q->where('p.center_id', $this->centerId())
                        ->orWhere(function ($q) {
                            $q->where('p.status', 'PICKED_UP')
                                ->where(
                                    'p.center_id',
                                    $this->centerId()
                                );
                        });
                })
                ->select('p.*', 'a.city', 'a.province')
                ->orderByDesc('p.id')
                ->paginate(20),

            'areas' => DB::table('delivery_areas')
                ->where('center_id', $this->centerId())
                ->get(),
        ]);
    }

    public function receive(Request $r, int $id)
    {
        $p = DB::table('parcels')->find($id);

        abort_unless(
            $p
            && $p->status === 'PICKED_UP'
            && (int) $p->center_id === (int) $this->centerId(),
            422
        );

        Workflow::move(
            $id,
            'AT_SORTING_CENTER',
            auth()->id(),
            null,
            null,
            $this->centerId()
        );

        return back()->with('success', 'Parcel scanned at center.');
    }

    public function sort(Request $r,int $id)
    {
        \App\Services\AreaRouting::sort($id,auth()->id());
        return redirect('/center/delivery')->with('success','Destination area matched automatically. Assign a delivery rider here.');
    }
    public function assign(int $id)
    {
        return redirect('/center/delivery')->with('success','Choose the delivery rider in Delivery.');
    }

    public function returned(int $id)
    {
        Workflow::move(
            $id,
            'RETURNED',
            auth()->id(),
            null,
            null,
            $this->centerId()
        );

        return back()->with('success', 'Returned.');
    }

    public function report()
    {
        return $this->listing(
            'Parcel report',
            DB::table('parcels')
                ->where('center_id', $this->centerId())
        );
    }

    public function assignments()
    {
        \App\Services\DeliveryOffers::refresh();
        \App\Services\PickupRequests::refresh();
        return view('rider_assignments', [
            'links' => app(CommonController::class)->links(),

            'assignments' => DB::table('assignments as s')
                ->join('parcels as p', 'p.id', '=', 's.parcel_id')
                ->join('orders as o', 'o.id', '=', 'p.order_id')
                ->join('addresses as a', 'a.id', '=', 'o.address_id')
                ->where('s.rider_id', auth()->id())->whereNotIn('s.status',['expired','declined','reassigned','cancelled'])
                ->select(
                    's.*',
                    'p.status as parcel_status',
                    'p.tracking_code', 'p.proof_path', 'o.id as order_id', 'o.total as cod_total',
                    'o.address_snapshot', 'a.recipient', 'a.phone', 'a.street', 'a.barangay',
                    'a.city',
                    'a.province'
                )
                ->orderByDesc('s.id')
                ->paginate(20),
        ]);
    }

    public function scan(Request $r)
    {
        $v = $r->validate(['code' => 'required|string|max:2000']);
        $code = \App\Services\ParcelCode::parse($v['code']);
        $assignment = DB::table('assignments as s')
            ->join('parcels as p', 'p.id', '=', 's.parcel_id')
            ->where('s.rider_id', auth()->id())->where('s.status','accepted')->where('p.tracking_code', $code)
            ->orderByRaw("s.status in ('offered','accepted') desc")->orderByDesc('s.id')
            ->select('s.id', 's.parcel_id')->first();
        if (!$assignment) \App\Services\Support::fail('code', 'No assigned parcel matches this QR or tracking code.');
        // Open the correct pagination page, including older assignments.
        $newer = DB::table('assignments')->where('rider_id', auth()->id())->whereNotIn('status',['expired','declined','reassigned','cancelled'])->where('id', '>', $assignment->id)->count();
        return redirect('/rider/assignments?page='.(intdiv($newer, 20) + 1).'#assignment-'.$assignment->id)
            ->with('scanned_parcel', $assignment->parcel_id)->with('scanned_code', $code)
            ->with('success', 'Parcel identified. Check its details before confirming the next action.');
    }

    public function accept(int $id)
    {
        \App\Services\Dispatch::accept($id, auth()->id());

        return back()->with('success', 'Assignment accepted.');
    }

    public function riderStep(Request $r, int $id)
    {
        $v = $r->validate([
            'status' => 'required|in:PICKED_UP,AT_SORTING_CENTER,OUT_FOR_DELIVERY,DELIVERED,DELIVERY_FAILED',
            'reason' => 'required_if:status,DELIVERY_FAILED|nullable|string|max:2000',
        ]);

        if (in_array($v['status'], ['PICKED_UP', 'DELIVERED'], true)) {
            $r->validate(['scanned_code' => 'required|string|max:2000']);
            $parcel = DB::table('parcels')->find($id);
            if (!$parcel || !hash_equals($parcel->tracking_code, \App\Services\ParcelCode::parse($r->input('scanned_code')))) {
                \App\Services\Support::fail('scanned_code', 'Scan this parcel label or enter its exact tracking code.');
            }
        }
        Workflow::move(
            $id,
            $v['status'],
            auth()->id(),
            $v['reason'] ?? null
        );

        return back()->with('success', 'Parcel updated.');
    }

    public function earnings()
    {
        return $this->listing(
            'Completed trips and earnings',
            DB::table('assignments')
                ->where('rider_id', auth()->id())
                ->where('status', 'completed')
        );
    }
}