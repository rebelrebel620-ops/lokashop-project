<section id="customer-reviews" class="customer-reviews">
<div class="review-section-head"><div><span class="review-eyebrow">FROM OUR BUYERS</span><h2>Customer reviews</h2></div><span>{{$reviewSummary['count']}} {{$reviewSummary['count']===1?'review':'reviews'}}</span></div>
<div class="rating-overview"><div class="rating-score"><strong>{{$reviewSummary['count']?number_format($reviewSummary['average'],1):'—'}}</strong><span>out of 5</span><div class="rating-stars" aria-hidden="true">{{str_repeat('★',(int)round($reviewSummary['average']))}}{{str_repeat('☆',5-(int)round($reviewSummary['average']))}}</div><small>{{$reviewSummary['count']?'Based on buyer ratings':'Be the first to review this product'}}</small></div><div class="rating-distribution">@foreach([5,4,3,2,1] as $stars)@php $n=(int)$reviewSummary['distribution']->get($stars,0);$pct=$reviewSummary['count']?$n/$reviewSummary['count']*100:0; @endphp<div><span>{{$stars}} ★</span><meter min="0" max="100" value="{{$pct}}" aria-label="{{$stars}} stars: {{$n}} reviews">{{$n}}</meter><span>{{$n}}</span></div>@endforeach</div></div>
<div class="public-review-list">
@forelse($productReviews as $review)
@php $words=preg_split('/\s+/',trim($review->reviewer_name??''));$display=($words[0]??'')!==''?$words[0].(count($words)>1?' '.mb_substr(end($words),0,1).'.':''):'LokaShop buyer'; @endphp
<article class="customer-review"><div class="customer-review-head"><div class="review-avatar" aria-hidden="true">{{mb_strtoupper(mb_substr($display,0,1))}}</div><div><strong>{{$display}}</strong><small>Verified purchase · {{\Illuminate\Support\Carbon::parse($review->created_at)->format('M j, Y')}}</small></div><span class="rating-stars" aria-label="{{$review->stars}} out of 5 stars">{{str_repeat('★',(int)$review->stars)}}{{str_repeat('☆',5-(int)$review->stars)}}</span></div><p class="customer-comment">{{$review->comment ?: 'This buyer left a star rating.'}}</p>@include('review-photo-strip')
@if($review->seller_response)<div class="seller-review-response"><strong>Seller's reply</strong><p>{{$review->seller_response}}</p></div>@endif
</article>
@empty<div class="review-empty"><h3>No customer reviews yet</h3><p>Buyers can review this product after completing their purchase.</p></div>@endforelse
</div>
{{$productReviews->links('components.pagination')}}
@auth @if(auth()->user()->role==='buyer')<p class="review-help">Bought this product? <a href="/tools/ratings?tab=pending">Review your completed purchases</a>.</p>@endif @endauth
</section>
