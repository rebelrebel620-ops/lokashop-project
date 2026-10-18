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
        return view('center_pickups', [
            'links' => app(CommonController::class)->links(),

            'requests' => DB::table('assignments as s')
                ->join('parcels as p', 'p.id', '=', 's.parcel_id')
                ->join('orders as o', 'o.id', '=', 'p.order_id')
                ->join('addresses as a', 'a.id', '=', 'o.address_id')
                ->where('s.kind', 'pickup')
                ->where('s.status', 'requested')
                ->whereNull('p.center_id')
                ->whereIn(
                    's.rider_id',
                    DB::table('users')
                        ->where('company_id', auth()->id())
                        ->select('id')
                )
                ->select(
                    's.*',
                    'p.tracking_code',
                    'a.city',
                    'a.province'
                )
                ->paginate(20),
        ]);
    }

    public function verifyPickup(int $id)
    {
        $candidate=DB::table('assignments')->where('id',$id)->first();
        if($candidate) \App\Services\PickupRequests::refresh($candidate->parcel_id);
        DB::transaction(function () use ($id) {
            $a = DB::table('assignments')
                ->where('id', $id)
                ->where('kind', 'pickup')
                ->lockForUpdate()
                ->first();

            abort_unless($a, 404);

            $rider = DB::table('users')->find($a->rider_id);

            abort_unless(
                $rider && $rider->company_id == auth()->id(),
                403
            );

            $p = DB::table('parcels')
                ->where('id', $a->parcel_id)
                ->lockForUpdate()
                ->first();

            \App\Services\Municipalities::courier($rider, $p, 'pickup');

            if (
                $a->status === 'offered'
                && $p->center_id == $this->centerId()
            ) {
                return;
            }

            abort_unless(
                $a->status === 'requested' && !$p->center_id,
                422
            );

            DB::table('parcels')
                ->where('id', $p->id)
                ->update([
                    'center_id' => $this->centerId(),
                    'updated_at' => now(),
                ]);

            DB::table('assignments')
                ->where('id', $id)
                ->update([
                    'status' => 'offered',
                    'expires_at' => \App\Services\PickupRequests::deadline($a),
                    'updated_at' => now(),
                ]);
        });

        return back()->with(
            'success',
            'Pickup verified. The original 12-hour seller-request deadline still applies.'
        );
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

    public function sort(Request $r, int $id)
    {
        $v = $r->validate([
            'area_id' => 'required|exists:delivery_areas,id',
        ]);

        $p = DB::table('parcels')->find($id);

        abort_unless(
            $p && (int) $p->center_id === (int) $this->centerId(),
            403
        );

        $area = DB::table('delivery_areas')->find($v['area_id']);

        $order = DB::table('orders')
            ->where('id', $p->order_id)
            ->first();

        $address = $order->address_snapshot
            ? json_decode($order->address_snapshot)
            : DB::table('addresses')->find($order->address_id);

        abort_unless(
            (int) $area->center_id === (int) $this->centerId()
            && \App\Services\Municipalities::matches($area, $address),
            422,
            'Area must match the buyer address.'
        );

        Workflow::move(
            $id,
            'SORTED',
            auth()->id(),
            null,
            $area->id,
            $this->centerId()
        );

        return back()->with('success', 'Parcel sorted.');
    }

    public function assign(int $id)
    {
        $p = DB::table('parcels')
            ->where('id', $id)
            ->where('center_id', $this->centerId())
            ->first();

        abort_unless($p, 403);

        $area = DB::table('delivery_areas')
            ->find($p->delivery_area_id);

        \App\Services\Dispatch::offer(
            $id,
            auth()->id(),
            $area->rider_id ?? null
        );

        return back()->with('success', 'Delivery offer sent. The rider has 12 hours to accept.');
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
                ->where('s.rider_id', auth()->id())
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
            ->where('s.rider_id', auth()->id())->where('p.tracking_code', $code)
            ->orderByRaw("s.status in ('offered','accepted') desc")->orderByDesc('s.id')
            ->select('s.id', 's.parcel_id')->first();
        if (!$assignment) \App\Services\Support::fail('code', 'No assigned parcel matches this QR or tracking code.');
        // Open the correct pagination page, including older assignments.
        $newer = DB::table('assignments')->where('rider_id', auth()->id())->where('id', '>', $assignment->id)->count();
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
            'status' => 'required|in:PICKED_UP,OUT_FOR_DELIVERY,DELIVERED,DELIVERY_FAILED',
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