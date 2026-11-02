@extends('app')
@section('title','Products - Seller Center')
@section('content')
<link rel="stylesheet" href="{{asset('css/product-media.css')}}">
<link rel="stylesheet" href="{{asset('css/seller-products-workspace.css')}}">
<script src="{{asset('js/product-media.js')}}" defer></script>
<div class="page-head"><div><span>SELLER WORKSPACE</span><h1>Products</h1><p>Keep your listings, photos and variations together.</p></div><a href="#new-product" class="btn" onclick="document.getElementById('new-product').open=true">Add product</a></div>
<section class="products-workspace">
<form method="get" class="product-search"><label>Search your products<input name="q" value="{{request('q')}}" placeholder="Product name"></label><button class="btn">Search</button><a href="/seller/products" class="btn alt">Clear</a></form>
@forelse($products as $p)
<article class="listing-card">
<header class="listing-summary">@include('product-photo',['photoProduct'=>$p,'photoSize'=>76])<div><small>PRODUCT #{{$p->id}}</small><h2>{{$p->name}}</h2><p>₱{{number_format($p->price,2)}} · {{$p->stock}} overall stock</p></div><span class="badge {{$p->approved?'green':'orange'}}">{{$p->draft?'Draft':($p->approved?'Approved':'Awaiting review')}}</span></header>
<details class="listing-section"><summary>Edit details &amp; photos</summary>
      <form method="post" enctype="multipart/form-data" action="/seller/products/{{$p->id}}" class="product-editor">@csrf<input type="hidden" name="_request_key" value="{{(string)\Illuminate\Support\Str::uuid()}}">
       <label>Product name<input name="name" value="{{$p->name}}" required></label>
       <label>Base price (₱)<input name="price" type="number" step="0.01" value="{{$p->price}}" required></label>
       <input type="hidden" name="original_stock" value="{{$p->stock}}"><label>Overall stock (units)<input name="stock" type="number" value="{{$p->stock}}" min="0" data-stock-product="{{$p->id}}" required></label><small data-stock-label="{{$p->id}}">{{$p->stock}} available</small>
       <label>Discount (%)<input name="discount_percent" type="number" step="0.01" value="{{$p->discount_percent}}" required></label>
       <label>Subcategory<select name="category_id" required><option value="" disabled @selected(!$categories->contains('id',$p->category_id))>Choose subcategory</option>@foreach($categories as $category)<option value="{{$category->id}}" @selected($category->id===$p->category_id)>{{$category->name}}</option>@endforeach</select></label><label>Description<textarea name="description" required>{{$p->description}}</textarea></label><label>Weight (kg)<input name="weight" type="number" step="0.001" value="{{$p->weight}}" required></label><fieldset class="seller-photos"><legend>Product photos · choose a cover</legend><p>First image is the cover used in listings. Keep at least one photo; up to 9 total.</p><div class="saved-photos">
@foreach(json_decode($p->images??'[]',true)?:[] as $photo)
<div><img src="{{\App\Services\Support::imageUrl($photo)}}" alt="{{$p->name}} photo {{$loop->iteration}}" loading="lazy"><label><input type="radio" name="cover_image" value="{{$photo}}"> Use as cover</label><label><input type="checkbox" name="remove_images[]" value="{{$photo}}"> Remove</label></div>
@endforeach
</div><label>Add photos<input type="file" name="images[]" multiple accept=".jpg,.jpeg,.png" data-image-upload></label><small>JPG or PNG · 5 MB each. New photos append to existing photos.</small><div class="upload-preview" aria-live="polite"></div></fieldset><label>Purchase limit<input name="purchase_limit" type="number" min="1" value="{{$p->purchase_limit}}"></label><label><input type="checkbox" name="draft" value="1" @checked($p->draft)> Save as draft</label><button class="btn sm">Update</button><button type="submit" form="remove-product-{{$p->id}}" name="action" value="delete" class="btn sm" style="background:#dc2626;color:white" onclick="return confirm('Remove this product?')">Remove</button>
      </form>
      <form id="remove-product-{{$p->id}}" method="post" action="/tools/product/action" style="display:inline-flex;gap:8px;margin:8px 0">@csrf<input type="hidden" name="_request_key" value="{{(string)\Illuminate\Support\Str::uuid()}}"><input type="hidden" name="id" value="{{$p->id}}"><button type="submit" name="action" value="{{$p->active?'deactivate':'activate'}}" class="btn sm alt">{{$p->active?'Deactivate':'Activate'}}</button><button name="action" value="duplicate" class="btn sm alt">Duplicate as draft</button></form>

