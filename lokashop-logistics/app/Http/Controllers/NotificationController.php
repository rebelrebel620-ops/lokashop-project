<?php
namespace App\Http\Controllers;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class NotificationController extends Controller {
 public function index(){return view('notifications',['links'=>app(CommonController::class)->links(),'notifications'=>DB::table('notifications')->where('user_id',auth()->id())->orderByDesc('id')->paginate(25)]);}
 public function count(){return response()->json(['unread'=>DB::table('notifications')->where('user_id',auth()->id())->whereNull('read_at')->count()])->header('Cache-Control','private, no-store');}
 public function read(Request $r){$v=$r->validate(['id'=>'nullable|integer|min:1']);$q=DB::table('notifications')->where('user_id',auth()->id());if(!empty($v['id'])){abort_unless((clone $q)->where('id',$v['id'])->exists(),404);$q->where('id',$v['id']);}$q->whereNull('read_at')->update(['read_at'=>now(),'updated_at'=>now()]);return back()->with('success','Notifications marked as read.');}
}
