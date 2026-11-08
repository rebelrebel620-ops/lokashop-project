<dialog id="logout-confirm" aria-labelledby="logout-title" style="border:0;border-radius:14px;padding:28px;max-width:360px"><h2 id="logout-title">Do you want to log out?</h2><div style="display:flex;gap:12px"><button type="button" id="logout-yes" class="btn">Yes, log out</button><button type="button" id="logout-no" class="btn alt">No, go back</button></div></dialog>
<script src="/js/usability.js" defer></script>
@if(!request()->is('login','register','company/register*','company/verify*','verify-email*','password*','activate*','reset*'))
@php $liveState=\App\Services\LiveUpdates::snapshot(); @endphp
<script id="lokashop-live-script" src="{{asset('js/live-updates.js')}}" data-revision="{{$liveState['revision']}}" data-endpoint="{{url('/live/state')}}" data-authenticated="{{$liveState['authenticated']?'1':'0'}}" defer></script>
@endif
