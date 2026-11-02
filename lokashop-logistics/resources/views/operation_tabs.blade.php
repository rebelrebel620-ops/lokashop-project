<nav class="operation-tabs" aria-label="{{ $tabTitle }} stages">
@foreach($tabOptions as $key=>$label)<a class="{{request('tab',array_key_first($tabOptions))===$key?'active':''}}" href="{{request()->fullUrlWithQuery(['tab'=>$key,'page'=>1,'trips_page'=>1,'legacy_page'=>1])}}">{{$label}}</a>@endforeach
</nav>