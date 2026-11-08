@php
$galleryPhotos=json_decode($galleryProduct->images??'[]',true);
$galleryPhotos=is_array($galleryPhotos)?array_values(array_filter($galleryPhotos,fn($photo)=>is_string($photo)&&trim($photo)!=='')):[];
@endphp
<div class="compact-gallery" data-compact-gallery>
@if(count($galleryPhotos))
<img class="compact-main" src="{{\App\Services\Support::productImageUrl($galleryPhotos[0])}}" alt="{{$galleryProduct->name}}" loading="lazy" decoding="async">
<div class="compact-thumbnails" aria-label="Product photos">
@foreach($galleryPhotos as $photo)<button type="button" aria-label="View photo {{$loop->iteration}}" aria-pressed="{{$loop->first?'true':'false'}}" data-photo="{{\App\Services\Support::productImageUrl($photo)}}"><img src="{{\App\Services\Support::productImageUrl($photo)}}" alt="Photo {{$loop->iteration}}" loading="lazy" decoding="async"></button>@endforeach
</div>
@else<div class="media-empty">No product photo</div>@endif
</div>
