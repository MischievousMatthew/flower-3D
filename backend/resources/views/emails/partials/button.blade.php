{{-- A rounded navy button. Needs $url and $label. --}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" class="bc-btn" style="margin:{{ $margin ?? '26px 0 8px' }};">
    <tr>
        <td style="border-radius:999px;background:#363F4E;">
            <a href="{{ $url }}" target="_blank" style="display:inline-block;padding:14px 30px;border-radius:999px;background:#363F4E;font-family:'Poppins','Segoe UI',Helvetica,Arial,sans-serif;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;">
                {{ $label }} &rarr;
            </a>
        </td>
    </tr>
</table>
