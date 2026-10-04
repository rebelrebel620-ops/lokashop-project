<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ApiToken;
use App\Services\Workflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * JSON API for the Flutter rider app.
 *
 * It uses the same MySQL tables and the same Workflow::move() state machine as the
 * rider web portal (LogisticsController::assignments/accept/riderStep), so the web
 * portal and the mobile app always agree.
 */
class RiderApiController extends Controller
{
    // ---------------------------------------------------------------- auth

    public function login(Request $r): JsonResponse
    {
        $v = $r->validate(['email' => 'required|email', 'password' => 'required']);

        $u = User::where('email', $v['email'])->first();
        if (!$u || !Hash::check($v['password'], (string) $u->password)) {
            return response()->json(['message' => 'Incorrect login details.'], 422);
        }
        if ($u->approval_status !== 'approved' || !$u->active) {
            return response()->json(['message' => 'Account waiting for approval or inactive.'], 403);
        }
        if ($u->role !== 'rider') {
            return response()->json(['message' => 'This app is for riders only. Use the LokaShop website for your role.'], 403);
        }

        [$token, $expires] = ApiToken::issue($u);
        return response()->json(['token' => $token, 'expires_at' => $expires, 'rider' => $this->riderJson($u)]);
    }

    public function me(Request $r): JsonResponse
    {
        $id = $r->user()->id;
        $open = ['offered', 'accepted'];
        $count = fn (string $kind) => DB::table('assignments')->where('rider_id', $id)->where('kind', $kind)->whereIn('status', $open)->count();

        return response()->json([
            'rider' => $this->riderJson($r->user()),
            'stats' => [
                'pickups_assigned' => $count('pickup'),
                'deliveries_in_progress' => $count('delivery'),
                'completed' => DB::table('assignments')->where('rider_id', $id)->where('status', 'completed')->count(),
                'total_earnings' => (float) DB::table('assignments')->where('rider_id', $id)->where('status', 'completed')->sum('earnings'),
            ],
        ]);
    }

    public function updateProfile(Request $r): JsonResponse
    {
        $v = $r->validate(['name' => 'required|string|max:150', 'phone' => 'required|string|max:40']);
        DB::table('users')->where('id', $r->user()->id)->update($v + ['updated_at' => now()]);
        return response()->json(['message' => 'Profile saved.', 'rider' => $this->riderJson(User::find($r->user()->id))]);
    }

    // --------------------------------------------------------- assignments

    public function assignments(Request $r): JsonResponse
    {
        $q = $this->base($r->user()->id);
        match ($r->query('filter', 'all')) {
            'active' => $q->whereIn('s.status', ['offered', 'accepted']),
            'completed' => $q->where('s.status', 'completed'),
            default => null,
        };

        $rows = $q->orderByDesc('s.id')->limit(100)->get();
        return response()->json(['data' => $this->shape($rows)]);
    }

    /** Same rule as LogisticsController::accept(). */
    public function accept(Request $r, int $id): JsonResponse
    {
        $rid = $r->user()->id;
        $a = DB::table('assignments')->where('id', $id)->where('rider_id', $rid)->where('status', 'offered')->first();
        abort_unless($a, 403, 'This assignment is no longer available to accept.');

        DB::table('assignments')->where('id', $id)->update(['status' => 'accepted', 'updated_at' => now()]);

        $row = $this->base($rid)->where('s.id', $id)->first();
        return response()->json(['message' => 'Assignment accepted.', 'assignment' => $this->shape([$row])[0]]);
    }

    /**
     * Same validation as LogisticsController::riderStep(); every transition and
     * permission check is enforced by Workflow::move().
     */
    public function status(Request $r, int $id): JsonResponse
    {
        $v = $r->validate([
            'status' => 'required|in:PICKED_UP,OUT_FOR_DELIVERY,DELIVERED,DELIVERY_FAILED',
            'reason' => 'required_if:status,DELIVERY_FAILED|nullable|string|max:2000',
        ]);

        Workflow::move($id, $v['status'], $r->user()->id, $v['reason'] ?? null);

        $row = $this->base($r->user()->id)->where('s.parcel_id', $id)->orderByDesc('s.id')->first();
        return response()->json(['message' => 'Parcel updated.', 'assignment' => $row ? $this->shape([$row])[0] : null]);
    }

