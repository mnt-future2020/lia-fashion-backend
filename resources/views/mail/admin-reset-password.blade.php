@component('mail::message')
# Admin Password Reset

You are receiving this email because we received a password reset request for your admin account.

@component('mail::button', ['url' => $url])
Reset Password
@endcomponent

If you didn't request a password reset, no further action is required.

If you're having trouble clicking the "Reset Password" button, copy and paste the URL below into your web browser:
{{ $url }}

Thanks,<br>
{{ config('app.name') }}
@endcomponent
