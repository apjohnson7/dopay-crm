@extends('layouts.app')
@section('title', 'Team messages')
@section('content')
@php $me = auth()->user(); $myTz = $me->country()?->timezone ?? 'UTC'; $myLbl = $me->country()?->timezone_label ?? 'UTC'; @endphp
<div class="ph"><div><h1>Team messages</h1><p class="sub">Message colleagues in any branch or country. Everyone’s role, branch and local time are shown.</p></div>
  <div class="ph-act"><button class="btn pri" data-open-modal="newMsg">New message</button></div></div>
<div class="chat {{ $current ? 'has-thread' : '' }}">
  <aside class="card chat-list"><div class="chat-items">
    @forelse($conversations as $c)
      @php $last = $c->messages->last(); $un = $c->unreadFor($me); $other = $c->otherUser($me); @endphp
      <a class="ci {{ $current?->id === $c->id ? 'on' : '' }} {{ $un ? 'unread' : '' }}" href="{{ route('messages.index', ['c' => $c->id]) }}" style="text-decoration:none">
        <span class="cav {{ $c->type }}">{{ $other ? $other->initials() : '#' }}</span>
        <span class="cm"><b>{{ $c->titleFor($me) }}</b><span>{{ $last ? ($last->user_id === $me->id ? 'You: ' : '').\Illuminate\Support\Str::limit($last->body, 60) : 'No messages yet' }}</span></span>
        <span class="cr"><span>{{ $last?->created_at->setTimezone($myTz)->format($last?->created_at->isToday() ? 'H:i' : 'j M') }}</span>@if($un)<i>{{ $un }}</i>@endif</span></a>
    @empty <div class="empty">No conversations yet. Start one with New message.</div> @endforelse
  </div></aside>
  <section class="card chat-main">
    @if($current)
      @php $other = $current->otherUser($me); @endphp
      <div class="chat-h"><a class="btn ghost sm chat-back" href="{{ route('messages.index') }}">←</a><span class="cav {{ $current->type }}">{{ $other ? $other->initials() : '#' }}</span>
        <div style="min-width:0;flex:1"><b>{{ $current->titleFor($me) }}</b><div class="small muted">
          @if($other){{ $other->roleName() }} · {{ $other->branch?->name }}, {{ $other->country()?->name }} · their time {{ $other->country()?->localTime() }}
          @else {{ $current->users->count() }} members · {{ $current->users->map(fn ($u) => $u->country()?->name)->filter()->unique()->implode(', ') }} @endif</div></div></div>
      <div class="chat-body" id="chatBody">
        @php $lastDay = null; @endphp
        @foreach($current->messages as $m)
          @php $day = $m->created_at->setTimezone($myTz)->toDateString(); $mine = $m->user_id === $me->id; $theirs = $m->user->country(); @endphp
          @if($day !== $lastDay)<div class="day">{{ $m->created_at->setTimezone($myTz)->isToday() ? 'Today' : $m->created_at->setTimezone($myTz)->format('j M Y') }}</div>@php $lastDay = $day; @endphp @endif
          <div class="bm {{ $mine ? 'me' : '' }}">
            @if(!$mine && !$other)<span class="who"><b>{{ $m->user->name }}</b> · {{ $m->user->roleName() }} · {{ $theirs?->name }}</span>@endif
            <div class="bb">{!! preg_replace('/@([\p{Lu}][\w\'-]+(?: [\p{Lu}][\w\'-]+)?)/u', '<span class="mention">@$1</span>', e($m->body)) !!}
              @if($m->attachable)<div><a class="refchip" href="{{ $m->attachable instanceof \App\Models\FinanceForm ? route('forms.show', $m->attachable) : route('invoices.show', $m->attachable) }}">📄 <span class="mono">{{ $m->attachable->reference ?? $m->attachable->number }}</span></a></div>@endif</div>
            <span class="t">{{ $m->created_at->setTimezone($myTz)->format('H:i') }} {{ $myLbl }}@if(!$mine && $theirs && $theirs->timezone !== $myTz) · {{ $theirs->localTime($m->created_at) }} for them@endif</span>
          </div>
        @endforeach
        <span id="end"></span>
      </div>
      <form class="composer" method="post" action="{{ route('messages.send', $current) }}">@csrf
        <textarea class="inp" id="msgText" name="body" placeholder="Message {{ $current->titleFor($me) }}… (Enter to send, Shift+Enter for a new line, @Name to mention)"></textarea>
        <div class="row" style="justify-content:space-between"><select class="sel" name="attach" style="max-width:320px" aria-label="Attach a record"><option value="">Attach a record…</option>
          <optgroup label="Finance forms">@foreach($attachables['forms'] as $f)<option value="form:{{ $f->id }}" @selected(request('attach') === 'form:'.$f->id)>{{ $f->reference }}</option>@endforeach</optgroup>
          <optgroup label="Invoices">@foreach($attachables['invoices'] as $i)<option value="invoice:{{ $i->id }}">{{ $i->number }}</option>@endforeach</optgroup></select>
          <button class="btn pri">Send</button></div>
      </form>
    @else
      <div class="empty" style="margin:auto">Pick a conversation, or start a new one with anyone in any branch or country.</div>
    @endif
  </section>
</div>

<dialog class="modal-d" id="newMsg" @if(request('to') || (request('attach') && !$current)) open @endif><form method="post" action="{{ route('messages.store') }}">@csrf
  <div class="modal-h"><h2>New message</h2><button class="icon-btn" data-close-modal>✕</button></div>
  <div class="modal-b">
    <div class="f"><label for="peopleQ">Find people</label><input id="peopleQ" class="inp" placeholder="Name, role or country"></div>
    <div id="peopleList" class="ppl">
      @foreach($people as $country => $group)
        <div class="lbl" style="margin:10px 0 4px">{{ $country }}</div>
        @foreach($group as $p)<label class="pp"><input type="checkbox" name="user_ids[]" value="{{ $p->id }}" @checked((int) request('to') === $p->id)><span class="cav">{{ $p->initials() }}</span><span><b>{{ $p->name }}</b><span class="small muted">{{ $p->roleName() }} · {{ $p->branch?->name }} · {{ $p->country()?->localTime() }}</span></span></label>@endforeach
      @endforeach
    </div>
    <div class="f" style="margin-top:12px"><label for="gname">Group name (only for several people)</label><input id="gname" name="name"></div>
    <div class="f" style="margin-top:10px"><label for="nbody">Message</label><textarea id="nbody" name="body" required>{{ request('attach') ? 'Please see this record.' : '' }}</textarea></div>
    <input type="hidden" name="attach" value="{{ request('attach') }}">
  </div>
  <div class="modal-f"><button class="btn" data-close-modal>Cancel</button><button class="btn pri">Send</button></div></form></dialog>
@endsection