    // ---------------------------------------------------------------- scan

    /**
     * Look up a scanned QR code. The QR may contain the bare tracking code or a
     * tracking link (…/track?code=LKS-… or …/track/LKS-…). Returns this rider's
     * assignment for that parcel, including the actions allowed right now.
     */
    public function scan(Request $r): JsonResponse
    {
        $v = $r->validate(['code' => 'required|string|max:2000']);
        $code = $this->trackingCode($v['code']);

        $parcel = $code === '' ? null : DB::table('parcels')->where('tracking_code', $code)->first();
        if (!$parcel) {
            return response()->json(['message' => 'No parcel found for that QR code.'], 404);
        }

        $row = $this->base($r->user()->id)
            ->where('s.parcel_id', $parcel->id)
            ->orderByRaw("s.status in ('offered','accepted') desc")
            ->orderByDesc('s.id')
            ->first();
        if (!$row) {
            return response()->json(['message' => 'This parcel is not assigned to you.'], 403);
        }

        return response()->json(['assignment' => $this->shape([$row])[0]]);
    }

    // ------------------------------------------------------------ earnings

    /** Same data as LogisticsController::earnings(): completed assignments. */
    public function earnings(Request $r): JsonResponse
    {
        $q = DB::table('assignments as s')
            ->join('parcels as p', 'p.id', '=', 's.parcel_id')
            ->where('s.rider_id', $r->user()->id)
            ->where('s.status', 'completed');

        return response()->json([
            'total' => (float) (clone $q)->sum('s.earnings'),
            'count' => (clone $q)->count(),
            'data' => $q->orderByDesc('s.updated_at')->orderByDesc('s.id')->limit(100)
                ->get(['s.id', 's.parcel_id', 's.kind', 's.earnings', 's.updated_at as completed_at', 'p.tracking_code'])
                ->map(fn ($e) => [
                    'id' => (int) $e->id,
                    'parcel_id' => (int) $e->parcel_id,
                    'kind' => $e->kind,
                    'earnings' => (float) $e->earnings,
                    'completed_at' => $e->completed_at,
                    'tracking_code' => $e->tracking_code,
                ])->values(),
        ]);
    }

    // ------------------------------------------------------------- helpers

