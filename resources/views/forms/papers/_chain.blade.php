<table class="ft"><thead><tr><th style="width:34%">Step</th><th>Signature</th><th style="width:15%">Date</th><th style="width:21%">{{ $nameLabel ?? 'Name' }}</th></tr></thead><tbody>
@foreach($steps ?? app(\App\Services\ApprovalService::class)->steps($form) as $i => $st)@php $s = $sigs[$i] ?? null; @endphp
<tr><td class="fl">{{ $st['label'] }}</td><td>@include('forms.papers._sig', ['s' => $s])</td><td>{{ $s ? $s->signed_at->format('d/m/Y') : '' }}</td><td>{{ $s?->user->name }}</td></tr>@endforeach
</tbody></table>
