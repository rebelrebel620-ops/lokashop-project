@extends('app')
@section('content')<div class="page-head"><div><h1>My addresses</h1><p>Select your location, then enter your street and house number.</p></div></div>
@foreach($addresses->push(null) as $a)<div class="form-card"><h2>{{$a?'Edit address':'Add address'}}</h2><form method="post" action="/tools/address">@csrf
@if($a)<input type="hidden" name="id" value="{{$a->id}}">@endif
@foreach(['recipient'=>'Recipient','phone'=>'Phone','region'=>'Region','province'=>'Province','city'=>'City / municipality','barangay'=>'Barangay','street'=>'Street / house number','postal_code'=>'Postal code'] as $field=>$label)<label>{{$label}}<input name="{{$field}}" value="{{old($field,$a->$field??'')}}" @required($field!=='region')></label>@endforeach
<input type="hidden" name="is_default" value="0"><label><input type="checkbox" name="is_default" value="1" @checked($a->is_default??false)> Default delivery address</label><button class="btn">Save address</button></form>
@if($a)<form method="post" action="/tools/address/delete">@csrf<input type="hidden" name="id" value="{{$a->id}}"><button class="btn alt">Delete address</button></form>@endif</div>@endforeach
@endsection