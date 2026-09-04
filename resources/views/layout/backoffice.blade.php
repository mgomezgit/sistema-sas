<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Plataforma Reservas')</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/shepherd.js/dist/css/shepherd.css" rel="stylesheet">
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
            /* Fondos de los eventos del calendario. Las variantes "-soft" son
               translúcidas al 12%, así que sobre el fondo casi negro de este modo
               quedan prácticamente invisibles. Estas versiones son pasteles claros
               y opacos: mantienen la familia de color, se distinguen entre sí, y
               dejan leer el texto oscuro que llevan encima. */
            --warning-evento: #f4dc9e;
            --accent-evento: color-mix(in srgb, var(--accent) 42%, #ffffff);
            --success-evento: #a9e3bd;
            --danger-evento: #f3adb5;
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
            /* Sobre el fondo claro de este modo las variantes "-soft" ya se ven
               bien, así que los eventos del calendario las siguen usando tal cual
               (se mantiene idéntico a como se veía antes). */
            --warning-evento: var(--warning-soft);
            --accent-evento: var(--accent-soft);
            --success-evento: var(--success-soft);
            --danger-evento: var(--danger-soft);
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

        /* ---------- Confeti de celebración ---------- */
        .particula-confeti {
            position: fixed;
            top: -24px;
            z-index: 3000;
            pointer-events: none;
            will-change: transform, opacity;
        }

        @keyframes caidaConfeti {
            0% {
                transform: translate(0, 0) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translate(var(--desvio-x), 110vh) rotate(var(--giro-final));
                opacity: 0;
            }
        }

        /* ---------- Pantalla de bienvenida (una sola vez) ---------- */
        #overlay-bienvenida {
            position: fixed;
            inset: 0;
            z-index: 2500;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background-color: var(--bg-body);
            backdrop-filter: blur(6px);
        }

        #overlay-bienvenida.visible {
            display: flex;
            animation: aparecerOverlay 0.45s cubic-bezier(0.4, 0, 0.2, 1);
        }

        #overlay-bienvenida.saliendo {
            animation: salirOverlay 0.35s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        @keyframes aparecerOverlay {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes salirOverlay {
            from { opacity: 1; }
            to { opacity: 0; }
        }

        .contenido-bienvenida {
            text-align: center;
            max-width: 560px;
            animation: entradaContenidoBienvenida 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes entradaContenidoBienvenida {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .icono-bienvenida {
            width: 84px;
            height: 84px;
            border-radius: 50%;
            background-color: var(--accent-soft);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.4rem;
            margin: 0 auto 1.75rem;
        }

        .contenido-bienvenida h1 {
            color: var(--text-primary);
            font-weight: 700;
            font-size: clamp(1.8rem, 4.5vw, 2.6rem);
            line-height: 1.2;
            margin-bottom: 1rem;
        }

        .contenido-bienvenida p {
            color: var(--text-secondary);
            font-size: 1.02rem;
            line-height: 1.65;
            margin-bottom: 2rem;
        }

        #btn-empecemos {
            font-size: 1rem;
            padding: 0.8rem 2rem;
        }

        /* ---------- Drawer de primeros pasos ---------- */
        #drawer-onboarding {
            display: none;
        }

        /* Con la pestaña visible se reserva espacio a la derecha: si no, tapaba
           el borde de tablas y calendarios (columnas, paginación, buscador). */
        body.con-drawer-onboarding main {
            padding-right: 5.5rem;
        }

        /* Pestaña colapsada, anclada al borde derecho. */
        #pestana-onboarding {
            position: fixed;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            z-index: 1040;
            width: 52px;
            height: 220px;
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-right: none;
            border-radius: var(--radius-card) 0 0 var(--radius-card);
            box-shadow: var(--shadow-card);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.85rem;
            color: var(--text-primary);
            transition: var(--transition-base);
        }

        #pestana-onboarding:hover {
            background-color: var(--bg-card-hover);
            width: 58px;
        }

        #pestana-onboarding .icono-pestana {
            font-size: 1.35rem;
            color: var(--accent);
        }

        #pestana-onboarding .conteo-pestana {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-primary);
            writing-mode: vertical-rl;
            letter-spacing: 0.08em;
        }

        /* Panel deslizante. */
        #panel-onboarding {
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            width: 360px;
            max-width: 100vw;
            z-index: 1045;
            background-color: var(--bg-card);
            border-left: 1px solid var(--border-color);
            box-shadow: -12px 0 40px rgba(0, 0, 0, 0.28);
            padding: 1.5rem 1.35rem;
            overflow-y: auto;
            transform: translateX(100%);
            transition: transform 0.38s cubic-bezier(0.16, 1, 0.3, 1);
        }

        #panel-onboarding.abierto {
            transform: translateX(0);
        }

        .cabecera-onboarding {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            margin-bottom: 0.35rem;
        }

        .cabecera-onboarding .titulo-onboarding {
            color: var(--text-primary);
            font-weight: 700;
            font-size: 1.1rem;
            line-height: 1.3;
        }

        .btn-cerrar-onboarding {
            margin-left: auto;
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1.15rem;
            line-height: 1;
            padding: 0.15rem 0.3rem;
            border-radius: var(--radius-sm);
            transition: var(--transition-base);
        }

        .btn-cerrar-onboarding:hover {
            background-color: var(--bg-card-hover);
            color: var(--text-primary);
        }

        .subtitulo-onboarding {
            color: var(--text-secondary);
            font-size: 0.86rem;
            line-height: 1.5;
            margin-bottom: 1rem;
        }

        .barra-progreso-onboarding {
            height: 8px;
            background-color: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: 999px;
            overflow: hidden;
            margin-bottom: 1.1rem;
        }

        .relleno-progreso-onboarding {
            height: 100%;
            width: 0;
            background-color: var(--accent);
            border-radius: 999px;
            transition: width 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .paso-onboarding {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.7rem 0.6rem;
            margin-bottom: 0.2rem;
            border-radius: var(--radius-sm);
            font-size: 0.89rem;
            color: var(--text-primary);
            transition: var(--transition-base);
        }

        .paso-onboarding .icono-paso {
            font-size: 1.15rem;
            flex-shrink: 0;
        }

        .paso-onboarding.hecho .icono-paso {
            color: var(--success);
        }

        .paso-onboarding.pendiente .icono-paso {
            color: var(--text-muted);
        }

        .paso-onboarding.hecho .texto-paso {
            color: var(--text-secondary);
            text-decoration: line-through;
        }

        .paso-onboarding .texto-paso {
            flex: 1;
            line-height: 1.35;
        }

        /* Solo el primer paso pendiente late, para señalar qué sigue. */
        .paso-onboarding.siguiente {
            background-color: var(--accent-soft);
            animation: latidoPaso 2s ease-in-out infinite;
        }

        @keyframes latidoPaso {
            0%, 100% { box-shadow: 0 0 0 0 var(--accent-soft); }
            50% { box-shadow: 0 0 0 7px transparent; }
        }

        /* "Pop" del check cuando un paso acaba de completarse. */
        .icono-paso.recien-completado {
            animation: popCheck 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes popCheck {
            0% { transform: scale(0); }
            60% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }

        .btn-ir-paso {
            border: 1px solid var(--border-color);
            background-color: transparent;
            color: var(--accent);
            border-radius: var(--radius-sm);
            padding: 0.22rem 0.65rem;
            font-size: 0.78rem;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition-base);
            flex-shrink: 0;
        }

        .btn-ir-paso:hover {
            background-color: var(--accent-soft);
            border-color: var(--accent);
            color: var(--accent);
        }

        #contenedor-boton-final {
            margin-top: 1.25rem;
        }

        @media (max-width: 575.98px) {
            #panel-onboarding {
                width: 100vw;
            }
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

        /* ================= TOURS CONTEXTUALES (Shepherd.js) =================
           Se sobrescribe el tema morado por defecto de la librería para que
           las burbujas usen las mismas variables de color del panel, tanto en
           modo oscuro como claro. */

        .shepherd-element {
            background-color: var(--bg-card);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-card);
            border: 1px solid var(--border-color);
            max-width: 360px;
        }

        .shepherd-arrow:before {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
        }

        .shepherd-has-title .shepherd-content .shepherd-header {
            background-color: var(--bg-card);
            padding: 1rem 1.25rem 0.25rem;
        }

        .shepherd-title {
            color: var(--text-primary);
            font-weight: 700;
            font-size: 1.02rem;
        }

        .shepherd-text {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.5;
            padding: 0.5rem 1.25rem 1rem;
        }

        .shepherd-cancel-icon {
            color: var(--text-muted);
        }

        .shepherd-cancel-icon:hover {
            color: var(--text-primary);
        }

        .shepherd-footer {
            padding: 0 1.25rem 1.25rem;
            gap: 0.5rem;
        }

        .shepherd-button {
            background-color: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 0.85rem;
            padding: 0.45rem 0.9rem;
            transition: var(--transition-base);
        }

        .shepherd-button:not(:disabled):hover {
            background-color: var(--bg-card-hover);
            border-color: var(--border-color-strong);
            color: var(--text-primary);
        }

        .shepherd-button.btn-primario-accento {
            background-color: var(--accent);
            border-color: var(--accent);
            color: var(--text-sobre-accent);
        }

        .shepherd-button.btn-primario-accento:not(:disabled):hover {
            background-color: var(--accent-hover);
            border-color: var(--accent-hover);
            color: var(--text-sobre-accent);
        }

        .shepherd-modal-overlay-container {
            opacity: 0.55;
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
            <span class="nombre-marca" id="nombre-negocio-lateral" title="{{ session('nombre_negocio_sesion') ?? 'Plataforma Reservas' }}">
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
                                <i class="bi bi-building"></i>
                                <span id="nombre-negocio-sesion">{{ session('nombre_negocio_sesion') ?? 'Negocio' }}</span>
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

    @if (! \App\Models\Rol::esRolEmpleado(session('id_rol')) && session('tenant_id') !== null)
        {{-- Bienvenida y primeros pasos: solo para el administrador de un negocio. --}}
        <div id="overlay-bienvenida">
            <div class="contenido-bienvenida">
                <div class="icono-bienvenida">
                    <i class="bi bi-stars"></i>
                </div>
                <h1>Gracias por confiar en nosotros</h1>
                <p>
                    Nos alegra tenerte aquí. Dale tu color al panel y deja lista tu agenda:
                    te acompañamos paso a paso para que empieces a recibir clientes hoy mismo.
                </p>
                <button type="button" id="btn-empecemos" class="btn-primario-accento">
                    <i class="bi bi-rocket-takeoff"></i> Empecemos
                </button>
            </div>
        </div>

        <div id="drawer-onboarding">
            <button type="button" id="pestana-onboarding" aria-label="Primeros pasos">
                <i class="bi bi-rocket-takeoff icono-pestana"></i>
                <span class="conteo-pestana" id="conteo-onboarding">0/6</span>
            </button>

            <div id="panel-onboarding">
                <div class="cabecera-onboarding">
                    <div class="titulo-onboarding">Pon en marcha tu negocio</div>
                    <button type="button" class="btn-cerrar-onboarding" id="btn-cerrar-onboarding" aria-label="Colapsar">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
                <p class="subtitulo-onboarding">
                    Cinco cosas rápidas y tu agenda queda lista para recibir clientes.
                </p>

                <div class="barra-progreso-onboarding">
                    <div class="relleno-progreso-onboarding" id="relleno-progreso-onboarding"></div>
                </div>

                <div id="lista-pasos-onboarding"></div>

                <div id="contenedor-boton-final"></div>
            </div>
        </div>
    @endif

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios@1.7.7/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/shepherd.js/dist/js/shepherd.min.js"></script>

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
        // Lee el valor real de una variable CSS del tema. Se usa donde una librería
        // externa (por ejemplo SweetAlert2) no resuelve var(--nombre) por sí sola.
        //
        // Se consulta sobre <body> y no sobre :root porque las variables de modo y
        // acento se definen en body.modo-* / body.acento-*; leerlas desde :root
        // devolvía siempre cadena vacía. Las pocas que sí viven en :root se
        // resuelven igual aquí por herencia.
        function colorVariable(nombreVariable) {
            return getComputedStyle(document.body).getPropertyValue(nombreVariable).trim();
        }

        /**
         * Explosión breve de confeti. Los colores salen de las variables del tema,
         * así que la celebración sigue el acento configurado por cada negocio.
         * Las partículas se eliminan solas al terminar su animación.
         */
        function dispararConfeti(cantidad, origenX) {
            var totalParticulas = cantidad || 100;
            // Punto horizontal de origen en % de la pantalla (50 = centro).
            var centro = (origenX === undefined || origenX === null) ? 50 : origenX;
            var colores = [colorVariable('--accent'), colorVariable('--success'), colorVariable('--warning')];

            for (var i = 0; i < totalParticulas; i++) {
                var particula = document.createElement('div');
                particula.className = 'particula-confeti';

                var tamano = 8 + Math.random() * 6;                  // entre 8 y 14px
                var duracion = 1.6 + Math.random() * 0.7;            // entre 1.6s y 2.3s
                var desvioX = (Math.random() * 320) - 160;           // dispersión lateral
                var giro = 360 + Math.random() * 720;                // vueltas al caer

                // Se reparten alrededor del origen, sin salirse de la pantalla.
                var posicion = centro + (Math.random() * 30 - 15);
                particula.style.left = Math.max(0, Math.min(100, posicion)) + 'vw';
                particula.style.width = tamano + 'px';
                particula.style.height = tamano + 'px';
                particula.style.backgroundColor = colores[Math.floor(Math.random() * colores.length)];
                particula.style.borderRadius = Math.random() > 0.5 ? '50%' : '2px';
                particula.style.setProperty('--desvio-x', desvioX + 'px');
                particula.style.setProperty('--giro-final', giro + 'deg');
                particula.style.animation = 'caidaConfeti ' + duracion + 's cubic-bezier(0.25, 0.6, 0.5, 1) forwards';
                particula.style.animationDelay = (Math.random() * 0.35) + 's';

                document.body.appendChild(particula);

                // Se limpia al terminar; el timeout cubre también el retraso inicial.
                (function (elemento, milisegundos) {
                    setTimeout(function () {
                        if (elemento.parentNode) {
                            elemento.parentNode.removeChild(elemento);
                        }
                    }, milisegundos);
                })(particula, (duracion + 0.5) * 1000);
            }
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

        /* ================= TOURS CONTEXTUALES (Shepherd.js) =================
         * Burbujas guía ancladas a campos concretos de cada pantalla, para
         * acompañar al usuario mientras completa un paso del onboarding.
         * Se disparan cuando la URL trae "?guia=<id>" (ver botón "Ir" del
         * drawer) y viven en sessionStorage: una vez vistos u omitidos, no
         * vuelven a aparecer dentro de la misma sesión de navegador.
         */

        var CLAVE_TOURS_VISTOS = 'tours_contextuales_vistos';

        function tourYaVisto(idTour) {
            var vistos = [];

            try {
                vistos = JSON.parse(sessionStorage.getItem(CLAVE_TOURS_VISTOS)) || [];
            } catch (e) {
                vistos = [];
            }

            return vistos.indexOf(idTour) !== -1;
        }

        function marcarTourVisto(idTour) {
            var vistos = [];

            try {
                vistos = JSON.parse(sessionStorage.getItem(CLAVE_TOURS_VISTOS)) || [];
            } catch (e) {
                vistos = [];
            }

            if (vistos.indexOf(idTour) === -1) {
                vistos.push(idTour);
                sessionStorage.setItem(CLAVE_TOURS_VISTOS, JSON.stringify(vistos));
            }
        }

        /**
         * Crea e inicia un tour de Shepherd con el tema del panel ya aplicado.
         *
         * @param {string} idTour  Identificador único (coincide con el valor
         *                         de "?guia=" que manda el drawer).
         * @param {Array}  pasos   Objetos { attachTo, title, text, [beforeShowMe] }.
         *                         "beforeShowMe" es opcional: una función que se
         *                         ejecuta justo antes de mostrar ese paso (por
         *                         ejemplo, abrir un modal para poder señalar un
         *                         campo que vive dentro de él).
         */
        function iniciarTourContextual(idTour, pasos) {
            if (typeof Shepherd === 'undefined' || tourYaVisto(idTour)) {
                return;
            }

            // Los pasos cuyo selector no existe en el DOM se descartan de
            // antemano: Shepherd no sabe recuperarse de un selector vacío.
            var pasosValidos = pasos.filter(function (paso) {
                var selector = paso.attachTo && paso.attachTo.element;

                return !selector || document.querySelector(selector) !== null;
            });

            if (pasosValidos.length === 0) {
                return;
            }

            var tour = new Shepherd.Tour({
                useModalOverlay: true,
                defaultStepOptions: {
                    classes: 'shepherd-theme-panel',
                    scrollTo: { behavior: 'smooth', block: 'center' },
                    cancelIcon: { enabled: true }
                }
            });

            pasosValidos.forEach(function (paso, indice) {
                var esPrimero = indice === 0;
                var esUltimo = indice === pasosValidos.length - 1;
                var botones = [];

                if (!esPrimero) {
                    botones.push({ text: 'Atrás', action: tour.back, classes: 'shepherd-btn-secundario' });
                }

                botones.push({
                    text: esUltimo ? 'Entendido' : 'Siguiente',
                    classes: 'btn-primario-accento',
                    action: function () {
                        // Permite, por ejemplo, abrir el modal donde vive el
                        // siguiente campo antes de que Shepherd intente
                        // señalarlo. Si "beforeShowMe" devuelve una promesa
                        // (p. ej. resuelta cuando el modal termina de abrirse),
                        // se espera antes de avanzar; si no, se usa un margen
                        // fijo prudente.
                        var siguiente = pasosValidos[indice + 1];

                        if (siguiente && typeof siguiente.beforeShowMe === 'function') {
                            var resultado = siguiente.beforeShowMe();

                            if (resultado && typeof resultado.then === 'function') {
                                resultado.then(function () { tour.next(); });
                            } else {
                                setTimeout(function () { tour.next(); }, 350);
                            }

                            return;
                        }

                        tour.next();
                    }
                });

                tour.addStep({
                    id: idTour + '-' + indice,
                    title: paso.title,
                    text: paso.text,
                    attachTo: paso.attachTo,
                    buttons: esPrimero
                        ? [{ text: 'Saltar', classes: 'shepherd-btn-secundario', action: tour.cancel }].concat(botones)
                        : botones
                });
            });

            tour.on('complete', function () { marcarTourVisto(idTour); });
            tour.on('cancel', function () { marcarTourVisto(idTour); });

            tour.start();
        }

        /**
         * Punto de entrada para cada vista: si la URL trae "?guia=<idPaso>" y
         * ese paso del onboarding todavía está pendiente, ejecuta "callback"
         * (que normalmente arma los pasos y llama a iniciarTourContextual).
         * Si el paso ya está completo, no molesta con la guía.
         */
        window.iniciarGuiaSiCorresponde = function (idPaso, callback) {
            var parametros = new URLSearchParams(window.location.search);

            if (parametros.get('guia') !== idPaso || typeof axiosSipleInterno !== 'function') {
                return;
            }

            axiosSipleInterno('GET', 'request/negocio/progreso-onboarding', {}, {}, false, function (respuesta) {
                if (respuesta.error != 0) {
                    return;
                }

                var pasos = (respuesta.data.onboarding && respuesta.data.onboarding.pasos) || [];
                var paso = pasos.filter(function (p) { return p.id === idPaso; })[0];

                if (paso && paso.completado === true) {
                    return;
                }

                callback();
            });
        };

        /* ================= BIENVENIDA Y PRIMEROS PASOS ================= */

        // Texto y destino de cada uno de los 6 pasos. El botón de cierre no es
        // un paso: se muestra aparte cuando los 6 quedan completados.
        var PASOS_ONBOARDING = {
            personalizar: { texto: 'Personaliza los colores de tu panel', destino: 'backoffice/personalizar' },
            horario: { texto: 'Define tus días y horas de atención', destino: 'backoffice/configuracion' },
            recurso: { texto: 'Registra el primer servicio que ofreces', destino: 'backoffice/recursos' },
            empleado: { texto: 'Suma a alguien de tu equipo', destino: 'backoffice/empleados' },
            cliente: { texto: 'Registra a tu primer cliente', destino: 'backoffice/clientes' },
            reserva: { texto: 'Agenda tu primera reserva', destino: 'backoffice/reservas' }
        };

        // Se guardan los ids ya completados (no solo el total) para saber cuál
        // paso es el nuevo y animar su check.
        var CLAVE_PROGRESO_SESION = 'onboarding_ids_completados';

        function abrirDrawerOnboarding() {
            jQuery('#panel-onboarding').addClass('abierto');
        }

        function cerrarDrawerOnboarding() {
            jQuery('#panel-onboarding').removeClass('abierto');
        }

        function mostrarBienvenida() {
            jQuery('#overlay-bienvenida').addClass('visible');
            // Dos ráfagas simultáneas, una por cada lado de la pantalla.
            dispararConfeti(110, 15);
            dispararConfeti(110, 85);
        }

        function pintarDrawerOnboarding(onboarding) {
            var drawer = jQuery('#drawer-onboarding');

            // Sin drawer en el DOM (empleado o super admin) no hay nada que hacer.
            if (drawer.length === 0) {
                return false;
            }

            if (!onboarding || onboarding.tour_completado === true) {
                drawer.hide();
                jQuery('body').removeClass('con-drawer-onboarding');

                return false;
            }

            // Los 6 pasos vienen del backend; el total nunca se escribe a mano.
            var pasos = onboarding.pasos || [];
            var total = pasos.length;

            var idsCompletados = pasos.filter(function (paso) {
                return paso.completado === true;
            }).map(function (paso) {
                return paso.id;
            });

            var completados = idsCompletados.length;

            // Se comparan ids, no cantidades: así se sabe exactamente cuál paso
            // es nuevo aunque se completen en cualquier orden.
            var previos = [];

            try {
                previos = JSON.parse(sessionStorage.getItem(CLAVE_PROGRESO_SESION)) || [];
            } catch (e) {
                previos = [];
            }

            var recienCompletados = idsCompletados.filter(function (id) {
                return previos.indexOf(id) === -1;
            });

            // En la primera carga de la sesión no se celebra lo ya hecho antes.
            var primeraLectura = sessionStorage.getItem(CLAVE_PROGRESO_SESION) === null;
            var huboAvance = !primeraLectura && recienCompletados.length > 0;

            jQuery('#conteo-onboarding').text(completados + '/' + total);
            jQuery('#relleno-progreso-onboarding').css('width', (total ? (completados / total * 100) : 0) + '%');

            // La lista se reconstruye siempre desde la respuesta fresca.
            var lista = jQuery('#lista-pasos-onboarding');
            lista.empty();

            var primerPendienteMarcado = false;

            pasos.forEach(function (paso) {
                var definicion = PASOS_ONBOARDING[paso.id];

                if (!definicion) {
                    return;
                }

                var hecho = paso.completado === true;
                var icono = hecho ? 'bi-check-circle-fill' : 'bi-circle';
                var clases = hecho ? 'hecho' : 'pendiente';

                // Solo el primer pendiente late, para señalar qué sigue.
                if (!hecho && !primerPendienteMarcado) {
                    clases += ' siguiente';
                    primerPendienteMarcado = true;
                }

                var clasesIcono = 'bi ' + icono + ' icono-paso';

                if (hecho && recienCompletados.indexOf(paso.id) !== -1 && !primeraLectura) {
                    clasesIcono += ' recien-completado';
                }

                // Un paso ya cumplido no necesita botón para ir a hacerlo.
                // El parámetro "guia" le indica a la pantalla de destino que
                // debe iniciar su tour contextual con Shepherd.
                var boton = hecho
                    ? ''
                    : '<a href="' + UrlGlobal + definicion.destino + '?guia=' + paso.id + '" class="btn-ir-paso">Ir</a>';

                lista.append(
                    '<div class="paso-onboarding ' + clases + '">' +
                    '<i class="' + clasesIcono + '"></i>' +
                    '<span class="texto-paso">' + definicion.texto + '</span>' +
                    boton +
                    '</div>'
                );
            });

            // El botón final va aparte de la lista y solo con los 6 pasos hechos.
            var contenedorFinal = jQuery('#contenedor-boton-final');
            contenedorFinal.empty();

            if (total > 0 && completados === total) {
                contenedorFinal.html(
                    '<button type="button" id="btn-finalizar-onboarding" class="btn-primario-accento w-100">' +
                    '<i class="bi bi-stars"></i> ¡Genial, ya terminé!' +
                    '</button>'
                );
            }

            drawer.show();
            jQuery('body').addClass('con-drawer-onboarding');

            if (huboAvance) {
                dispararConfeti(110);
                // Se abre solo, esté el usuario en la pantalla que esté.
                abrirDrawerOnboarding();
            }

            sessionStorage.setItem(CLAVE_PROGRESO_SESION, JSON.stringify(idsCompletados));

            // La bienvenida se muestra una única vez, antes de cualquier paso.
            if (onboarding.bienvenida_vista === false) {
                mostrarBienvenida();
            }

            // Se avisa a quien llamó si acaba de completarse un paso, para que
            // no muestre encima un modal que tape la celebración.
            return huboAvance;
        }

        function cargarProgresoOnboarding(alTerminar) {
            // Sin drawer (empleado o super admin) se responde "sin avance" para
            // que la vista muestre su aviso normal.
            if (jQuery('#drawer-onboarding').length === 0) {
                if (alTerminar) {
                    alTerminar(false);
                }

                return;
            }

            axiosSipleInterno('GET', 'request/negocio/progreso-onboarding', {}, {}, false, function (respuesta) {
                var huboAvance = false;

                if (respuesta.error == 0) {
                    huboAvance = pintarDrawerOnboarding(respuesta.data.onboarding) === true;
                }

                if (alTerminar) {
                    alTerminar(huboAvance);
                }
            });
        }

        /**
         * Aviso de guardado correcto para las vistas que pueden completar un paso
         * de los primeros pasos (recurso, empleado, cliente, reserva, tema y
         * horario).
         *
         * Si el guardado completó un paso, la celebración del drawer (confeti,
         * check y el siguiente paso a la vista) ES el aviso: no se abre ningún
         * modal ni se recarga la página, para no tapar justo lo que se acaba de
         * destacar. Si no completó ningún paso, se muestra el aviso de siempre.
         *
         * Es global a propósito: la sección de scripts de cada vista se imprime
         * después de este bloque, así que la función ya está definida cuando sus
         * callbacks la invocan.
         */
        window.avisarGuardado = function (mensaje) {
            cargarProgresoOnboarding(function (huboAvance) {
                if (!huboAvance) {
                    notificarUsuario(mensaje, 'success');
                }
            });
        };

        jQuery('#btn-empecemos').on('click', function () {
            axiosSipleInterno('POST', 'request/negocio/marcar-bienvenida', {}, {}, false);

            var overlay = jQuery('#overlay-bienvenida');
            overlay.addClass('saliendo');

            setTimeout(function () {
                overlay.removeClass('visible saliendo');
                // Tras la bienvenida se abre el drawer para guiar el primer paso.
                abrirDrawerOnboarding();
            }, 350);
        });

        jQuery('#pestana-onboarding').on('click', function () {
            jQuery('#panel-onboarding').toggleClass('abierto');
        });

        jQuery('#btn-cerrar-onboarding').on('click', function () {
            // Solo colapsa: la pestaña sigue disponible.
            cerrarDrawerOnboarding();
        });

        jQuery('#contenedor-boton-final').on('click', '#btn-finalizar-onboarding', function () {
            axiosSipleInterno('POST', 'request/negocio/completar-onboarding', {}, {}, true, function (respuesta) {
                if (respuesta.error == 0) {
                    dispararConfeti(280);
                    cerrarDrawerOnboarding();
                    jQuery('#drawer-onboarding').hide();
                    jQuery('body').removeClass('con-drawer-onboarding');
                    sessionStorage.removeItem(CLAVE_PROGRESO_SESION);
                } else {
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        });

        jQuery(document).ready(function () {
            cargarProgresoOnboarding();
        });
    </script>

    @yield('scripts')
</body>
</html>
