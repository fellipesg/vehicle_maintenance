<x-mail::message>
@if($error === null)
# Importação concluída

O arquivo **{{ $filename }}** foi importado.

<x-mail::table>
| Resultado | Oficinas |
| :---- | ----: |
| Novas na lista | {{ $counts['created'] }} |
| Já importadas ou e-mail repetido | {{ $counts['duplicates'] }} |
| Na lista de descadastro | {{ $counts['suppressed'] }} |
| Já são clientes | {{ $counts['customers'] }} |
| CNPJ ou e-mail inválido | {{ $counts['invalid'] }} |
</x-mail::table>

As novas entram como pendentes. Nada é enviado enquanto a prospecção estiver desligada.
@else
# Importação não concluída

O arquivo **{{ $filename }}** não foi importado.

**Motivo:** {{ $error }}

Corrija o arquivo e envie de novo. Reenviar é seguro: o que já entrou não é duplicado.
@endif

<x-mail::button :url="$indexUrl">
Abrir a prospecção
</x-mail::button>

RevisaLog
</x-mail::message>
