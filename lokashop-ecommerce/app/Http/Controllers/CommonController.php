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
  $order=DB::table('orders')->where('id',$parcel->order_id)->first();
  abort_unless($order,404);
  $address=DB::table('addresses')->find($order->address_id);
  $buyer=DB::table('users')->find($order->buyer_id);
  $seller=DB::table('users')->find($order->seller_id);
  $items=DB::table('order_items')->where('order_id',$order->id)->get();
  $payment=DB::table('payments')->where('order_id',$order->id)->orderByDesc('id')->first();
  return view('track',['parcel'=>$parcel,'order'=>$order,'address'=>$address,'buyer'=>$buyer,'seller'=>$seller,'items'=>$items,'payment'=>$payment]);
 }
 public function authForm(Request $r){return view('auth',['register'=>$r->is('register'),'roles'=>['buyer','seller']]);}
 public function register(Request $r){
  $roles=['buyer','seller'];
  $v=$r->validate(['role'=>['required',Rule::in($roles)],'name'=>'required|string|max:150','phone'=>'required|string|max:40','email'=>'required|email|unique:users,email','password'=>'required|min:8|confirmed','business_name'=>'required_if:role,seller,sorting_center|nullable|string|max:180','vehicle'=>'required_if:role,rider|nullable|string|max:120','id_document'=>'required|file|mimes:pdf,jpg,jpeg,png|max:5120','permit_document'=>'required_if:role,seller,sorting_center,rider|nullable|file|mimes:pdf,jpg,jpeg,png|max:5120']);
  DB::transaction(function()use($r,$v){$id=DB::table('users')->insertGetId(['name'=>$v['name'],'phone'=>$v['phone'],'email'=>$v['email'],'password'=>Hash::make($v['password']),'role'=>$v['role'],'vehicle'=>$v['vehicle']??null,'approval_status'=>$v['role']==='buyer'?'approved':'pending','active'=>1,'created_at'=>now(),'updated_at'=>now()]);
   foreach(['id_document'=>'id','permit_document'=>'permit'] as $key=>$kind)if($r->hasFile($key)) DB::table('registration_documents')->insert(['user_id'=>$id,'kind'=>$kind,'path'=>$r->file($key)->store('documents'),'created_at'=>now(),'updated_at'=>now()]);
   if($v['role']==='seller')DB::table('seller_businesses')->insert(['user_id'=>$id,'name'=>$v['business_name'],'created_at'=>now(),'updated_at'=>now()]);
   if($v['role']==='sorting_center')DB::table('sorting_centers')->insert(['user_id'=>$id,'name'=>$v['business_name'],'created_at'=>now(),'updated_at'=>now()]);
  });return redirect('/login')->with('success',$v['role']==='buyer'?'Account created. You can log in now.':'Seller registration sent for approval.'); }
 public function login(Request $r){$v=$r->validate(['email'=>'required|email','password'=>'required']);if(!Auth::attempt($v))return back()->withErrors(['email'=>'Incorrect login details.']);$r->session()->regenerate();$u=Auth::user();if($u->approval_status!=='approved'||!$u->active){Auth::logout();return back()->withErrors(['email'=>'Account waiting for approval or inactive.']);}$roles=['buyer','seller','admin'];if(!in_array($u->role,$roles,true)){Auth::logout();return back()->withErrors(['email'=>'Use the correct LokaShop website for this role.']);}return redirect('/dashboard');}
 public function logout(Request $r){Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return redirect('/login');}
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
  $rating=DB::table('ratings')->join('orders','orders.id','=','ratings.order_id')->where('orders.seller_id',$id);
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
 public function links(){return match(auth()->user()->role){'buyer'=>['Shop'=>'/shop','Cart'=>'/cart','My orders'=>'/orders','Complaints'=>'/complaints'],'seller'=>['Products'=>'/seller/products','Orders'=>'/seller/orders','Sales'=>'/seller/reports','Messages'=>'/messages'],'admin'=>['Approvals'=>'/admin/approvals','Users'=>'/admin/users','Products'=>'/admin/products','Complaints'=>'/complaints','Reports'=>'/admin/reports','Announcements'=>'/admin/announcements'],'sorting_center'=>['Applications'=>'/center/approvals','Pickups'=>'/center/pickups','Parcels'=>'/center/parcels','Areas'=>'/center/areas','Reports'=>'/center/reports'],'rider'=>['Assignments'=>'/rider/assignments','Earnings'=>'/rider/earnings'],default=>[]};}
 public function profile(){return view('profile',['links'=>$this->links(),'user'=>auth()->user(),'notifications'=>DB::table('notifications')->where('user_id',auth()->id())->orderByDesc('id')->limit(20)->get()]);}
 public function updateProfile(Request $r){$v=$r->validate(['name'=>'required|string|max:150','phone'=>'required|string|max:40']);DB::table('users')->where('id',auth()->id())->update($v+['updated_at'=>now()]);return back()->with('success','Profile saved.');}
 public function messages(){return view('messages',['links'=>$this->links(),'users'=>DB::table('users')->where('active',1)->where('approval_status','approved')->where('id','!=',auth()->id())->when(auth()->user()->role==='admin',fn($q)=>$q->whereIn('role',['buyer','seller']))->get(),'messages'=>DB::table('messages')->where('sender_id',auth()->id())->orWhere('recipient_id',auth()->id())->orderByDesc('id')->limit(50)->get()]);}
 public function sendMessage(Request $r){$v=$r->validate(['recipient_id'=>'required|exists:users,id|different:sender_id','body'=>'required|string|max:2000']);abort_if((int)$v['recipient_id']===auth()->id(),422);if(auth()->user()->role==='admin')abort_unless(DB::table('users')->where('id',$v['recipient_id'])->whereIn('role',['buyer','seller'])->exists(),403);DB::table('messages')->insert($v+['sender_id'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);return back()->with('success','Sent.');}
}
