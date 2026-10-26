<?php
namespace App\Http\Controllers;

use App\Services\AdminUserDetails;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AdminUserDetailsController extends Controller
{
    public function show(Request $request, int $id)
    {
        $logistics=config('app.site_kind')==='logistics';
        abort_unless(auth()->check() && auth()->user()->role===($logistics?'logistics_admin':'admin'),403);
        $details=AdminUserDetails::forAccount($id,$logistics);
        $query=http_build_query($request->only(['search','role','state','approval','page']));
        return view('admin_user_details',[
            'links'=>app(CommonController::class)->links(),
            'details'=>$details,
            'backUrl'=>url('/admin/users').($query?'?'.$query:''),
        ]);
    }
}
