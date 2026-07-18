@extends('layouts.app')

@section('title', 'Consignação — procuração')

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8">
    <h1 class="mb-2 text-3xl font-bold">Veículo em consignação</h1>
    <p class="mb-6 text-sm text-automotive-600">
        O CRLV-e está em nome de outra pessoa. Envie o PDF da <strong>procuração do proprietário</strong> para solicitar acesso ao histórico de manutenções.
    </p>

    <form method="POST" action="{{ route('user.vehicles.consignment.store') }}" enctype="multipart/form-data" class="card space-y-4">
        @csrf
        <div>
            <label for="power_of_attorney" class="form-label">Procuração (PDF) *</label>
            <input type="file" name="power_of_attorney" id="power_of_attorney" accept="application/pdf,.pdf" required class="form-input">
            @error('power_of_attorney')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="btn-primary">Enviar procuração</button>
    </form>
</div>
@endsection
