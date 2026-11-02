@php
$optionSource=$variant ?: $variantsByProduct->get($p->id,collect())->first();
$optionPairs=json_decode($optionSource->options??'{}',true)?:[];
$types=array_keys($optionPairs);$values=$variant?array_values($optionPairs):[];
@endphp
<form method="post" enctype="multipart/form-data" action="/tools/variant" class="variant-editor">
@csrf<input type="hidden" name="_request_key" value="{{(string)\Illuminate\Support\Str::uuid()}}"><input type="hidden" name="product_id" value="{{$p->id}}">
@if($variant)<input type="hidden" name="id" value="{{$variant->id}}"><input type="hidden" name="original_stock" value="{{$variant->stock}}">@endif
<div class="option-pair"><label>Option type<input name="option_types[]" value="{{$types[0]??''}}" placeholder="e.g. Color" maxlength="50" required></label><label>Option value<input name="option_values[]" value="{{$values[0]??''}}" placeholder="e.g. Black" maxlength="100" required></label></div>
<div class="option-pair"><label>Second type (optional)<input name="option_types[]" value="{{$types[1]??''}}" placeholder="e.g. Size" maxlength="50"></label><label>Second value (optional)<input name="option_values[]" value="{{$values[1]??''}}" placeholder="e.g. Medium" maxlength="100"></label></div>
<div class="option-pair"><label>Display name (optional)<input name="name" value="{{$variant->name??''}}" maxlength="120" placeholder="Automatically uses your option values"></label><label>SKU (optional)<input name="sku" value="{{$variant->sku??''}}" maxlength="100" placeholder="Generated if blank"></label></div>
<div class="option-pair"><label>Price adjustment (₱)<input name="price_adjustment" type="number" step="0.01" value="{{$variant->price_adjustment??0}}" required><small>Added to the base price before discount. Use 0 for the same price.</small></label><label>Variation stock<input name="stock" type="number" min="0" max="99999" value="{{$variant->stock??0}}" required></label></div>
@if($variant && $variant->image)<img src="{{\App\Services\Support::imageUrl($variant->image)}}" alt="{{$variant->name}}" width="80" height="80" style="object-fit:contain">@endif
<label>Variation photo (optional)<input type="file" name="image" accept=".jpg,.jpeg,.png"><small>JPG or PNG, up to 5 MB. Leave blank to keep the existing photo.</small></label>
<button class="btn">{{$variant?'Save variation':'Add variation'}}</button>
</form>
