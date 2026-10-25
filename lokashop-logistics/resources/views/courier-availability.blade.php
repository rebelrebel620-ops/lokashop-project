@php
 $courierOnline=(bool) auth()->user()->online;
 $courierHasJobs=\Illuminate\Support\Facades\DB::table('assignments')->where('rider_id',auth()->id())->where('status','accepted')->exists();
@endphp
<section class="courier-availability" aria-label="Courier availability">
 <div><strong>Work availability</strong><p><span class="availability-indicator {{ $courierOnline ? 'is-online' : '' }}"></span><b>{{ $courierOnline ? 'Online — ready for jobs' : 'Offline — not receiving jobs' }}</b></p><small>{{ $courierOnline ? 'You can receive and accept new assignments.' : 'Go Online when you are ready to receive jobs.' }}</small></div>
 <div class="availability-controls" role="group" aria-label="Change work availability">
  <form method="post" action="/ops/availability">@csrf<input type="hidden" name="online" value="1"><button class="availability-on" type="submit" aria-pressed="{{ $courierOnline ? 'true' : 'false' }}">{{ $courierOnline ? 'Online ✓' : 'Go Online' }}</button></form>
  <form method="post" action="/ops/availability">@csrf<input type="hidden" name="online" value="0"><button class="availability-off" type="submit" aria-pressed="{{ $courierOnline ? 'false' : 'true' }}" @disabled($courierHasJobs)>{{ $courierOnline ? 'Go Offline' : 'Offline ✓' }}</button></form>
 </div>
 @if($courierHasJobs)<small class="availability-help">Finish your accepted pickups, deliveries or transfers before going Offline.</small>@endif
</section>
