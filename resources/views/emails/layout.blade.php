<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('subject')</title>
</head>
<body style="margin:0;padding:0;background:#F6F8FC;font-family:Arial,Helvetica,sans-serif;color:#475467;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F6F8FC;padding:24px 0;">
    <tr><td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#FFFFFF;border:1px solid #EAECF0;border-radius:12px;">
            <tr><td style="padding:20px 24px;border-bottom:3px solid #155EEF;">
                <span style="font-size:20px;font-weight:bold;color:#101828;">{{ config('app.name') }}</span>
            </td></tr>
            <tr><td style="padding:24px;font-size:14px;line-height:1.6;color:#475467;">
                @yield('body')
            </td></tr>
            <tr><td style="padding:16px 24px;background:#F9FAFB;border-top:1px solid #EAECF0;font-size:12px;line-height:1.5;color:#667085;">
                Email otomatis dari {{ config('app.name') }}. Jadwal, harga, dan ketersediaan pada aplikasi ini adalah <strong>data demonstrasi</strong>, bukan informasi maskapai, kereta, atau hotel yang sebenarnya. Mohon tidak membalas email ini.
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>