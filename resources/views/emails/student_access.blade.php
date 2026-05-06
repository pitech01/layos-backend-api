<x-mail::message>
# Hello, {{ $user->name }}

This is an update regarding your access to the cohort: **{{ $cohortName }}**.

@if($status === 'active')
Your access has been **Activated**. You can now log in to the student portal and continue your learning modules.
@else
Your access has been set to **Inactive**.
@if($reason)

**Message from Instructor:**
"{{ $reason }}"
@endif
@endif

<x-mail::button :url="config('app.frontend_url') . '/login'">
Go to Dashboard
</x-mail::button>

If you have any questions regarding this update, please contact your instructor directly.

Best regards,<br>
The {{ config('app.name') }} Team
</x-mail::message>
