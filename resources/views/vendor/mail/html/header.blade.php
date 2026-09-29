@props(['url'])
{{-- Faixa escura (#0b1c2c, a cor de fundo do lockup) com o logo em 182×44, a proporção do arquivo
     (447×108): o Outlook respeita width/height e achataria o logo com outra razão. --}}
<tr>
<td class="header" bgcolor="#0b1c2c" align="center">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ \App\Support\AppStorage::brandUrl('lockup-horizontal.png') }}" alt="RevisaLog" width="182" height="44" style="height: 44px; width: 182px; max-width: 182px; border: 0;">
</a>
</td>
</tr>
