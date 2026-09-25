@extends('emails.layout')

@section('title', __('emails.newsletter_conferma.subject'))

@section('content')
    <h1 style="color: #003063; font-family: 'Montserrat', Arial, sans-serif; font-size: 24px; font-weight: 700; margin: 0 0 8px; text-align: center;">
        {{ __('emails.newsletter_conferma.heading') }}
    </h1>
    <p style="color: #666666; font-size: 14px; text-align: center; margin: 0 0 28px; line-height: 1.6;">
        {{ __('emails.newsletter_conferma.intro') }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding: 0 0 24px;">
                <a href="{{ $confermaUrl }}"
                   style="display: inline-block; background-color: #003063; color: #ffffff; padding: 14px 32px; text-decoration: none; border-radius: 6px; font-weight: 700; font-size: 14px; letter-spacing: 0.3px;">
                    {{ __('emails.newsletter_conferma.button') }}
                </a>
            </td>
        </tr>
    </table>

    <p style="color: #888888; font-size: 13px; text-align: center; margin: 0; line-height: 1.6;">
        {{ __('emails.newsletter_conferma.ignore') }}
    </p>
@endsection
