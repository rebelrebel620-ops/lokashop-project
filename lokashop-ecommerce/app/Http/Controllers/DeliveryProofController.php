<?php
namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\{DB, Storage};
use App\Services\Support;

class DeliveryProofController extends Controller
{
    public function show(int $id)
    {
        $order = Support::ownsOrder($id);
        $parcel = DB::table('parcels')->where('order_id', $id)->first();
        abort_unless($parcel && $parcel->proof_path && in_array($order->status, ['DELIVERED', 'COMPLETED']), 404);
        $path = $parcel->proof_path;
        abort_unless(preg_match('~^delivery-proofs/[A-Za-z0-9_-]+\.(jpg|jpeg|png)$~i', $path), 404);
        $disk = Storage::disk('local');
        if (!$disk->exists($path)) {
            if (request()->expectsJson()) abort(404, 'Delivery photo is unavailable. Contact support to check shared proof storage.');
            return response()->view('proof-unavailable', ['order' => $order], 404)
                ->header('Cache-Control', 'private, no-store');
        }
        return response()->file($disk->path($path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
