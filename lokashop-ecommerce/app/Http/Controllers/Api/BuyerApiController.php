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
        $r->merge(['role'=>'buyer']);
        $id=\App\Services\Registration::create($r);
        return response()->json(['message'=>'Check your email for a verification code.','verification_token'=>BuyerVerificationController::token($id),'email'=>$r->input('email'),'retry_after'=>60],202);
    }

    public function login(Request $r)
    {
        $r->merge(['email'=>mb_strtolower(trim((string)$r->input('email')))]);
        $v = $r->validate(['email' => 'required|email', 'password' => 'required']);
        $user = DB::table('users')->where('email', $v['email'])->first();

        if (!$user || !Hash::check($v['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Incorrect login details.']);
        }
        if ($user->role !== 'buyer') {
            throw ValidationException::withMessages(['email' => 'This account is not a buyer account.']);
        }
        if ($user->active && $user->account_status==='active' && $user->approval_status==='unverified' && ($user->verification_required ?? false)) {
            return response()->json(['message'=>'Verify your email before logging in.','verification_required'=>true,'verification_token'=>BuyerVerificationController::token(\App\Services\PendingRegistration::resumeLegacy($user->id)),'email'=>$user->email],403);
        }
        if($user->account_status==='banned')throw ValidationException::withMessages(['email'=>'This account is permanently banned. Appeals are unavailable.']);
        $suspended=$user->account_status==='suspended';
        if ($user->approval_status !== 'approved' || (!$suspended&&(!$user->active||$user->account_status!=='active')) || (($user->verification_required ?? false) && !$user->email_verified_at)) {
            throw ValidationException::withMessages(['email' => 'Your account is inactive or unavailable. Contact LokaShop support.']);
        }

        $token = Str::random(60);
        DB::table('users')->where('id', $user->id)->update(['remember_token' => hash('sha256',$token),'api_token_expires_at'=>now()->addMinutes(30),'last_seen_at'=>now()]);

        return response()->json([
            'token' => $token,
            'account_status'=>$user->account_status,'appeal_required'=>$suspended,'appeal_url'=>$suspended?url('/api/appeal'):null,
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone,'profile_photo_url'=>\App\Services\Support::imageUrl($user->profile_photo),'account_status'=>$user->account_status],
        ]);
    }

    public function logout(Request $r)
    {
        DB::table('users')->where('id', auth()->id())->update(['remember_token' => null]);
        return response()->json(['message' => 'Logged out.']);
    }

    public function me()
    {
        $u = DB::table('users')->where('id', auth()->id())->first(['id', 'name', 'email', 'phone', 'profile_photo', 'created_at']);
        $u->profile_photo_url=\App\Services\Support::imageUrl($u->profile_photo);return response()->json($u);
    }

    public function updateProfile(Request $r){\App\Services\Cart::profile($r);return response()->json(['message'=>'Profile saved.']);}

    /* ---------------- Catalog ---------------- */

    public function categories()
    {
        return response()->json(DB::table('categories')->whereNotNull('category_code')->orderBy('id')->get());
    }

    public function products(Request $r){$page=\App\Services\Catalog::query($r)->paginate(20)->withQueryString();$page->getCollection()->transform(fn($p)=>\App\Http\Controllers\Api\MobileBuyerController::productCard($p));return response()->json($page);}

    public function product(int $id)
    {
        $p = \App\Services\Commerce::catalog(DB::table('products'))->join('categories', 'categories.id', '=', 'products.category_id')
            ->select('products.*', 'categories.name as category')
            ->where('products.id', $id)->where('active', 1)->where('approved', 1)->where('draft',0)->whereNull('products.deleted_at')->first();
        abort_unless($p, 404);
        $seller = DB::table('seller_businesses')->where('user_id', $p->seller_id)->first();
        $rating = DB::table('item_reviews')->join('order_items','order_items.id','=','item_reviews.order_item_id')
            ->where('order_items.product_id', $id);
        return response()->json([
            'product' => \App\Http\Controllers\Api\MobileBuyerController::productCard($p),
            'seller_name' => $seller->name ?? null,
            'variations' => DB::table('product_variations')->where('product_id', $id)->whereNull('deleted_at')->get()->map(function($v)use($p){$v->effective_price=\App\Services\Catalog::price($p,$v);return $v;}),
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
            ->select('c.*', 'p.name', 'p.price', 'p.discount_percent', 'p.seller_id', 'p.stock as product_stock', 'p.images',
                'v.name as variation', 'v.price_adjustment', 'v.stock as variation_stock')
            ->orderByDesc('c.id')->get()->map(function($row){$p=DB::table('products')->find($row->product_id);$variant=$row->variation_id?DB::table('product_variations')->find($row->variation_id):null;$row->effective_price=\App\Services\Catalog::price($p,$variant);return $row;});
    }

    public function cart()
    {
        return response()->json($this->cartRows());
    }

    public function cartAdd(Request $r){\App\Services\Cart::add($r);return response()->json($this->cartRows());}

    public function cartUpdate(Request $r,int $id){\App\Services\Cart::update($r,$id);return response()->json($this->cartRows());}

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

    public function addressAdd(Request $r){app(\App\Http\Controllers\MarketController::class)->address($r);return $this->addresses();}

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

    public function checkout(Request $r){$ids=\App\Services\Commerce::checkout($r);return response()->json(['message'=>'Order placed.','order_ids'=>$ids]);}

    public function orders()
    {
        return response()->json(
            DB::table('orders as o')->join('parcels as p', 'p.order_id', '=', 'o.id')
                ->join('users as s', 's.id', '=', 'o.seller_id')
                ->leftJoin('seller_businesses as sb', 'sb.user_id', '=', 'o.seller_id')
                ->where('o.buyer_id', auth()->id())
                ->select('o.*', 'p.id as parcel_id', 'p.tracking_code', 'p.failure_reason',
                    DB::raw("CASE WHEN p.proof_path IS NOT NULL AND o.status IN ('DELIVERED','COMPLETED') THEN 1 ELSE 0 END as has_delivery_proof"),
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
                    DB::raw("CASE WHEN p.proof_path IS NOT NULL AND o.status IN ('DELIVERED','COMPLETED') THEN 1 ELSE 0 END as has_delivery_proof"),
                DB::raw('COALESCE(sb.name, s.name) as seller_name'))
            ->first();
        abort_unless($order, 404);
        $order->proof_url = $order->has_delivery_proof ? url('/api/orders/'.$order->id.'/proof') : null;
        return response()->json([
            'order' => $order,
            'items' => DB::table('order_items')->where('order_id', $id)->get()->map(function($i){$i->image_url=\App\Services\Support::imageUrl($i->image);$i->review=DB::table('item_reviews')->where('order_item_id',$i->id)->where('buyer_id',auth()->id())->first();return $i;}),
            'address' => $order->address_snapshot ? json_decode($order->address_snapshot) : DB::table('addresses')->where('id', $order->address_id)->first(),
            'payment' => DB::table('payments')->where('order_id', $id)->first(),
            'history' => DB::table('status_history')->where('parcel_id', $order->parcel_id)->orderBy('id')->get(),
            'rating' => DB::table('ratings')->where('order_id', $id)->first(),
            'return_request'=>DB::table('return_requests')->where('order_id',$id)->where('buyer_id',auth()->id())->first(),
        ]);
    }

    public function orderConfirm(int $id)
    {
        $p = DB::table('parcels')->where('order_id', $id)->first();
        abort_unless($p, 404);
        Workflow::move($p->id, 'COMPLETED', auth()->id());
        return response()->json(['message' => 'Order completed.']);
    }

    public function orderRating(Request $r,int $id){$o=\App\Services\Support::ownsOrder($id);abort_unless($o->buyer_id==auth()->id(),403);if(!$r->filled('order_item_id')){$items=DB::table('order_items')->where('order_id',$id)->get();if($items->count()!==1)\App\Services\Support::fail('order_item_id','Select an order item to review on the Reviews page.');$r->merge(['order_item_id'=>$items[0]->id]);}abort_unless(DB::table('order_items')->where('id',$r->order_item_id)->where('order_id',$id)->exists(),403);return app(\App\Http\Controllers\MarketController::class)->review($r);}

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
        return response()->json(DB::table('users')->where('active', 1)->where('account_status','active')->where('approval_status', 'approved')
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

    public function sendMessage(Request $r){$v=$r->validate(['recipient_id'=>'required|integer|exists:users,id','body'=>'required|string|max:1000','attachment'=>'nullable|file|mimes:jpg,jpeg,png|extensions:jpg,jpeg,png|max:5120']);$u=auth()->user();abort_if($v['recipient_id']==$u->id,422);$recipient=DB::table('users')->find($v['recipient_id']);abort_unless($recipient->active&&$recipient->approval_status==='approved',403);$roles=match($u->role){'buyer'=>['seller','admin'],'seller'=>['buyer','admin'],'admin'=>['buyer','seller'],'rider'=>['sorting_center'],'sorting_center'=>['rider','logistics_admin'],'logistics_admin'=>['sorting_center']};abort_unless(in_array($recipient->role,$roles,true),403);if($u->role==='rider')abort_unless($u->company_id==$recipient->id,403);if($u->role==='sorting_center'&&$recipient->role==='rider')abort_unless($recipient->company_id==$u->id,403);unset($v['attachment']);if($r->hasFile('attachment'))$v['attachment']=$r->file('attachment')->store('chat','local');DB::table('messages')->insert($v+['sender_id'=>$u->id,'created_at'=>now(),'updated_at'=>now()]);return $r->expectsJson()?response()->json(['message'=>'Sent.']):back()->with('success','Sent.');}

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
