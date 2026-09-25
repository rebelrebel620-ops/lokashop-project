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
<header class="m-header">
  <a class="brand" href="/">
    <span class="logo">L</span>
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
      <a class="btn sm" href="/register">Register</a>
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
</body>
</html>
