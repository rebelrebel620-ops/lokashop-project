<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>@yield('title','LokaShop')</title>
<link rel="icon" href="/favicon.jpg">
@vite('resources/css/app.css')
</head>
<body>
@php
 $role = auth()->user()->role;
 $isBuyer = $role === 'buyer';
 $roleLabel = match($role){'buyer'=>'Marketplace','seller'=>'Seller Center','admin'=>'Admin','sorting_center'=>'Sorting Center','rider'=>'Rider App',default=>''};
 $navLinks = ['Overview' => '/dashboard'] + $links;
 if (!array_key_exists('Messages', $navLinks)) $navLinks['Messages'] = '/messages';
 if (!array_key_exists('Account', $navLinks)) $navLinks['Account'] = '/profile';
 $active = fn($url) => request()->is(ltrim($url,'/')) || request()->is(ltrim($url,'/').'/*');
 $icon = function($label){
  $map = [
   'Overview'=>'<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v10h14V10"/>',
   'Shop'=>'<path d="M4 8h16l-1.5 11H5.5L4 8Z"/><path d="M8 8V6a4 4 0 0 1 8 0v2"/>',
   'Cart'=>'<circle cx="9" cy="20" r="1.4"/><circle cx="17" cy="20" r="1.4"/><path d="M3 4h2l2.2 11.2A2 2 0 0 0 9.16 17H17a2 2 0 0 0 1.96-1.6L20.5 8H6"/>',
   'My orders'=>'<path d="M21 8 12 3 3 8l9 5 9-5Z"/><path d="M3 8v9l9 5 9-5V8"/><path d="M12 13v9"/>',
   'Orders'=>'<path d="M21 8 12 3 3 8l9 5 9-5Z"/><path d="M3 8v9l9 5 9-5V8"/><path d="M12 13v9"/>',
   'Pickup requests'=>'<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a4 4 0 0 1 8 0v2"/>',
   'Applications'=>'<path d="m9 12 2 2 4-4"/><rect x="3" y="4" width="18" height="16" rx="2"/>',
   'Approvals'=>'<path d="m9 12 2 2 4-4"/><rect x="3" y="4" width="18" height="16" rx="2"/>',
   'Products'=>'<path d="M21 8 12 3 3 8l9 5 9-5Z"/><path d="M3 8v9l9 5 9-5V8"/><path d="M12 13v9"/>',
   'Seller products'=>'<path d="M21 8 12 3 3 8l9 5 9-5Z"/><path d="M3 8v9l9 5 9-5V8"/><path d="M12 13v9"/>',
   'Inventory'=>'<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18"/>',
   'Vouchers'=>'<path d="M4 8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4V8Z"/>',
   'Sales'=>'<path d="M4 19V5"/><path d="m4 19 6-6 4 4 6-8"/>',
   'Sales reports'=>'<path d="M4 19V5"/><path d="m4 19 6-6 4 4 6-8"/>',
   'Reports'=>'<path d="M4 19V5"/><path d="m4 19 6-6 4 4 6-8"/>',
   'Earnings'=>'<path d="M4 19V5"/><path d="m4 19 6-6 4 4 6-8"/>',
   'Complaints'=>'<path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9 2.5 18a1.8 1.8 0 0 0 1.6 2.6h15.8a1.8 1.8 0 0 0 1.6-2.6L13.7 3.9a1.8 1.8 0 0 0-3.4 0Z"/>',
   'Messages'=>'<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10Z"/>',
   'Users'=>'<circle cx="9" cy="8" r="3.2"/><path d="M2.5 20c0-3.6 3-6 6.5-6s6.5 2.4 6.5 6"/><circle cx="17.5" cy="9" r="2.6"/><path d="M15.5 14.2c2.7.3 5 2.3 5 5.8"/>',
   'Announcements'=>'<path d="M4 10v4a1 1 0 0 0 1 1h2l4 4V5L7 9H5a1 1 0 0 0-1 1Z"/><path d="M15 8a4 4 0 0 1 0 8"/><path d="M18 5a8 8 0 0 1 0 14"/>',
   'Areas'=>'<path d="M12 22s7-6.1 7-12a7 7 0 1 0-14 0c0 5.9 7 12 7 12Z"/><circle cx="12" cy="10" r="2.6"/>',
   'Assignments'=>'<rect x="3" y="6" width="14" height="12" rx="2"/><path d="M9 3h4v3H9z"/><path d="m17 10 4-2v9l-4-2"/>',
   'Parcels'=>'<path d="M21 8 12 3 3 8l9 5 9-5Z"/><path d="M3 8v9l9 5 9-5V8"/><path d="M12 13v9"/>',
   'Account'=>'<circle cx="12" cy="8" r="3.6"/><path d="M4.5 20c0-4.1 3.4-7 7.5-7s7.5 2.9 7.5 7"/>',
  ];
  $d = $map[$label] ?? '<path d="M21 8 12 3 3 8l9 5 9-5Z"/><path d="M3 8v9l9 5 9-5V8"/><path d="M12 13v9"/>';
  return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'.$d.'</svg>';
 };
 $cartCount = $isBuyer ? \Illuminate\Support\Facades\DB::table('cart_items')->where('buyer_id',auth()->id())->sum('quantity') : 0;
 $unread = \Illuminate\Support\Facades\DB::table('notifications')->where('user_id',auth()->id())->whereNull('read_at')->count();
 $initial = mb_strtoupper(mb_substr(auth()->user()->name,0,1));
