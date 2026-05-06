<x-mail::message>
# System Notification

Hello Instructor,

{{ $data['message'] }}

<x-mail::button :url="config('app.frontend_url') . '/instructor-login'">
Login to Dashboard
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
