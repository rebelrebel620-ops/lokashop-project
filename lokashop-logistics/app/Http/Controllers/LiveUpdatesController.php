<?php
namespace App\Http\Controllers;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use App\Services\LiveUpdates;
class LiveUpdatesController extends Controller {
 public function state(Request $r){$u=auth()->user();if($u&&(!$u->active||$u->account_status!=='active'||$u->approval_status!=='approved'||($u->last_seen_at&&now()->diffInMinutes($u->last_seen_at,true)>=30)))return response()->json(['session_changed'=>true],401)->header('Cache-Control','private, no-store');return response()->json(LiveUpdates::snapshot())->header('Cache-Control','private, no-store')->header('Vary','Cookie');}
}