@endphp
@php $sidebarClass = $isBuyer ? 'sidebar' : 'sidebar dark'; @endphp

@if($isBuyer)
<header class="topbar" style="padding:12px 4%">
 <button class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('open')">&#9776;</button>
 <a href="/" class="brand" style="display:flex;align-items:center;gap:9px;color:var(--dark);font-weight:800">
  <img src="/logo.jpg" alt="LokaShop" style="width:36px;height:36px;border-radius:9px">LokaShop
 </a>
 <form class="tsearch" action="/shop" method="get" style="margin:0">
  <input name="q" placeholder="Search products, brands, or stores...">
 </form>
 <div class="ticons">
  <a href="/profile" title="Notifications">&#128276;@if($unread)<span class="badge-dot">{{$unread}}</span>@endif</a>
  <a href="/cart" title="Cart">&#128722;@if($cartCount)<span class="badge-dot">{{$cartCount}}</span>@endif</a>
  <div class="who"><div class="av">{{$initial}}</div><div><b>{{auth()->user()->name}}</b><small>Buyer</small></div></div>
 </div>
</header>
@endif

<div class="appshell">
 <aside class="{{$sidebarClass}}">
  @unless($isBuyer)
  <a href="/" class="brand">
   <img src="/logo.jpg" alt="LokaShop">
   <span>LokaShop<small>{{$roleLabel}}</small></span>
  </a>
  @endunless
  <nav>
   @foreach($navLinks as $label => $url)
   <a href="{{$url}}" class="{{$active($url) ? 'active' : ''}}">{!! $icon($label) !!}<span>{{$label}}</span></a>
   @endforeach
  </nav>
  <hr>
  <form method="post" action="/logout">@csrf<button class="btn alt" style="width:100%">Log out</button></form>
  @if(!$isBuyer)
  <div class="sidebot"><b>Need help?</b>Message the LokaShop team any time from your Messages tab.</div>
  @endif
 </aside>

 <div style="flex:1;min-width:0;display:flex;flex-direction:column">
  @unless($isBuyer)
  <header class="topbar">
   <button class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('open')">&#9776;</button>
   <form class="tsearch" style="margin:0" onsubmit="return false">
    <input placeholder="Search {{ $role==='admin' ? 'users, orders, products...' : 'orders, products, or buyers...' }}">
   </form>
   <div class="ticons">
    <a href="/profile" title="Notifications">&#128276;@if($unread)<span class="badge-dot">{{$unread}}</span>@endif</a>
    <div class="who"><div class="av">{{$initial}}</div><div><b>{{auth()->user()->name}}</b><small>{{ucfirst(str_replace('_',' ',$role))}}</small></div></div>
   </div>
  </header>
  @endunless

  <main class="main"><div class="main-in">
   @if(session('success'))<p class="notice">{{session('success')}}</p>@endif
   @if($errors->any())<div class="error">@foreach($errors->all() as $error)<p>{{$error}}</p>@endforeach</div>@endif
   @yield('content')
  </div></main>
 </div>
</div>
</body>
</html>
