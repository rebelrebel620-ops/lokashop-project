@php
$galleryPhotos=json_decode($galleryProduct->images??'[]',true);
$galleryPhotos=is_array($galleryPhotos)?array_values(array_filter($galleryPhotos,fn($photo)=>is_string($photo)&&trim($photo)!=='')):[];
@endphp
<div class="compact-gallery" data-compact-gallery>
@if(count($galleryPhotos))
<img class="compact-main" src="{{\App\Services\Support::imageUrl($galleryPhotos[0])}}" alt="{{$galleryProduct->name}}" loading="lazy">
<div class="compact-thumbnails" aria-label="Product photos">
@foreach($galleryPhotos as $photo)<button type="button" aria-label="View photo {{$loop->iteration}}" aria-pressed="{{$loop->first?'true':'false'}}" data-photo="{{\App\Services\Support::imageUrl($photo)}}"><img src="{{\App\Services\Support::imageUrl($photo)}}" alt="Photo {{$loop->iteration}}" loading="lazy"></button>@endforeach
</div>
@else<div class="media-empty">No product photo</div>@endif
</div>
