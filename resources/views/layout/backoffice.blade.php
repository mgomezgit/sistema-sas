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
        :root {
            --bg-body: #0a0a0d;
            --bg-sidebar: #000000;
            --bg-card: #17171c;
            --bg-card-hover: #1e1e24;
            --bg-input: #101014;
            --border-color: #2a2a32;
            --border-color-strong: #3a3a44;
            --accent: #e11d2e;
            --accent-hover: #ff2e42;
            --accent-soft: rgba(225, 29, 46, 0.12);
            --accent-glow: rgba(225, 29, 46, 0.25);
            --text-primary: #f4f4f5;
            --text-secondary: #9a9aa5;
            --text-muted: #5c5c66;
            --success: #22c55e;
            --success-soft: rgba(34, 197, 94, 0.12);
            --warning: #eab308;
            --warning-soft: rgba(234, 179, 8, 0.12);
            --danger: #e11d2e;
            --danger-soft: rgba(225, 29, 46, 0.12);
            --radius-card: 16px;
            --radius-sm: 10px;
            --shadow-card: 0 1px 2px rgba(0,0,0,0.4), 0 8px 24px rgba(0,0,0,0.25);
            --shadow-glow: 0 0 0 1px var(--accent-soft), 0 4px 20px var(--accent-glow);
            --transition-base: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
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
            color: #fff;
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
            color: #fff;
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
            color: #fff;
        }

        .btn-outline-danger {
            color: var(--danger);
            border-color: var(--danger);
        }

        .btn-outline-danger:hover {
            background-color: var(--danger);
            color: #fff;
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
            background-color: rgba(255, 255, 255, 0.02);
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

        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
            color: var(--text-muted) !important;
        }

        table.dataTable tbody tr {
            background-color: transparent;
        }

        table.dataTable.stripe tbody tr.odd > *,
        table.dataTable.display tbody tr.odd > * {
            background-color: rgba(255, 255, 255, 0.02);
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

        .topbar-usuario {
            text-align: right;
        }

        .topbar-usuario .nombre-usuario {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.9rem;
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
            background-color: rgba(10, 10, 13, 0.75);
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
<body>

    <div id="loader_proceso">
        <div class="spinner-border" role="status" style="width: 3rem; height: 3rem; color: var(--accent);">
            <span class="visually-hidden">Cargando...</span>
        </div>
    </div>

    <aside id="sidebar">
        <div class="sidebar-header">
            <span class="logo-dot"></span>
            <span>Plataforma Reservas</span>
        </div>
        <nav id="menu-lateral">
            <a href="{{ url('backoffice/dashboard') }}" class="menu-item @if (request()->is('backoffice/dashboard')) active @endif">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
            <a href="{{ url('backoffice/usuarios') }}" class="menu-item @if (request()->is('backoffice/usuarios')) active @endif">
                <i class="bi bi-people"></i>
                <span>Usuarios</span>
            </a>
            @if (session('tenant_id') !== null)
                <a href="{{ url('backoffice/clientes') }}" class="menu-item @if (request()->is('backoffice/clientes')) active @endif">
                    <i class="bi bi-person-vcard"></i>
                    <span>Clientes</span>
                </a>
            @endif
            <!-- Los enlaces de cada módulo se agregan aquí a medida que se construyen -->
        </nav>
    </aside>

    <div id="contenido-principal">
        <header id="topbar">
            <h1 class="h5 mb-0">@yield('title')</h1>
            <div class="d-flex align-items-center gap-3">
                <div class="topbar-usuario">
                    <div class="nombre-usuario">{{ session('nombre_usuario', 'Invitado') }}</div>
                    @if (session('tenant_id') === null)
                        <span class="badge-rol-sesion super-admin">
                            <i class="bi bi-shield-check"></i> Super Admin
                        </span>
                    @else
                        <span class="badge-rol-sesion negocio">
                            <i class="bi bi-building"></i> Negocio
                        </span>
                    @endif
                </div>
                <button type="button" id="btn-salir" class="btn-salir-sesion">
                    <i class="bi bi-box-arrow-right"></i> Salir
                </button>
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
        jQuery("#btn-salir").on("click", function () {
            window.location.href = UrlGlobal + "backoffice/logout";
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
