{{-- Second-person authorization for sensitive actions. Include inside a <form>. --}}
<div class="fg">
  <div class="f full"><label for="reason-{{ $id }}">Reason</label><textarea id="reason-{{ $id }}" name="reason" @if($required ?? true) required @endif minlength="5" placeholder="e.g. Customer returned 5 units; issuing a corrected invoice"></textarea></div>
  <div class="f"><label for="auth-{{ $id }}">Authorized by</label><select id="auth-{{ $id }}" name="authorizer_id" @if($required ?? true) required @endif>@if(!($required ?? true))<option value="">Choose…</option>@endif@foreach($authorizers as $a)<option value="{{ $a->id }}">{{ $a->name }} · {{ $a->roleName() }}</option>@endforeach</select></div>
  <div class="f"><label for="pin-{{ $id }}">Authorizer PIN</label><input id="pin-{{ $id }}" name="authorizer_pin" type="password" inputmode="numeric" @if($required ?? true) required @endif></div>
</div>
