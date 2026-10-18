@extends('app')
@section('content')<div class="page-head"><div><h1>My store</h1><p>Your public store details and product groups.</p></div></div>
<div class="form-card"><h2>Store details</h2><form method="post" action="/tools/store" enctype="multipart/form-data">@csrf
<label>Store name<input name="name" readonly value="{{$store->name}}" minlength="3" maxlength="30" required></label>
<p>Registered main category: <b>{{$store->category}}</b>. Store name and main category are permanent.</p><label>About your store<textarea name="description" maxlength="500">{{old('description',$store->description)}}</textarea></label>
<label>Store logo<input type="file" name="logo" accept=".jpg,.jpeg,.png"></label><label>Cover image<input type="file" name="banner" accept=".jpg,.jpeg,.png"></label>
<input type="hidden" name="vacation" value="0"><label><input type="checkbox" name="vacation" value="1" @checked($store->vacation)> Pause new orders while on vacation</label><button class="btn">Save store</button></form></div>
<div class="section-card"><h2>Store categories</h2><p>Use categories such as Clothing or Accessories to organize your products.</p>
@forelse($categories as $category)<div class="list-row"><b>{{$category->name}}</b><form method="post" action="/tools/category/delete">@csrf<input type="hidden" name="id" value="{{$category->id}}"><button class="btn alt">Remove</button></form></div>@empty<p>No categories yet.</p>@endforelse
@php $form=['label'=>'Add category','action'=>'/tools/category','fields'=>['name','parent_id:number?','visible:checkbox','position:number?']]; @endphp @include('tool-form')
</div><section class="form-card"><h3>Assign products to categories</h3>
<form method="post" action="/tools/product-categories">@csrf<label>Product<select name="product_id" required>@foreach(\Illuminate\Support\Facades\DB::table('products')->where('seller_id',auth()->id())->whereNull('deleted_at')->get() as $product)<option value="{{$product->id}}">{{$product->name}}</option>@endforeach</select></label><p>Choose up to three categories.</p>@foreach($categories as $category)<label><input type="checkbox" name="category_ids[]" value="{{$category->id}}">{{$category->name}}</label>@endforeach<button class="btn">Save categories</button></form></section>
@endsection