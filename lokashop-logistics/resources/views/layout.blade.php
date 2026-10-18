<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>@yield('title','LokaShop Logistics')</title>
<link rel="icon" type="image/png" href="{{ asset('images/logistics-logo.png') }}">
@vite('resources/css/app.css')
@yield('page-styles')
<link rel="stylesheet" href="{{ asset('css/logistics-public.css') }}">
<link rel="stylesheet" href="{{ asset('css/workspace-design.css') }}">
<script src="{{ asset('js/workspace-design.js') }}" defer></script>
</head>
<body class="logi logistics-public @yield('body-class')">
<header class="m-header">
  <a class="brand" href="/">
    <img src="{{ asset('images/logistics-logo.png') }}" alt="LokaShop Logistics logo" width="44" height="44" style="width:44px;height:44px;object-fit:contain;border-radius:10px;flex-shrink:0;display:block">
    LokaShop <small>Logistics</small>
  </a>
  <nav class="m-nav">
    <a href="/">Home</a>
    <a href="/track">Track parcel</a>
    @auth
      <a href="/dashboard">Dashboard</a>
      <a href="/messages">Messages</a>
      <a href="/profile">Account</a>
      <form method="post" action="/logout"><input type="hidden" name="_token" value="{{csrf_token()}}"><button class="alt sm">Log out</button></form>
    @else
      <a href="/login">Log in</a>
      <a class="btn sm" href="/register">Sign up</a>
    @endauth
  </nav>
</header>

@yield('hero')

<div class="wrap">
  @if(session('success'))<p class="notice">{{ session('success') }}</p>@endif
  @if($errors->any())
    <div class="error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
  @endif
  @yield('content')
</div>

<footer class="m-footer">
  <div class="foot">
    <div>
      <h4>LokaShop Logistics</h4>
      <small style="margin:0">Pickup, sorting and last-mile delivery for local sellers.</small>
    </div>
    <div>
      <h4>Quick links</h4>
      <a href="/track">Track a parcel</a>
      <a href="/register">Join as courier or center</a>
      <a href="/login">Partner log in</a>
    </div>
  </div>
  <small class="bottom">&copy; {{ date('Y') }} LokaShop. All rights reserved.</small>
</footer>
<script>document.querySelectorAll('form[method="post"]').forEach(f=>{if(!f.querySelector('[name="_request_key"]')){const k=document.createElement('input');k.type='hidden';k.name='_request_key';k.value=crypto.randomUUID();f.appendChild(k);}f.addEventListener('submit',e=>{if(f.dataset.submitted){e.preventDefault();return;}f.dataset.submitted='1';setTimeout(()=>f.querySelectorAll('button[type="submit"],button:not([type])').forEach(b=>b.disabled=true),0);});});</script>@include('shared-interactions')
</body>
</html>
