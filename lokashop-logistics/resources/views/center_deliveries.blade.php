@extends('dashboard')
@section('title','Delivery')
@section('panel')
<div class="page-head"><div><h1>Delivery</h1><p>Areas match the buyer province, municipality and barangay automatically. Assign an active rider covering that area.</p></div></div>
@forelse($parcels as $p)
<div class="panel-card" style="padding:24px" id="delivery-{{$p->id}}">
 <h2>{{$p->tracking_code}} · Order #{{$p->order_id}}</h2><x-badge :status="$p->status" />
 @php $a=$addresses[$p->id];$offer=$offers[$p->id]; @endphp
 <p>Destination: {{$a->barangay ?? ''}}, {{$a->city ?? ''}}, {{$a->province ?? ''}}</p>
 <p><strong>Matching areas:</strong> {{$matches[$p->id]->pluck('name')->implode(', ') ?: 'No matching area. Create one or transfer to the destination municipality.'}}</p>
 @if($p->status==='OUT_FOR_DELIVERY')<p>Next: rider completes delivery with a proof photo and COD confirmation. The buyer then confirms Order received.</p>@endif
 @if(in_array($p->status,['AT_SORTING_CENTER','SORTED','DELIVERY_FAILED','ASSIGNED_TO_RIDER']))
  @if($offer)<p>Next: {{$offer->status==='accepted'?'rider starts delivery':'rider accepts the job'}}. Rider #{{$offer->rider_id}}.</p>
  @else
   <form method="post" action="/center/delivery/{{$p->id}}/assign">@csrf
    <label>Active area rider<select name="rider_id" required><option value="">Choose rider</option>@foreach($riders[$p->id] as $u)<option value="{{$u->id}}">{{$u->name}}</option>@endforeach</select></label>
    @if($riders[$p->id]->isEmpty())<p>No active rider with capacity covers this destination. Check Areas and rider availability.</p>@endif
    <button @disabled($riders[$p->id]->isEmpty())>Assign delivery rider</button>
   </form>
  @endif
 @endif
</div>
@empty<div class="panel-card empty">No parcels ready for delivery.</div>@endforelse
{{$parcels->links('components.pagination')}}
@endsection
