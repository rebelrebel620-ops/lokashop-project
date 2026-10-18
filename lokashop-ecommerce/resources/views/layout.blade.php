<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>@yield('title','LokaShop Marketplace')</title>
<link rel="icon" href="/favicon.jpg">
@vite('resources/css/app.css')
@yield('page-styles')
<link rel="stylesheet" href="{{ asset('css/public-navigation.css') }}">
<link rel="stylesheet" href="{{ asset('css/workspace-design.css') }}">
<script src="{{ asset('js/workspace-design.js') }}" defer></script>
</head>
<body class="@yield('body-class')">
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
<script>document.querySelectorAll('form[method="post"]').forEach(f=>{if(!f.querySelector('[name="_request_key"]')){const k=document.createElement('input');k.type='hidden';k.name='_request_key';k.value=crypto.randomUUID();f.appendChild(k);}f.addEventListener('submit',e=>{if(f.dataset.submitted){e.preventDefault();return;}f.dataset.submitted='1';setTimeout(()=>f.querySelectorAll('button[type="submit"],button:not([type])').forEach(b=>b.disabled=true),0);});});</script>@include('shared-interactions')
</body>
</html>
