<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')" />
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer: what this email is and why it arrived. No copyright line, no
     marketing, no unsubscribe link -- these are transactional only. --}}
<x-slot:footer>
<x-mail::footer>
Sent by {{ config('app.name') }} because this address was entered on the site.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
