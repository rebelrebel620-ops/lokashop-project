@extends('app')
@section('title',$title.' - LokaShop')
@section('content')
<div class="page-head"><div><h1>{{$title}}</h1></div></div>

@php $rows = collect($records->items()); $cols = $rows->isNotEmpty() ? array_keys(array_diff_key((array)$rows->first(), array_flip(['password','remember_token']))) : []; @endphp
<div class="table-card">
 @if($rows->isNotEmpty())
 <table>
  <thead><tr>@foreach($cols as $c)<th>{{str_replace('_',' ',$c)}}</th>@endforeach @if(isset($actions))<th></th>@endif</tr></thead>
  <tbody>
   @foreach($rows as $record)
   <tr>
    @foreach($cols as $c)
     @php $value = $record->{$c} ?? null; @endphp
     <td>
      @if($c==='status' || $c==='role')<span class="badge {{ in_array($value,['approved','active','DELIVERED','COMPLETED']) ? 'green' : (in_array($value,['pending','open']) ? 'orange' : 'blue') }}">{{is_scalar($value)?ucfirst(str_replace('_',' ',(string)$value)):'-'}}</span>
      @elseif($c==='active')<span class="badge {{$value?'green':'gray'}}">{{$value?'Active':'Inactive'}}</span>
      @elseif($c==='total'||$c==='commission')₱{{number_format((float)$value,2)}}
      @else {{is_scalar($value)?$value:'-'}}
      @endif
     </td>
    @endforeach
    @if(isset($actions))
    <td>@foreach($actions as $action)<form method="post" action="{{$action['url']}}">@csrf<input type="hidden" name="id" value="{{$record->id}}"><button class="btn sm">{{$action['label']}}</button></form>@endforeach</td>
    @endif
   </tr>
   @endforeach
  </tbody>
 </table>
 @else
 <div class="empty">No records yet.</div>
 @endif
</div>
{{$records->links()}}
@endsection
