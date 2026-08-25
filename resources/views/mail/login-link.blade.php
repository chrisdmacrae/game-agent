<x-mail::message>
# Sign in to {{ config('app.name') }}

<x-mail::button :url="$url">
Sign in
</x-mail::button>

This link signs you in once and expires in 15 minutes. If you didn't request it, ignore this email.

<x-slot:subcopy>
If the button does not work, paste this into your browser: <span class="break-all">[{{ $url }}]({{ $url }})</span>
</x-slot:subcopy>
</x-mail::message>
