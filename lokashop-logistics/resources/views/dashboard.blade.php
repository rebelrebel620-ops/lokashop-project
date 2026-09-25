<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>@yield('title','LokaShop Logistics')</title>
<link rel="icon" href="/favicon.jpg">
@vite('resources/css/app.css')
</head>
<body class="logi">
@php
  $roleLabel = ucfirst(str_replace('_',' ',auth()->user()->role));
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
  <aside class="sidebar">
    <a class="brand" href="/dashboard">
      <span class="logo">L</span>
      <span>LokaShop<br><small>{{ $roleLabel }} portal</small></span>
    </a>
    <nav>
      <a href="/dashboard" class="{{ request()->is('dashboard') ? 'active' : '' }}"><x-icon name="home"/> Overview</a>
      @foreach($links as $label => $url)
        <a href="{{ $url }}" class="{{ request()->is(trim($url,'/').'*') && $url !== '/' ? 'active' : '' }}">
          <x-icon :name="$iconFor($label)"/> {{ $label }}
        </a>
      @endforeach
      <a href="/messages" class="{{ request()->is('messages') ? 'active' : '' }}"><x-icon name="chat"/> Messages</a>
      <a href="/profile" class="{{ request()->is('profile') ? 'active' : '' }}"><x-icon name="gear"/> Account</a>
    </nav>
    <div class="sidebar-foot">&copy; {{ date('Y') }} LokaShop Logistics</div>
  </aside>

  <div class="main">
    <div class="topbar">
      <form class="search-box" action="/track" method="get">
        <x-icon name="search" size="18"/>
        <input name="code" placeholder="Search tracking ID (e.g. LKS-1024)">
      </form>
      <div class="spacer"></div>
      <a class="icon-btn" href="/messages"><x-icon name="chat" size="18"/></a>
      <a class="icon-btn" href="/profile"><x-icon name="bell" size="18"/><span class="dot"></span></a>
      <details class="user-menu">
        <summary>
          <span class="av">{{ $initials }}</span>
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
</body>
</html>
