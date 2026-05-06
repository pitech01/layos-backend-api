<x-mail::message>
# Password Reset Request

Hello,

You are receiving this email because we received a password reset request for your account. Please use the 6-digit verification code below to proceed with resetting your password:

<x-mail::panel>
<h1 style="text-align: center; letter-spacing: 5px; color: #1e293b; margin: 0; font-size: 32px;">{{ $code }}</h1>
</x-mail::panel>

**This code will expire in 15 minutes.**

If you did not request a password reset, no further action is required. For your security, please do not share this code with anyone.

Best regards,<br>
The {{ config('app.name') }} Team
</x-mail::message>
