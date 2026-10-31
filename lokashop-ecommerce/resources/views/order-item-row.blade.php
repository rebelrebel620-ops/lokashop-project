<div class="purchase-item">
@if($item->image)<img src="{{\App\Services\Support::imageUrl($item->image)}}" alt="{{$item->name}}" loading="lazy">@else<div class="order-photo-empty">No photo</div>@endif
<div><h3>{{$item->name}}</h3><small>Quantity: {{$item->quantity}}</small></div><strong>₱{{number_format($item->unit_price*$item->quantity,2)}}</strong>
</div>
