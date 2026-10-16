<form method="post" action="{{$form['action']}}" enctype="multipart/form-data" data-safe-submit>@csrf
<input type="hidden" name="_request_key" value="{{(string)\Illuminate\Support\Str::uuid()}}">
@foreach($form['fields'] as $definition)
@php
 $optional=str_contains($definition,'?');$definition=str_replace('?','',$definition);$parts=explode(':',$definition);$field=$parts[0];$type=$parts[1]??'text';$multiple=str_ends_with($type,'[]');$type=str_replace('[]','',$type);$label=ucwords(str_replace('_',' ',preg_replace('/_id$/','',$field)));$choices=\App\Services\FormChoices::for($field,$form['action']);
@endphp
@if($field==='id' && $optional) @continue @endif
<label style="display:block;margin:12px 0">{{$label}} @if($optional)<small>(optional)</small>@endif
@if(in_array($field,['product_ids','category_ids']))
@php $source=$field==='product_ids'?\Illuminate\Support\Facades\DB::table('products')->where('seller_id',auth()->id())->whereNull('deleted_at')->get():\Illuminate\Support\Facades\DB::table('store_categories')->where('seller_id',auth()->id())->get(); @endphp
<select name="{{$field}}[]" multiple required size="5">@foreach($source as $item)<option value="{{$item->id}}" @selected(in_array($item->id,(array)old($field,[])))>{{$item->name}}</option>@endforeach</select><small>Hold Ctrl to select more than one.</small>
@elseif($choices!==null)<select name="{{$field}}" @required(!$optional)><option value="">Choose {{strtolower($label)}}</option>@foreach($choices as $value=>$text)<option value="{{$value}}" @selected((string)old($field)===(string)$value)>{{$text}}</option>@endforeach</select>
@elseif($type==='textarea')<textarea name="{{$field}}" @required(!$optional)>{{old($field)}}</textarea>
@elseif($type==='select')<select name="{{$field}}" @required(!$optional)>@foreach(explode(',',$parts[2]??'') as $choice)<option value="{{$choice}}" @selected(old($field)===$choice)>{{$field==='courier_type'?($choice==='rider'?'Logistics rider — motorcycle/tricycle':'Logistics driver — trucks'):ucfirst(str_replace('_',' ',$choice))}}</option>@endforeach</select>
@elseif($type==='checkbox')<input type="hidden" name="{{$field}}" value="0"><input type="checkbox" name="{{$field}}" value="1" @checked(old($field,in_array($field,['active','visible'])))>
@else<input type="{{$type}}" name="{{$field}}{{$multiple?'[]':''}}" @if($type!=='file'&&$type!=='password') value="{{old($field)}}" @endif @if($type==='number') step="any" @endif @if($multiple) multiple @endif @required(!$optional)>
@endif
@error($field)<small style="color:#b91c1c">{{$message}}</small>@enderror
</label>
@endforeach
<button type="submit" class="btn">{{$form['label']}}</button>
</form>
