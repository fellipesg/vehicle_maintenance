@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; color: #111827; font-size: 19px; font-weight: 700; text-decoration: none;">
{{ $slot }}
</a>
</td>
</tr>
