@extends('app')
@section('title','Products - Seller Center')
@section('content')
<div class="page-head"><div><h1>Products</h1><p>Manage your listings, variations and vouchers.</p></div></div>

<div class="panel-grid">
 <div>
  <div class="table-card">
   <div class="shead" style="padding:16px 18px 0"><h3>Your listings</h3></div>
   <table>
    <thead><tr><th>Product</th><th>Price</th><th>Stock</th><th>Discount %</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse($products as $p)
    <tr>
     <td>@include('product-photo',['photoProduct'=>$p,'photoSize'=>72])<b>#{{$p->id}} {{$p->name}}</b></td>
     <td colspan="4">
      <form method="post" enctype="multipart/form-data" action="/seller/products/{{$p->id}}" class="inline-form">@csrf<input type="hidden" name="_request_key" value="{{(string)\Illuminate\Support\Str::uuid()}}">
       <input name="name" value="{{$p->name}}" required>
       <input name="price" type="number" step="0.01" value="{{$p->price}}" required>
       <input type="hidden" name="original_stock" value="{{$p->stock}}"><input name="stock" type="number" value="{{$p->stock}}" min="0" data-stock-product="{{$p->id}}" required><small data-stock-label="{{$p->id}}">{{$p->stock}} available</small>
       <input name="discount_percent" type="number" step="0.01" value="{{$p->discount_percent}}" required>
       <select name="category_id" required><option value="" disabled @selected(!$categories->contains('id',$p->category_id))>Choose subcategory</option>@foreach($categories as $category)<option value="{{$category->id}}" @selected($category->id===$p->category_id)>{{$category->name}}</option>@endforeach</select><textarea name="description" required>{{$p->description}}</textarea><input name="weight" type="number" step="0.001" value="{{$p->weight}}" required><input type="file" name="images[]" multiple accept=".jpg,.jpeg,.png"><label>Purchase limit<input name="purchase_limit" type="number" min="1" value="{{$p->purchase_limit}}"></label><label><input type="checkbox" name="draft" value="1" @checked($p->draft)> Save as draft</label><button class="btn sm">Update</button><button type="submit" form="remove-product-{{$p->id}}" name="action" value="delete" class="btn sm" style="background:#dc2626;color:white" onclick="return confirm('Remove this product?')">Remove</button>
      </form>
      <form id="remove-product-{{$p->id}}" method="post" action="/tools/product/action" style="display:inline-flex;gap:8px;margin:8px 0">@csrf<input type="hidden" name="_request_key" value="{{(string)\Illuminate\Support\Str::uuid()}}"><input type="hidden" name="id" value="{{$p->id}}"><button type="submit" name="action" value="{{$p->active?'deactivate':'activate'}}" class="btn sm alt">{{$p->active?'Deactivate':'Activate'}}</button><button name="action" value="duplicate" class="btn sm alt">Duplicate as draft</button></form>
      <form method="post" enctype="multipart/form-data" action="/seller/products/{{$p->id}}/variations" class="inline-form" style="margin-top:6px">@csrf<input type="hidden" name="_request_key" value="{{(string)\Illuminate\Support\Str::uuid()}}">
       <input name="name" placeholder="Variation name" required><input name="sku" placeholder="Unique SKU" required><input name="options" placeholder='{"Size":"M"}' required><input type="hidden" name="product_id" value="{{$p->id}}">
       <input name="price_adjustment" type="number" step="0.01" value="0" required>
       <input name="stock" type="number" min="0" placeholder="Stock" required>
       <button class="btn sm alt">Add variation</button>
      </form>
     </td>
     <td><span class="badge {{$p->approved?'green':'orange'}}">{{$p->approved?'Approved':'Awaiting review'}}</span></td>
    </tr>
    @empty
    <tr><td colspan="6" class="empty">No products yet.</td></tr>
    @endforelse
    </tbody>
   </table>
  </div>
  {{$products->links('components.pagination')}}
 </div>

 <div>
  <div class="form-card">
   <h3>Add product</h3>
   <form method="post" enctype="multipart/form-data" action="/seller/products">@csrf<input type="hidden" name="_request_key" value="{{(string)\Illuminate\Support\Str::uuid()}}"><label>Weight (kg)<input name="weight" type="number" step="0.001" min="0.001" value="1" required></label><label>Images (1–9)<input type="file" name="images[]" accept=".jpg,.jpeg,.png" multiple required></label>
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
  </div>
  <div class="form-card">
<h3>Promotions</h3><p><a href="/tools/promotions">Create vouchers, scheduled sales and flash sales</a></p>
  </div>
 </div>
</div>
<section class="form-card"><h3>Manage existing variants</h3>
@php $form=['label'=>'Update variant / add variant image','action'=>'/tools/variant','fields'=>['id?','product_id:number','name','sku','options','price_adjustment:number','stock:number','image:file?']]; @endphp @include('tool-form')
@php $form=['label'=>'Remove variant','action'=>'/tools/variant/delete','fields'=>['id:number']]; @endphp @include('tool-form')
</section>
<script>
async function refreshStock(){
 if(document.hidden)return;
 try{const response=await fetch('/seller/stock',{headers:{Accept:'application/json'},cache:'no-store'});if(!response.ok)return;
 for(const product of await response.json()){
  const input=document.querySelector(`[data-stock-product="${product.id}"]`);if(!input)continue;
  const original=input.form.querySelector('[name="original_stock"]');
  document.querySelector(`[data-stock-label="${product.id}"]`).textContent=product.stock+' available';
  if(document.activeElement!==input&&input.value===original.value){input.value=product.stock;original.value=product.stock;}
 }
 }catch(error){/* Next refresh retries after a temporary network failure. */}
}
setInterval(refreshStock,15000);window.addEventListener('focus',refreshStock);
</script>
@endsection
