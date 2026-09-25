@extends('app')
@section('title',$title.' - LokaShop')
@section('content')
<div class="page-head"><div><h1>{{$title}}</h1></div></div>
<div class="form-card">
 <form method="post" action="{{$action}}" enctype="multipart/form-data">@csrf
  @foreach($fields as $name=>$label)
  <label>{{$label}}<input name="{{$name}}" value="{{old($name)}}"></label>
  @endforeach
  <button class="btn">Save</button>
 </form>
</div>
@endsection
