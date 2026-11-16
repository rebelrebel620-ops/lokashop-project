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
@if(request()->is('ops/cod')&&auth()->user()->role==='rider')
@php
 $codBalance=\Illuminate\Support\Facades\DB::table('cod_collections')->where('rider_id',auth()->id())->where('company_id',auth()->user()->company_id)->whereNull('remittance_id')->sum('amount');
 $codShort=\Illuminate\Support\Facades\DB::table('remittances')->where('rider_id',auth()->id())->where('company_id',auth()->user()->company_id)->where('status','short')->get()->sum(fn($x)=>max(0,$x->expected-$x->amount));
@endphp
<section class="design-card"><h2>Remit all outstanding COD</h2><p>Unsubmitted collections: ₱{{number_format($codBalance,2)}} · Existing shortages: ₱{{number_format($codShort,2)}}</p><p>Submit <strong>₱{{number_format($codBalance+$codShort,2)}}</strong> in one go. A reference is generated automatically. Pending submissions still need sorting-center confirmation; only hand over the amount shown here.</p>
<form method="post" action="/ops/remit" data-safe-submit>@csrf<input type="hidden" name="_request_key" value="{{(string)\Illuminate\Support\Str::uuid()}}"><input type="hidden" name="remit_all" value="1"><button class="btn" @disabled($codBalance+$codShort<=0)>Remit all · ₱{{number_format($codBalance+$codShort,2)}}</button></form>
<p><small>Reference is optional on the forms below. Enter a receipt or transfer reference if available; otherwise the system generates one.</small></p></section>
@endif
<div class="workspace-records">
@forelse($workspaceCards as $card)
<article class="design-card workspace-record"><header class="workspace-record-header"><div><h2>{{$card['title']}}</h2><small>@if($card['id'])Reference #{{$card['id']}}@endif @if($card['date']) · {{\Illuminate\Support\Carbon::parse($card['date'])->format('M j, Y')}}@endif</small></div>@if($card['status'])<span class="design-pill">{{ucfirst(strtolower(str_replace('_',' ',$card['status'])))}}</span>@endif</header><dl class="workspace-facts">@foreach($card['fields'] as $label=>$value)<div><dt>{{$label}}</dt><dd>{{$value}}</dd></div>@endforeach</dl>
@if(request()->is('ops/rates'))@php $rateData=\Illuminate\Support\Arr::only($card['source'],['id','origin_province','city','province','base_fee','extra_kg_fee','active']); @endphp<button type="button" class="rate-edit" data-rate='@json($rateData)'>Edit rate</button>@endif
@if(isset($card['source']['photos'],$card['source']['order_id']))<div class="workspace-evidence">@foreach(json_decode($card['source']['photos']??'[]',true)??[] as $i=>$path)<a href="/returns/{{$card['id']}}/photos/{{$i}}" target="_blank" rel="noopener">View evidence {{$i+1}}</a>@endforeach</div>@endif
</article>
@empty<section class="design-card design-empty"><div class="empty-orbit">✓</div><h2>Nothing here yet</h2><p>Your records will appear here when there is activity.</p></section>@endforelse
</div>
@if(count($forms))<div class="workspace-action-grid">@foreach($forms as $form)
@if($form['action']==='/ops/remittance/confirm')
<section class="design-card"><h2>Confirm remittance</h2><p>Confirm only after counting and receiving the rider's cash.</p><button class="btn" type="button" data-open-remittance-dialog>Confirm remittance</button></section>
<dialog id="remittance-confirm-dialog" class="remittance-dialog" aria-labelledby="remittance-confirm-title" @if($errors->any()&&old('_remittance_popup')) data-reopen-remittance-dialog @endif>
<header><h2 id="remittance-confirm-title">Confirm remittance</h2><button type="button" class="btn alt" data-close-remittance-dialog aria-label="Close">×</button></header><p>Confirm that you have received and counted the full amount. Short remittances must be topped up first.</p>
@if($errors->any()&&old('_remittance_popup'))<div role="alert">@foreach($errors->all() as $error)<p>{{$error}}</p>@endforeach</div>@endif
@include('tool-form',['form'=>$form])<button type="button" class="btn alt" data-close-remittance-dialog>Cancel</button></dialog>
<link rel="stylesheet" href="{{asset('css/remittance-dialog.css')}}"><script src="{{asset('js/remittance-dialog.js')}}" defer></script>
@elseif($form['action']==='/ops/rate')
<section class="design-card rate-popup-launch"><div><h2>Save shipping rate</h2><p>Create a rate using the existing location and shipping-fee rules.</p></div><button type="button" data-open-rate-popup aria-haspopup="dialog" aria-controls="shipping-rate-dialog">Create rate</button></section>
<dialog id="shipping-rate-dialog" class="shipping-rate-dialog" aria-labelledby="shipping-rate-title" @if($errors->any()&&old('_rate_popup')) data-reopen-rate-popup @endif>
<header><div><span>SHIPPING RATES</span><h2 id="shipping-rate-title">Save shipping rate</h2></div><button type="button" class="rate-popup-close" data-close-rate-popup aria-label="Close shipping rate">×</button></header>
@if($errors->any()&&old('_rate_popup'))<div class="rate-popup-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{$error}}</li>@endforeach</ul></div>@endif
@include('tool-form',['form'=>$form])
<footer><button type="button" class="rate-popup-cancel" data-close-rate-popup>Cancel</button></footer></dialog>
@else
<section class="design-card workspace-action"><div class="design-card-heading"><div><h2>{{$form['label']}}</h2><p>Complete the details below to continue.</p></div></div>@include('tool-form',['form'=>$form])</section>
@endif
@endforeach</div>@endif
@if(request()->is('ops/rates'))<link rel="stylesheet" href="{{asset('css/shipping-rate-dialog.css')}}"><script src="{{asset('js/shipping-rate-dialog.js')}}" defer></script>@endif

@endsection
