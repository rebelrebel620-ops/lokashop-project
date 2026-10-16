<?php
namespace App\Http\Controllers;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
class CommonController extends Controller {
 public function track(string $code){
  $parcel=DB::table('parcels')->where('tracking_code',$code)->first();
  abort_unless($parcel,404);
  if(!auth()->check() || !DB::table('orders')->where('id',$parcel->order_id)->where(fn($q)=>$q->where('buyer_id',auth()->id())->orWhere('seller_id',auth()->id()))->exists())return view('public-track',['parcel'=>$parcel]);
  $order=DB::table('orders')->where('id',$parcel->order_id)->first();
  abort_unless($order,404);
  $address=$order->address_snapshot?json_decode($order->address_snapshot):DB::table('addresses')->find($order->address_id);
  $buyer=DB::table('users')->find($order->buyer_id);
  $seller=DB::table('users')->find($order->seller_id);
  $items=DB::table('order_items')->where('order_id',$order->id)->get();
  $payment=DB::table('payments')->where('order_id',$order->id)->orderByDesc('id')->first();
  return view('track',['parcel'=>$parcel,'order'=>$order,'address'=>$address,'buyer'=>$buyer,'seller'=>$seller,'items'=>$items,'payment'=>$payment]);
 }
 public function authForm(Request $r){return view('auth',['register'=>$r->is('register'),'roles'=>['buyer','seller']]);}
 public function register(Request $r){
  $id=\App\Services\Registration::create($r);
  $r->session()->regenerate(); $r->session()->put('pending_registration_id',$id);
  return redirect('/verify-email')->with('success','Check your email for the verification code.');
 }
 public function login(Request $r){
  $r->merge(['email'=>mb_strtolower(trim((string)$r->input('email')))]);
  $v=$r->validate(['email'=>'required|email','password'=>'required']);
  $candidate=DB::table('users')->whereRaw('LOWER(TRIM(email)) = ?',[$v['email']])->first();
  if(!$candidate || !Hash::check($v['password'],$candidate->password))return back()->withErrors(['email'=>'Incorrect login details.'])->withInput($r->only('email'));
  if(!in_array($candidate->role,['buyer','seller','admin'],true))return back()->withErrors(['email'=>'Use the correct LokaShop website for this role.']);
  if($candidate->account_status==='suspended'){Auth::loginUsingId($candidate->id);$r->session()->regenerate();return redirect('/appeal');}
  if($candidate->account_status==='banned')return back()->withErrors(['email'=>'This account is permanently banned. Appeals are unavailable.']);
  if($candidate->approval_status==='unverified'&&$candidate->account_status==='active'){
   $r->session()->regenerate();$r->session()->put('pending_registration_id',\App\Services\PendingRegistration::resumeLegacy($candidate->id));
   return redirect('/verify-email')->with('success','Verify your email before logging in. You can request a new code here.');
  }
  if($candidate->role==='seller'&&$candidate->approval_status==='pending'&&$candidate->active&&$candidate->account_status==='active'){Auth::loginUsingId($candidate->id);$r->session()->regenerate();DB::table('users')->where('id',$candidate->id)->update(['last_seen_at'=>now()]);return redirect('/application/drafts');}
  if($candidate->approval_status!=='approved'||!$candidate->active||$candidate->account_status!=='active')return back()->withErrors(['email'=>'Account waiting for approval or inactive. '.($candidate->rejection_reason??'')]);
  Auth::loginUsingId($candidate->id);DB::table('users')->where('id',auth()->id())->update(['last_seen_at'=>now()]);$r->session()->regenerate();return redirect('/dashboard');
 }
 public function logout(Request $r){DB::table('users')->where('id',auth()->id())->update(['online'=>0,'last_seen_at'=>null]);Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return redirect('/login');}
 public function dashboard(){return view('dashboard',['links'=>$this->links()]+$this->dashboardData());}
 private function dashboardData(){
  $u=auth()->user();
  if($u->role==='buyer') return $this->buyerDashboard($u->id);
  if($u->role==='seller') return $this->sellerDashboard($u->id);
  if($u->role==='admin') return $this->adminDashboard();
  return [];
 }
 private function stepOf($status){return match($status){'PLACED'=>1,'CONFIRMED','PREPARING'=>2,'READY_FOR_PICKUP','PICKED_UP'=>3,'AT_SORTING_CENTER','SORTED','ASSIGNED_TO_RIDER'=>4,'OUT_FOR_DELIVERY'=>5,'DELIVERED','COMPLETED'=>6,default=>1};}
 private function buyerDashboard($id){
  $orders=DB::table('orders')->where('buyer_id',$id);
  $active=(clone $orders)->whereNotIn('status',['DELIVERED','COMPLETED','RETURNED'])->count();
  $delivered=(clone $orders)->whereIn('status',['DELIVERED','COMPLETED'])->count();
  $voucherQuery=DB::table('vouchers')->where('active',1)->where(fn($q)=>$q->whereNull('expires_at')->orWhere('expires_at','>',now()));
  $voucherCount=(clone $voucherQuery)->count();$vouchers=$voucherQuery->orderByDesc('id')->limit(3)->get();
  $latest=DB::table('orders as o')->join('parcels as p','p.order_id','=','o.id')->where('o.buyer_id',$id)->whereNotIn('o.status',['COMPLETED','RETURNED'])->orderByDesc('o.id')->select('o.*','p.tracking_code')->first();
  return ['stat_active'=>$active,'stat_delivered'=>$delivered,'stat_vouchers'=>$voucherCount,'vouchers'=>$vouchers,
   'latest_order'=>$latest,'latest_step'=>$latest?$this->stepOf($latest->status):null,
   'address'=>DB::table('addresses')->where('user_id',$id)->orderByDesc('is_default')->orderByDesc('id')->first(),
   'notifications'=>DB::table('notifications')->where('user_id',$id)->orderByDesc('id')->limit(3)->get(),
   'recommended'=>DB::table('products')->where('active',1)->where('approved',1)->orderByDesc('id')->limit(4)->get()];
 }
 private function sellerDashboard($id){
  $orders=DB::table('orders')->where('seller_id',$id);
  $salesToday=(clone $orders)->whereDate('created_at',now()->toDateString())->sum('total');
  $toPrepare=(clone $orders)->whereIn('status',['PLACED','CONFIRMED','PREPARING'])->count();
  $ready=(clone $orders)->where('status','READY_FOR_PICKUP')->count();
  $rating=DB::table('item_reviews')->where('seller_id',$id);
  $recent=DB::table('orders as o')->join('users as b','b.id','=','o.buyer_id')->join('parcels as p','p.order_id','=','o.id')->join('order_items as oi','oi.order_id','=','o.id')->where('o.seller_id',$id)->groupBy('o.id','b.name','o.total','o.status','p.id')->orderByDesc('o.id')->limit(5)->select('o.id','o.total','o.status','b.name as buyer','p.id as parcel_id',DB::raw('COUNT(oi.id) as items'))->get();
  $days=collect(range(6,0))->map(fn($d)=>now()->subDays($d)->toDateString());
  $byDay=(clone $orders)->whereDate('created_at','>=',now()->subDays(6)->toDateString())->selectRaw('DATE(created_at) d,SUM(total) t')->groupBy('d')->pluck('t','d');
  $chart=$days->map(fn($d)=>['label'=>date('M j',strtotime($d)),'value'=>(float)($byDay[$d]??0)]);
  $topProducts=DB::table('order_items as oi')->join('orders as o','o.id','=','oi.order_id')->join('products as p','p.id','=','oi.product_id')->where('o.seller_id',$id)->groupBy('p.id','p.name','p.price')->select('p.id','p.name','p.price',DB::raw('SUM(oi.quantity) as sold'))->orderByDesc('sold')->limit(3)->get();
  return ['stat_sales_today'=>$salesToday,'stat_to_prepare'=>$toPrepare,'stat_ready'=>$ready,
   'store_rating'=>round((clone $rating)->avg('stars')??0,1),'store_reviews'=>(clone $rating)->count(),
   'recent_orders'=>$recent,'chart'=>$chart,'top_products'=>$topProducts];
 }
 private function adminDashboard(){
  $done=['DELIVERED','COMPLETED'];
  $orders=DB::table('orders')->whereIn('status',$done);
  $totalSales=(clone $orders)->sum('total');$commission=(clone $orders)->sum('commission');
  $last7=(clone $orders)->where('created_at','>=',now()->subDays(7))->sum('total');
  $prev7=(clone $orders)->whereBetween('created_at',[now()->subDays(14),now()->subDays(7)])->sum('total');
  $trend=$prev7>0?round((($last7-$prev7)/$prev7)*100,1):($last7>0?100:0);
  $pending=DB::table('users')->where('role','seller')->where('approval_status','pending');
  $openComplaints=DB::table('complaints')->where('status','open')->count();
  $pendingProducts=DB::table('products')->where('approved',0)->count();
  $days=collect(range(6,0))->map(fn($d)=>now()->subDays($d)->toDateString());
  $byDay=(clone $orders)->whereDate('created_at','>=',now()->subDays(6)->toDateString())->selectRaw('DATE(created_at) d,SUM(total) t,SUM(commission) c')->groupBy('d')->get()->keyBy('d');
  $chart=$days->map(fn($d)=>['label'=>date('M j',strtotime($d)),'sales'=>(float)($byDay[$d]->t??0),'commission'=>(float)($byDay[$d]->c??0)]);
  $recentReg=DB::table('users')->whereIn('role',['buyer','seller'])->orderByDesc('created_at')->limit(3)->get()->map(fn($u)=>['label'=>$u->name.' registered as a '.$u->role,'type'=>'Registration','at'=>$u->created_at]);
  $recentProd=DB::table('products')->orderByDesc('created_at')->limit(2)->get()->map(fn($p)=>['label'=>'Product "'.$p->name.'" submitted for compliance review','type'=>'Product','at'=>$p->created_at]);
  $recentComplaint=DB::table('complaints')->orderByDesc('updated_at')->limit(2)->get()->map(fn($c)=>['label'=>'Complaint #'.$c->id.' '.($c->status==='resolved'?'resolved':'updated'),'type'=>'Complaint','at'=>$c->updated_at]);
  $recentAnn=DB::table('announcements')->orderByDesc('created_at')->limit(1)->get()->map(fn($a)=>['label'=>'Announcement published: '.$a->title,'type'=>'Announcement','at'=>$a->created_at]);
  $activity=collect()->concat($recentReg)->concat($recentProd)->concat($recentComplaint)->concat($recentAnn)->sortByDesc('at')->take(5)->values();
  return ['stat_total_sales'=>$totalSales,'stat_commission'=>$commission,'stat_trend'=>$trend,
   'stat_pending'=>(clone $pending)->count(),'stat_complaints'=>$openComplaints,'stat_pending_products'=>$pendingProducts,
   'pending_users'=>(clone $pending)->orderByDesc('created_at')->limit(3)->get(),
   'chart'=>$chart,'activity'=>$activity];
 }
 public function links(){return match(auth()->user()->role){'buyer'=>['Shop'=>'/shop','Cart'=>'/cart','Notifications'=>'/notifications','My orders'=>'/orders','Addresses'=>'/addresses','Wishlist'=>'/tools/wishlist','Vouchers'=>'/tools/vouchers','Returns'=>'/tools/returns','Reviews'=>'/ratings','Complaints'=>'/complaints'],'seller'=>['Products'=>'/seller/products','Orders'=>'/seller/orders','Sales'=>'/seller/reports','Store'=>'/tools/store','Addresses'=>'/seller/address','Promotions'=>'/tools/promotions','Vouchers'=>'/tools/vouchers','Returns'=>'/tools/returns','Earnings'=>'/tools/earnings','Reviews'=>'/ratings','Messages'=>'/messages'],'admin'=>['Approvals'=>'/admin/approvals','Users'=>'/admin/users','Appeals'=>'/admin/appeals','Products'=>'/admin/products','Complaints'=>'/admin/complaints','Reports'=>'/admin/reports','Announcements'=>'/admin/announcements','Payments'=>'/tools/payments','Platform vouchers'=>'/tools/platform'],'sorting_center'=>['Applications'=>'/center/approvals','Pickups'=>'/center/pickups','Parcels'=>'/center/parcels','Areas'=>'/center/areas','Reports'=>'/center/reports'],'rider'=>['Assignments'=>'/rider/assignments','Earnings'=>'/rider/earnings'],default=>[]};}
 public function profile(){return view('profile',['links'=>$this->links(),'user'=>auth()->user(),'notifications'=>DB::table('notifications')->where('user_id',auth()->id())->orderByDesc('id')->limit(20)->get()]);}
 public function updateProfile(Request $r){\App\Services\Cart::profile($r);return back()->with('success','Profile saved.');}
 public function messages(){return view('messages',['links'=>$this->links(),'users'=>DB::table('users')->where('active',1)->where('approval_status','approved')->where('id','!=',auth()->id())->when(auth()->user()->role==='admin',fn($q)=>$q->whereIn('role',['buyer','seller']))->get(),'messages'=>DB::table('messages')->where('sender_id',auth()->id())->orWhere('recipient_id',auth()->id())->orderByDesc('id')->limit(50)->get()]);}
 public function sendMessage(Request $r){$v=$r->validate(['recipient_id'=>'required|integer|exists:users,id','body'=>'required|string|max:1000','attachment'=>'nullable|file|mimes:jpg,jpeg,png|extensions:jpg,jpeg,png|max:5120']);$u=auth()->user();abort_if($v['recipient_id']==$u->id,422);$recipient=DB::table('users')->find($v['recipient_id']);abort_unless($recipient->active&&$recipient->approval_status==='approved',403);$roles=match($u->role){'buyer'=>['seller','admin'],'seller'=>['buyer','admin'],'admin'=>['buyer','seller'],'rider'=>['sorting_center'],'sorting_center'=>['rider','logistics_admin'],'logistics_admin'=>['sorting_center']};abort_unless(in_array($recipient->role,$roles,true),403);if($u->role==='rider')abort_unless($u->company_id==$recipient->id,403);if($u->role==='sorting_center'&&$recipient->role==='rider')abort_unless($recipient->company_id==$u->id,403);unset($v['attachment']);if($r->hasFile('attachment'))$v['attachment']=$r->file('attachment')->store('chat','local');DB::table('messages')->insert($v+['sender_id'=>$u->id,'created_at'=>now(),'updated_at'=>now()]);return $r->expectsJson()?response()->json(['message'=>'Sent.']):back()->with('success','Sent.');}
}
