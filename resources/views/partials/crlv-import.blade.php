<div class="card border-dashed border-automotive-300 bg-automotive-50/60">
    <h2 class="text-lg font-semibold text-automotive-900">Importar do CRLV-e</h2>
    <p class="mt-1 text-sm text-automotive-600">
        {{ $description ?? 'Exporte o PDF pelo app Carteira Digital de Trânsito (CDT) e envie aqui para pré-preencher o cadastro.' }}
    </p>

    <form method="POST" action="{{ $importRoute }}" enctype="multipart/form-data" class="mt-4 space-y-3">
        @csrf
        <div>
            <label for="{{ $inputId ?? 'crlv' }}" class="form-label">Arquivo CRLV-e (PDF)</label>
            <input type="file" name="crlv" id="{{ $inputId ?? 'crlv' }}" accept="application/pdf,.pdf" required class="form-input">
            @error('crlv')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="btn-secondary">{{ $submitLabel ?? 'Ler CRLV-e e revisar dados' }}</button>
    </form>
</div>
