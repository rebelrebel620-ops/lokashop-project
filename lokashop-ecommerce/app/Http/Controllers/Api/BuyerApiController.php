<?php
namespace App\Http\Controllers\Api;

use App\Services\Workflow;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BuyerApiController extends Controller
{
    /* ---------------- Auth ---------------- */

    public function register(Request $r)
    {
        $v = $r->validate([
            'name' => 'required|string|max:150',
            'phone' => 'required|string|max:40',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
            'id_document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $id = DB::transaction(function () use ($r, $v) {
            $id = DB::table('users')->insertGetId([
                'name' => $v['name'], 'phone' => $v['phone'], 'email' => $v['email'],
                'password' => Hash::make($v['password']), 'role' => 'buyer',
                'approval_status' => 'approved', 'active' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($r->hasFile('id_document')) {
                DB::table('registration_documents')->insert([
                    'user_id' => $id, 'kind' => 'id',
                    'path' => $r->file('id_document')->store('documents'),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return $id;
        });

        return response()->json(['message' => 'Account created. You can log in now.', 'user_id' => $id]);
    }

    public function login(Request $r)
    {
        $v = $r->validate(['email' => 'required|email', 'password' => 'required']);
        $user = DB::table('users')->where('email', $v['email'])->first();

        if (!$user || !Hash::check($v['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Incorrect login details.']);
        }
        if ($user->role !== 'buyer') {
            throw ValidationException::withMessages(['email' => 'This account is not a buyer account.']);
        }
        if ($user->approval_status !== 'approved' || !$user->active) {
            throw ValidationException::withMessages(['email' => 'Account waiting for approval or inactive.']);
        }

        $token = Str::random(60);
        DB::table('users')->where('id', $user->id)->update(['remember_token' => $token]);

        return response()->json([
            'token' => $token,
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone],
        ]);
    }

    public function logout(Request $r)
    {
        DB::table('users')->where('id', auth()->id())->update(['remember_token' => null]);
        return response()->json(['message' => 'Logged out.']);
    }

    public function me()
    {
        $u = DB::table('users')->where('id', auth()->id())->first(['id', 'name', 'email', 'phone', 'created_at']);
        return response()->json($u);
    }

    public function updateProfile(Request $r)
    {
        $v = $r->validate(['name' => 'required|string|max:150', 'phone' => 'required|string|max:40']);
        DB::table('users')->where('id', auth()->id())->update($v + ['updated_at' => now()]);
        return response()->json(['message' => 'Profile saved.']);
    }

    /* ---------------- Catalog ---------------- */

    public function categories()
    {
        return response()->json(DB::table('categories')->orderBy('name')->get());
    }

    public function products(Request $r)
    {
        $q = DB::table('products')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->select('products.*', 'categories.name as category')
            ->where('products.active', 1)->where('products.approved', 1);
        if ($r->filled('q')) $q->where('products.name', 'like', '%' . $r->q . '%');
        if ($r->filled('category')) $q->where('category_id', $r->integer('category'));
        return response()->json($q->orderByDesc('products.id')->paginate(20));
    }

    public function product(int $id)
    {
        $p = DB::table('products')->join('categories', 'categories.id', '=', 'products.category_id')
            ->select('products.*', 'categories.name as category')
            ->where('products.id', $id)->where('active', 1)->where('approved', 1)->first();
        abort_unless($p, 404);
        $seller = DB::table('seller_businesses')->where('user_id', $p->seller_id)->first();
        $rating = DB::table('ratings')->join('orders', 'orders.id', '=', 'ratings.order_id')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->where('order_items.product_id', $id);
        return response()->json([
            'product' => $p,
            'seller_name' => $seller->name ?? null,
            'variations' => DB::table('product_variations')->where('product_id', $id)->get(),
            'rating_avg' => round((clone $rating)->avg('stars') ?? 0, 1),
            'rating_count' => (clone $rating)->count(),
        ]);
    }

    /* ---------------- Cart ---------------- */

    private function cartRows()
    {
        return DB::table('cart_items as c')
            ->join('products as p', 'p.id', '=', 'c.product_id')
            ->leftJoin('product_variations as v', 'v.id', '=', 'c.variation_id')
            ->where('c.buyer_id', auth()->id())
            ->select('c.*', 'p.name', 'p.price', 'p.discount_percent', 'p.seller_id', 'p.stock as product_stock',
                'v.name as variation', 'v.price_adjustment', 'v.stock as variation_stock')
            ->orderByDesc('c.id')->get();
    }

    public function cart()
    {
        return response()->json($this->cartRows());
    }

    public function cartAdd(Request $r)
    {
        $v = $r->validate([
            'product_id' => 'required|exists:products,id',
            'variation_id' => 'nullable|exists:product_variations,id',
            'quantity' => 'required|integer|min:1|max:100',
        ]);
        $p = DB::table('products')->where('id', $v['product_id'])->where('active', 1)->where('approved', 1)->first();
        abort_unless($p, 404);
        if (isset($v['variation_id'])) {
            abort_unless(DB::table('product_variations')->where('id', $v['variation_id'])->where('product_id', $p->id)->exists(), 422);
        }
        $existing = DB::table('cart_items')->where('buyer_id', auth()->id())->where('product_id', $p->id)
            ->where('variation_id', $v['variation_id'] ?? null)->first();
        if ($existing) {
            DB::table('cart_items')->where('id', $existing->id)->update(['quantity' => $existing->quantity + $v['quantity'], 'updated_at' => now()]);
        } else {
            DB::table('cart_items')->insert(['buyer_id' => auth()->id(), 'product_id' => $p->id,
                'variation_id' => $v['variation_id'] ?? null, 'quantity' => $v['quantity'],
                'created_at' => now(), 'updated_at' => now()]);
        }
        return response()->json($this->cartRows());
    }

    public function cartUpdate(Request $r, int $id)
    {
        $v = $r->validate(['quantity' => 'required|integer|min:1|max:100']);
        abort_unless(DB::table('cart_items')->where('id', $id)->where('buyer_id', auth()->id())->exists(), 404);
        DB::table('cart_items')->where('id', $id)->update(['quantity' => $v['quantity'], 'updated_at' => now()]);
        return response()->json($this->cartRows());
    }

    public function cartRemove(int $id)
    {
        DB::table('cart_items')->where('id', $id)->where('buyer_id', auth()->id())->delete();
        return response()->json($this->cartRows());
    }

    /* ---------------- Addresses ---------------- */

    public function addresses()
    {
        return response()->json(DB::table('addresses')->where('user_id', auth()->id())
            ->orderByDesc('is_default')->orderByDesc('id')->get());
    }

    public function addressAdd(Request $r)
    {
        $v = $r->validate([
            'recipient' => 'required|max:150', 'phone' => 'required|max:40', 'street' => 'required|max:200',
            'barangay' => 'required|max:120', 'city' => 'required|max:120', 'province' => 'required|max:120',
            'postal_code' => 'nullable|max:20', 'is_default' => 'nullable|boolean',
        ]);
        $makeDefault = (bool) ($v['is_default'] ?? false);
        unset($v['is_default']);
        DB::transaction(function () use ($v, $makeDefault) {
            if ($makeDefault) DB::table('addresses')->where('user_id', auth()->id())->update(['is_default' => 0]);
            DB::table('addresses')->insert($v + ['user_id' => auth()->id(), 'is_default' => $makeDefault ? 1 : 0,
                'created_at' => now(), 'updated_at' => now()]);
        });
        return response()->json(DB::table('addresses')->where('user_id', auth()->id())->orderByDesc('is_default')->orderByDesc('id')->get());
    }

    public function addressSetDefault(int $id)
    {
        abort_unless(DB::table('addresses')->where('id', $id)->where('user_id', auth()->id())->exists(), 404);
        DB::transaction(function () use ($id) {
            DB::table('addresses')->where('user_id', auth()->id())->update(['is_default' => 0]);
            DB::table('addresses')->where('id', $id)->update(['is_default' => 1]);
        });
        return response()->json(DB::table('addresses')->where('user_id', auth()->id())->orderByDesc('is_default')->orderByDesc('id')->get());
    }

    /* ---------------- Checkout / Orders ---------------- */

    public function checkout(Request $r)
    {
        $v = $r->validate([
            'address_id' => 'required|integer', 'payment_method' => 'required|in:cod,manual', 'voucher' => 'nullable|string|max:60',
        ]);
        abort_unless(DB::table('addresses')->where('id', $v['address_id'])->where('user_id', auth()->id())->exists(), 403);
        $ids = DB::table('cart_items')->where('buyer_id', auth()->id())->pluck('id');
        abort_if($ids->isEmpty(), 422, 'Cart is empty');

        $orderIds = DB::transaction(function () use ($ids, $v) {
            $rows = DB::table('cart_items')->whereIn('id', $ids)->where('buyer_id', auth()->id())->lockForUpdate()->get();
            $groups = [];
            foreach ($rows as $row) {
                $p = DB::table('products')->where('id', $row->product_id)->lockForUpdate()->first();
                abort_unless($p && $p->active && $p->approved, 422, 'Product unavailable');
                $variation = $row->variation_id ? DB::table('product_variations')->where('id', $row->variation_id)->where('product_id', $p->id)->lockForUpdate()->first() : null;
                abort_if($row->variation_id && !$variation, 422);
                abort_if($row->quantity > ($variation ? $variation->stock : $p->stock) || $row->quantity > $p->stock, 422, 'Not enough stock');
                $price = round(((float) $p->price + (float) ($variation->price_adjustment ?? 0)) * (1 - (float) $p->discount_percent / 100), 2);
                $groups[$p->seller_id][] = ['row' => $row, 'product' => $p, 'variation' => $variation, 'price' => $price];
            }
            $voucher = null;
            if (!empty($v['voucher'])) {
                $voucher = DB::table('vouchers')->where('code', $v['voucher'])->where('active', 1)
                    ->where(function ($q) { $q->whereNull('expires_at')->orWhere('expires_at', '>', now()); })->first();
                if (!$voucher) throw ValidationException::withMessages(['voucher' => 'Voucher invalid or expired.']);
                if ($voucher->seller_id && (count($groups) !== 1 || !isset($groups[$voucher->seller_id]))) {
                    throw ValidationException::withMessages(['voucher' => 'Voucher is only for its seller.']);
                }
            }
            $created = [];
            foreach ($groups as $sellerId => $items) {
                $subtotal = array_sum(array_map(fn($x) => $x['row']->quantity * $x['price'], $items));
                $discount = round($subtotal * (float) ($voucher->discount_percent ?? 0) / 100, 2);
                $total = round($subtotal - $discount, 2);
                $oid = DB::table('orders')->insertGetId(['buyer_id' => auth()->id(), 'seller_id' => $sellerId,
                    'address_id' => $v['address_id'], 'status' => 'PLACED', 'total' => $total, 'discount' => $discount,
                    'commission' => round($total * .1, 2), 'voucher_id' => $voucher->id ?? null,
                    'created_at' => now(), 'updated_at' => now()]);
                $created[] = $oid;
                foreach ($items as $x) {
                    $row = $x['row']; $p = $x['product'];
                    DB::table('products')->where('id', $p->id)->decrement('stock', $row->quantity);
                    if ($x['variation']) DB::table('product_variations')->where('id', $x['variation']->id)->decrement('stock', $row->quantity);
                    DB::table('order_items')->insert(['order_id' => $oid, 'product_id' => $p->id, 'variation_id' => $row->variation_id,
                        'name' => $p->name . ' ' . ($x['variation']->name ?? ''), 'quantity' => $row->quantity, 'unit_price' => $x['price'],
                        'created_at' => now(), 'updated_at' => now()]);
                }
                DB::table('payments')->insert(['order_id' => $oid, 'method' => $v['payment_method'],
                    'status' => $v['payment_method'] === 'cod' ? 'due_on_delivery' : 'awaiting_manual_confirmation',
                    'amount' => $total, 'created_at' => now(), 'updated_at' => now()]);
                $pid = DB::table('parcels')->insertGetId(['order_id' => $oid, 'tracking_code' => 'LKS-' . strtoupper(Str::random(12)),
                    'status' => 'PLACED', 'created_at' => now(), 'updated_at' => now()]);
                DB::table('status_history')->insert(['parcel_id' => $pid, 'from_status' => null, 'to_status' => 'PLACED',
                    'actor_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
                DB::table('notifications')->insert(['user_id' => $sellerId, 'body' => 'New order #' . $oid, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('cart_items')->whereIn('id', $ids)->where('buyer_id', auth()->id())->delete();
            return $created;
        });

        return response()->json(['message' => 'Order placed.', 'order_ids' => $orderIds]);
    }

    public function orders()
    {
        return response()->json(
            DB::table('orders as o')->join('parcels as p', 'p.order_id', '=', 'o.id')
                ->join('users as s', 's.id', '=', 'o.seller_id')
                ->leftJoin('seller_businesses as sb', 'sb.user_id', '=', 'o.seller_id')
                ->where('o.buyer_id', auth()->id())
                ->select('o.*', 'p.id as parcel_id', 'p.tracking_code', 'p.failure_reason',
                    DB::raw('COALESCE(sb.name, s.name) as seller_name'))
                ->orderByDesc('o.id')->get()
        );
    }

    public function orderDetail(int $id)
    {
        $order = DB::table('orders as o')->join('parcels as p', 'p.order_id', '=', 'o.id')
            ->leftJoin('seller_businesses as sb', 'sb.user_id', '=', 'o.seller_id')
            ->leftJoin('users as s', 's.id', '=', 'o.seller_id')
            ->where('o.id', $id)->where('o.buyer_id', auth()->id())
            ->select('o.*', 'p.id as parcel_id', 'p.tracking_code', 'p.failure_reason',
                DB::raw('COALESCE(sb.name, s.name) as seller_name'))
            ->first();
        abort_unless($order, 404);
        return response()->json([
            'order' => $order,
            'items' => DB::table('order_items')->where('order_id', $id)->get(),
            'address' => DB::table('addresses')->where('id', $order->address_id)->first(),
            'payment' => DB::table('payments')->where('order_id', $id)->first(),
            'history' => DB::table('status_history')->where('parcel_id', $order->parcel_id)->orderBy('id')->get(),
            'rating' => DB::table('ratings')->where('order_id', $id)->first(),
        ]);
    }

    public function orderConfirm(int $id)
    {
        $p = DB::table('parcels')->where('order_id', $id)->first();
        abort_unless($p, 404);
        Workflow::move($p->id, 'COMPLETED', auth()->id());
        return response()->json(['message' => 'Order completed.']);
    }

    public function orderRating(Request $r, int $id)
    {
        $v = $r->validate(['stars' => 'required|integer|between:1,5', 'comment' => 'nullable|string|max:2000']);
        abort_unless(DB::table('orders')->where('id', $id)->where('buyer_id', auth()->id())->where('status', 'COMPLETED')->exists(), 403);
        DB::table('ratings')->updateOrInsert(['order_id' => $id], $v + ['buyer_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
        return response()->json(['message' => 'Review saved.']);
    }

    /* ---------------- Misc: vouchers, notifications, messages, complaints ---------------- */

    public function vouchers()
    {
        return response()->json(DB::table('vouchers')->where('active', 1)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('id')->get());
    }

    public function notifications()
    {
        return response()->json(DB::table('notifications')->where('user_id', auth()->id())->orderByDesc('id')->limit(50)->get());
    }

    public function messageContacts()
    {
        return response()->json(DB::table('users')->where('active', 1)->where('approval_status', 'approved')
            ->where('id', '!=', auth()->id())->whereIn('role', ['seller', 'admin'])->select('id', 'name', 'role')->get());
    }

    public function messageThread(int $userId)
    {
        return response()->json(DB::table('messages')
            ->where(function ($q) use ($userId) {
                $q->where('sender_id', auth()->id())->where('recipient_id', $userId);
            })->orWhere(function ($q) use ($userId) {
                $q->where('sender_id', $userId)->where('recipient_id', auth()->id());
            })->orderBy('id')->get());
    }

    public function sendMessage(Request $r)
    {
        $v = $r->validate(['recipient_id' => 'required|exists:users,id|different:sender_id', 'body' => 'required|string|max:2000']);
        abort_if((int) $v['recipient_id'] === auth()->id(), 422);
        DB::table('messages')->insert($v + ['sender_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
        return response()->json(['message' => 'Sent.']);
    }

    public function complaints()
    {
        return response()->json(DB::table('complaints')->where('user_id', auth()->id())->orderByDesc('id')->get());
    }

    public function complaintCreate(Request $r)
    {
        $v = $r->validate(['order_id' => 'required|integer', 'description' => 'required|string|max:3000']);
        abort_unless(DB::table('orders')->where('id', $v['order_id'])->where('buyer_id', auth()->id())->exists(), 403);
        DB::table('complaints')->insert($v + ['user_id' => auth()->id(), 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
        return response()->json(['message' => 'Complaint sent.']);
    }
}
