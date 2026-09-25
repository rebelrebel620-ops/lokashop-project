@extends('layout')
@section('hero')
<section class="hero">
 <div class="hero-in">
  <div>
   <h1>Your everyday finds, delivered with care.</h1>
   <p>Shop trusted local sellers and track every order from checkout to your doorstep.</p>
   <form action="/shop" style="display:flex;gap:10px;max-width:520px;margin:22px 0 0">
    <input name="q" placeholder="Search products, e.g. reusable bag" style="margin:0;border:0;padding:14px 16px">
    <button style="margin:0;background:var(--lime);color:var(--dark)">Search</button>
   </form>
   <div class="btnrow">
    <a class="btn lg" href="/shop">Shop now &rarr;</a>
    <a class="btn lg alt" href="/register">Become a seller</a>
   </div>
   <div class="stats">
    <div><b>{{$stats['products']}}</b><span>Products</span></div>
    <div><b>{{$stats['sellers']}}</b><span>Verified sellers</span></div>
    <div><b>{{$stats['orders']}}</b><span>Orders delivered</span></div>
   </div>
  </div>
  <div class="hero-visual">
   <div class="vc"><div class="em">&#127911;</div>Electronics</div>
   <div class="vc"><div class="em">&#128092;</div>Fashion</div>
   <div class="vc"><div class="em">&#129530;</div>Beauty</div>
   <div class="vc wide"><span>&#128230; Support local sellers &middot; Go further together</span><span>&#128994;</span></div>
  </div>
 </div>
</section>
@endsection
@section('content')
@if($notice)<div class="banner"><strong>{{$notice->title}}:</strong> {{$notice->body}}</div>@endif

<section class="featbar" style="margin:-32px -20px 40px;border-radius:16px">
 <div class="in">
  <div class="f"><div class="ic">&#9989;</div><div><b>Verified sellers</b><small>Trusted local shops</small></div></div>
  <div class="f"><div class="ic">&#128274;</div><div><b>Secure checkout</b><small>Multiple payment methods</small></div></div>
  <div class="f"><div class="ic">&#128666;</div><div><b>Live order tracking</b><small>Real-time updates</small></div></div>
  <div class="f"><div class="ic">&#128205;</div><div><b>Local delivery</b><small>Supporting Philippine communities</small></div></div>
 </div>
</section>

<section class="sec">
 <h2>Shop by category</h2>
 <div class="cat-grid">
  @foreach($categories as $c)
  <a class="cat-card" href="/shop?category={{$c->id}}"><div class="ic">{{['Electronics'=>'💻','Fashion'=>'👕','Home & Living'=>'🛋️','Beauty'=>'🧴','Groceries'=>'🛒'][$c->name] ?? '🏷️'}}</div>{{$c->name}}</a>
  @endforeach
  <a class="cat-card" href="/shop"><div class="ic">&#8734;</div>All products</a>
 </div>
</section>

<section class="sec">
 <h2>Trending picks</h2>
 <p class="sub">Fresh from local sellers &middot; approved products, updated live.</p>
 <div class="grid">
  @forelse($featured as $p)
  <div class="card">
   <div class="product" style="--h:{{($p->category_id*47)%360}}">{{mb_strtoupper(mb_substr($p->name,0,1))}}</div>
   <span class="tag">{{$p->category}}</span>
   @if($p->discount_percent>0)<span class="tag off">{{rtrim(rtrim(number_format($p->discount_percent,1),'0'),'.')}}% OFF</span>@endif
   <h3>{{$p->name}}</h3>
   <p class="price">₱{{number_format($p->price*(1-$p->discount_percent/100),2)}}@if($p->discount_percent>0)<span class="price strike">₱{{number_format($p->price,2)}}</span>@endif</p>
   <small>{{$p->stock}} in stock</small><br>
   <a class="btn" href="/product/{{$p->id}}">View product</a>
  </div>
  @empty
  <p>No products yet. Check back soon.</p>
  @endforelse
 </div>
</section>

<section class="sec">
 <h2>From cart to doorstep</h2>
 <p class="sub">A simple and secure shopping experience, made for every Juan.</p>
 <div class="steps-row">
  <div class="st"><div class="ic">&#128722;</div><div><b>1. Checkout</b><small>Choose your items and secure payment</small></div></div>
  <div class="st"><div class="ic">&#128230;</div><div><b>2. Seller prepares</b><small>Your order is packed with care</small></div></div>
  <div class="st"><div class="ic">&#128666;</div><div><b>3. Out for delivery</b><small>Our local riders bring it to you</small></div></div>
  <div class="st"><div class="ic">&#128205;</div><div><b>4. Track your order</b><small>Get real-time updates to your doorstep</small></div></div>
 </div>
</section>

<section class="band">
 <h2>Sell your products on LokaShop</h2>
 <p>Register your business, list products and reach local buyers.</p>
 <a class="btn lime lg" href="/register">Become a seller</a>
</section>
@endsection
