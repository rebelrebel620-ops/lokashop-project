<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ApiToken;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\{Auth,Cache,DB,Hash};
use Illuminate\Support\Str;

/** Native login and a short-lived, single-use handoff to the existing web workspace. */
class MobileWorkspaceController extends Controller
{
    public static function allowed($user): bool
    {
        return $user && in_array($user->role, ['rider','sorting_center'], true)
            && $user->approval_status === 'approved' && $user->active
            && $user->account_status === 'active'
            && (!$user->verification_required || $user->email_verified_at);
    }

    private function user(Request $request): User
    {
        $user = ApiToken::user($request->bearerToken());
        abort_unless(self::allowed($user), 401, 'Session expired or account unavailable. Sign in again.');
        DB::table('users')->where('id', $user->id)->update(['last_seen_at'=>now()]);
        Auth::setUser($user);
        $request->setUserResolver(fn() => $user);
        return $user;
    }

    private function profile(User $user): array
    {
        return ['id'=>$user->id,'name'=>$user->name,'email'=>$user->email,'phone'=>$user->phone,
            'role'=>$user->role,'courier_type'=>$user->courier_type,'vehicle'=>$user->vehicle,
            'online'=>(bool)$user->online,'latitude'=>$user->latitude,'longitude'=>$user->longitude,
            'license_number'=>$user->license_number,'license_expires_at'=>$user->license_expires_at,
            'plate_number'=>$user->plate_number,'company_id'=>$user->company_id,'profile_photo_url'=>($user->profile_photo?'/api/mobile/photo?v='.rawurlencode((string)$user->profile_photo):null)];
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email'=>'required|email','password'=>'required|string']);
        $user = User::where('email', mb_strtolower(trim($data['email'])))->first();
        if (!$user || !Hash::check($data['password'], (string)$user->password)) {
            return response()->json(['message'=>'Incorrect email or password.'],422);
        }
        if (!self::allowed($user)) return response()->json(['message'=>'Use an approved, verified rider, driver or sorting center account. Pending applications must be approved first.'],403);
        DB::table('users')->where('id',$user->id)->update(['last_seen_at'=>now()]);
        [$token,$expires] = ApiToken::issue($user);
        return response()->json(['token'=>$token,'expires_at'=>$expires,'rider'=>$this->profile($user)]);
    }

    public function me(Request $request)
    {
        $user = $this->user($request);
        if ($user->role === 'rider') return app(RiderApiController::class)->me($request);
        return response()->json(['rider'=>$this->profile($user),'stats'=>[]]);
    }

    public function ticket(Request $request)
    {
        $user = $this->user($request);
        $ticket = Str::random(64);
        Cache::put('mobile-workspace:'.hash('sha256',$ticket), ['user_id'=>$user->id,
            'stamp'=>hash('sha256',$user->password.'|'.$user->remember_token)], now()->addSeconds(60));
        return response()->json(['ticket'=>$ticket,'expires_in'=>60])->header('Cache-Control','no-store');
    }

    public function enter(Request $request)
    {
        $ticket = $request->validate(['ticket'=>'required|string|size:64'])['ticket'];
        $key = 'mobile-workspace:'.hash('sha256',$ticket);
        $saved = Cache::lock($key.':lock',5)->block(3,fn()=>Cache::pull($key));
        abort_unless($saved,403,'Sign-in link expired. Return to the app and reconnect.');
        $user = User::find($saved['user_id']);
        abort_unless(self::allowed($user) && hash_equals($saved['stamp'],hash('sha256',$user->password.'|'.$user->remember_token)),403);
        // Do not carry a previous user's web session into this account.
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('mobile_workspace',true);
        return redirect($user->role==='sorting_center'?'/dashboard':($user->courier_type==='driver'?'/driver/trips':'/rider/assignments'))
            ->header('Cache-Control','no-store')->header('Referrer-Policy','no-referrer');
    }

    public function report(Request $request)
    {
        $user = $this->user($request);
        abort_unless($user->role === 'sorting_center',403);
        $request->merge(['format'=>'pdf']);
        return app(ReportController::class)->index($request);
    }

    public function document(Request $request, int $id)
    {
        $user = $this->user($request);
        abort_unless($user->role === 'sorting_center',403);
        return app(LogisticsAdminController::class)->document($id);
    }

    public function logout(Request $request)
    {
        $user = $this->user($request);
        DB::table('users')->where('id',$user->id)->update(['remember_token'=>null,'online'=>0]);
        return response()->json(['message'=>'Signed out.']);
    }
}
