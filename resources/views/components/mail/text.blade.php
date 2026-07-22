@props(['muted' => false, 'size' => '15.5px'])
<p style="margin:0 0 16px; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:{{ $size }}; line-height:1.65; color:{{ $muted ? '#8a8f9c' : '#4a4f5e' }};">{{ $slot }}</p>
