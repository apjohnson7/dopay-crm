@if(session('status'))<div class="alert-flash" role="status">{{ session('status') }}</div>@endif
@if($errors->any())
  <div class="alert-err" role="alert">Please fix the following:<ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif
