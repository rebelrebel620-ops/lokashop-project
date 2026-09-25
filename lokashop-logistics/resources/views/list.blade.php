@extends('dashboard')
@section('title', $title)
@section('panel')
<div class="page-head"><div><h1>{{ $title }}</h1></div></div>

<div class="panel-card">
  <div class="p-body flush table-wrap">
    <table class="data">
      @if($records->isNotEmpty())
        <thead>
          <tr>
            @foreach($records->first() as $key=>$value)
              @if(is_scalar($value) && !in_array($key,['password','remember_token']))<th>{{ $key }}</th>@endif
            @endforeach
            @if(isset($actions))<th></th>@endif
          </tr>
        </thead>
      @endif
      <tbody>
      @forelse($records as $record)
        <tr>
          @foreach($record as $key=>$value)
            @if(is_scalar($value) && !in_array($key,['password','remember_token']))<td>{{ $value }}</td>@endif
          @endforeach
          @if(isset($actions))
            <td>
              @foreach($actions as $action)
                <form class="inline" method="post" action="{{ $action['url'] }}">@csrf
                  <input type="hidden" name="id" value="{{ $record->id }}">
                  <button class="sm">{{ $action['label'] }}</button>
                </form>
              @endforeach
            </td>
          @endif
        </tr>
      @empty
        <tr><td class="empty">No records yet.</td></tr>
      @endforelse
      </tbody>
    </table>
    {{ $records->links('components.pagination') }}
  </div>
</div>
@endsection
