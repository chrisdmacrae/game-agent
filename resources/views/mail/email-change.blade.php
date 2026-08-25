<x-mail::message>
# Confirm your new email address

Someone moved a {{ config('app.name') }} account to this address.

<x-mail::button :url="$url">
Confirm this address
</x-mail::button>

This link confirms the change once and expires in 15 minutes. The old address keeps signing you in until you use it. If you didn't request it, ignore this email.

<x-slot:subcopy>
If the button does not work, paste this into your browser: <span class="break-all">[{{ $url }}]({{ $url }})</span>
</x-slot:subcopy>
</x-mail::message>
