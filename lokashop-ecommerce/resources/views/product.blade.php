@extends(auth()->check() && auth()->user()->role === 'buyer' ? 'app' : 'layout')
@php $links=auth()->check()?app(\App\Http\Controllers\CommonController::class)->links():[]; @endphp
@section('title',$p->name.' - LokaShop')
@section('content')
<div class="panel-grid">
 <div>
  <div class="card">
   <div class="product" style="--h:{{($p->category_id*47)%360}}">{{mb_strtoupper(mb_substr($p->name,0,1))}}</div>
  </div>
 </div>
 <div class="card">
  <h1>{{$p->name}}</h1>
  <p>{{$p->description}}</p>
  @foreach(json_decode($p->images??'[]',true) as $image)<img src="{{\App\Services\Support::imageUrl($image)}}" style="max-width:160px" alt="{{$p->name}}">@endforeach
  @php $vacation=\Illuminate\Support\Facades\DB::table('seller_businesses')->where('user_id',$p->seller_id)->value('vacation'); @endphp
  @if($vacation)<p>Store is on vacation. Orders are paused.</p>@endif
  <p class="price" style="font-size:1.6rem">₱{{number_format(\App\Services\Catalog::price($p),2)}}
   @if($p->discount_percent>0)<span class="price strike">₱{{number_format($p->price,2)}}</span> <span class="tag off">{{rtrim(rtrim(number_format($p->discount_percent,1),'0'),'.')}}% OFF</span>@endif
  </p>
  <small>{{$p->stock}} in stock</small><p id="selected-variant"></p><img id="variant-image" style="max-width:180px" hidden alt="Selected variation">
  @foreach(\Illuminate\Support\Facades\DB::table('promotions')->where('product_id',$p->id)->where('active',1)->where('starts_at','<=',now())->where('ends_at','>',now())->get() as $promo)<p>{{ucfirst($promo->type)}} ends {{$promo->ends_at}}</p>@endforeach

  @auth
   @if(auth()->user()->role==='buyer')
   <form method="post" action="/cart" style="margin-top:16px">@csrf
    <input type="hidden" name="product_id" value="{{$p->id}}">
    <label>Variation<select name="variation_id"><option value="">{{$variations->isEmpty()?'Standard':'Select a variation'}}</option>@foreach($variations as $v)<option value="{{$v->id}}" @disabled($v->stock<=0) data-price="{{\App\Services\Catalog::price($p,$v)}}" data-stock="{{$v->stock}}" data-image="{{\App\Services\Support::imageUrl($v->image)}}">{{$v->name}} ({{$v->stock}} available)</option>@endforeach</select></label>
    <label>Quantity<input type="number" min="1" name="quantity" value="1"></label>
    <button class="btn lg" @disabled($vacation||$p->stock<=0)>Add to cart</button><button class="btn" formaction="/buy-now" @disabled($vacation||$p->stock<=0)>Buy now</button>
   </form>
   <form method="post" action="/tools/wishlist">@csrf<input type="hidden" name="product_id" value="{{$p->id}}"><input type="hidden" name="enabled" value="1"><button>Save to wishlist</button></form>
   <form method="post" action="/tools/follow">@csrf<input type="hidden" name="seller_id" value="{{$p->seller_id}}"><input type="hidden" name="enabled" value="1"><button>Follow store</button></form>
   @endif
  @else
  <a class="btn lg" href="/login" style="margin-top:16px">Log in to order</a>
  @endauth
 </div>
</div>
<script>const variants=document.querySelector('[name="variation_id"]');variants?.addEventListener('change',()=>{const o=variants.selectedOptions[0];const info=document.getElementById('selected-variant');info.textContent=o.dataset.price?'Selected price: PHP '+Number(o.dataset.price).toFixed(2)+'; '+o.dataset.stock+' left':'';const image=document.getElementById('variant-image');image.hidden=!o.dataset.image;if(o.dataset.image)image.src=o.dataset.image;});</script>
@endsection
