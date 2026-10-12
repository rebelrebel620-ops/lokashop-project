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
     <td><b>#{{$p->id}} {{$p->name}}</b></td>
     <td colspan="4">
      <form method="post" action="/seller/products/{{$p->id}}" class="inline-form">@csrf
       <input name="name" value="{{$p->name}}" required>
       <input name="price" type="number" step="0.01" value="{{$p->price}}" required>
       <input name="stock" type="number" value="{{$p->stock}}" min="0" required>
       <input name="discount_percent" type="number" step="0.01" value="{{$p->discount_percent}}" required>
       <button class="btn sm">Update</button>
      </form>
      <form method="post" action="/seller/products/{{$p->id}}/variations" class="inline-form" style="margin-top:6px">@csrf
       <input name="name" placeholder="Variation name" required>
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
  {{$products->links()}}
 </div>

 <div>
  <div class="form-card">
   <h3>Add product</h3>
   <form method="post" action="/seller/products">@csrf
    <label>Product name<input name="name" placeholder="e.g. Reusable shopping bag" value="{{old('name')}}" required></label>
    <label>Product description<textarea name="description" placeholder="Describe the material, size, color, and what is included.">{{old('description')}}</textarea></label>
    <label>Product category<select name="category_id" required><option value="">Choose a product category</option>@foreach($categories as $c)<option value="{{$c->id}}" @selected((string)old('category_id',$defaultCategoryId)===(string)$c->id)>{{$c->name}}</option>@endforeach</select></label>
    <p><small>@if($defaultCategoryId)Your shop category, {{$shopCategory}}, is selected by default. Change it if this product belongs to another category.@else Choose the category that best describes this product.@endif</small></p>
    <label>Price (₱)<input name="price" type="number" step="0.01" min="0.01" placeholder="e.g. 199.00" value="{{old('price')}}" required></label>
    <label>Discount (%)<input name="discount_percent" type="number" step="0.01" min="0" max="100" value="{{old('discount_percent',0)}}" required></label>
    <p><small>Enter 0 for no discount. For example, 10 gives buyers 10% off the listed price.</small></p>
    <label>Stock quantity (units)<input name="stock" type="number" min="0" step="1" placeholder="e.g. 25" value="{{old('stock')}}" required></label>
    <p><small>Enter the number of units available to sell. Use 0 if the product is out of stock.</small></p>
    <button class="btn" style="width:100%">Submit for admin review</button>
   </form>
  </div>
  <div class="form-card">
   <h3>Create voucher</h3>
   <form method="post" action="/seller/vouchers">@csrf
    <input name="code" placeholder="Voucher code" required>
    <input name="discount_percent" type="number" min="0" max="100" step="0.01" placeholder="Discount %" required>
    <label>Expires<input name="expires_at" type="datetime-local"></label>
    <button class="btn lime" style="width:100%">Create voucher</button>
   </form>
  </div>
 </div>
</div>
@endsection
