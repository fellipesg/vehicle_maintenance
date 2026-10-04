@extends('layouts.admin')

@section('title', 'Prospecção')

@php
    use App\Http\Controllers\Web\Admin\OutreachController;

    $adminBreadcrumbs = [['Cadastros'], ['Prospecção']];
    $formatDate = fn ($date): string => $date ? \App\Support\DisplayTime::local($date)->format('d/m/Y H:i') : '—';
@endphp

@section('content')
    <x-ui.page-header title="Prospecção" description="Convites por e-mail para oficinas encontradas no cadastro público de CNPJ. No máximo dois e-mails por oficina, com limite diário.">
        <x-slot:actions>
            @if($paused)
                <form method="POST" action="{{ route('admin.outreach.resume') }}">
                    @csrf
                    <x-ui.button type="submit" icon="play" loading-label="Retomando…">Retomar</x-ui.button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.outreach.pause') }}">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" icon="pause" loading-label="Pausando…">Pausar</x-ui.button>
                </form>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="space-y-6">
        <x-ui.alert :variant="$active ? 'success' : 'warning'" :title="$active ? 'Envio ativo' : 'Envio parado'" data-outreach-state>
            @if(! $enabled)
                A prospecção está desligada (OUTREACH_ENABLED).
            @elseif($paused)
                A prospecção está pausada neste painel.
            @else
                Um e-mail por vez, em dias úteis, das 9h às 17h (Brasília).
            @endif
            Hoje: {{ $sentToday }} de {{ $dailyLimit }} envios.
        </x-ui.alert>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5" data-slot="outreach-funnel">
            @foreach($funnel as $funnelLabel => $funnelCount)
                <x-ui.stat :label="$funnelLabel" :value="number_format($funnelCount, 0, ',', '.')" />
            @endforeach
        </div>

        <x-ui.card>
            <form method="POST" action="{{ route('admin.outreach.import') }}" enctype="multipart/form-data" class="grid gap-3" data-slot="outreach-import">
                @csrf
                <x-ui.field name="csv" label="Importar CSV" hint="O arquivo gerado por outreach:extract-receita. Quem já é cliente, está na lista de supressão ou já foi importado fica de fora." :bag="OutreachController::IMPORT_BAG">
                    <x-ui.file-input accept=".csv,text/csv,text/plain" :max-mb="5" required />
                </x-ui.field>
                <div class="flex justify-end">
                    <x-ui.button type="submit" icon="arrow-up-tray" loading-label="Importando…">Importar</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <form method="GET" action="{{ route('admin.outreach.index') }}" role="search" aria-label="Buscar oficinas prospectadas" class="flex flex-col gap-2 sm:flex-row" data-submit-busy="off">
            <label for="outreach-search" class="sr-only">Buscar por nome, CNPJ ou e-mail</label>
            <x-ui.input type="search" id="outreach-search" name="q" :value="$search" placeholder="Nome, CNPJ ou e-mail" leading-icon="magnifying-glass" autocomplete="off" class="min-w-0 flex-1" />
            <x-ui.select name="situacao" id="outreach-status" aria-label="Situação" :options="$statuses" :value="$status?->value" placeholder="Todas as situações" class="sm:w-56" />
            <x-ui.button type="submit" variant="secondary">Filtrar</x-ui.button>
        </form>

        <x-ui.table caption="Oficinas prospectadas" stack>
            <x-slot:head>
                <tr>
                    <th class="min-w-56">Oficina</th>
                    <th class="min-w-56">Contato</th>
                    <th>Situação</th>
                    <th>Primeiro envio</th>
                    <th class="text-right"><span class="sr-only">Ações</span></th>
                </tr>
            </x-slot:head>

            @foreach($prospects as $prospect)
                <tr>
                    <th scope="row" class="font-medium">
                        {{ $prospect->displayName() }}
                        <span class="block text-xs font-normal text-muted-foreground">CNPJ {{ $prospect->formattedCnpj() }} · {{ $prospect->city }}/{{ $prospect->state }}</span>
                    </th>
                    <td class="text-muted-foreground">
                        {{ $prospect->email }}
                        @if($prospect->phone)
                            <span class="block text-xs">{{ $prospect->phone }}</span>
                        @endif
                    </td>
                    <td>
                        <x-ui.badge :variant="$prospect->status->badgeVariant()">{{ $prospect->status->label() }}</x-ui.badge>
                        @if($prospect->last_error)
                            <span class="block text-xs text-danger">{{ \Illuminate\Support\Str::limit($prospect->last_error, 80) }}</span>
                        @endif
                    </td>
                    <td class="text-muted-foreground">{{ $formatDate($prospect->first_sent_at) }}</td>
                    <td class="py-2 text-right">
                        <x-admin.row-actions :label="'Ações para a oficina '.$prospect->displayName()" :id="'prospect-'.$prospect->id.'-acoes'">
                            <x-ui.dropdown-item :action="route('admin.outreach.replied', $prospect)" icon="chat-bubble-left-right">Marcar como respondeu</x-ui.dropdown-item>
                            <x-ui.dropdown-item :action="route('admin.outreach.converted', $prospect)" icon="check-circle">Marcar como convertida</x-ui.dropdown-item>
                            <x-ui.dropdown-item
                                :action="route('admin.outreach.bounced', $prospect)"
                                icon="exclamation-triangle"
                                :data-confirm="'O e-mail '.$prospect->email.' não receberá mais mensagens de prospecção.'"
                                data-confirm-title="Marcar como devolvido?"
                                data-confirm-action-label="Marcar como devolvido"
                            >Marcar como devolvido</x-ui.dropdown-item>
                            <x-ui.dropdown-item
                                :action="route('admin.outreach.unsubscribe', $prospect)"
                                icon="x-circle"
                                variant="danger"
                                :data-confirm="'O e-mail '.$prospect->email.' não receberá mais mensagens de prospecção. Não é possível desfazer por aqui.'"
                                data-confirm-title="Descadastrar esta oficina?"
                                data-confirm-action-label="Descadastrar"
                                data-confirm-variant="danger"
                            >Descadastrar</x-ui.dropdown-item>
                        </x-admin.row-actions>
                    </td>
                </tr>
            @endforeach

            <x-slot:empty>
                <x-ui.empty-state
                    icon="envelope"
                    title="Nenhuma oficina prospectada"
                    description="Importe o CSV gerado por outreach:extract-receita para começar."
                    variant="plain"
                    size="sm"
                    heading-level="p"
                />
            </x-slot:empty>
        </x-ui.table>

        <div>{{ $prospects->links() }}</div>
    </div>
@endsection
