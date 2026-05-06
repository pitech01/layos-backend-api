<x-mail::message>
# Welcome to Layos Group, {{ $user->name }}!

We are excited to have you on board. Your registration for **{{ $courseName }}** has been received successfully.

### Your Login Credentials:
**Email:** {{ $user->email }}
**Password:** {{ $password }}

*Please change your password after your first login for security reasons.*

<x-mail::button :url="config('app.frontend_url') . '/login'">
Access Student Dashboard
</x-mail::button>

If you have any questions, please don't hesitate to reach out to our support team.

Best regards,<br>
The {{ config('app.name') }} Team
</x-mail::message>
