{{--
    Leitura do CRLV-e (PDF exportado do app Carteira Digital de Trânsito): card com a área de soltar
    (<x-ui.file-input>) e o botão, que fica "Lendo CRLV-e…" durante o envio (resources/js/ui/
    submit-busy.js). Usado no passo Documento do assistente (resources/views/vehicles/entry) e na
    edição do veículo.

    Variáveis:
    - importRoute (obrigatória): URL do POST que lê o documento.
    - inputId: id do campo de arquivo ('crlv').
    - title: título do card ('Importar do CRLV-e'); headingLevel: h2 (o padrão) | h3 | h4.
    - description: frase abaixo do título.
    - recommended: badge "Recomendado" ao lado do título.
    - submitLabel: rótulo do botão ('Ler CRLV-e'); loadingLabel: rótulo durante o envio ('Lendo CRLV-e…').
--}}
@php
    $crlvInputId = $inputId ?? 'crlv';
    $crlvMaxMb = \App\Support\Vehicle\VehicleEntryFlow::DOCUMENT_MAX_KILOBYTES / 1024;
@endphp
<x-ui.card
    as="section"
    :heading-level="$headingLevel ?? 'h2'"
    :description="$description ?? 'Exporte o PDF no app Carteira Digital de Trânsito (CDT) e envie aqui para preencher os dados.'"
    data-crlv-import
>
    <x-slot:title>{{ $title ?? 'Importar do CRLV-e' }}@if($recommended ?? false) <x-ui.badge variant="primary" class="ml-1 align-middle">Recomendado</x-ui.badge>@endif</x-slot:title>

    <form method="POST" action="{{ $importRoute }}" enctype="multipart/form-data" class="space-y-4">
        @csrf

        <x-ui.field name="crlv" label="PDF do CRLV-e" required>
            <x-ui.file-input :id="$crlvInputId" accept="application/pdf,.pdf" :max-mb="$crlvMaxMb" required />
        </x-ui.field>

        <div class="flex flex-wrap items-center justify-end gap-2 max-sm:*:grow">
            <x-ui.button type="submit" icon="document-text" :loading-label="$loadingLabel ?? 'Lendo CRLV-e…'">{{ $submitLabel ?? 'Ler CRLV-e' }}</x-ui.button>
        </div>
    </form>
</x-ui.card>
