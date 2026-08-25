@props(['url'])
{{-- There is no logo. The brand is the type-only wordmark, set as text so it
     survives image blocking. The slot is ignored on purpose: the wordmark is
     fixed lettering, not the configured app name. --}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">BUILD<span class="wordmark-sep">/</span>YOUR<span class="wordmark-sep">/</span>BUILD</a>
</td>
</tr>
