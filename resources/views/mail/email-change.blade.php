<x-mail::message>
# Confirm your new email

Someone asked to move a {{ config('app.name') }} account to this address. Confirm it below. This link can only be used once and expires in 15 minutes.

<x-mail::button :url="$url">
Confirm this address
</x-mail::button>

Until you confirm, the old address keeps working. If you didn't request this, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
