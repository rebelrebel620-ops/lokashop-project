@php
 $courierOnline=(bool) auth()->user()->online;
 $courierHasJobs=\Illuminate\Support\Facades\DB::table('assignments')->where('rider_id',auth()->id())->where('status','accepted')->exists();
@endphp
<section class="courier-availability" aria-label="Courier availability">
 <div><strong>Work availability</strong><p><span class="availability-indicator {{ $courierOnline ? 'is-online' : '' }}"></span><b>{{ $courierOnline ? 'Active / Online' : 'Not active / Offline' }}</b></p><small>{{ $courierOnline ? 'You can receive and accept new assignments.' : 'Go Active when you are ready to receive assignments.' }}</small></div>
 <div class="availability-controls" role="group" aria-label="Change work availability">
  <form method="post" action="/ops/availability">@csrf<input type="hidden" name="online" value="1"><button class="availability-on" type="submit" aria-pressed="{{ $courierOnline ? 'true' : 'false' }}">{{ $courierOnline ? 'Active ✓' : 'Go Active' }}</button></form>
  <form method="post" action="/ops/availability">@csrf<input type="hidden" name="online" value="0"><button class="availability-off" type="submit" aria-pressed="{{ $courierOnline ? 'false' : 'true' }}" @disabled($courierHasJobs)>{{ $courierOnline ? 'Go Not active' : 'Not active ✓' }}</button></form>
 </div>
 @if($courierHasJobs)<small class="availability-help">Finish your accepted pickups, deliveries or transfers before going Not active.</small>@endif
</section>
