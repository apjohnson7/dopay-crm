{{-- Both marks print on every document: DoPay and A4S. The A4S logo is public/img/a4s-logo.png; replace that file to change it.
     Table markup so DomPDF renders it the same as the screen. Pass $pdf when rendering a PDF, and $size (px) to change the height. --}}
@php $sz = $size ?? 48; $a4s = public_path('img/a4s-logo.png'); @endphp
<table style="border-collapse:collapse"><tr>
  <td style="vertical-align:middle;padding:0 8px 0 0"><img src="{{ isset($pdf) ? public_path('img/dopay-logo.png') : asset('img/dopay-logo.png') }}" style="width:{{ $sz }}px" alt="DoPay logo"></td>
  <td style="vertical-align:middle;padding:0 8px;border-left:1px solid #D5DDD9">
    @if(file_exists($a4s))<img src="{{ isset($pdf) ? $a4s : asset('img/a4s-logo.png') }}" style="height:{{ round($sz * 0.8) }}px" alt="A4S logo">
    @else<span style="display:inline-block;border:1.5px solid #1B2320;border-radius:6px;padding:{{ round($sz / 4) }}px {{ round($sz / 5) }}px;font-weight:700;font-size:{{ round($sz / 3.2) }}px;letter-spacing:.04em;color:#1B2320">A4S</span>@endif
  </td>
</tr></table>
