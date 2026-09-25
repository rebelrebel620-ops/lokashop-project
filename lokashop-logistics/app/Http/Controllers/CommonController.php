<?php
namespace App\Http\Controllers;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
class CommonController extends Controller {
 public function authForm(Request $r){return view('auth',['register'=>$r->is('register'),'roles'=>str_contains(config('app.name'),'Logistics')?['sorting_center','rider']:['buyer','seller']]);}
 public function register(Request $r){
  $roles=str_contains(config('app.name'),'Logistics')?['sorting_center','rider']:['buyer','seller'];
  $v=$r->validate(['role'=>['required',Rule::in($roles)],'name'=>'required|string|max:150','phone'=>'required|string|max:40','email'=>'required|email|unique:users,email','password'=>'required|min:8|confirmed','business_name'=>'required_if:role,seller,sorting_center|nullable|string|max:180','vehicle'=>'required_if:role,rider|nullable|string|max:120','id_document'=>'required|file|mimes:pdf,jpg,jpeg,png|max:5120','permit_document'=>'required_if:role,seller,sorting_center,rider|nullable|file|mimes:pdf,jpg,jpeg,png|max:5120']);
  DB::transaction(function()use($r,$v){$id=DB::table('users')->insertGetId(['name'=>$v['name'],'phone'=>$v['phone'],'email'=>$v['email'],'password'=>Hash::make($v['password']),'role'=>$v['role'],'vehicle'=>$v['vehicle']??null,'approval_status'=>'pending','active'=>1,'created_at'=>now(),'updated_at'=>now()]);
   foreach(['id_document'=>'id','permit_document'=>'permit'] as $key=>$kind)if($r->hasFile($key)) DB::table('registration_documents')->insert(['user_id'=>$id,'kind'=>$kind,'path'=>$r->file($key)->store('documents'),'created_at'=>now(),'updated_at'=>now()]);
   if($v['role']==='seller')DB::table('seller_businesses')->insert(['user_id'=>$id,'name'=>$v['business_name'],'created_at'=>now(),'updated_at'=>now()]);
   if($v['role']==='sorting_center')DB::table('sorting_centers')->insert(['user_id'=>$id,'name'=>$v['business_name'],'created_at'=>now(),'updated_at'=>now()]);
  });return redirect('/login')->with('success','Registration sent for approval.'); }
 public function login(Request $r){$v=$r->validate(['email'=>'required|email','password'=>'required']);if(!Auth::attempt($v))return back()->withErrors(['email'=>'Incorrect login details.']);$r->session()->regenerate();$u=Auth::user();if($u->approval_status!=='approved'||!$u->active){Auth::logout();return back()->withErrors(['email'=>'Account waiting for approval or inactive.']);}$roles=str_contains(config('app.name'),'Logistics')?['sorting_center','rider']:['buyer','seller','admin'];if(!in_array($u->role,$roles,true)){Auth::logout();return back()->withErrors(['email'=>'Use the correct LokaShop website for this role.']);}return redirect('/dashboard');}
 public function logout(Request $r){Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return redirect('/login');}
 public function dashboard(){return view('dashboard',['links'=>$this->links()]);}
 public function links(){return match(auth()->user()->role){'buyer'=>['Shop'=>'/','Cart'=>'/cart','My orders'=>'/orders','Addresses'=>'/addresses','Feedback'=>'/ratings','Complaints'=>'/complaints'],'seller'=>['Products'=>'/seller/products','Orders'=>'/seller/orders','Sales'=>'/seller/reports','Feedback'=>'/ratings'],'admin'=>['Approvals'=>'/admin/approvals','Users'=>'/admin/users','Products'=>'/admin/products','Complaints'=>'/complaints','Reports'=>'/admin/reports','Announcements'=>'/admin/announcements'],'sorting_center'=>['Applications'=>'/center/approvals','Pickups'=>'/center/pickups','Parcels'=>'/center/parcels','Areas'=>'/center/areas','Reports'=>'/center/reports'],'rider'=>['Assignments'=>'/rider/assignments','Earnings'=>'/rider/earnings'],default=>[]};}
 public function profile(){return view('profile',['links'=>$this->links(),'user'=>auth()->user(),'notifications'=>DB::table('notifications')->where('user_id',auth()->id())->orderByDesc('id')->limit(20)->get()]);}
 public function updateProfile(Request $r){$v=$r->validate(['name'=>'required|string|max:150','phone'=>'required|string|max:40']);DB::table('users')->where('id',auth()->id())->update($v+['updated_at'=>now()]);return back()->with('success','Profile saved.');}
 public function messages(){return view('messages',['links'=>$this->links(),'users'=>DB::table('users')->where('active',1)->where('approval_status','approved')->where('id','!=',auth()->id())->get(),'messages'=>DB::table('messages')->where('sender_id',auth()->id())->orWhere('recipient_id',auth()->id())->orderByDesc('id')->limit(50)->get()]);}
 public function sendMessage(Request $r){$v=$r->validate(['recipient_id'=>'required|exists:users,id|different:sender_id','body'=>'required|string|max:2000']);abort_if((int)$v['recipient_id']===auth()->id(),422);DB::table('messages')->insert($v+['sender_id'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);return back()->with('success','Sent.');}
}
