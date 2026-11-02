<form method="post" action="{{$admin?'/tools/platform-voucher':'/tools/voucher'}}" class="voucher-create-form" data-voucher-form data-owner="{{$admin?'lokashop':'store'}}">
@csrf
<input type="hidden" name="_request_key" value="{{(string)\Illuminate\Support\Str::uuid()}}">
@if($voucher)<input type="hidden" name="id" value="{{$voucher->id}}">
@endif
<fieldset><legend>1. Offer details</legend><div class="voucher-field-grid"><label>Voucher code<input name="code" value="{{old('code',$voucher->code??'')}}" placeholder="e.g. LOKASAVE15" pattern="[A-Za-z0-9]{4,12}" minlength="4" maxlength="12" required @readonly($locked)><small>4–12 letters or numbers. Codes must be unique.</small></label>
<label>Discount type<select name="type" @disabled($locked)>
@foreach(['percent'=>'Percentage off products','fixed'=>'Peso amount off products']+($admin?['shipping'=>'Peso amount off shipping']:[]) as $type=>$label)<option value="{{$type}}" @selected(old('type',$voucher->type??'percent')===$type)>{{$label}}</option>
@endforeach</select>
@if($locked)<input type="hidden" name="type" value="{{$voucher->type}}">
@endif<small>{{$admin?'LokaShop offers apply to products; shipping offers reduce delivery fees.':'All offers apply only to products from your store.'}}</small></label>
<label><span data-value-label>Discount value (%)</span><input type="number" name="value" min="0.01" step="0.01" value="{{old('value',$voucher->value??15)}}" required @readonly($locked)></label>
<label>Minimum merchandise spend (₱)<input type="number" name="minimum_spend" min="0" step="0.01" value="{{old('minimum_spend',$voucher->minimum_spend??0)}}" required @readonly($locked)></label>
<label>Maximum discount (₱)<input type="number" name="cap" min="0.01" step="0.01" placeholder="Optional cap" value="{{old('cap',$voucher->cap??'')}}" @readonly($locked)><small>Leave blank for no additional cap. A discount never exceeds its eligible total.</small></label></div></fieldset>
<fieldset><legend>2. Schedule &amp; usage</legend><div class="voucher-field-grid"><label>Starts<input type="datetime-local" step="1" name="starts_at" value="{{old('starts_at',$voucher?($voucher->starts_at?\Illuminate\Support\Carbon::parse($voucher->starts_at)->format('Y-m-d\TH:i:s'):''):now()->format('Y-m-d\TH:i:s'))}}" @required(!$voucher) @readonly($locked)></label>
<label>Expires<input type="datetime-local" step="1" name="expires_at" value="{{old('expires_at',$voucher?($voucher->expires_at?\Illuminate\Support\Carbon::parse($voucher->expires_at)->format('Y-m-d\TH:i:s'):''):now()->addMonth()->format('Y-m-d\TH:i:s'))}}" @required(!$voucher)><small>Blank on existing vouchers means no expiry.</small></label>
<label>Total redemptions allowed<input type="number" step="1" min="{{$voucher?max(1,(int)$voucher->used_count):1}}" name="usage_limit" value="{{old('usage_limit',$voucher?($voucher->usage_limit??''):100)}}" @required(!$voucher)><small>Counts actual checkout uses, not claims. Blank on existing vouchers means unlimited.</small></label>
<label>Uses per buyer<input type="number" step="1" min="1" name="per_buyer_limit" value="{{old('per_buyer_limit',$voucher->per_buyer_limit??1)}}" required @readonly($locked)></label></div></fieldset>
<input type="hidden" name="claimable" value="{{$locked?(int)$voucher->claimable:0}}"><label class="voucher-checkbox"><input type="checkbox" name="claimable" value="1" @checked((bool)old('claimable',$voucher->claimable??true)) @disabled($locked)> Show in buyer vouchers with a Claim button</label><p class="voucher-help">Unchecked offers are code-only. Share the code with buyers to use at checkout.</p>
<div class="voucher-form-actions"><button class="btn">{{$voucher?'Save changes':'Create voucher'}}</button>
@if($voucher)<a class="btn alt" href="{{$admin?'/tools/platform':'/tools/vouchers'}}">Cancel edit</a>
@endif</div></form>
