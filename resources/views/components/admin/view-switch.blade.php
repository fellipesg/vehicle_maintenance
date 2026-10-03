{{--
    Alternador entre a lista e o mapa de um cadastro do admin (Oficinas, Usuários), sobre o
    <x-ui.segmented> no modo links. São links, não abas: cada visão tem a própria URL. A atual
    recebe aria-current="page", fundo de destaque e texto em negrito, então não depende só da cor.

    Props:
    - list: URL da lista (obrigatório).
    - map: URL do mapa (obrigatório).
    - current: list | map (qual visão está aberta).
    - label: nome do grupo para leitor de tela (padrão "Visualização").

    Ex.: <x-admin.view-switch :list="route('admin.workshops.index')" :map="route('admin.maps.workshops')" current="list" />
--}}
@props([
    'list' => null,
    'map' => null,
    'current' => 'list',
    'label' => 'Visualização',
])
@php
    \App\Support\UiProps::required('x-admin.view-switch', 'list', $list);
    \App\Support\UiProps::required('x-admin.view-switch', 'map', $map);
    $viewSwitchCurrent = \App\Support\UiProps::oneOf('x-admin.view-switch', 'current', $current, ['list', 'map'], 'list');
    $viewSwitchOptions = [
        ['value' => 'list', 'label' => 'Lista', 'href' => $list, 'icon' => 'bars-3'],
        ['value' => 'map', 'label' => 'Mapa', 'href' => $map, 'icon' => 'map'],
    ];
@endphp
<x-ui.segmented
    :attributes="$attributes->class('shrink-0')->merge(['data-slot' => 'view-switch'])"
    :label="$label"
    :options="$viewSwitchOptions"
    :value="$viewSwitchCurrent"
    equal
/>
