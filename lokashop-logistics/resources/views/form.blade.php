@extends('dashboard')
@section('title', $title)
@section('panel')
<div class="form-card" style="max-width:560px">
  <h2>{{ $title }}</h2>
  <form method="post" action="{{ $action }}" enctype="multipart/form-data">
    @csrf
    @foreach($fields as $name => $label)
      <label>{{ is_array($label) ? $name : $label }}</label>
      <input name="{{ $name }}" value="{{ old($name) }}">
    @endforeach
    <button class="block">Save</button>
  </form>
</div>
@endsection
