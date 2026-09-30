---
paths:
  - 'resources/views/layouts/**,resources/views/admin/**'
---

# Views Admin

## Admin shell uses layouts.admin sidebar
All admin.* Blade views must @extends('layouts.admin') with vertical nav in layouts/partials/nav-admin.blade.php (sidebar 240px + topbar). Never use layouts.app horizontal navbar for admin routes.

## Admin page contract
Each admin view passes only the page name in @section('title'), and that name equals the H1 drawn by <x-ui.page-header> (the <title> becomes "{Página} · Admin · RevisaLog"). The topbar trail comes from $adminBreadcrumbs in the <x-ui.breadcrumb> format. Views do not create their own container (mx-auto/px/py) or "← Voltar" links: spacing and width belong to layouts.admin (admin_content_width for forms). Deletes use data-confirm with <x-ui.confirm-dialog>, never the native confirm.

## Row actions, form dialogs and map pins
Row actions in admin tables go only through <x-admin.row-actions> (the ⋯ menu), with Excluir last and data-confirm on its item. A page with several forms for the same field (create and edit in dialogs) uses validateWithBag per form and data-dialog-open-on-load on the dialog that failed (initDialogs reopens it), with native inputs, because x-ui.input and x-ui.switch read old() globally. Map pins go in a <script type="application/json"> with only id, name, lat, lng, city and label; the link to the record comes from the list rendered on the server. Admin scripts enter through resources/js/admin.js (initAdmin).

## Collapsible sidebar and one command palette per page
From md the admin sidebar collapses with <x-ui.sidebar-toggle> (resources/js/ui/sidebar.js, state in localStorage with try/catch): the head script of layouts.admin puts data-sidebar="collapsed" on <html> before first paint, and the sidebar styles itself with md:in-data-[sidebar=collapsed]:* classes (icons keep tooltips). layouts.admin and layouts.partials.navbar render <x-ui.command> once per page (Ctrl K, ⌘K on Mac): the portal destinations from Portal::navigation(), the primary action and "Buscar veículo por placa, chassi ou RENAVAM", which opens admin.vehicles.index?search= (admin) or vehicle.search?identifier= (portals). No new route and no <form>: without JS the triggers stay plain links.
