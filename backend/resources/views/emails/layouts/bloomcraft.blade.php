{{--
    The shared look of every BloomCraft email: the logo's navy (#363F4E) and
    green (#4DB173), Cormorant Garamond headings and Poppins text, as on the site.
    Tables and inline styles only, so it holds up in Gmail, Outlook and phones.

    A page sets: title, preheader (the preview line in the inbox), eyebrow,
    heading, and the content section.
--}}
@php
    $site = rtrim(config('app.frontend_url', 'https://bloomcraft-app.vercel.app'), '/');
    $serif = "'Cormorant Garamond', Georgia, 'Times New Roman', serif";
    $sans = "'Poppins', 'Segoe UI', Helvetica, Arial, sans-serif";
@endphp
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>@yield('title', 'BloomCraft')</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; }
        a { color: #2f8f5b; }
        @media only screen and (max-width: 620px) {
            .bc-shell { padding: 16px 10px !important; }
            .bc-pad { padding-left: 24px !important; padding-right: 24px !important; }
            .bc-heading { font-size: 30px !important; }
            .bc-btn a { display: block !important; text-align: center !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background:#eef2ef;">
    {{-- Inbox preview line --}}
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;font-size:1px;line-height:1px;">
        @yield('preheader')&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef2ef;">
        <tr>
            <td class="bc-shell" align="center" style="padding:36px 16px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;">

                    {{-- Header: the logo on navy, a thin green line under it --}}
                    <tr>
                        <td style="background:#363F4E;border-radius:20px 20px 0 0;padding:26px 40px;" class="bc-pad">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="vertical-align:middle;padding-right:10px;">
                                        <img src="{{ $site }}/bloomcraft-darkmode-removebg.png" width="40" height="40" alt="" style="display:block;border:0;width:40px;height:40px;">
                                    </td>
                                    <td style="vertical-align:middle;font-family:{!! $serif !!};font-size:26px;font-weight:600;color:#ffffff;letter-spacing:.01em;">
                                        BloomCraft
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="height:4px;line-height:4px;font-size:0;background:#4DB173;">&nbsp;</td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td class="bc-pad" style="background:#ffffff;padding:40px 40px 36px;border-radius:0 0 20px 20px;font-family:{!! $sans !!};color:#3b4250;">
                            @hasSection('eyebrow')
                                <p style="margin:0 0 10px;font-family:{!! $sans !!};font-size:12px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:@yield('eyebrow_color', '#2f8f5b');">
                                    @yield('eyebrow')
                                </p>
                            @endif
                            <h1 class="bc-heading" style="margin:0 0 18px;font-family:{!! $serif !!};font-size:36px;line-height:1.15;font-weight:600;color:#363F4E;">
                                @yield('heading')
                            </h1>

                            @yield('content')
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td align="center" style="padding:26px 24px 8px;font-family:{!! $sans !!};font-size:12px;line-height:1.7;color:#7a8290;">
                            <span style="font-family:{!! $serif !!};font-size:16px;font-weight:600;color:#363F4E;">BloomCraft</span><br>
                            Flowers, made your way.<br>
                            <a href="{{ $site }}" style="color:#2f8f5b;text-decoration:none;">{{ preg_replace('#^https?://#', '', $site) }}</a>
                            &nbsp;&middot;&nbsp; &copy; {{ date('Y') }} BloomCraft
                        </td>
                    </tr>
                    @hasSection('footnote')
                        <tr>
                            <td align="center" style="padding:4px 24px 0;font-family:{!! $sans !!};font-size:11.5px;line-height:1.6;color:#9aa1ab;">
                                @yield('footnote')
                            </td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
