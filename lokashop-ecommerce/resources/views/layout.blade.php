<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>@yield('title','LokaShop Marketplace')</title>
<link rel="icon" href="/favicon.jpg">
@vite('resources/css/app.css')
</head>
<body>
<div class="topribbon">&#128666; Free delivery on selected orders &middot; Shop local, live better.</div>
<header class="site">
 <a class="brand" href="/"><img src="/logo.jpg" alt="LokaShop logo">LokaShop<small>Shop Local. Live Better.</small></a>
 <form class="hsearch" action="/shop" method="get">
  <input name="q" placeholder="Search products, brands, or stores...">
  <button>Search</button>
 </form>
 <nav class="hnav">
  <a href="/shop">Shop</a>
  @auth
   <a href="/dashboard">Dashboard</a>
   <a href="/messages">Messages</a>
   <a href="/profile">Account</a>
   <form method="post" action="/logout">@csrf<button class="btn alt">Log out</button></form>
  @else
   <a href="/login">Log in</a>
   <a class="btn" href="/register">Sign up</a>
  @endauth
 </nav>
</header>
@yield('hero')
<div class="wrap">
 @if(session('success'))<p class="notice">{{session('success')}}</p>@endif
 @if($errors->any())<div class="error">@foreach($errors->all() as $error)<p>{{$error}}</p>@endforeach</div>@endif
 @yield('content')
</div>
<footer>
 <div class="foot">
  <div><h4>LokaShop Marketplace</h4><small style="margin:0">Local products from local sellers, delivered to your door.</small></div>
  <div><h4>Quick links</h4><a href="/shop">Browse products</a><a href="/register">Sell on LokaShop</a><a href="/login">Log in</a></div>
 </div>
 <small>&copy; {{date('Y')}} LokaShop. All rights reserved.</small>
</footer>
</body>
</html>