    private function riderJson($u): array
    {
        return ['id' => (int) $u->id, 'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone, 'vehicle' => $u->vehicle];
    }

    private function trackingCode(string $raw): string
    {
        $raw = trim($raw);
        if (preg_match('~^https?://~i', $raw)) {
            $parts = parse_url($raw) ?: [];
            if (!empty($parts['query'])) {
                parse_str($parts['query'], $query);
                if (!empty($query['code']) && is_string($query['code'])) return trim($query['code']);
            }
            if (!empty($parts['path']) && preg_match('~/track/([^/?#]+)~i', $parts['path'], $m)) {
                return trim(urldecode($m[1]));
            }
        }
        return $raw;
    }

    /** One row per assignment, joined with parcel / order / buyer address / seller / latest payment. */
    private function base(int $riderId)
    {
        return DB::table('assignments as s')
            ->join('parcels as p', 'p.id', '=', 's.parcel_id')
            ->join('orders as o', 'o.id', '=', 'p.order_id')
            ->join('addresses as a', 'a.id', '=', 'o.address_id')
            ->join('users as sel', 'sel.id', '=', 'o.seller_id')
            ->leftJoin('payments as pay', 'pay.id', '=', DB::raw('(select max(id) from payments where order_id = o.id)'))
            ->where('s.rider_id', $riderId)
            ->select(
                's.id', 's.parcel_id', 's.kind', 's.status', 's.earnings', 's.created_at',
                'p.tracking_code', 'p.status as parcel_status', 'p.failure_reason',
                'o.id as order_id',
                'a.recipient', 'a.phone as recipient_phone', 'a.street', 'a.barangay', 'a.city', 'a.province', 'a.postal_code',
                'sel.name as seller_name', 'sel.phone as seller_phone',
                'pay.method as pay_method', 'pay.status as pay_status', 'pay.amount as pay_amount'
            );
    }

    /** Buttons the rider may press right now. Mirrors rider_assignments.blade.php exactly. */
    private function actions(object $r): array
    {
        if ($r->status === 'offered') {
            return [['type' => 'accept', 'status' => null, 'label' => 'Accept assignment', 'needs_reason' => false]];
        }
        if ($r->status !== 'accepted') return [];

        if ($r->kind === 'pickup' && $r->parcel_status === 'READY_FOR_PICKUP') {
            return [['type' => 'status', 'status' => 'PICKED_UP', 'label' => 'Confirm pickup from seller', 'needs_reason' => false]];
        }
        if ($r->kind === 'delivery' && $r->parcel_status === 'ASSIGNED_TO_RIDER') {
            return [['type' => 'status', 'status' => 'OUT_FOR_DELIVERY', 'label' => 'Out for delivery', 'needs_reason' => false]];
        }
        if ($r->kind === 'delivery' && $r->parcel_status === 'OUT_FOR_DELIVERY') {
            return [
                ['type' => 'status', 'status' => 'DELIVERED', 'label' => 'Mark delivered', 'needs_reason' => false],
                ['type' => 'status', 'status' => 'DELIVERY_FAILED', 'label' => 'Delivery failed', 'needs_reason' => true],
            ];
        }
        return [];
    }

    /**
     * Turn rows into API objects. The web portal only shows city/province to the rider;
     * the full recipient address, contact numbers and payment info are only included
     * while the rider has ACCEPTED the job and it is actionable.
     */
    private function shape($rows): array
    {
        $rows = collect($rows)->filter()->values();
        $items = $rows->isEmpty() ? collect() : DB::table('order_items')
            ->whereIn('order_id', $rows->pluck('order_id')->unique()->all())
            ->get(['order_id', 'name', 'quantity'])->groupBy('order_id');

        return $rows->map(function ($r) use ($items) {
            $actions = $this->actions($r);
            $detail = $r->status === 'accepted' && !empty($actions);

            $out = [
                'id' => (int) $r->id,
                'parcel_id' => (int) $r->parcel_id,
                'order_id' => (int) $r->order_id,
                'kind' => $r->kind,
                'status' => $r->status,
                'earnings' => (float) $r->earnings,
                'created_at' => $r->created_at,
                'tracking_code' => $r->tracking_code,
                'parcel_status' => $r->parcel_status,
                'failure_reason' => $r->failure_reason,
                'city' => $r->city,
                'province' => $r->province,
                'actions' => $actions,
                'recipient' => null,
                'seller' => null,
                'payment' => null,
                'items' => [],
            ];

            if ($detail) {
                $out['items'] = ($items[$r->order_id] ?? collect())
                    ->map(fn ($i) => ['name' => $i->name, 'quantity' => (int) $i->quantity])->values()->all();

                if ($r->kind === 'pickup') {
                    $out['seller'] = ['name' => $r->seller_name, 'phone' => $r->seller_phone];
                } else {
                    $out['recipient'] = [
                        'name' => $r->recipient, 'phone' => $r->recipient_phone, 'street' => $r->street,
                        'barangay' => $r->barangay, 'postal_code' => $r->postal_code,
                    ];
                    if ($r->pay_method !== null) {
                        $out['payment'] = ['method' => $r->pay_method, 'status' => $r->pay_status, 'amount' => (float) $r->pay_amount];
                    }
                }
            }
            return $out;
        })->all();
    }
}
