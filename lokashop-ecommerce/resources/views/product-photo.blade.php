@php $photos=json_decode($photoProduct->images??'[]',true)?:[];$photo=$photos[0]??null; @endphp
@if($photo)<img src="{{\App\Services\Support::imageUrl($photo)}}" alt="{{$photoProduct->name}}" loading="lazy" style="width:{{$photoSize??180}}px;max-width:100%;height:{{$photoSize??180}}px;object-fit:contain;border-radius:10px;background:#f5f5f5">
@else<div style="padding:20px;background:#f5f5f5;color:#667085">No photo</div>@endif
