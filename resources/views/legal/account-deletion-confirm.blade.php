@extends('layouts.app')

@section('title', 'Confirmar exclusão da conta')

@push('head')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
<x-ui.container size="sm" padded>
    <x-ui.page-header eyebrow="RevisaLog" title="Confirmar exclusão da conta">
        <x-slot:description>
            Último passo. Ao confirmar, os dados pessoais de <strong>{{ $user->email }}</strong> são apagados
            e não há como desfazer.
        </x-slot:description>
    </x-ui.page-header>

    <x-ui.card padding="lg">
        <div class="grid gap-5">
            <x-ui.alert variant="warning" title="O que acontece ao confirmar">
                Nome, e-mail, telefone, documento, endereço, foto, senha e dispositivos de notificação são
                apagados, e o vínculo com seus veículos é desfeito. O histórico de manutenções segue no chassi,
                sem nenhuma ligação com você.
            </x-ui.alert>

            <p class="text-sm text-muted-foreground">
                Não foi você que pediu? Basta fechar esta página: nada é excluído sem a confirmação, e o link
                perde a validade sozinho.
            </p>

            <form method="POST"
                  action="{{ $confirmationUrl }}"
                  class="flex justify-end border-t border-border pt-5 max-sm:*:grow"
                  data-slot="form-actions"
                  data-confirm="Os seus dados pessoais serão apagados. Não é possível desfazer."
                  data-confirm-title="Excluir a conta?"
                  data-confirm-action-label="Excluir conta"
                  data-confirm-variant="danger">
                @csrf
                @method('DELETE')

                <x-ui.button type="submit" variant="danger" icon="trash" loading-label="Excluindo…">
                    Excluir conta
                </x-ui.button>
            </form>
        </div>
    </x-ui.card>
</x-ui.container>
@endsection
