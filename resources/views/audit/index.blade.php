@extends('layouts.app')
@section('title', 'Audit trail')
@section('content')
<div class="ph"><div><h1>Audit trail</h1><p class="sub">Financial records are never deleted. Every change is recorded with who, when, from where and what changed.</p></div></div>
<form class="row" style="margin-bottom:12px"><input class="inp" name="q" value="{{ $q }}" style="max-width:320px" placeholder="Record number or action"><select class="sel" name="user"><option value="">All users</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected(request('user') == $u->id)>{{ $u->name }}</option>@endforeach</select><button class="btn">Filter</button></form>
<section class="card"><div class="tw"><table class="tbl cards"><thead><tr><th>When (UTC)</th><th>User</th><th>Action</th><th>Before</th><th>After</th><th>IP / device</th></tr></thead><tbody>
@forelse($logs as $l)
  <tr><td data-label="When">{{ $l->created_at->format('j M Y, H:i') }}</td><td data-label="User">{{ $l->user?->name ?? 'System' }}</td><td class="lead" data-label="Action"><b>{{ $l->action }}</b><span class="sub2 mono">{{ $l->record_label }}</span></td>
    <td data-label="Before"><span class="small">{{ $l->before ? \Illuminate\Support\Str::limit(json_encode($l->before), 120) : '—' }}</span></td><td data-label="After"><span class="small">{{ $l->after ? \Illuminate\Support\Str::limit(json_encode($l->after), 120) : '—' }}</span></td>
    <td data-label="IP"><span class="small muted">{{ $l->ip_address }} · {{ \Illuminate\Support\Str::limit($l->user_agent, 40) }}</span></td></tr>
@empty <tr><td colspan="6" class="empty">No entries match.</td></tr> @endforelse
</tbody></table></div>{{ $logs->links() }}</section>
@endsection
