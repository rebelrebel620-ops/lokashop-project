@php
 $overview=\App\Services\OverviewCharts::data();$palette=['#146b43','#65a46f','#b9dc91','#d5ad58','#608e88','#a6bcb1'];
 $total=array_sum(array_column($overview['segments'],'value'));$peak=max(1,max(array_column($overview['days'],'value')));$week=array_sum(array_column($overview['days'],'value'));
 $points=collect($overview['days'])->map(fn($day,$i)=>(48+$i*66).','.round(170-$day['value']/$peak*125,2))->implode(' ');$offset=0;
@endphp
<link rel="stylesheet" href="{{asset('css/overview-charts.css')}}">
<section class="loka-insights" aria-label="Overview charts">
 <article class="loka-chart-card">
  <header><div><span class="loka-chart-eyebrow">ACTIVITY · LAST 7 DAYS</span><h2>{{$overview['title']}}</h2><p>New {{$overview['noun']}} created each day</p></div><div class="loka-chart-number">{{number_format($week)}}<small>this week</small></div></header>
  <svg class="loka-trend" viewBox="0 0 490 220" role="img" aria-label="{{$overview['title']}} over the last seven days; daily counts listed below">
   @foreach([0,1,2] as $tick)<line x1="48" x2="444" y1="{{170-$tick*62.5}}" y2="{{170-$tick*62.5}}" stroke="#e4ede6"/><text x="36" y="{{174-$tick*62.5}}" text-anchor="end">{{round($peak*$tick/2,1)}}</text>@endforeach
   <polygon points="48,170 {{$points}} 444,170" fill="#eaf4e9"/>
   <polyline points="{{$points}}" fill="none" stroke="#146b43" stroke-width="3" stroke-linejoin="round"/>
   @foreach($overview['days'] as $i=>$day)<circle cx="{{48+$i*66}}" cy="{{170-$day['value']/$peak*125}}" r="4" fill="#146b43" tabindex="0"><title>{{$day['label']}}: {{$day['value']}} {{$overview['noun']}}</title></circle><text x="{{48+$i*66}}" y="202" text-anchor="middle">{{$day['label']}}</text>@endforeach
  </svg>
  <details class="loka-chart-data"><summary>View daily counts</summary><dl>@foreach($overview['days'] as $day)<div><dt>{{$day['label']}}</dt><dd>{{$day['value']}}</dd></div>@endforeach</dl></details>
 </article>
 <article class="loka-chart-card">
  <header><div><span class="loka-chart-eyebrow">STATUS BREAKDOWN</span><h2>{{ucfirst($overview['noun'])}} at a glance</h2><p>Current records · @if(request()->filled('from')||request()->filled('to')) selected date range @else all time @endif</p></div></header>
  <div class="loka-donut-layout">
   <div class="loka-donut"><svg viewBox="0 0 160 160" role="img" aria-label="Status distribution, {{$total}} total {{$overview['noun']}}. Values listed alongside."><circle cx="80" cy="80" r="60" fill="none" stroke="#edf2ec" stroke-width="19"/>
   @foreach($overview['segments'] as $i=>$segment)
    @php $portion=$total>0?$segment['value']/$total*100:0; @endphp
    @if($portion>0)<circle cx="80" cy="80" r="60" fill="none" stroke="{{$palette[$i%6]}}" stroke-width="19" pathLength="100" stroke-dasharray="{{$portion}} {{100-$portion}}" stroke-dashoffset="{{-$offset}}" transform="rotate(-90 80 80)"><title>{{$segment['label']}}: {{$segment['value']}}</title></circle>@endif
    @php $offset+=$portion; @endphp
   @endforeach</svg><div><strong>{{number_format($total)}}</strong><small>total {{$overview['noun']}}</small></div></div>
   <ul class="loka-chart-legend">@foreach($overview['segments'] as $i=>$segment)<li><span class="loka-legend-dot" style="background:{{$palette[$i%6]}}"></span><span>{{$segment['label']}}</span><strong>{{number_format($segment['value'])}}</strong><small>{{$total?round($segment['value']/$total*100):0}}%</small></li>@endforeach</ul>
  </div>
  @if(!$total)<p class="loka-chart-empty">No {{$overview['noun']}} yet. Your chart will fill as activity is recorded.</p>@endif
 </article>
</section>
