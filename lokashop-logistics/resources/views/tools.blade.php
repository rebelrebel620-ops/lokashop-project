@extends(config('app.site_kind')==='logistics'?'dashboard':'app')
@section('title',$title)
@section(config('app.site_kind')==='logistics'?'panel':'content')
@php
    $workspaceDescriptions = [
        'ops/rates' => 'Manage your municipality shipping rates for new checkouts.',
        'ops/cod' => 'Reconcile courier collections and confirm remittances.',
        'ops/returns' => 'Track parcels returning to sellers and confirm receipt.',
        'ops/tracking' => 'Find parcels, review exceptions, and correct operational statuses.',
        'center/reports' => 'Filter parcel activity and export a PDF report.',
    ];
    $workspaceDescription = $workspaceDescriptions[request()->path()] ?? 'View records and manage the details below.';
@endphp
<div class="page-head"><div><span class="design-eyebrow">YOUR WORKSPACE</span><h1>{{ $title }}</h1><p>{{ $workspaceDescription }}</p></div></div>
@if(isset($report))<section class="design-card"><h2>Filter report</h2><form method="get" class="design-field-grid"><label>Search / tracking<input name="q" value="{{request('q')}}"></label><label>From<input type="date" name="from" value="{{request('from')}}"></label><label>To<input type="date" name="to" value="{{request('to')}}"></label><button>Apply filters</button></form><p><a class="btn" href="{{request()->fullUrlWithQuery(['format'=>'pdf'])}}">Download PDF</a></p></section>@endif
@php $workspaceCards=\App\Services\WorkspaceRecords::cards($records); @endphp
@if(auth()->user()->role==='sorting_center')<section class="design-card ops-summary"><p><b>{{count($workspaceCards)}}</b> records in this view</p>@if(request()->is('ops/tracking'))<form method="get"><input name="q" value="{{request('q')}}" aria-label="Search tracking, order or buyer" placeholder="Tracking ID, order or buyer"><button>Find parcel</button><a href="/ops/tracking">Clear</a></form>@endif</section>@endif
<div class="workspace-records">
@forelse($workspaceCards as $card)
<article class="design-card workspace-record"><header class="workspace-record-header"><div><h2>{{$card['title']}}</h2><small>@if($card['id'])Reference #{{$card['id']}}@endif @if($card['date']) · {{\Illuminate\Support\Carbon::parse($card['date'])->format('M j, Y')}}@endif</small></div>@if($card['status'])<span class="design-pill">{{ucfirst(strtolower(str_replace('_',' ',$card['status'])))}}</span>@endif</header><dl class="workspace-facts">@foreach($card['fields'] as $label=>$value)<div><dt>{{$label}}</dt><dd>{{$value}}</dd></div>@endforeach</dl>
@if(request()->is('ops/rates'))@php $rateData=\Illuminate\Support\Arr::only($card['source'],['id','origin_province','city','province','base_fee','extra_kg_fee','active']); @endphp<button type="button" class="rate-edit" data-rate='@json($rateData)'>Edit rate</button>@endif
@if(isset($card['source']['photos'],$card['source']['order_id']))<div class="workspace-evidence">@foreach(json_decode($card['source']['photos']??'[]',true)??[] as $i=>$path)<a href="/returns/{{$card['id']}}/photos/{{$i}}" target="_blank" rel="noopener">View evidence {{$i+1}}</a>@endforeach</div>@endif
</article>
@empty<section class="design-card design-empty"><div class="empty-orbit">✓</div><h2>Nothing here yet</h2><p>Your records will appear here when there is activity.</p></section>@endforelse
</div>
@if(count($forms))<div class="workspace-action-grid">@foreach($forms as $form)<section class="design-card workspace-action"><div class="design-card-heading"><div><h2>{{$form['label']}}</h2><p>Complete the details below to continue.</p></div></div>@include('tool-form',['form'=>$form])</section>@endforeach</div>@endif
@if(request()->is('ops/rates'))<p><a class="btn" href="/ops/rates">Start a new rate</a></p><script>
document.querySelectorAll('.rate-edit').forEach(button=>button.addEventListener('click',()=>{const rate=JSON.parse(button.dataset.rate),form=document.querySelector('form[action="/ops/rate"]');if(!form)return;let id=form.querySelector('[name="id"]');if(!id){id=document.createElement('input');id.type='hidden';id.name='id';form.append(id);}for(const [key,value] of Object.entries(rate)){const input=form.querySelector('[name="'+key+'"]'+(key==='active'?'[type="checkbox"]':''));if(input){if(input.type==='checkbox')input.checked=Boolean(Number(value));else input.value=value??'';}}form.querySelector('[name="reason"]').value='';form.scrollIntoView({behavior:'smooth',block:'center'});form.querySelector('[name="reason"]').focus();}));
</script>@endif
@endsection
