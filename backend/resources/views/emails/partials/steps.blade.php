{{-- Numbered steps in green circles. Needs $steps: [[title, detail], ...] --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:6px 0 4px;">
    @foreach ($steps as $index => [$title, $detail])
        <tr>
            <td width="40" valign="top" style="padding:0 0 18px;">
                <div style="width:28px;height:28px;border-radius:50%;background:#e6f4eb;color:#2f8f5b;font-family:'Poppins','Segoe UI',Helvetica,Arial,sans-serif;font-size:13px;font-weight:600;line-height:28px;text-align:center;">{{ $index + 1 }}</div>
            </td>
            <td valign="top" style="padding:3px 0 18px;font-family:'Poppins','Segoe UI',Helvetica,Arial,sans-serif;">
                <p style="margin:0;font-size:14.5px;font-weight:600;color:#363F4E;">{{ $title }}</p>
                <p style="margin:2px 0 0;font-size:13.5px;line-height:1.6;color:#6b7380;">{{ $detail }}</p>
            </td>
        </tr>
    @endforeach
</table>
