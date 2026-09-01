<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Plataforma Reservas')</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.11/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.3/css/buttons.bootstrap5.min.css" rel="stylesheet">

    <style>
        /* ============================================================
           Sistema de tema: 2 modos (claro/oscuro) x 7 acentos, combinables.
           Para agregar un acento nuevo, solo se necesita un bloque
           body.acento-nombre-nuevo {...} con esas 3 variables.

           Capa 1 (MODO): fondos, textos, bordes, sombras y semánticos.
           Capa 2 (ACENTO): únicamente el color de marca.
           ============================================================ */

        :root {
            /* Valores estructurales, iguales en todos los temas. */
            --radius-card: 16px;
            --radius-sm: 10px;
            --transition-base: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            --text-sobre-accent: #ffffff;
            /* Derivadas del acento: siguen automáticamente al acento activo. */
            --accent-glow: var(--accent-soft);
            --shadow-glow: 0 0 0 1px var(--accent-soft), 0 4px 20px var(--accent-soft);
        }

        /* ---------- Capa 1: MODO ---------- */

        body.modo-oscuro {
            --bg-body: #0a0a0d;
            --bg-sidebar: #000000;
            --bg-card: #17171c;
            --bg-card-hover: #1e1e24;
            --bg-input: #101014;
            --border-color: #2a2a32;
            --border-color-strong: #3a3a44;
            --text-primary: #f4f4f5;
            --text-secondary: #9a9aa5;
            --text-muted: #5c5c66;
            --text-sidebar: #cbd5e1;
            --success: #22c55e;
            --success-soft: rgba(34, 197, 94, 0.12);
            --warning: #eab308;
            --warning-soft: rgba(234, 179, 8, 0.12);
            --danger: #e11d2e;
            --danger-soft: rgba(225, 29, 46, 0.12);
            --shadow-card: 0 1px 2px rgba(0,0,0,0.4), 0 8px 24px rgba(0,0,0,0.25);
            --stripe-fila: rgba(255, 255, 255, 0.02);
            --overlay-loader: rgba(10, 10, 13, 0.75);
        }

        body.modo-claro {
            --bg-body: #fdf7f5;
            --bg-sidebar: #f7e9e6;
            --bg-card: #ffffff;
            --bg-card-hover: #fdf2ef;
            --bg-input: #ffffff;
            --border-color: #ecd9d5;
            --border-color-strong: #ddbfb8;
            --text-primary: #3a2b28;
            --text-secondary: #8a6f6a;
            --text-muted: #b3a09b;
            --text-sidebar: #5c4340;
            --success: #4f9d6d;
            --success-soft: rgba(79, 157, 109, 0.14);
            --warning: #c08a3e;
            --warning-soft: rgba(192, 138, 62, 0.14);
            --danger: #c2596a;
            --danger-soft: rgba(194, 89, 106, 0.14);
            --shadow-card: 0 1px 2px rgba(120, 80, 80, 0.06), 0 8px 24px rgba(120, 80, 80, 0.07);
            --stripe-fila: rgba(0, 0, 0, 0.018);
            --overlay-loader: rgba(253, 247, 245, 0.8);
        }

        /* ---------- Capa 2: ACENTO ---------- */

        body.acento-oro-rosa {
            --accent: #b76e79;
            --accent-hover: #a35c67;
            --accent-soft: rgba(183, 110, 121, 0.12);
        }

        body.acento-dorado {
            --accent: #c9a227;
            --accent-hover: #b08e1f;
            --accent-soft: rgba(201, 162, 39, 0.12);
        }

        body.acento-amarillo {
            --accent: #eab308;
            --accent-hover: #ca9a06;
            --accent-soft: rgba(234, 179, 8, 0.12);
        }

        body.acento-naranja {
            --accent: #ea580c;
            --accent-hover: #c8490a;
            --accent-soft: rgba(234, 88, 12, 0.12);
        }

        body.acento-rojo {
            --accent: #e11d2e;
            --accent-hover: #ff2e42;
            --accent-soft: rgba(225, 29, 46, 0.12);
        }

        body.acento-azul {
            --accent: #2563eb;
            --accent-hover: #1d4ed8;
            --accent-soft: rgba(37, 99, 235, 0.12);
        }

        body.acento-verde {
            --accent: #16a34a;
            --accent-hover: #128038;
            --accent-soft: rgba(22, 163, 74, 0.12);
        }

        /* En modo claro el sidebar es claro: los textos deben invertirse a oscuro
           y la X de los modales no necesita el filtro de inversión. */
        body.modo-claro #sidebar .sidebar-header span,
        body.modo-claro .disparador-usuario .nombre-usuario,
        body.modo-claro #topbar h1 {
            color: var(--text-primary);
        }

        body.modo-claro .menu-item {
            color: var(--text-sidebar);
        }

        body.modo-claro .btn-close {
            filter: none;
        }

        * {
            scrollbar-width: thin;
            scrollbar-color: var(--border-color-strong) var(--bg-body);
        }

        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-body);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border-color-strong);
            border-radius: 999px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--text-muted);
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-primary);
        }

        a {
            color: var(--accent);
        }

        .text-secondary {
            color: var(--text-secondary) !important;
        }

        .text-muted {
            color: var(--text-muted) !important;
        }

        /* ---------- Componentes reutilizables ---------- */

        .card-elevada {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-card);
            padding: 1.5rem;
            transition: var(--transition-base);
        }

        .card-elevada.interactiva:hover {
            background-color: var(--bg-card-hover);
            border-color: var(--border-color-strong);
        }

        .card-acento {
            border-top: 3px solid var(--accent);
            box-shadow: var(--shadow-glow), var(--shadow-card);
        }

        .kpi-tile {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
        }

        .kpi-tile .kpi-icono {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background-color: var(--accent-soft);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-bottom: 1rem;
        }

        .kpi-tile .kpi-label {
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.08em;
            color: var(--text-secondary);
            font-weight: 600;
            margin-bottom: 0.35rem;
        }

        .kpi-tile .kpi-valor {
            font-size: 2.25rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.1;
        }

        .badge-estado-activo,
        .badge-estado-inactivo,
        .badge-proximamente {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.3rem 0.7rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge-estado-activo {
            background-color: var(--success-soft);
            color: var(--success);
        }

        .badge-estado-inactivo {
            background-color: var(--bg-input);
            color: var(--text-muted);
        }

        .badge-proximamente {
            background-color: var(--warning-soft);
            color: var(--warning);
        }

        .fila-tabla-hover tbody tr,
        table.dataTable tbody tr {
            transition: var(--transition-base);
        }

        .fila-tabla-hover tbody tr:hover,
        table.dataTable tbody tr:hover {
            background-color: var(--bg-card-hover) !important;
        }

        .btn-accion-icono {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: transparent;
            border: none;
            color: var(--text-secondary);
            transition: var(--transition-base);
        }

        .btn-accion-icono:hover {
            background-color: var(--accent-soft);
            color: var(--accent);
        }

        .btn-accion-icono.btn-accion-eliminar:hover {
            background-color: var(--danger-soft);
            color: var(--danger);
        }

        .btn-primario-accento {
            background-color: var(--accent);
            border: 1px solid var(--accent);
            color: var(--text-sobre-accent);
            border-radius: var(--radius-sm);
            font-weight: 600;
            padding: 0.55rem 1.15rem;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            transition: var(--transition-base);
        }

        .btn-primario-accento:hover {
            background-color: var(--accent-hover);
            border-color: var(--accent-hover);
            color: var(--text-sobre-accent);
            transform: translateY(-1px);
        }

        /* ---------- Overrides oscuros de Bootstrap ---------- */

        .card {
            background-color: var(--bg-card);
            border-color: var(--border-color);
            color: var(--text-primary);
        }

        .form-label {
            color: var(--text-secondary);
            font-size: 0.85rem;
            font-weight: 500;
        }

        .form-control,
        .form-select {
            background-color: var(--bg-input);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            transition: var(--transition-base);
        }

        .form-control:focus,
        .form-select:focus {
            background-color: var(--bg-input);
            border-color: var(--accent);
            color: var(--text-primary);
            box-shadow: 0 0 0 0.2rem var(--accent-soft);
        }

        .form-control::placeholder {
            color: var(--text-muted);
        }

        .form-control:disabled,
        .form-select:disabled {
            background-color: var(--bg-input);
            color: var(--text-muted);
            opacity: 0.7;
        }

        .input-group-text {
            background-color: var(--bg-input);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
        }

        .modal-content {
            background-color: var(--bg-card);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-card);
        }

        .modal-header,
        .modal-footer {
            border-color: var(--border-color);
        }

        .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }

        .btn-secondary {
            background-color: var(--bg-input);
            border-color: var(--border-color);
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background-color: var(--bg-card-hover);
            border-color: var(--border-color-strong);
            color: var(--text-primary);
        }

        .btn-outline-primary {
            color: var(--accent);
            border-color: var(--accent);
        }

        .btn-outline-primary:hover {
            background-color: var(--accent);
            color: var(--text-sobre-accent);
        }

        .btn-outline-danger {
            color: var(--danger);
            border-color: var(--danger);
        }

        .btn-outline-danger:hover {
            background-color: var(--danger);
            color: var(--text-sobre-accent);
        }

        .table {
            color: var(--text-primary);
            border-color: var(--border-color);
        }

        .table > :not(caption) > * > * {
            background-color: transparent;
            color: var(--text-primary);
            border-bottom-color: var(--border-color);
            box-shadow: none;
        }

        .table-striped > tbody > tr:nth-of-type(odd) > * {
            background-color: var(--stripe-fila);
            color: var(--text-primary);
        }

        .table thead th {
            color: var(--text-secondary);
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.06em;
            font-weight: 600;
            border-bottom-color: var(--border-color-strong);
        }

        .dropdown-menu {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .dropdown-item {
            color: var(--text-primary);
        }

        .dropdown-item:hover {
            background-color: var(--bg-card-hover);
            color: var(--text-primary);
        }

        .tooltip-inner {
            background-color: var(--bg-card-hover);
            border: 1px solid var(--border-color-strong);
            color: var(--text-primary);
        }

        /* ---------- DataTables ---------- */

        .dataTables_wrapper {
            color: var(--text-secondary);
        }

        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            background-color: var(--bg-input);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: var(--radius-sm);
        }

        .dataTables_wrapper .dataTables_filter input:focus {
            outline: none;
            border-color: var(--accent);
        }

        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_length label,
        .dataTables_wrapper .dataTables_filter label {
            color: var(--text-secondary);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            color: var(--text-secondary) !important;
            border: 1px solid transparent !important;
            border-radius: var(--radius-sm);
            background: transparent !important;
            transition: var(--transition-base);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: var(--accent-soft) !important;
            color: var(--accent) !important;
            border-color: transparent !important;
        }

        /* DataTables con Bootstrap 5 pinta la paginación como .page-link, no como
           .paginate_button: sin estas reglas queda el azul por defecto de Bootstrap
           y rompe el tema. */
        .dataTables_wrapper .page-link,
        .pagination .page-link {
            background-color: transparent;
            border-color: var(--border-color);
            color: var(--text-secondary);
            transition: var(--transition-base);
        }

        .dataTables_wrapper .page-link:hover,
        .pagination .page-link:hover {
            background-color: var(--accent-soft);
            border-color: var(--border-color);
            color: var(--accent);
        }

        .dataTables_wrapper .page-item.active .page-link,
        .pagination .page-item.active .page-link {
            background-color: var(--accent);
            border-color: var(--accent);
            color: var(--text-sobre-accent);
        }

        .dataTables_wrapper .page-item.disabled .page-link,
        .pagination .page-item.disabled .page-link {
            background-color: transparent;
            border-color: var(--border-color);
            color: var(--text-muted);
        }

        .dataTables_wrapper .page-link:focus,
        .pagination .page-link:focus {
            box-shadow: 0 0 0 0.2rem var(--accent-soft);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
            color: var(--text-muted) !important;
        }

        table.dataTable tbody tr {
            background-color: transparent;
        }

        table.dataTable.stripe tbody tr.odd > *,
        table.dataTable.display tbody tr.odd > * {
            background-color: var(--stripe-fila);
        }

        /* ---------- Sidebar ---------- */

        #sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 250px;
            background-color: var(--bg-sidebar);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            z-index: 1030;
            border-right: 1px solid var(--border-color);
        }

        #sidebar .sidebar-header {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 1.35rem 1.1rem;
            border-bottom: 1px solid var(--border-color);
        }

        #sidebar .sidebar-header .logo-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background-color: var(--accent);
            box-shadow: 0 0 10px var(--accent-glow);
            flex-shrink: 0;
        }

        #sidebar .sidebar-header span {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 1rem;
            letter-spacing: 0.2px;
        }

        /* El nombre del negocio puede ser largo: se recorta con puntos
           suspensivos para no romper el ancho del sidebar. */
        #sidebar .sidebar-header .nombre-marca {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            min-width: 0;
        }

        #menu-lateral {
            flex: 1;
            padding: 0.75rem 0;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.6rem 1rem;
            margin: 0.15rem 0.6rem;
            border-radius: var(--radius-sm);
            color: var(--text-secondary);
            text-decoration: none;
            border-left: 3px solid transparent;
            transition: var(--transition-base);
            font-size: 0.9rem;
        }

        .menu-item i {
            font-size: 1.05rem;
            width: 1.25rem;
            text-align: center;
        }

        .menu-item:hover {
            background-color: var(--bg-card-hover);
            color: var(--text-primary);
            text-decoration: none;
        }

        .menu-item.active {
            background-color: var(--accent-soft);
            border-left-color: var(--accent);
            color: var(--accent);
        }

        .menu-item.active i {
            color: var(--accent);
        }

        #contenido-principal {
            margin-left: 250px;
        }

        #topbar {
            background-color: var(--bg-sidebar);
            border-bottom: 1px solid var(--border-color);
            padding: 0.85rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        #topbar h1 {
            color: var(--text-primary);
        }

        /* ---------- Menú de usuario del topbar ---------- */

        .disparador-usuario {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            background-color: transparent;
            border: 1px solid transparent;
            border-radius: var(--radius-sm);
            padding: 0.35rem 0.6rem;
            transition: var(--transition-base);
            /* El botón no hereda el color del tema por defecto: sin esto el
               nombre saldría en negro sobre el topbar oscuro. */
            color: var(--text-primary);
        }

        .disparador-usuario .nombre-usuario {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .disparador-usuario:hover,
        .disparador-usuario[aria-expanded="true"] {
            background-color: var(--bg-card-hover);
            border-color: var(--border-color);
        }

        .avatar-usuario {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background-color: var(--accent-soft);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
            letter-spacing: 0.02em;
            flex-shrink: 0;
        }

        .datos-disparador {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            line-height: 1.2;
            min-width: 0;
        }

        .flecha-usuario {
            color: var(--text-secondary);
            font-size: 0.8rem;
            transition: var(--transition-base);
        }

        .disparador-usuario[aria-expanded="true"] .flecha-usuario {
            transform: rotate(180deg);
        }

        .menu-usuario {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-card);
            padding: 0.4rem;
            min-width: 250px;
            margin-top: 0.4rem;
        }

        .encabezado-menu-usuario {
            padding: 0.7rem 0.85rem 0.5rem;
        }

        .encabezado-menu-usuario .nombre-completo {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.92rem;
            word-break: break-word;
        }

        .encabezado-menu-usuario .email-usuario {
            color: var(--text-secondary);
            font-size: 0.8rem;
            word-break: break-all;
        }

        .menu-usuario .dropdown-divider {
            border-top: 1px solid var(--border-color);
            opacity: 1;
            margin: 0.35rem 0;
        }

        .menu-usuario .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            color: var(--text-primary);
            border-radius: var(--radius-sm);
            padding: 0.6rem 0.85rem;
            font-size: 0.9rem;
            transition: var(--transition-base);
        }

        .menu-usuario .dropdown-item:hover,
        .menu-usuario .dropdown-item:focus {
            background-color: var(--bg-card-hover);
            color: var(--text-primary);
        }

        .menu-usuario .dropdown-item i {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        .menu-usuario .dropdown-item.item-salir,
        .menu-usuario .dropdown-item.item-salir i {
            color: var(--danger);
        }

        .menu-usuario .dropdown-item.item-salir:hover {
            background-color: var(--danger-soft);
            color: var(--danger);
        }

        @media (max-width: 575.98px) {
            .datos-disparador {
                display: none;
            }
        }

        .badge-rol-sesion {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.7rem;
            padding: 0.15rem 0.55rem;
            border-radius: 999px;
            margin-top: 0.2rem;
        }

        .badge-rol-sesion.super-admin {
            background-color: var(--accent-soft);
            color: var(--accent);
        }

        .badge-rol-sesion.negocio {
            background-color: var(--bg-input);
            color: var(--text-secondary);
        }

        .btn-salir-sesion {
            background-color: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            border-radius: var(--radius-sm);
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
            transition: var(--transition-base);
        }

        .btn-salir-sesion:hover {
            background-color: var(--danger-soft);
            border-color: var(--danger);
            color: var(--danger);
        }

        main {
            padding: 1.5rem;
        }

        #loader_proceso {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--overlay-loader);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        @media (max-width: 991.98px) {
            #sidebar {
                width: 72px;
            }

            #sidebar .sidebar-header span,
            .menu-item span {
                display: none;
            }

            #contenido-principal {
                margin-left: 72px;
            }
        }
    </style>

    @yield('estilos')
