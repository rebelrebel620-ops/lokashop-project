@extends(config('app.site_kind')==='logistics'?'dashboard':'app')
@section('title',$title)
@section(config('app.site_kind')==='logistics'?'panel':'content')
<div class="page-head"><div><span class="design-eyebrow">YOUR WORKSPACE</span><h1>{{$title}}</h1><p>View your records and manage the details below.</p></div></div>
@if(isset($report))<section class="design-card"><h2>Filter report</h2><form method="get" class="design-field-grid"><label>Search / tracking<input name="q" value="{{request('q')}}"></label><label>From<input type="date" name="from" value="{{request('from')}}"></label><label>To<input type="date" name="to" value="{{request('to')}}"></label><button>Apply filters</button></form><p><a href="{{request()->fullUrlWithQuery(['format'=>'csv'])}}">Download CSV</a> · <a href="{{request()->fullUrlWithQuery(['format'=>'xlsx'])}}">Download spreadsheet</a></p></section>@endif
@php $workspaceCards=\App\Services\WorkspaceRecords::cards($records); @endphp
<div class="workspace-records">
@forelse($workspaceCards as $card)
<article class="design-card workspace-record"><header class="workspace-record-header"><div><h2>{{$card['title']}}</h2><small>@if($card['id'])Reference #{{$card['id']}}@endif @if($card['date']) · {{\Illuminate\Support\Carbon::parse($card['date'])->format('M j, Y')}}@endif</small></div>@if($card['status'])<span class="design-pill">{{ucfirst(strtolower(str_replace('_',' ',$card['status'])))}}</span>@endif</header><dl class="workspace-facts">@foreach($card['fields'] as $label=>$value)<div><dt>{{$label}}</dt><dd>{{$value}}</dd></div>@endforeach</dl>
@if(isset($card['source']['photos'],$card['source']['order_id']))<div class="workspace-evidence">@foreach(json_decode($card['source']['photos']??'[]',true)??[] as $i=>$path)<a href="/returns/{{$card['id']}}/photos/{{$i}}" target="_blank" rel="noopener">View evidence {{$i+1}}</a>@endforeach</div>@endif
</article>
@empty<section class="design-card design-empty"><div class="empty-orbit">✓</div><h2>Nothing here yet</h2><p>Your records will appear here when there is activity.</p></section>@endforelse
</div>
@if(count($forms))<div class="workspace-action-grid">@foreach($forms as $form)<section class="design-card workspace-action"><div class="design-card-heading"><div><h2>{{$form['label']}}</h2><p>Complete the details below to continue.</p></div></div>@include('tool-form',['form'=>$form])</section>@endforeach</div>@endif
@endsection
