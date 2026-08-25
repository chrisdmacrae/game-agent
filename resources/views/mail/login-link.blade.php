<x-mail::message>
# Sign in to {{ config('app.name') }}

Click the button below to sign in. This link can only be used once and expires in 15 minutes.

<x-mail::button :url="$url">
Sign in
</x-mail::button>

If you didn't request this link, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
