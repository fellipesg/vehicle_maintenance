{{--
    Abas (padrão WAI-ARIA Tabs, ativação automática) com resources/js/ui/tabs.js: seta esquerda e
    direita trocam de aba, Home e End vão à primeira e à última; só a aba ativa entra no Tab
    (tabindex móvel), e o Tab seguinte vai para o painel.

    Props:
    - label (obrigatório): nome da lista de abas (aria-label do tablist), ex.: "Seções do veículo".
    - variant: pills (trilho cinza com a aba ativa em card, o padrão) | line (sublinhado, para abas
      de página).
    - syncUrl: true grava a aba no fragmento da URL (#id-do-painel) sem rolar a página e abre a aba
      do fragmento ao carregar, para link direto. A raiz tem scroll-mt-20: ao rolar até as abas do
      fragmento, o tablist para abaixo da navbar fixa (64px) ou da topbar do admin (56px).

    Slots:
    - tabs (obrigatório): as <x-ui.tab>.
    - o padrão: os <x-ui.tab-panel>, na mesma ordem.

    A aba ativa vem do servidor (:active="true" na aba e no painel). Sem nenhuma marcada, o JS ativa
    a primeira. A lista tem --prevent-on-load-init para o HSTabs do Preline não assumir o tablist.

    Ex.:
    <x-ui.tabs label="Seções do veículo" sync-url>
        <x-slot:tabs>
            <x-ui.tab target="linha-do-tempo" :active="true" icon="clock">Linha do tempo</x-ui.tab>
            <x-ui.tab target="placas">Histórico de placas</x-ui.tab>
        </x-slot:tabs>
        <x-ui.tab-panel id="linha-do-tempo" :active="true">...</x-ui.tab-panel>
        <x-ui.tab-panel id="placas">...</x-ui.tab-panel>
    </x-ui.tabs>
--}}
@props([
    'label',
    'variant' => 'pills',
    'syncUrl' => false,
])
@php
    \App\Support\UiProps::required('x-ui.tabs', 'label', $label, 'O label é o nome acessível da lista de abas.');
    $tabsListClass = match (\App\Support\UiProps::oneOf('x-ui.tabs', 'variant', $variant, ['pills', 'line'], 'pills')) {
        'pills' => 'inline-flex max-w-full items-center gap-1 overflow-x-auto rounded-control bg-surface-muted p-1',
        'line' => 'flex max-w-full items-end gap-4 overflow-x-auto border-b border-border',
    };
@endphp
<div {{ $attributes->class('min-w-0 scroll-mt-20') }} data-ui-tabs @if($syncUrl) data-ui-tabs-sync-url @endif>
    <div role="tablist" aria-label="{{ $label }}" aria-orientation="horizontal" class="--prevent-on-load-init {{ $tabsListClass }}">
        {{ $tabs }}
    </div>
    {{ $slot }}
</div>
