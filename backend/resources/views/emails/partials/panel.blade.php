{{--
    A soft box with a label: tone 'green' (default), 'rose' for bad news,
    'amber' for a warning, 'plain' for details. Needs $label; the text is $body
    (escaped, line breaks kept) or $html (already safe).
--}}
@php
    $tones = [
        'green' => ['#f1f8f3', '#4DB173', '#2f8f5b'],
        'rose'  => ['#fdf3f2', '#d9786c', '#a8443a'],
        'amber' => ['#fdf8ec', '#e0b04c', '#8a6417'],
        'plain' => ['#f6f8f7', '#cfd6d2', '#5b6472'],
    ];
    [$bg, $edge, $ink] = $tones[$tone ?? 'green'] ?? $tones['green'];
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:22px 0;">
    <tr>
        <td style="background:{{ $bg }};border-left:4px solid {{ $edge }};border-radius:12px;padding:16px 20px;font-family:'Poppins','Segoe UI',Helvetica,Arial,sans-serif;">
            <p style="margin:0 0 6px;font-size:11.5px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:{{ $ink }};">{{ $label }}</p>
            <div style="margin:0;font-size:14.5px;line-height:1.7;color:#3b4250;">
                @isset($html){!! $html !!}@else{!! nl2br(e($body ?? '')) !!}@endisset
            </div>
        </td>
    </tr>
</table>
