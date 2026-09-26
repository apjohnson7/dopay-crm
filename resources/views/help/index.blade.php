@extends('layouts.app')
@section('title', 'Help & guide')
@section('content')
<div class="ph"><div><h1>Help & guide</h1><p class="sub">How to do things in Dopay and where to find them. Try “How do I fill an expense memo?”</p></div></div>
<form class="row" style="margin-bottom:16px"><input class="inp" name="q" value="{{ $q }}" style="max-width:480px" placeholder="Ask how to do something, or where to find it"><button class="btn pri">Ask</button>@if($q)<a class="btn ghost" href="{{ route('help') }}">Show all topics</a>@endif</form>
<div class="grid g-2">
  @forelse($topics as $t)
    <section class="card help-topic"><h3>{{ $t['title'] }}</h3><div class="small" style="line-height:1.6">{!! $t['answer'] !!}</div>@if(!empty($t['where']))<div class="where">Where: {{ $t['where'] }}</div>@endif
      @if(!empty($t['route']) && \Illuminate\Support\Facades\Route::has($t['route']))<a class="btn sm" style="margin-top:10px" href="{{ route($t['route'], $t['params'] ?? []) }}">Take me there</a>@endif</section>
  @empty <div class="card empty">No topic matches. Try other words, or message the Financial Controller from Team messages.</div> @endforelse
</div>
@endsection