</head>
<body class="modo-{{ session('modo_tema') ?? 'oscuro' }} acento-{{ str_replace('_', '-', session('color_acento') ?? 'rojo') }}">

    <div id="loader_proceso">
        <div class="spinner-border" role="status" style="width: 3rem; height: 3rem; color: var(--accent);">
            <span class="visually-hidden">Cargando...</span>
        </div>
    </div>

    <aside id="sidebar">
        <div class="sidebar-header">
            <span class="logo-dot"></span>
            <span class="nombre-marca" title="{{ session('nombre_negocio_sesion') ?? 'Plataforma Reservas' }}">
                {{ session('nombre_negocio_sesion') ?? 'Plataforma Reservas' }}
            </span>
        </div>
        <nav id="menu-lateral">
            <a href="{{ url('backoffice/dashboard') }}" class="menu-item @if (request()->is('backoffice/dashboard')) active @endif">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
            @if (\App\Models\Rol::esRolEmpleado(session('id_rol')))
                <a href="{{ url('backoffice/mis-citas') }}" class="menu-item @if (request()->is('backoffice/mis-citas')) active @endif">
                    <i class="bi bi-calendar2-check"></i>
                    <span>Mis Citas</span>
                </a>
            @else
            <a href="{{ url('backoffice/usuarios') }}" class="menu-item @if (request()->is('backoffice/usuarios')) active @endif">
                <i class="bi bi-people"></i>
                <span>Usuarios</span>
            </a>
            @if (session('tenant_id') !== null)
                <a href="{{ url('backoffice/clientes') }}" class="menu-item @if (request()->is('backoffice/clientes')) active @endif">
                    <i class="bi bi-person-vcard"></i>
                    <span>Clientes</span>
                </a>
                <a href="{{ url('backoffice/recursos') }}" class="menu-item @if (request()->is('backoffice/recursos')) active @endif">
                    <i class="bi bi-collection"></i>
                    <span>Recursos</span>
                </a>
                <a href="{{ url('backoffice/empleados') }}" class="menu-item @if (request()->is('backoffice/empleados')) active @endif">
                    <i class="bi bi-person-badge"></i>
                    <span>Empleados</span>
                </a>
                <a href="{{ url('backoffice/reservas') }}" class="menu-item @if (request()->is('backoffice/reservas')) active @endif">
                    <i class="bi bi-calendar-check"></i>
                    <span>Reservas</span>
                </a>
                <a href="{{ url('backoffice/reservas/historial') }}" class="menu-item @if (request()->is('backoffice/reservas/historial')) active @endif">
                    <i class="bi bi-clock-history"></i>
                    <span>Historial</span>
                </a>
                <a href="{{ url('backoffice/personalizar') }}" class="menu-item @if (request()->is('backoffice/personalizar')) active @endif">
                    <i class="bi bi-palette2"></i>
                    <span>Personalizar</span>
                </a>
            @endif
            @endif
            <!-- Los enlaces de cada módulo se agregan aquí a medida que se construyen -->
        </nav>
    </aside>

    <div id="contenido-principal">
        <header id="topbar">
            <h1 class="h5 mb-0">@yield('title')</h1>
            @php
                // Iniciales del usuario para el avatar (máximo dos letras).
                $nombreSesion = session('nombre_usuario', 'Invitado');
                $partesNombre = preg_split('/\s+/', trim($nombreSesion));
                $inicialesUsuario = '';
                foreach (array_slice($partesNombre, 0, 2) as $parte) {
                    $inicialesUsuario .= mb_strtoupper(mb_substr($parte, 0, 1));
                }
                $inicialesUsuario = $inicialesUsuario ?: 'U';

                $esAdminDeNegocio = ! \App\Models\Rol::esRolEmpleado(session('id_rol')) && session('tenant_id') !== null;
            @endphp

            <div class="dropdown">
                <button type="button" id="btn-menu-usuario" class="disparador-usuario" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="avatar-usuario">{{ $inicialesUsuario }}</span>
                    <span class="datos-disparador">
                        <span class="nombre-usuario">{{ $nombreSesion }}</span>
                        @if (session('tenant_id') === null)
                            <span class="badge-rol-sesion super-admin">
                                <i class="bi bi-shield-check"></i> Super Admin
                            </span>
                        @else
                            <span class="badge-rol-sesion negocio">
                                <i class="bi bi-building"></i> {{ session('nombre_negocio_sesion') ?? 'Negocio' }}
                            </span>
                        @endif
                    </span>
                    <i class="bi bi-chevron-down flecha-usuario"></i>
                </button>

                <ul class="dropdown-menu dropdown-menu-end menu-usuario" aria-labelledby="btn-menu-usuario">
                    <li>
                        <div class="encabezado-menu-usuario">
                            <div class="nombre-completo">{{ $nombreSesion }}</div>
                            <div class="email-usuario">{{ session('email') }}</div>
                        </div>
                    </li>

                    @if ($esAdminDeNegocio)
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="{{ url('backoffice/configuracion') }}">
                                <i class="bi bi-gear"></i> Configurar negocio
                            </a>
                        </li>
                    @endif

                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <button type="button" class="dropdown-item item-salir" id="btn-salir">
                            <i class="bi bi-box-arrow-right"></i> Cerrar sesión
                        </button>
                    </li>
                </ul>
            </div>
        </header>

        <main>
            @yield('content')
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios@1.7.7/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script src="https://cdn.datatables.net/1.13.11/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.11/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.3/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.3/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/pdfmake@0.2.12/build/pdfmake.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/pdfmake@0.2.12/build/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.3/js/buttons.html5.min.js"></script>

    <script>
        const UrlGlobal = "{{ url('/') }}/";
    </script>
    <script src="{{ asset('js/utilidades.js') }}"></script>
    <script src="{{ asset('js/validador.js') }}"></script>

    <script>
        // Lee el valor real de una variable CSS del :root. Se usa donde una librería
        // externa (por ejemplo SweetAlert2) no resuelve var(--nombre) por sí sola,
        // para que los colores sigan viviendo centralizados en el :root del layout.
        function colorVariable(nombreVariable) {
            return getComputedStyle(document.documentElement).getPropertyValue(nombreVariable).trim();
        }

        jQuery("#btn-salir").on("click", function () {
            Swal.fire({
                title: '¿Seguro que quieres cerrar sesión?',
                icon: 'question',
                background: colorVariable('--bg-card'),
                color: colorVariable('--text-primary'),
                confirmButtonColor: colorVariable('--accent'),
                showCancelButton: true,
                confirmButtonText: 'Sí, salir',
                cancelButtonText: 'Cancelar'
            }).then(function (resultado) {
                if (resultado.isConfirmed) {
                    window.location.href = UrlGlobal + "backoffice/logout";
                }
            });
        });

        jQuery(function () {
            var listaTooltips = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            listaTooltips.map(function (elemento) {
                return new bootstrap.Tooltip(elemento);
            });
        });
    </script>

    @yield('scripts')
</body>
</html>
