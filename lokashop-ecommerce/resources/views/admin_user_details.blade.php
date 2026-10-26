@extends(config('app.site_kind')==='logistics'?'dashboard':'app')
@section('title','User details - LokaShop')
@section(config('app.site_kind')==='logistics'?'panel':'content')
<div class="admin-detail-page">
 <a class="admin-back" href="{{$backUrl}}">← Back to users</a>
 <header class="admin-profile-header">
  <div class="admin-profile-avatar"><span>{{mb_substr($details['name'],0,1)}}</span>@if($details['photo'])<img src="{{$details['photo']}}" alt="Profile photo of {{$details['name']}}" width="88" height="88" onerror="this.remove()">@endif</div>
  <div class="admin-profile-heading"><span class="admin-eyebrow">ACCOUNT OVERVIEW</span><h1>{{$details['name']}}</h1><p>{{$details['role']}} · Account #{{$details['id']}}</p></div>
  <span class="admin-readonly">Read-only details</span>
 </header>
 <div class="admin-profile-stats">@foreach($details['stats'] as $label=>$value)<article><strong>{{number_format($value)}}</strong><span>{{$label}}</span></article>@endforeach</div>
 <div class="admin-profile-grid">
  <section class="admin-profile-card"><h2>Basic information</h2><dl class="admin-profile-facts">@foreach($details['facts'] as $label=>$value)<div><dt>{{$label}}</dt><dd>{{filled($value)?$value:'Not provided'}}</dd></div>@endforeach</dl></section>
  <div class="admin-profile-stack">
   @foreach($details['sections'] as $section)<section class="admin-profile-card"><h2>{{$section['title']}}</h2><dl class="admin-profile-facts">@foreach($section['facts'] as $label=>$value)<div><dt>{{$label}}</dt><dd>{{filled($value)?$value:'Not provided'}}</dd></div>@endforeach</dl></section>@endforeach
   <section class="admin-profile-card"><h2>Primary address</h2><p class="admin-address">{{$details['address']??'No address recorded.'}}</p></section>
  </div>
 </div>
 @if($details['showAreas'])
 <section class="admin-profile-card"><div class="admin-section-heading"><h2>{{$details['role']==='Rider'?'Assigned pickup and delivery areas':'Sorting center service areas'}}</h2><span>{{$details['areaCount']}} total</span></div>
 @if($details['areas']->isEmpty())<p class="admin-empty">No areas assigned yet.</p>@else
 <div class="admin-detail-table"><table><thead><tr><th>Area</th><th>Barangay</th><th>Municipality / city</th><th>Province</th>@if($details['role']!=='Rider')<th>Assigned rider</th>@endif</tr></thead><tbody>@foreach($details['areas'] as $area)<tr><td>{{$area->name}}</td><td>{{$area->barangay??'Not specified'}}</td><td>{{$area->city}}</td><td>{{$area->province}}</td>@if($details['role']!=='Rider')<td>{{$area->rider_name??'Not assigned'}}</td>@endif</tr>@endforeach</tbody></table></div>
 @if($details['areaCount']>12)<p class="admin-empty">Showing the first 12 areas by name.</p>@endif
 @endif</section>
 @endif
 @if($details['showTrips'])
 <section class="admin-profile-card"><h2>Recent transport trips</h2><p class="admin-empty">Latest 8 trips between sorting centers.</p>
 @if($details['trips']->isEmpty())<p class="admin-empty">No bulk transport trips recorded yet.</p>@else
 <div class="admin-detail-table"><table><thead><tr><th>Trip</th><th>Origin center</th><th>Destination centers</th><th>Status</th><th>Parcels</th><th>Departed</th><th>Completed</th></tr></thead><tbody>@foreach($details['trips'] as $trip)<tr><td>#{{$trip->id}}</td><td>{{$trip->origin_name??'Not recorded'}}</td><td>{{$trip->destinations?:'Not recorded'}}</td><td>{{ucwords(str_replace('_',' ',$trip->status))}}</td><td>{{$trip->parcel_count}}</td><td>{{$trip->departed_at??'Not departed'}}</td><td>{{$trip->completed_at??'Not completed'}}</td></tr>@endforeach</tbody></table></div>
 @endif</section>
 @endif
</div>
@endsection
