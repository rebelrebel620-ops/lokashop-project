<form method="post" action="{{$form['action']}}" enctype="multipart/form-data" data-safe-submit class="partner-form">@csrf
<input type="hidden" name="_request_key" value="{{(string)\Illuminate\Support\Str::uuid()}}">
@foreach($form['fields'] as $definition)
@php
 $optional=str_contains($definition,'?');$definition=str_replace('?','',$definition);$parts=explode(':',$definition);$field=$parts[0];$type=$parts[1]??'text';$multiple=str_ends_with($type,'[]');$type=str_replace('[]','',$type);$label=ucwords(str_replace('_',' ',preg_replace('/_id$/','',$field)));$choices=\App\Services\FormChoices::for($field,$form['action']);
@endphp
@if($field==='id' && $optional) @continue @endif
@php
 $sections=['courier_type'=>'01 · Courier type','first_name'=>'02 · Personal details','business_name'=>'03 · Company details','region'=>'04 · Address and service area','vehicle'=>'05 · Vehicle and license','id_document'=>'06 · Required documents','password'=>'07 · Account security'];
 $tips=['phone'=>'Use 09 followed by 9 digits.','date_of_birth'=>'You must be at least 18 years old.','license_number'=>'Format: A12-34-567890','license_expires_at'=>'Your license must be valid today or later.','postal_code'=>'Enter your 4-digit postal code.','id_document'=>'Government-issued ID. JPG, PNG or PDF, up to 5 MB.','permit_document'=>'Vehicle OR/CR. JPG, PNG or PDF, up to 5 MB.','sec_document'=>'SEC registration certificate. JPG, PNG or PDF, up to 5 MB.','bir_document'=>'BIR Form 2303. JPG, PNG or PDF, up to 5 MB.','password'=>'At least 8 characters, with uppercase, lowercase, a number and a special character.'];
@endphp
@if(isset($sections[$field]))<h3 class="partner-section">{{ preg_replace('/^\d+ · /','',$sections[$field]) }}</h3>@endif
<label class="partner-field partner-field-{{$field}}">{{ ['courier_type'=>'Courier type','id_document'=>'Government-issued ID','permit_document'=>'Vehicle OR/CR','sec_document'=>'SEC certificate','bir_document'=>'BIR Form 2303','terms'=>'I agree to the Terms and Privacy Policy'][$field] ?? $label }} @if($optional)<small>(optional)</small>@endif
@if(in_array($field,['product_ids','category_ids']))
@php $source=$field==='product_ids'?\Illuminate\Support\Facades\DB::table('products')->where('seller_id',auth()->id())->whereNull('deleted_at')->get():\Illuminate\Support\Facades\DB::table('store_categories')->where('seller_id',auth()->id())->get(); @endphp
<select name="{{$field}}[]" multiple required size="5">@foreach($source as $item)<option value="{{$item->id}}" @selected(in_array($item->id,(array)old($field,[])))>{{$item->name}}</option>@endforeach</select><small>Hold Ctrl to select more than one.</small>
@elseif($choices!==null)<select name="{{$field}}" @required(!$optional)><option value="">Choose {{strtolower($label)}}</option>@foreach($choices as $value=>$text)<option value="{{$value}}" @selected((string)old($field)===(string)$value)>{{$text}}</option>@endforeach</select>
@elseif($type==='textarea')<textarea name="{{$field}}" @required(!$optional)>{{old($field)}}</textarea>
@elseif($type==='select')<select name="{{$field}}" @required(!$optional)>@foreach(explode(',',$parts[2]??'') as $choice)<option value="{{$choice}}" @selected(old($field,$field==='courier_type'?(request('type')==='driver'?'driver':'rider'):null)===$choice)>{{$field==='courier_type'?($choice==='rider'?'Logistics rider — motorcycle/tricycle':'Logistics driver — trucks'):ucfirst(str_replace('_',' ',$choice))}}</option>@endforeach</select>
@elseif($type==='checkbox')<input type="hidden" name="{{$field}}" value="0"><input type="checkbox" name="{{$field}}" value="1" required @checked(old($field,in_array($field,['active','visible'])))>
@else<input type="{{$type}}" name="{{$field}}{{$multiple?'[]':''}}" @if($type!=='file'&&$type!=='password') value="{{old($field)}}" @endif @if($type==='number') step="any" @endif @if($type==='file') accept=".jpg,.jpeg,.png,.pdf" @endif @if($field==='phone') inputmode="tel" placeholder="09171234567" @endif @if($field==='postal_code') inputmode="numeric" maxlength="4" @endif @if($type==='password') minlength="8" autocomplete="new-password" @endif @if($field==='date_of_birth') max="{{ now()->subYears(18)->toDateString() }}" @endif @if($multiple) multiple @endif @required(!$optional)>
@endif
@if(isset($tips[$field]))<small class="field-hint">{{ $tips[$field] }}</small>@endif
@error($field)<small style="color:#b91c1c">{{$message}}</small>@enderror
</label>
@endforeach
<button type="submit" class="btn">Submit application</button>
</form>

<script>
(()=>{
 const type=document.querySelector('[name="courier_type"]'),vehicle=document.querySelector('[name="vehicle"]');
 function updateVehicles(){if(!type||!vehicle)return;const allowed=type.value==='driver'?['4-wheel truck','6-wheel truck','8-wheel truck','10-wheel truck']:['motorcycle','tricycle'];Array.from(vehicle.options).forEach(option=>{option.disabled=!allowed.includes(option.value);option.hidden=option.disabled;});if(!allowed.includes(vehicle.value))vehicle.value=allowed[0];}
 type?.addEventListener('change',updateVehicles);updateVehicles();
 document.querySelectorAll('.partner-form input[type="password"]').forEach(input=>{const row=document.createElement('span');row.className='partner-password';input.before(row);row.append(input);const button=document.createElement('button');button.type='button';button.textContent='Show';button.setAttribute('aria-label','Show password');button.setAttribute('aria-pressed','false');row.append(button);button.addEventListener('click',()=>{const show=input.type==='password';input.type=show?'text':'password';button.textContent=show?'Hide':'Show';button.setAttribute('aria-label',show?'Hide password':'Show password');button.setAttribute('aria-pressed',String(show));});});
})();
</script>
