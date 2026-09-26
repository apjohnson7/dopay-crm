@extends('layouts.app')
@section('title', 'Search')
@section('content')
<div class="ph"><div><h1>Search</h1><p class="sub">Results for “{{ $q }}”. Every word must match.</p></div></div>
@forelse($results as $group => $rows)
  <section class="card" style="margin-bottom:12px"><div class="card-h"><h3>{{ $group }}</h3><span class="small muted">{{ count($rows) }}</span></div>
    <div class="feed">@foreach($rows as $r)<a class="feed-i" href="{{ $r['url'] }}" style="text-decoration:none;color:inherit"><div class="t"><b>{{ $r['title'] }}</b><span>{{ $r['sub'] }}</span></div></a>@endforeach</div></section>
@empty <div class="card empty">Nothing matches “{{ $q }}”.</div> @endforelse
@endsection