</details>
<details class="listing-section" @if(session('errors')) open @endif><summary>Variations · {{$variantsByProduct->get($p->id,collect())->count()}} options</summary>
<p class="workspace-help">Add choices such as Color: Black and Size: Medium. Use the same option types for every variation. Buyers choose a combination before ordering. Each variation has its own stock; overall product stock also limits purchases.</p>
@foreach($variantsByProduct->get($p->id,collect()) as $variant)
<details class="variant-row"><summary>{{$variant->name}} · {{$variant->stock}} in stock · ₱{{number_format(\App\Services\Catalog::price($p,$variant),2)}}</summary>
@include('seller-variant-editor',['variant'=>$variant])
<form method="post" action="/tools/variant/delete">@csrf<input type="hidden" name="id" value="{{$variant->id}}"><button class="btn danger" onclick="return confirm('Remove this variation from new purchases?')">Remove variation</button></form>
</details>
@endforeach
<h3>Add a variation</h3>@include('seller-variant-editor',['variant'=>null])
</details></article>
@empty<div class="form-card"><h2>No matching products</h2><p>Add your first listing below or clear your search.</p></div>@endforelse
{{$products->links('components.pagination')}}
<details class="form-card" id="new-product"><summary>Add a new product</summary><p>Create the listing first, then open its Variations section to add choices.</p>
   <form method="post" enctype="multipart/form-data" action="/seller/products">@csrf<input type="hidden" name="_request_key" value="{{(string)\Illuminate\Support\Str::uuid()}}"><label>Weight (kg)<input name="weight" type="number" step="0.001" min="0.001" value="1" required></label><label>Images (1–9)<input type="file" name="images[]" accept=".jpg,.jpeg,.png" multiple required data-image-upload></label><p><small>Select up to 9 JPG/PNG images, 5 MB each. The first selected image becomes the listing cover. You can change the cover after saving.</small></p><div class="upload-preview" aria-live="polite"></div>
    <label>Product name<input name="name" placeholder="e.g. Reusable shopping bag" value="{{old('name')}}" required></label>
    <label>Product description<textarea name="description" placeholder="Describe the material, size, color, and what is included.">{{old('description')}}</textarea></label>
    <label>Product category<select name="category_id" required><option value="">Choose a product category</option>@foreach($categories as $c)<option value="{{$c->id}}" @selected((string)old('category_id',$defaultCategoryId)===(string)$c->id)>{{$c->name}}</option>@endforeach</select></label>
    <p><small>Registered main category: {{$shopCategory}}. Choose one of its six subcategories. This main category cannot be changed.</small></p>
    @if($categories->isEmpty())<p role="alert">Your legacy store category needs administrator assignment before products can be saved.</p>@endif
    <label>Price (₱)<input name="price" type="number" step="0.01" min="0.01" placeholder="e.g. 199.00" value="{{old('price')}}" required></label>
    <label>Discount (%)<input name="discount_percent" type="number" step="0.01" min="0" max="100" value="{{old('discount_percent',0)}}" required></label>
    <p><small>Enter 0 for no discount. For example, 10 gives buyers 10% off the listed price.</small></p>
    <label>Stock quantity (units)<input name="stock" type="number" min="0" step="1" placeholder="e.g. 25" value="{{old('stock')}}" required></label>
    <p><small>Enter the number of units available to sell. Use 0 if the product is out of stock.</small></p>
    <label>Purchase limit (optional)<input type="number" name="purchase_limit" min="1"></label><label><input type="checkbox" name="draft" value="1"> Save as draft</label>
    <button class="btn" style="width:100%">Submit for admin review</button>
   </form>
</details></section>
@endsection
