@extends(config('app.site_kind')==='logistics'?'dashboard':'app')
@section('title',$title)
@section(config('app.site_kind')==='logistics'?'panel':'content')
@if(collect($forms)->contains(fn($form)=>in_array($form['action'],['/tools/return','/tools/return/escalate','/tools/return/respond','/tools/return/received'])))
<link rel="stylesheet" href="{{asset('css/return-request-dialog.css')}}"><script src="{{asset('js/return-request-dialog.js')}}" defer></script>
@endif
<div class="page-head"><div><span class="design-eyebrow">YOUR WORKSPACE</span><h1>{{$title}}</h1><p>View your records and manage the details below.</p></div></div>
@if(isset($report))<section class="design-card"><h2>Filter report</h2><form method="get" class="design-field-grid"><label>Search / tracking<input name="q" value="{{request('q')}}"></label><label>From<input type="date" name="from" value="{{request('from')}}"></label><label>To<input type="date" name="to" value="{{request('to')}}"></label><button>Apply filters</button></form><p><a class="btn" href="{{request()->fullUrlWithQuery(['format'=>'pdf'])}}">Download PDF</a></p></section>@endif
@php
 $sellerReturns=request()->is('tools/returns')&&auth()->user()->role==='seller';
 $returnTab=in_array(request('return_status'),['all','pending','approved','rejected'])?request('return_status'):'pending';
 $workspaceCards=\App\Services\WorkspaceRecords::cards($records);
 if($sellerReturns&&$returnTab!=='all')$workspaceCards=collect($workspaceCards)->filter(fn($card)=>$returnTab==='pending'?in_array($card['status'],['requested','disputed']):($returnTab==='approved'?in_array($card['status'],['approved','received']):$card['status']==='rejected'));
@endphp
@if($sellerReturns)
<nav class="return-status-tabs" aria-label="Return status">
@foreach(['pending'=>'Pending returns','approved'=>'Approved returns','rejected'=>'Rejected returns','all'=>'All returns'] as $key=>$label)<a class="btn {{$returnTab===$key?'':'alt'}}" href="{{request()->fullUrlWithQuery(['return_status'=>$key])}}" @if($returnTab===$key) aria-current="page" @endif>{{$label}}</a>@endforeach
</nav>
@endif
<div class="workspace-records">
@forelse($workspaceCards as $card)
<article class="design-card workspace-record"><header class="workspace-record-header"><div><h2>{{$card['title']}}</h2><small>@if($card['id'])Reference #{{$card['id']}}@endif @if($card['date']) · {{\Illuminate\Support\Carbon::parse($card['date'])->format('M j, Y')}}@endif</small></div>@if($card['status'])<span class="design-pill">{{ucfirst(strtolower(str_replace('_',' ',$card['status'])))}}</span>@endif</header><dl class="workspace-facts">@foreach($card['fields'] as $label=>$value)<div><dt>{{$label}}</dt><dd>{{$value}}</dd></div>@endforeach</dl>
@if(!empty($card['source']['video_path']))<div><strong>Return video proof</strong><video controls playsinline preload="metadata" style="width:100%;max-width:520px" src="/returns/{{$card['id']}}/video"></video></div>@endif
@if(isset($card['source']['photos'],$card['source']['order_id']))<div class="workspace-evidence">@foreach(json_decode($card['source']['photos']??'[]',true)??[] as $i=>$path)<a href="/returns/{{$card['id']}}/photos/{{$i}}" target="_blank" rel="noopener">View evidence {{$i+1}}</a>@endforeach</div>@endif
@if($sellerReturns)
<footer class="return-case-actions" aria-label="Return case actions">
@if($card['status']==='requested')
<button type="button" class="btn" data-return-case="{{$card['id']}}" data-return-decision="approved">Approve return</button>
<button type="button" class="btn return-reject" data-return-case="{{$card['id']}}" data-return-decision="rejected">Reject return</button>
@elseif($card['status']==='approved')
<button type="button" class="btn" data-return-case="{{$card['id']}}" data-return-receive>Confirm returned items received</button>
@endif
</footer>
@endif
</article>
@empty<section class="design-card design-empty"><div class="empty-orbit">✓</div><h2>Nothing here yet</h2><p>Your records will appear here when there is activity.</p></section>@endforelse
</div>
@if(count($forms))<div class="workspace-action-grid">
@foreach($forms as $form)
@php $returnAction=in_array($form['action'],['/tools/return','/tools/return/escalate','/tools/return/respond','/tools/return/received']);$dialogId='return-action-'.$loop->index; @endphp
<section class="design-card workspace-action"><div class="design-card-heading"><div><h2>{{$form['label']}}</h2><p>Complete the details below to continue.</p></div></div>
@if($returnAction)
<button type="button" class="btn" data-return-dialog="{{$dialogId}}" aria-haspopup="dialog" aria-controls="{{$dialogId}}">{{$form['label']}}</button>
<dialog id="{{$dialogId}}" class="return-request-dialog" aria-labelledby="{{$dialogId}}-title" data-return-action="{{$form['action']}}" @if($errors->any()&&old('_return_action')===$form['action']) data-reopen-return-dialog @endif>
<header><div><span>RETURN REQUEST</span><h2 id="{{$dialogId}}-title">{{$form['label']}}</h2></div><button type="button" class="return-dialog-close" data-close-return-dialog aria-label="Close">×</button></header>
@if($errors->any()&&old('_return_action')===$form['action']&&$form['action']!=='/tools/return')<div class="return-form-errors" role="alert">@foreach($errors->all() as $error)<p>{{$error}}</p>@endforeach</div>@endif
@endif
@if($form['action']==='/tools/return')@include('return-request-form',['returnOrder'=>null])@else @include('tool-form',['form'=>$form]) @endif
@if($returnAction)<p><button type="button" class="btn alt" data-close-return-dialog>Cancel</button></p></dialog>@endif
</section>
@endforeach
</div>@endif
@endsection
