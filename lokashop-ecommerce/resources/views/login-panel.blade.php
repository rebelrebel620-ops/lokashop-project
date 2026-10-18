<section class="login-shell" aria-labelledby="login-title">
 <aside class="login-story">
  <div class="login-story-copy"><span class="login-eyebrow">YOUR LOCAL MARKETPLACE</span><h2>Shop local.<br>Live better.</h2><p>Everyday finds from local sellers,<br>delivered with care.</p></div>
  <div class="login-story-note"><span aria-hidden="true">✓</span> Local shops. Everyday possibilities.</div>
 </aside>
 <div class="login-panel">
  <img class="login-logo" src="{{ asset('logo.jpg') }}" width="54" height="54" alt="LokaShop">
  <span class="login-eyebrow">GOOD TO SEE YOU AGAIN</span>
  <h1 id="login-title">Welcome back</h1>
  <p class="login-subtitle">Log in to continue your LokaShop journey.</p>
  <form id="account-form" action="/login" method="post">@csrf
   <label for="login-email">Email address</label>
   <input id="login-email" type="email" name="email" required maxlength="190" value="{{ old('email') }}" autocomplete="email" placeholder="you@example.com" @error('email') aria-invalid="true" aria-describedby="login-email-error" @enderror>
   @error('email')<small class="login-field-error" id="login-email-error">{{ $message }}</small>@enderror
   <label for="login-password">Password</label>
   <div class="login-password-field"><input id="login-password" type="password" name="password" required autocomplete="current-password" placeholder="Enter your password" @error('password') aria-invalid="true" aria-describedby="login-password-error" @enderror><button id="login-password-toggle" type="button" aria-controls="login-password" aria-label="Show password" aria-pressed="false">Show</button></div>
   @error('password')<small class="login-field-error" id="login-password-error">{{ $message }}</small>@enderror
   <button id="submit-account" class="login-submit" type="submit">Log in <span aria-hidden="true">→</span></button>
  </form>
  <p class="login-signup">New to LokaShop? <a href="/register">Create an account</a></p>
  <div class="login-local-note">Made for local finds. Made for you.</div>
 </div>
</section>
<script>
(()=>{
 const toggle=document.getElementById('login-password-toggle'),password=document.getElementById('login-password');
 toggle.addEventListener('click',()=>{const visible=password.type==='password';password.type=visible?'text':'password';toggle.textContent=visible?'Hide':'Show';toggle.setAttribute('aria-pressed',String(visible));toggle.setAttribute('aria-label',visible?'Hide password':'Show password');});
 document.getElementById('account-form').addEventListener('submit',()=>{const button=document.getElementById('submit-account');button.disabled=true;button.textContent='Logging in…';});
})();
</script>
