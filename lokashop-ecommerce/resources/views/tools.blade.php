@extends(config('app.site_kind')==='logistics'?'dashboard':'app')
@section('title',$title)
@section(config('app.site_kind')==='logistics'?'panel':'content')
<div class="page-head"><div><h1>{{$title}}</h1><p>Choose an item by name. New records receive their numbers automatically.</p></div></div>
@if(isset($report))<details><summary>Report filters</summary><form method="get"><label>Search / tracking<input name="q" value="{{request('q')}}"></label><label>From<input type="date" name="from" value="{{request('from')}}"></label><label>To<input type="date" name="to" value="{{request('to')}}"></label><button>Filter</button></form></details>@endif
@if(isset($report))<p><a href="{{request()->fullUrlWithQuery(['format'=>'csv'])}}">Export CSV</a> · <a href="{{request()->fullUrlWithQuery(['format'=>'xlsx'])}}">Export XLSX</a></p>@endif
<div class="section-card panel-card" style="padding:20px;margin:16px 0;overflow:auto">
@forelse($records as $record)
@php $data=\Illuminate\Support\Arr::except((array)$record,['password','remember_token','api_token','api_token_hash','activation_hash','delivery_otp_hash','address_snapshot','municipality_key']); @endphp
<details style="border-bottom:1px solid #ddd;padding:10px"><summary>{{ $data['id']??$loop->iteration }} — {{ $data['name']??$data['tracking_code']??$data['code']??$data['status']??$data['reference']??'Record' }}</summary><dl>
@foreach($data as $key=>$value)
<dt style="font-weight:bold">{{ucwords(str_replace('_',' ',$key))}}</dt><dd style="white-space:pre-wrap;overflow-wrap:anywhere">{{is_scalar($value)||is_null($value)?$value:json_encode($value,JSON_PRETTY_PRINT)}}</dd>
@endforeach
</dl>
@if(isset($data['photos'])&&isset($data['order_id']))@foreach(json_decode($data['photos']??'[]',true) as $i=>$path)<a href="/returns/{{$data['id']}}/photos/{{$i}}" target="_blank" rel="noopener">Evidence {{$i+1}}</a> @endforeach @endif
</details>
@empty<p>No records yet.</p>@endforelse
</div>
@foreach($forms as $form)
<details class="section-card panel-card" style="padding:20px;margin:16px 0" @if($loop->first) open @endif><summary>{{$form['label']}}</summary>@include('tool-form',['form'=>$form])</details>
@endforeach
@endsection
