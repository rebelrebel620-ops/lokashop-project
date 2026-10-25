@extends('dashboard')
@section('title','Driver trips')
@section('panel')
<div class="page-head"><div><h1>Driver trips</h1><p>Accept one trip for all parcels. Start transport once. Each destination center confirms only its own parcels.</p></div></div>
<details class="design-card setup-details"><summary>Older individual transfers</summary><p>Use this only for individual transfers assigned before bulk trips.</p><a class="btn alt" href="/rider/assignments">Open individual assignments</a></details>
<nav class="courier-buttons">@foreach(['new'=>'New trips','progress'=>'In progress','completed'=>'Completed'] as $key=>$label)<a class="btn {{$tab===$key?'':'alt'}}" href="/driver/trips?tab={{$key}}">{{$label}}</a>@endforeach</nav>
@forelse($trips as $trip)
<div class="panel-card" style="padding:24px"><h2>Trip #{{$trip['id']}} · {{ucfirst(str_replace('_',' ',$trip['status']))}}</h2><p>{{count($trip['stops'])}} destination centers · {{collect($trip['stops'])->sum(fn($s)=>$s['parcels']->count())}} parcels</p>
@if($trip['status']==='offered')<p>Accept before {{$trip['expires_at']}} (3-hour trip offer).</p><form method="post" action="/driver/trips/{{$trip['id']}}/accept">@csrf<button>Accept whole trip</button></form><form method="post" action="/driver/trips/{{$trip['id']}}/decline">@csrf<button class="danger">Decline whole trip</button></form>@endif
@if($trip['status']==='accepted')<form method="post" action="/driver/trips/{{$trip['id']}}/depart">@csrf<button>Start transport — all parcels loaded</button></form>@endif
@foreach($trip['stops'] as $stop)
<section style="margin-top:20px"><h3>Stop {{$stop['position']}}: {{$stop['center_name']}}</h3><p>{{$stop['city']}}, {{$stop['province']}} · {{$stop['received_at']?'Received by center':'Awaiting center receipt'}}</p>@if($stop['address'])<p>{{$stop['address']->street??''}}, {{$stop['address']->barangay??''}} · Contact: {{$stop['address']->phone??'Ask your origin center'}}</p>@endif<ul>@foreach($stop['parcels'] as $item)<li>{{$item->tracking_code}} · Order #{{$item->order_id}} · {{$item->received_at?'Received':'On this trip'}}</li>@endforeach</ul>
@if($stop['received_at'])<p class="handover-state done">Center receipt confirmed · {{$stop['received_at']}} @if($stop['received_by'])· {{$stop['received_by_name']??('Account #'.$stop['received_by'])}}@endif</p>
@elseif($trip['status']==='in_transit' && !$stop['handed_over_at'])
<form class="handover-form" method="post" action="/driver/trip-stops/{{$stop['id']}}/handover">@csrf<label class="acknowledge"><input type="checkbox" name="confirm_handover" value="1" required> I have arrived at {{$stop['center_name']}} and handed over this stop's parcels.</label><button>Confirm handover at this center</button></form>
@elseif($stop['handed_over_at'])<p class="handover-state waiting">You confirmed handover at {{$stop['handed_over_at']}}. Waiting for this center to confirm receipt.</p>
@else<p class="handover-state">Accept and start this trip before confirming handover.</p>@endif
</section>
@endforeach
@if($trip['status']==='in_transit')<p>Hand over the listed parcels at each destination. Confirm handover at each stop, then wait for center staff to confirm receipt. The trip completes after every center has acknowledged all its parcels. Stops may be visited in any order.</p>@endif
@if($trip['status']==='completed')<p>Trip complete: every destination center has confirmed all its parcels.</p>@endif
</div>@empty<div class="panel-card" style="padding:24px">No trips in this tab. Go Online to receive new offers.</div>@endforelse
{{$page->links('components.pagination')}}
<script>let handoverDirty=false;document.addEventListener('change',()=>handoverDirty=true);setInterval(()=>{if(!document.hidden&&!handoverDirty)location.reload();},20000);</script>
@endsection
