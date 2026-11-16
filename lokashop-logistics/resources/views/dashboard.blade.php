<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>@yield('title','LokaShop Logistics')</title>
<link rel="icon" type="image/png" href="{{ asset('images/logistics-logo.png') }}">
@vite('resources/css/app.css')
<link rel="stylesheet" href="{{ asset('css/logistics-operations.css') }}">
<link rel="stylesheet" href="{{ asset('css/workspace-design.css') }}">
<script src="{{ asset('js/workspace-design.js') }}" defer></script>
<link rel="stylesheet" href="{{asset('css/avatar-courier-fixes.css')}}">
<link rel="stylesheet" href="{{asset('css/admin-user-details.css')}}">
<link rel="stylesheet" href="{{asset('css/center-refresh.css')}}">
<link rel="stylesheet" href="{{ asset('css/navigation-polish.css').'?v=3' }}">
<link rel="stylesheet" href="{{asset('css/logistics-role-backgrounds.css')}}">
<link rel="stylesheet" href="{{asset('css/phone-density.css')}}">
<style id="logistics-responsive-drawer">
/* Phone and tablet navigation only; larger desktop layouts keep their existing shell. */
.phone-sidebar-toggle,.phone-sidebar-close,.phone-sidebar-backdrop{display:none!important}
@media screen and (max-width:1024px){
  body.logi .app-shell{flex-direction:column}
  body.logi .app-shell>.sidebar,body.logi.operations-redesign .app-shell>.sidebar{
    position:fixed;inset:0 auto 0 0;z-index:1001;width:min(300px,86vw);height:100vh;height:100dvh;
    max-height:none;box-sizing:border-box;display:flex;flex-direction:column;overflow-x:hidden;overflow-y:auto;
    transform:translateX(-100%);visibility:hidden;box-shadow:8px 0 30px #0003;
  }
  body.logi.phone-sidebar-open .app-shell>.sidebar{transform:translateX(0);visibility:visible}
  body.logi.phone-sidebar-open{overflow:hidden}
  body.logi .sidebar .brand{padding:20px 48px 18px 16px;min-width:0}
  body.logi .sidebar nav.grouped-navigation{flex:1;flex-direction:column;align-items:stretch;padding:6px 12px 24px;overflow:visible}
  body.logi .phone-sidebar-toggle{display:inline-flex!important;align-items:center;justify-content:center;flex:0 0 44px;width:44px;height:44px;padding:0;border:1px solid #cddfd3;border-radius:9px;background:#f3f8f4;color:#123f30;font-size:23px;cursor:pointer}
  body.logi .phone-sidebar-close{display:flex!important;position:absolute;top:16px;right:8px;width:40px;height:40px;padding:0;align-items:center;justify-content:center;border:0;border-radius:8px;background:#ffffff15;color:#fff;font-size:28px;cursor:pointer}
  body.logi .phone-sidebar-backdrop:not([hidden]){display:block!important;position:fixed;inset:0;z-index:1000;width:100%;height:100%;margin:0;padding:0;border:0;border-radius:0;background:#061f18a3;cursor:pointer}
  body.logi .topbar>.spacer{display:none}
  body.logi .topbar>.search-box{min-width:0;flex:1;padding:9px 8px}
  body.logi .topbar>.search-box input{min-width:0;width:100%}
  body.logi .topbar>.icon-btn{flex-shrink:0}
  body.logi .phone-sidebar-toggle:focus-visible,body.logi .phone-sidebar-close:focus-visible{outline:2px solid #63c743;outline-offset:2px}
}
</style>
</head>
<body class="logi {{in_array(auth()->user()->role,['sorting_center','rider'])?'operations-redesign':''}}" data-logistics-theme="{{auth()->user()->role==='rider'?(auth()->user()->courier_type==='driver'?'driver':'rider'):(auth()->user()->role==='sorting_center'?'center':'admin')}}">
@php
  $roleLabel = auth()->user()->role==='rider'?'Logistics '.ucfirst(auth()->user()->courier_type):ucfirst(str_replace('_',' ',auth()->user()->role));
  $navLinks = ['Overview' => '/dashboard'] + $links;
  if (!array_key_exists('Messages', $navLinks)) $navLinks['Messages'] = '/messages';
  if (!array_key_exists('Account', $navLinks)) $navLinks['Account'] = '/profile';
  $active = function($url) { $path = trim(parse_url($url, PHP_URL_PATH) ?? $url, '/'); return request()->is($path) || request()->is($path.'/*'); };
  $initials = collect(explode(' ', auth()->user()->name))->map(fn($p)=>mb_substr($p,0,1))->join('');
  $iconFor = function(string $label) {
    $l = strtolower($label);
    return match(true) {
      str_contains($l,'application') => 'clipboard',
      str_contains($l,'pickup')      => 'box',
      str_contains($l,'parcel')      => 'scan',
      str_contains($l,'area')        => 'map-pin',
      str_contains($l,'report')      => 'chart',
      str_contains($l,'assignment')  => 'truck',
      str_contains($l,'earning')     => 'wallet',
      default                        => 'home',
    };
  };
@endphp
<div class="app-shell">
  <button type="button" class="phone-sidebar-backdrop" aria-label="Close navigation" tabindex="-1" hidden></button>
  <aside class="sidebar" id="logistics-sidebar" aria-label="Main navigation">
    <button type="button" class="phone-sidebar-close" aria-label="Close navigation">&times;</button>
    <a class="brand" href="/dashboard">
      <img src="{{ asset('images/logistics-logo.png') }}" alt="LokaShop Logistics logo" width="44" height="44" style="width:44px;height:44px;object-fit:contain;border-radius:10px;flex-shrink:0;display:block">
      <span>LokaShop<br><small>{{ $roleLabel }} portal</small></span>
    </a>
    @include('navigation-groups')
    <div class="sidebar-foot">&copy; {{ date('Y') }} LokaShop Logistics</div>
  </aside>

  <div class="main">
    <div class="topbar">
      <button type="button" class="phone-sidebar-toggle" aria-controls="logistics-sidebar" aria-expanded="false" aria-label="Open navigation">&#9776;</button>
      <form class="search-box" action="/track" method="get">
        <x-icon name="search" size="18"/>
        <input name="code" placeholder="Search tracking ID (e.g. LKS-1024)">
      </form>
      <div class="spacer"></div>
      <a class="icon-btn" href="/messages"><x-icon name="chat" size="18"/></a>
      @php $notificationUnread=\Illuminate\Support\Facades\DB::table('notifications')->where('user_id',auth()->id())->whereNull('read_at')->count(); @endphp
      <a class="icon-btn" href="/notifications" title="Notifications" data-notification-bell aria-label="Notifications, {{$notificationUnread}} unread"><x-icon name="bell" size="18"/><span data-notification-count @if(!$notificationUnread) hidden @endif style="position:absolute;right:-5px;top:-5px;background:#bb3535;color:#fff;padding:2px 5px;border-radius:12px;font-size:10px;line-height:14px">{{$notificationUnread>99?'99+':$notificationUnread}}</span></a>
      <details class="user-menu">
        <summary>
          <span class="av">@if(auth()->user()->profile_photo)<img src="{{\App\Services\Support::imageUrl(auth()->user()->profile_photo)}}" alt="Your profile photo">@else{{ $initials }}@endif</span>
          <span class="who"><b>{{ auth()->user()->name }}</b><small>{{ $roleLabel }}</small></span>
        </summary>
        <div class="menu">
          <a href="/profile"><x-icon name="gear" size="16"/> &nbsp;Account</a>
          <form method="post" action="/logout"><input type="hidden" name="_token" value="{{ csrf_token() }}">
            <button style="background:transparent;color:inherit;width:100%"><x-icon name="logout" size="16"/> &nbsp;Log out</button>
          </form>
        </div>
      </details>
    </div>

    <div class="content">
      @if(session('success'))<p class="notice">{{ session('success') }}</p>@endif
      @if($errors->any())
        <div class="error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
      @endif

      @hasSection('panel')
        @yield('panel')
      @else
        <div class="page-head">
          <div>
            <h1>Welcome, {{ auth()->user()->name }}</h1>
            <p>Here's a quick jump-off point for your {{ strtolower($roleLabel) }} tasks.</p>
          </div>
        </div>
        <div class="stat-grid">
          @foreach($links as $label => $url)
            <a href="{{ $url }}" class="stat-card" style="text-decoration:none">
              <span class="ico c-green"><x-icon :name="$iconFor($label)"/></span>
              <div><div class="num" style="font-size:1.05rem">{{ $label }}</div><div class="lbl">Open {{ strtolower($label) }}</div></div>
            </a>
          @endforeach
        </div>
      @endif
    </div>
  </div>
</div>
<script>document.querySelectorAll('form[method="post"]').forEach(f=>{if(!f.querySelector('[name="_request_key"]')){const k=document.createElement('input');k.type='hidden';k.name='_request_key';k.value=crypto.randomUUID();f.appendChild(k);}f.addEventListener('submit',e=>{if(f.dataset.submitted){e.preventDefault();return;}f.dataset.submitted='1';setTimeout(()=>f.querySelectorAll('button[type="submit"],button:not([type])').forEach(b=>b.disabled=true),0);});});</script>@include('shared-interactions')
<script src="{{asset('js/notification-badge.js')}}" defer></script>
<script>
(() => {
  const sidebar = document.getElementById('logistics-sidebar');
  const toggle = document.querySelector('.phone-sidebar-toggle');
  const close = document.querySelector('.phone-sidebar-close');
  const backdrop = document.querySelector('.phone-sidebar-backdrop');
  if (!sidebar || !toggle || !close || !backdrop) return;
  const phone = window.matchMedia('(max-width:1024px)');
  const focusable = () => [...sidebar.querySelectorAll('a[href],button,summary,input,select,textarea,[tabindex="0"]')]
    .filter(el => !el.disabled && el.getClientRects().length);
  function setOpen(open, restoreFocus = true) {
    open = phone.matches && open;
    document.body.classList.toggle('phone-sidebar-open', open);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
    backdrop.hidden = !open;
    sidebar.inert = phone.matches && !open;
    if (phone.matches && !open) sidebar.setAttribute('aria-hidden', 'true');
    else sidebar.removeAttribute('aria-hidden');
    if (open) close.focus();
    else if (restoreFocus && phone.matches) toggle.focus();
  }
  toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
  close.addEventListener('click', () => setOpen(false));
  backdrop.addEventListener('click', () => setOpen(false));
  sidebar.addEventListener('click', event => {
    if (event.target.closest('a[href]') && phone.matches) setOpen(false, false);
  });
  document.addEventListener('keydown', event => {
    if (!phone.matches || toggle.getAttribute('aria-expanded') !== 'true') return;
    if (event.key === 'Escape') { event.preventDefault(); setOpen(false); }
    if (event.key === 'Tab') {
      const items = focusable(), first = items[0], last = items[items.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
  });
  if (phone.addEventListener) phone.addEventListener('change', () => setOpen(false, false));
  else phone.addListener(() => setOpen(false, false));
  setOpen(false, false);
})();
</script>
</body>
</html>
