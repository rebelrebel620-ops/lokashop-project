<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>@yield('title','LokaShop')</title>
<link rel="icon" href="/favicon.jpg">
@vite('resources/css/app.css')
<link rel="stylesheet" href="{{ asset('css/account-cart-fixes.css') }}">
<link rel="stylesheet" href="{{ asset('css/workspace-design.css') }}">
<script src="{{ asset('js/workspace-design.js') }}" defer></script>
<link rel="stylesheet" href="{{asset('css/avatar-courier-fixes.css')}}">
<link rel="stylesheet" href="{{asset('css/admin-user-details.css')}}">
<link rel="stylesheet" href="{{asset('css/role-backgrounds.css')}}">
<link rel="stylesheet" href="{{ asset('css/navigation-polish.css').'?v=3' }}">
<link rel="stylesheet" href="{{asset('css/phone-density.css')}}">
<style id="workspace-drawer-styles">
.workspace-menu-close,.workspace-menu-backdrop{display:none!important}
@media screen and (max-width:1024px){
 body .appshell>.sidebar,body .appshell>.sidebar.dark{position:fixed;inset:0 auto 0 0;z-index:1001;width:min(300px,86vw);height:100vh;height:100dvh;max-height:none;box-sizing:border-box;overflow-y:auto;overflow-x:hidden;transform:translateX(-100%);visibility:hidden;box-shadow:8px 0 30px #0003}
 body.workspace-menu-open .appshell>.sidebar{transform:translateX(0);visibility:visible}
 body.workspace-menu-open{overflow:hidden}
 body .appshell{display:flex!important}
 body .appshell>.sidebar~div{width:100%;margin-left:0}
 body .menu-toggle[data-workspace-menu]{display:inline-flex!important;align-items:center;justify-content:center;width:44px;height:44px;flex:0 0 44px;cursor:pointer}
 body .sidebar .brand{padding-right:44px}
 body .sidebar .workspace-menu-close{display:flex!important;position:sticky;top:8px;align-self:flex-end;flex-shrink:0;align-items:center;justify-content:center;z-index:2;width:40px;height:40px;min-height:40px;margin:0 0 4px auto;padding:0;background:#173f32;color:#fff;border:1px solid #a9cdb84d;border-radius:8px;font-size:28px;cursor:pointer}
 body .workspace-menu-backdrop:not([hidden]){display:block!important;position:fixed;inset:0;z-index:1000;width:100%;height:100%;margin:0;padding:0;border:0;border-radius:0;background:#061f18a3;cursor:pointer}
 body .workspace-menu-close:focus-visible,body [data-workspace-menu]:focus-visible{outline:2px solid #b4e88d;outline-offset:2px}
}
</style>
</head>
<body class="@yield('body-class')" data-workspace-role="{{auth()->user()->role}}">
@php
 $role = auth()->user()->role;
 $isBuyer = $role === 'buyer';
 $roleLabel = match($role){'buyer'=>'Marketplace','seller'=>'Seller Center','admin'=>'Admin','sorting_center'=>'Sorting Center','rider'=>'Rider App',default=>''};
 $navLinks = ['Overview' => '/dashboard'] + $links;
 if (!array_key_exists('Messages', $navLinks)) $navLinks['Messages'] = '/messages';
 if (!array_key_exists('Account', $navLinks)) $navLinks['Account'] = '/profile';
 $active = function($url){
  $path=trim(parse_url($url,PHP_URL_PATH)??$url,'/');
  if($path==='addresses')return request()->is('addresses','tools/addresses','tools/addresses/*');
  return request()->is($path) || request()->is($path.'/*');
 };
 $icon = function($label){
  $map = [
   'Wallet'=>'<rect x="3" y="5" width="18" height="15" rx="3"/><path d="M3 9h18M16 13h5M16 16h.01"/>',
   'Notifications'=>'<path d="M5 17h14l-2-3V9a5 5 0 0 0-10 0v5l-2 3ZM10 21h4"/>',
   'Wishlist'=>'<path d="M20.8 5.6a5.5 5.5 0 0 0-7.8 0L12 6.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 22l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>',
   'Returns'=>'<path d="m8 3-5 5 5 5M3 8h12a6 6 0 0 1 0 12h-4"/>',
   'Reviews'=>'<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9Z"/>',

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
   'Addresses'=>'<path d="M12 22s7-6.1 7-12a7 7 0 0 0-14 0c0 5.9 7 12 7 12Z"/><circle cx="12" cy="10" r="2.6"/>',
   'Account'=>'<circle cx="12" cy="8" r="3.6"/><path d="M4.5 20c0-4.1 3.4-7 7.5-7s7.5 2.9 7.5 7"/>',
  ];
  $d = $map[$label] ?? '<path d="M21 8 12 3 3 8l9 5 9-5Z"/><path d="M3 8v9l9 5 9-5V8"/><path d="M12 13v9"/>';
  return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'.$d.'</svg>';
 };
 $cartCount = $isBuyer ? \Illuminate\Support\Facades\DB::table('cart_items')->where('buyer_id',auth()->id())->distinct()->count('product_id') : 0;
 $unread = \Illuminate\Support\Facades\DB::table('notifications')->where('user_id',auth()->id())->whereNull('read_at')->count();
 $initial = mb_strtoupper(mb_substr(auth()->user()->name,0,1));
@endphp
@php $sidebarClass = $isBuyer ? 'sidebar' : 'sidebar dark'; @endphp

@if($isBuyer)
<header class="topbar" style="padding:12px 4%">
 <button type="button" class="menu-toggle" data-workspace-menu aria-controls="workspace-sidebar" aria-expanded="false" aria-label="Open navigation">&#9776;</button>
 <a href="/" class="brand" style="display:flex;align-items:center;gap:9px;color:var(--dark);font-weight:800">
  <img src="/logo.jpg" alt="LokaShop" style="width:36px;height:36px;border-radius:9px">LokaShop
 </a>
 <div class="ticons">
  <a href="/notifications" title="Notifications" data-notification-bell aria-label="Notifications, {{$unread}} unread">&#128276;<span class="badge-dot" data-notification-count @if(!$unread) hidden @endif>{{$unread}}</span></a>
  <a href="/cart" title="Cart">&#128722;@if($cartCount)<span class="badge-dot">{{$cartCount}}</span>@endif</a>
  <div class="who"><a class="av" href="/profile" aria-label="Your profile">@if(auth()->user()->profile_photo)<img src="{{\App\Services\Support::imageUrl(auth()->user()->profile_photo)}}" alt="Profile photo" style="width:100%;height:100%;object-fit:cover;border-radius:50%">@else{{$initial}}@endif</a><div><b>{{auth()->user()->name}}</b><small>Buyer</small></div></div>
 </div>
</header>
@endif

<div class="appshell">
 <button type="button" class="workspace-menu-backdrop" aria-label="Close navigation" tabindex="-1" hidden></button>
 <aside class="{{$sidebarClass}}" id="workspace-sidebar" aria-label="Main navigation">
  <button type="button" class="workspace-menu-close" aria-label="Close navigation">&times;</button>
  @unless($isBuyer)
  <a href="/" class="brand">
   <img src="/logo.jpg" alt="LokaShop">
   <span>LokaShop<small>{{$roleLabel}}</small></span>
  </a>
  @endunless
  @include('navigation-groups')
  <hr>
  <form method="post" action="/logout">@csrf<button class="btn alt" style="width:100%">Log out</button></form>
  @if(!$isBuyer)
  <div class="sidebot"><b>Need help?</b>Message the LokaShop team any time from your Messages tab.</div>
  @endif
 </aside>

 <div style="flex:1;min-width:0;display:flex;flex-direction:column">
  @unless($isBuyer)
  <header class="topbar">
   <button type="button" class="menu-toggle" data-workspace-menu aria-controls="workspace-sidebar" aria-expanded="false" aria-label="Open navigation">&#9776;</button>
   <div class="ticons">
    <a href="/notifications" title="Notifications" data-notification-bell aria-label="Notifications, {{$unread}} unread">&#128276;<span class="badge-dot" data-notification-count @if(!$unread) hidden @endif>{{$unread}}</span></a>
    <div class="who"><a class="av" href="/profile" aria-label="Your profile">@if(auth()->user()->profile_photo)<img src="{{\App\Services\Support::imageUrl(auth()->user()->profile_photo)}}" alt="Profile photo" style="width:100%;height:100%;object-fit:cover;border-radius:50%">@else{{$initial}}@endif</a><div><b>{{auth()->user()->name}}</b><small>{{ucfirst(str_replace('_',' ',$role))}}</small></div></div>
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
<script>document.querySelectorAll('form[method="post"]').forEach(f=>{if(!f.querySelector('[name="_request_key"]')){const k=document.createElement('input');k.type='hidden';k.name='_request_key';k.value=crypto.randomUUID();f.appendChild(k);}f.addEventListener('submit',e=>{if(f.dataset.submitted){e.preventDefault();return;}f.dataset.submitted='1';setTimeout(()=>f.querySelectorAll('button[type="submit"],button:not([type])').forEach(b=>b.disabled=true),0);});});</script>@include('shared-interactions')
<script src="{{asset('js/notification-badge.js')}}" defer></script>
<script id="workspace-drawer-controls">(() => {
 const sidebar=document.getElementById('workspace-sidebar');
 const toggles=[...document.querySelectorAll('[data-workspace-menu]')];
 const close=document.querySelector('.workspace-menu-close'),backdrop=document.querySelector('.workspace-menu-backdrop');
 if(!sidebar||!toggles.length||!close||!backdrop)return;
 const compact=window.matchMedia('(max-width:1024px)');let opener=toggles[0];
 const items=()=>[...sidebar.querySelectorAll('a[href],button,summary,input,select,[tabindex="0"]')].filter(el=>!el.disabled&&el.getClientRects().length);
 function setOpen(value,restore=true){const open=compact.matches&&value;document.body.classList.toggle('workspace-menu-open',open);sidebar.classList.remove('open');toggles.forEach(t=>{t.setAttribute('aria-expanded',String(open));t.setAttribute('aria-label',open?'Close navigation':'Open navigation')});backdrop.hidden=!open;sidebar.inert=compact.matches&&!open;if(sidebar.inert)sidebar.setAttribute('aria-hidden','true');else sidebar.removeAttribute('aria-hidden');if(open)close.focus();else if(restore&&compact.matches)opener.focus();}
 toggles.forEach(t=>t.addEventListener('click',()=>{opener=t;setOpen(!document.body.classList.contains('workspace-menu-open'))}));
 close.addEventListener('click',()=>setOpen(false));backdrop.addEventListener('click',()=>setOpen(false));
 sidebar.addEventListener('click',e=>{if(e.target.closest('a[href]'))setOpen(false,false)});
 document.addEventListener('keydown',e=>{if(!compact.matches||!document.body.classList.contains('workspace-menu-open'))return;if(e.key==='Escape'){e.preventDefault();setOpen(false)}if(e.key==='Tab'){const a=items(),first=a[0],last=a[a.length-1];if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus()}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus()}}});
 if(compact.addEventListener)compact.addEventListener('change',()=>setOpen(false,false));else compact.addListener(()=>setOpen(false,false));setOpen(false,false);
})();</script>
</body>
</html>
