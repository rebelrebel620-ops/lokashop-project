@extends('app')
@section('title',$seller?'Customer reviews':'My reviews')
@section('content')
<link rel="stylesheet" href="{{asset('css/product-reviews.css')}}">
<script src="{{asset('js/product-reviews.js')}}" defer></script>
<div class="page-head"><div><span class="review-eyebrow">CUSTOMER VOICES</span><h1>{{$seller?'Customer reviews':'My reviews'}}</h1><p>{{$seller?'Read buyer feedback and respond to your customers.':'Rate your completed purchases and share photos of your products.'}}</p></div></div>
@if(!$seller)<nav class="review-workspace-tabs" aria-label="Review sections"><a href="/tools/ratings?tab=pending" @if($tab==='pending') aria-current="page" @endif>To review <span>{{$counts['pending']}}</span></a><a href="/tools/ratings" @if($tab==='reviews') aria-current="page" @endif>My reviews <span>{{$counts['reviews']}}</span></a></nav>@endif
@if($selected)
<section class="review-editor"><div class="review-product-heading">@if($selected->image)<img src="{{\App\Services\Support::imageUrl($selected->image)}}" alt="{{$selected->name}}">@endif<div><span class="review-eyebrow">{{$editing?'EDIT YOUR REVIEW':'SHARE YOUR EXPERIENCE'}}</span><h2>{{$selected->name}}</h2><p>Order #{{$selected->order_id}} · Quantity {{$selected->quantity}}</p></div></div>
<form method="post" action="/tools/review" enctype="multipart/form-data">@csrf<input type="hidden" name="order_item_id" value="{{$selected->id}}">
<fieldset class="star-picker"><legend>How was your product?</legend><div class="star-options">@foreach([1,2,3,4,5] as $star)<label><input type="radio" name="stars" value="{{$star}}" required @checked((int)old('stars',$editing->stars??0)===$star)><span aria-hidden="true">★</span><small>{{$star}}</small><span class="review-sr">{{$star}} {{$star===1?'star':'stars'}}</span></label>@endforeach</div><p data-star-description aria-live="polite">Choose 1 to 5 stars</p></fieldset>
<label class="review-field">Your review <small>(optional)</small><textarea name="comment" maxlength="500" rows="4" placeholder="What did you like? Tell other buyers about the quality and your experience.">{{old('comment',$editing->comment??'')}}</textarea></label>
<label class="review-field">Add photos <small>(optional · up to 5 JPG/PNG images, 5 MB each)</small><input type="file" name="photos[]" accept=".jpg,.jpeg,.png" multiple data-review-upload></label><div class="review-upload-preview" aria-live="polite"></div>
@if($editing)<p class="review-help">You may edit once within 30 days of order completion. New photos replace existing photos; leave the upload empty to keep them.</p>@else<p class="review-help">Reviews are available for 30 days after order completion and may be edited once.</p>@endif
<div class="review-form-actions"><button class="btn">{{$editing?'Update review':'Publish review'}}</button><a class="btn alt" href="/tools/ratings?tab={{$tab}}">Cancel</a></div></form></section>
@endif
@if(!$seller&&$tab==='pending')
<div class="my-review-grid">@forelse($purchases as $purchase)<article class="my-review-card"><div class="review-product-heading">@if($purchase->image)<img src="{{\App\Services\Support::imageUrl($purchase->image)}}" alt="{{$purchase->name}}" loading="lazy">@else<div class="review-image-empty">No photo</div>@endif<div><h2>{{$purchase->name}}</h2><p>Order #{{$purchase->order_id}} · {{$purchase->quantity}} item(s)</p><small>Completed {{\Illuminate\Support\Carbon::parse($purchase->completed_at)->format('M j, Y')}}</small></div></div><a class="btn" href="{{url('/tools/ratings').'?'.http_build_query(['tab'=>'pending','write'=>$purchase->id])}}">Write review</a></article>@empty<section class="review-empty"><h2>No purchases waiting for review</h2><p>Eligible products appear after an order is completed, for up to 30 days.</p><a class="btn alt" href="/orders?tab=completed">View completed orders</a></section>@endforelse</div>{{$purchases->links('components.pagination')}}
@else
<div class="my-review-grid">
@forelse($reviews as $review)
<article class="my-review-card"><div class="review-product-heading">@if($review->product_image)<img src="{{\App\Services\Support::imageUrl($review->product_image)}}" alt="{{$review->product_name}}" loading="lazy">@else<div class="review-image-empty">No photo</div>@endif<div><h2>{{$review->product_name}}</h2><p>{{$seller?$review->buyer_name:'Your review'}} · {{\Illuminate\Support\Carbon::parse($review->created_at)->format('M j, Y')}}</p><span class="rating-stars" aria-label="{{$review->stars}} out of 5 stars">{{str_repeat('★',(int)$review->stars)}}{{str_repeat('☆',5-(int)$review->stars)}}</span></div></div><p class="customer-comment">{{$review->comment ?: 'A star rating was left for this product.'}}</p>@include('review-photo-strip')
@if($review->seller_response)<div class="seller-review-response"><strong>Seller's reply</strong><p>{{$review->seller_response}}</p></div>@endif
@if($seller)<form method="post" action="/tools/review/reply">@csrf<input type="hidden" name="id" value="{{$review->id}}"><label class="review-field">Reply to this review<textarea name="response" maxlength="500" rows="3" required>{{$review->seller_response}}</textarea></label><button class="btn">Save reply</button></form>@elseif($review->edit_count<1&&$review->order_status==='COMPLETED'&&$review->completed_at&&now()->lte(\Illuminate\Support\Carbon::parse($review->completed_at)->addDays(30)))<a class="btn alt" href="{{url('/tools/ratings').'?'.http_build_query(['write'=>$review->order_item_id])}}">Edit review</a>@endif
</article>
@empty<section class="review-empty"><h2>No reviews yet</h2><p>{{$seller?'Reviews will appear after buyers complete their purchases.':'Choose To review to rate an eligible purchase.'}}</p></section>@endforelse
</div>{{$reviews->links('components.pagination')}}
@endif
@endsection
