<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Savino Del Bene Volley')</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f0f0f0; font-family: 'Montserrat', Arial, sans-serif; -webkit-font-smoothing: antialiased;">

    {{-- Wrapper --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f0f0f0;">
        <tr>
            <td align="center" style="padding: 20px 10px;">

                {{-- Main container --}}
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">

                    {{-- Header --}}
                    <tr>
                        <td align="center" style="background-color: #003063; padding: 28px 20px;">
                            <img
                                src="{{ config('app.url') }}/images/logo.png"
                                alt="Savino Del Bene Volley"
                                style="max-height: 60px; width: auto; display: block; margin: 0 auto 12px;"
                            >
                            <p style="color: #ffffff; font-family: 'Montserrat', Arial, sans-serif; font-size: 18px; font-weight: 700; margin: 0; letter-spacing: 0.5px;">
                                Savino Del Bene Volley
                            </p>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="background-color: #ffffff; padding: 32px 28px; color: #1a1a1a; font-family: 'Montserrat', Arial, sans-serif; font-size: 15px; line-height: 1.6;">
                            @yield('content')
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background-color: #f5f5f5; padding: 24px 28px; font-family: 'Montserrat', Arial, sans-serif;">
                            @yield('footer_extra')

                            <p style="color: #888888; font-size: 13px; margin: 0 0 8px; text-align: center; line-height: 1.5;">
                                <strong>Savino Del Bene Volley</strong> | Firenze<br>
                                @if(\App\Models\SiteSetting::get('general.contact_email'))
                                    <a href="mailto:{{ \App\Models\SiteSetting::get('general.contact_email') }}" style="color: #003063; text-decoration: none;">
                                        {{ \App\Models\SiteSetting::get('general.contact_email') }}
                                    </a><br>
                                @endif
                                &copy; {{ date('Y') }} Savino Del Bene Volley
                            </p>

                            <p style="color: #aaaaaa; font-size: 11px; margin: 16px 0 0; text-align: center; border-top: 1px solid #e0e0e0; padding-top: 16px; line-height: 1.4;">
                                Questa email è stata generata automaticamente. Non rispondere a questo indirizzo.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>
