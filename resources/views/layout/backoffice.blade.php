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
            /* Las dos opciones de texto para los eventos del calendario. Cuál se
               usa lo decide el JS según la luminancia real del fondo del evento. */
            --texto-evento-oscuro: #2a2320;
            --texto-evento-claro: #f8f8f9;
            --texto-sobre-avatar: #2a2320;
            /* Derivadas del acento: siguen automáticamente al acento activo. */
            --accent-glow: var(--accent-soft);
            --shadow-glow: 0 0 0 1px var(--accent-soft), 0 4px 20px var(--accent-soft);

            /* Resplandor del ítem activo del menú lateral.
               Van aquí, en :root, y NO en cada bloque body.acento-*, para no
               romper la regla de "agregar un acento = 3 variables": color-mix
               deriva la intensidad del --accent que esté activo en ese momento.
               No se puede usar rgba(var(--accent), 0.3): --accent es un hex
               sólido, no canales sueltos, así que rgba() no lo acepta.
               Los valores de modo claro se ajustan más abajo. */
            --menu-glow-fuerte: color-mix(in srgb, var(--accent) 36%, transparent);
            --menu-glow-suave: color-mix(in srgb, var(--accent) 16%, transparent);
            /* Resplandor concentrado de la cápsula del ítem activo. */
            --capsula-glow: color-mix(in srgb, var(--accent) 45%, transparent);

            /* Segundo nivel de acento translúcido, más presente que
               --accent-soft (12%). Va aquí y no en cada body.acento-* para no
               romper la regla de "agregar un acento = 3 variables": color-mix
               lo deriva del --accent que esté activo. */
            --accent-soft2: color-mix(in srgb, var(--accent) 26%, transparent);

            /* Resplandor ambiental que rodea el sidebar completo: un aro ceñido
               al borde y un halo amplio y difuso. */
            --aro-ambiental: color-mix(in srgb, var(--accent) 20%, transparent);
            --halo-ambiental: color-mix(in srgb, var(--accent) 16%, transparent);

            /* Glow del navbar, concentrado hacia abajo (más presente en el
               borde inferior que arriba o a los lados). */
            --navbar-glow: color-mix(in srgb, var(--accent) 26%, transparent);
            --navbar-aro: color-mix(in srgb, var(--accent) 14%, transparent);

            /* Verde de estado "en línea". Es el ÚNICO color fijo fuera del
               sistema de tema: no representa la marca del negocio sino un estado
               universal (conectado / desconectado), igual que el rojo de error.
               Si siguiera al acento, un negocio con acento rojo mostraría
               "en línea" en rojo, que comunica lo contrario. */
            --estado-en-linea: #2ecc71;

            /* Geometría del armazón. El ancho del sidebar es una variable para
               que el margen del contenido lo siga solo, sin repetir el número:
               al colapsar basta con cambiarla en body. */
            --ancho-sidebar: 250px;
            --ancho-sidebar-colapsado: 84px;
            --gap-flotante: 16px;
            /* Curva con rebote suave para el colapso. */
            --curva-elastica: cubic-bezier(.34, 1.56, .64, 1);
        }

        /* Estado colapsado del sidebar (lo activa el botón de la cabecera). */
        body.sidebar-colapsado {
            --ancho-sidebar: var(--ancho-sidebar-colapsado);
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
            /* Paleta de los avatares de las tablas; el nombre decide cuál toca. */
            --avatar-1: #e8c67a;
            --avatar-2: #9fc0e8;
            --avatar-3: #a4cfae;
            --avatar-4: #e6a6ad;
            --avatar-5: #bcaadd;
            --avatar-6: #eab98d;
            --avatar-7: #93c9c6;
            --avatar-8: #d6bb98;
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
            /* El sidebar de este modo es claro: el mismo resplandor del modo
               oscuro se ve sucio encima, sobre todo con los acentos más claros
               (amarillo, dorado). Aquí baja de intensidad. */
            --menu-glow-fuerte: color-mix(in srgb, var(--accent) 24%, transparent);
            --menu-glow-suave: color-mix(in srgb, var(--accent) 11%, transparent);
            /* Paleta de los avatares de las tablas; el nombre decide cuál toca. */
            --avatar-1: #f3d9a4;
            --avatar-2: #cfe3f7;
            --avatar-3: #d5ecd9;
            --avatar-4: #f7d6d9;
            --avatar-5: #e2d9f3;
            --avatar-6: #fadfc9;
            --avatar-7: #cfeceb;
            --avatar-8: #eee0cf;
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

        /* Píldora neutra, en la misma familia que las demás: antes usaba el color
           "muted", que sobre el fondo del input casi no se distinguía. */
        .badge-estado-inactivo {
            background-color: var(--bg-card-hover);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
        }

        /* Rol del usuario, con el mismo tratamiento de píldora pastel. */
        .badge-rol {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.3rem 0.7rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
            white-space: nowrap;
            background-color: var(--accent-soft);
            color: var(--accent);
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

        /* Filas con algo más de aire que el Bootstrap base (0.5rem), pero sin
           pasarse: con 0.85rem las tablas quedaban demasiado altas en pantallas
           de portátil. Va aparte de .fila-tabla-hover para poder combinarlas sin
           que una pise a la otra. */
        .fila-tabla-amplia tbody td {
            padding-top: 0.6rem;
            padding-bottom: 0.6rem;
            font-size: 0.875rem;
            vertical-align: middle;
        }

        .fila-tabla-amplia thead th {
            padding-top: 0.55rem;
            padding-bottom: 0.55rem;
            font-size: 0.8rem;
        }

        /* ---------- Avatar de iniciales para las tablas ---------- */
        .avatar-iniciales {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            font-size: 0.7rem;
            font-weight: 700;
            line-height: 1;
            color: var(--texto-sobre-avatar);
            flex-shrink: 0;
        }

        .avatar-iniciales i {
            font-size: 0.82rem;
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

        /* Tarjeta flotante: se despega de los bordes y deja ver el fondo del
           body alrededor, para que sidebar y navbar se lean como dos piezas
           independientes y no como un marco pegado a la pantalla. */
        #sidebar {
            position: fixed;
            top: var(--gap-flotante);
            left: var(--gap-flotante);
            bottom: var(--gap-flotante);
            width: var(--ancho-sidebar);
            /* Gradiente casi imperceptible: solo da sensación de volumen. */
            background-image: linear-gradient(
                180deg,
                var(--bg-sidebar) 0%,
                color-mix(in srgb, var(--bg-sidebar) 92%, black 8%) 100%
            );
            display: flex;
            flex-direction: column;
            /* El scroll vive ahora en #menu-lateral, no aquí: así la tarjeta de
               perfil del pie queda anclada y no se va con el desplazamiento. */
            overflow: hidden;
            z-index: 1030;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-card);
            transition: width 0.5s var(--curva-elastica);
            /* Resplandor ambiental: un aro delgado ceñido al borde, un halo
               amplio que envuelve toda la tarjeta, y por último la sombra de
               profundidad en negro. En ese orden: lo más ceñido primero. */
            box-shadow:
                0 0 0 1px var(--aro-ambiental),
                0 0 52px var(--halo-ambiental),
                0 20px 50px rgba(0, 0, 0, 0.32);
        }

        /* En modo claro el sidebar es claro: el degradado tiene que ACLARAR
           hacia abajo, no oscurecer, o se ve como una sombra sucia. */
        body.modo-claro #sidebar {
            background-image: linear-gradient(
                180deg,
                var(--bg-sidebar) 0%,
                color-mix(in srgb, var(--bg-sidebar) 92%, white 8%) 100%
            );
        }

        /* Scrollbar temático y delgado. */
        #sidebar,
        #menu-lateral {
            scrollbar-width: thin;
            scrollbar-color: color-mix(in srgb, var(--accent) 20%, transparent) transparent;
        }

        #sidebar::-webkit-scrollbar,
        #menu-lateral::-webkit-scrollbar {
            width: 5px;
        }

        #sidebar::-webkit-scrollbar-track,
        #menu-lateral::-webkit-scrollbar-track {
            background: transparent;
        }

        #sidebar::-webkit-scrollbar-thumb,
        #menu-lateral::-webkit-scrollbar-thumb {
            background-color: color-mix(in srgb, var(--accent) 20%, transparent);
            border-radius: 999px;
        }

        #sidebar::-webkit-scrollbar-thumb:hover,
        #menu-lateral::-webkit-scrollbar-thumb:hover {
            background-color: color-mix(in srgb, var(--accent) 38%, transparent);
        }

        #sidebar .sidebar-header {
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 1.35rem 1.1rem;
            border-bottom: 1px solid var(--border-color);
        }

        /* Franja de luz superior: firma visual, apenas un hilo de acento que se
           desvanece hacia los lados. */
        #sidebar .sidebar-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background-image: linear-gradient(90deg, transparent, var(--accent), transparent);
            /* "Respiración": la franja late muy despacio, lo justo para que el
               panel se sienta vivo sin distraer. */
            animation: respiracionFranja 3.4s ease-in-out infinite;
        }

        @keyframes respiracionFranja {
            0%, 100% {
                opacity: 0.55;
                filter: brightness(1);
            }
            50% {
                opacity: 1;
                filter: brightness(1.35);
            }
        }

        #sidebar .sidebar-header .logo-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--accent);
            box-shadow: 0 0 10px var(--accent-glow);
            flex-shrink: 0;
            /* Indicador de "sesión activa". */
            animation: pulsoSesion 2.8s ease-in-out infinite;
        }

        @keyframes pulsoSesion {
            0%, 100% {
                opacity: 1;
                transform: scale(1);
            }
            50% {
                opacity: 0.45;
                transform: scale(0.82);
            }
        }

        /* Botón que colapsa/expande el sidebar: badge rectangular redondeado,
           más ancho que alto, con dos chevrons enfrentados. */
        #btn-colapsar-sidebar {
            margin-left: auto;
            flex-shrink: 0;
            width: 32px;
            height: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1px;
            background-color: color-mix(in srgb, var(--accent) 8%, transparent);
            border: 1px solid var(--border-color);
            border-radius: 9px;
            color: var(--text-secondary);
            font-size: 0.6rem;
            cursor: pointer;
            transition: transform 0.25s var(--curva-elastica),
                        box-shadow 0.25s ease, color 0.2s ease,
                        border-color 0.2s ease, background-color 0.2s ease;
        }

        #btn-colapsar-sidebar:hover {
            color: var(--accent);
            border-color: var(--accent);
            background-color: color-mix(in srgb, var(--accent) 14%, transparent);
            box-shadow: 0 0 12px var(--accent-soft2);
            transform: scale(1.06);
        }

        /* Sensación de "presionado". */
        #btn-colapsar-sidebar:active {
            transform: scale(0.9);
        }

        /* Cada chevron gira sobre sí mismo: "‹ ›" pasa a "› ‹". Rotar el par
           completo no serviría, porque el conjunto es simétrico y la vuelta de
           180° no se notaría. */
        #btn-colapsar-sidebar i {
            display: block;
            line-height: 1;
            transition: transform 0.5s var(--curva-elastica);
        }

        body.sidebar-colapsado #btn-colapsar-sidebar i {
            transform: rotate(180deg);
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
            overflow-y: auto;
            overflow-x: hidden;
            /* Sin esto un hijo de flex no se deja encoger por debajo de su
               contenido y el scroll nunca aparecería. */
            min-height: 0;
        }

        .menu-item {
            position: relative;
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

        /* Cada ícono va dentro de un "chip" redondeado. Se estiliza el propio
           <i>, así ninguna vista tiene que cambiar su marcado. La flecha de los
           grupos colapsables queda fuera: no es un ícono de sección. */
        .menu-item i:not(.icono-flecha-submenu) {
            font-size: 1rem;
            width: 29px;
            height: 29px;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background-color: color-mix(in srgb, var(--accent) 8%, transparent);
            /* El escalado se anima con transform, que no refluye: la fila no se
               mueve ni un píxel cuando el ícono crece. */
            transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1),
                        background-color 0.2s ease, color 0.2s ease;
        }

        /* Los labels se deslizan mientras se apagan, no solo desvanecen. */
        .menu-item > span {
            transition: opacity 0.22s ease, transform 0.22s ease;
        }

        .menu-item:hover i:not(.icono-flecha-submenu) {
            background-color: color-mix(in srgb, var(--accent) 16%, transparent);
        }

        .menu-item.active i:not(.icono-flecha-submenu) {
            background-color: color-mix(in srgb, var(--accent) 24%, transparent);
        }

        /* Etiqueta discreta que agrupa visualmente los ítems. No es un enlace ni
           un contenedor: solo un rótulo, sin caja ni borde. */
        .etiqueta-seccion {
            color: var(--text-secondary);
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            padding: 0 1rem;
            margin: 1.1rem 0.6rem 0.35rem;
            opacity: 0.7;
            user-select: none;
        }

        #menu-lateral .etiqueta-seccion:first-child {
            margin-top: 0.25rem;
        }

        /* Enlace que cuelga de un grupo desplegable (por ejemplo, cada reporte).
           El indentado lo pone ahora la línea del árbol (el contenedor), así que
           aquí solo queda el espacio entre esa línea y el ícono. */
        .menu-item.submenu-item {
            position: relative;
            padding-left: 1.15rem;
            padding-right: 0.8rem;
            font-size: 0.85rem;
            /* Un respiro vertical: además de separar los hijos, le da sitio al
               resplandor del activo para no chocar contra el recorte del
               contenedor que anima la apertura. */
            margin: 2px 0.6rem 2px 0;
        }

        .menu-item.submenu-item i {
            font-size: 0.92rem;
        }

        /* Estas dos reglas van prefijadas con "body" a propósito.
           Más arriba existe "body.modo-claro .menu-item { color: ... }", que en
           modo claro pesa más (0,0,2,1) que un ".menu-item:hover" suelto (0,0,2,0)
           y le ganaba el color: por eso hasta ahora, en modo claro, el ítem activo
           se veía con fondo y borde de acento pero el TEXTO seguía gris.
           Con "body" delante ambas quedan en 0,0,2,1 y, al declararse después,
           mandan en los dos modos sin tener que tocar la regla de modo claro. */
        /* El hover levanta apenas la fila y le pone una sombra corta: da la
           sensación de que el ítem se despega, sin cápsula (esa es del activo). */
        body .menu-item:hover {
            background-color: var(--bg-card-hover);
            color: var(--accent);
            text-decoration: none;
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.14);
        }

        .menu-item:hover i:not(.icono-flecha-submenu) {
            transform: scale(1.08);
        }

        /* El activo YA NO se rellena de color: se marca con una cápsula lateral
           corta y su propio resplandor concentrado. */
        body .menu-item.active {
            background-color: transparent;
            border-left-color: transparent;
            color: var(--accent);
            font-weight: 600;
        }

        .menu-item.active i:not(.icono-flecha-submenu) {
            color: var(--accent);
        }

        /* La cápsula. Va en ::after porque ::before ya lo usa la rama del árbol
           en los hijos de submenú: así conviven sin pisarse. */
        .menu-item.active::after {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 60%;
            border-radius: 999px;
            background-color: var(--accent);
            /* El resplandor se concentra en la barra, no se dispersa por la fila. */
            box-shadow: 0 0 9px var(--capsula-glow);
        }

        /* ---------- Submenú en árbol ---------- */

        /* Línea troncal: cae justo debajo del ícono del padre. */
        .submenu-lateral-contenido {
            margin-left: 2.2rem;
            border-left: 1.5px solid var(--border-color);
            transition: border-color 0.25s ease;
        }

        /* Cuando el hijo activo vive dentro de este grupo, el tronco se tiñe del
           acento para que la jerarquía se lea de un vistazo. Si el navegador no
           soporta :has(), simplemente se queda en --border-color: degrada bien. */
        .submenu-lateral-contenido:has(.menu-item.submenu-item.active) {
            border-left-color: var(--accent);
        }

        /* Rama horizontal que une el tronco con cada hijo. */
        .menu-item.submenu-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 0.72rem;
            height: 1.5px;
            background-color: var(--border-color);
            transition: background-color 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body .menu-item.submenu-item:hover::before,
        body .menu-item.submenu-item.active::before {
            background-color: var(--accent);
        }

        /* En los hijos la cápsula se corre hacia la derecha, justo donde termina
           la rama: así la rama conecta el tronco CON la cápsula, en vez de
           quedar una encima de la otra. */
        .menu-item.submenu-item.active::after {
            left: 0.78rem;
            height: 55%;
        }

        /* Los hijos entran con un fade + deslizamiento corto. Se listan las
           propiedades una a una en vez de "all" para no pisar la transición del
           ícono ni la del fondo. */
        .submenu-lateral .menu-item.submenu-item {
            opacity: 0;
            transform: translateX(-6px);
            transition: opacity 0.25s ease, transform 0.25s ease,
                        background-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
        }

        .submenu-lateral.abierto .menu-item.submenu-item {
            opacity: 1;
            transform: translateX(0);
        }

        /* Escalonado leve al abrir. Va solo en ".abierto" para que al cerrar se
           desvanezcan de inmediato, sin retardo que se sienta pesado. */
        .submenu-lateral.abierto .menu-item.submenu-item:nth-child(1) { transition-delay: 0.04s; }
        .submenu-lateral.abierto .menu-item.submenu-item:nth-child(2) { transition-delay: 0.08s; }
        .submenu-lateral.abierto .menu-item.submenu-item:nth-child(3) { transition-delay: 0.12s; }
        .submenu-lateral.abierto .menu-item.submenu-item:nth-child(4) { transition-delay: 0.16s; }
        .submenu-lateral.abierto .menu-item.submenu-item:nth-child(5) { transition-delay: 0.20s; }

        /* ---------- Grupo desplegable del menú lateral ---------- */

        /* El padre es un botón (no navega): solo abre/cierra sus subopciones. */
        .menu-item.menu-padre {
            width: calc(100% - 1.2rem);
            background: none;
            border: none;
            border-left: 3px solid transparent;
            cursor: pointer;
            font-family: inherit;
        }

        /* Bloque que arranca su propia sección sin necesitar un rótulo encima.
           Lleva además un lavado de acento que se desvanece hacia la derecha,
           para que la sección de pago se sienta distinta sin gritar. */
        .menu-item.menu-padre-separado {
            margin-top: 1rem;
            padding-top: 0.85rem;
            border-top: 1px solid var(--border-color);
            border-top-left-radius: 0;
            border-top-right-radius: 0;
            background-image: linear-gradient(
                90deg,
                color-mix(in srgb, var(--accent) 8%, transparent),
                transparent
            );
        }

        /* ---------- Sidebar colapsado (solo íconos) ---------- */

        /* Los textos se apagan con opacidad + ancho 0 en vez de display:none,
           para que la transición se vea fluida y no un corte seco, y además se
           corren hacia la izquierda mientras desaparecen. */
        body.sidebar-colapsado .menu-item > span,
        body.sidebar-colapsado #sidebar .sidebar-header .nombre-marca,
        body.sidebar-colapsado .datos-perfil-sidebar {
            opacity: 0;
            width: 0;
            transform: translateX(-8px);
            overflow: hidden;
            white-space: nowrap;
            transition: opacity 0.22s ease, transform 0.22s ease, width 0.3s ease;
        }

        /* Los rótulos de sección y los submenús desaparecen por completo:
           colapsado no hay sitio donde leerlos. */
        body.sidebar-colapsado .etiqueta-seccion,
        body.sidebar-colapsado .submenu-lateral,
        body.sidebar-colapsado .icono-flecha-submenu {
            display: none;
        }

        /* Íconos centrados en la fila. */
        body.sidebar-colapsado .menu-item {
            justify-content: center;
            gap: 0;
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }

        body.sidebar-colapsado .menu-item.menu-padre {
            width: calc(100% - 1.2rem);
        }

        /* La cápsula se pega al borde del ítem también en modo colapsado. */
        body.sidebar-colapsado .menu-item.active::after {
            left: 0;
        }

        body.sidebar-colapsado #sidebar .sidebar-header {
            justify-content: center;
            padding-left: 0.5rem;
            padding-right: 0.5rem;
            gap: 0.4rem;
        }

        body.sidebar-colapsado #btn-colapsar-sidebar {
            margin-left: 0;
        }

        /* Colapsado, la tarjeta de perfil se reduce al avatar centrado. */
        body.sidebar-colapsado .tarjeta-perfil {
            padding: 0.5rem;
            justify-content: center;
            gap: 0;
        }

        body.sidebar-colapsado #btn-opciones-perfil {
            display: none;
        }

        /* ---------- Tarjeta de perfil del pie del sidebar ---------- */

        .pie-sidebar {
            padding: 0.75rem;
            margin-top: auto;
        }

        .tarjeta-perfil {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.6rem;
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            /* Anillo tenue de acento, para que la tarjeta no se vea plana. */
            box-shadow: 0 0 0 1px color-mix(in srgb, var(--accent) 10%, transparent);
            transition: var(--transition-base);
        }

        .avatar-perfil {
            position: relative;
            width: 38px;
            height: 38px;
            flex-shrink: 0;
            border-radius: 50%;
            background-color: var(--accent-soft);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.82rem;
        }

        /* Punto de "en línea" en la esquina del avatar. */
        .punto-en-linea {
            position: absolute;
            right: -1px;
            bottom: -1px;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background-color: var(--estado-en-linea);
            /* El aro del color del sidebar lo recorta del avatar. */
            box-shadow: 0 0 0 2px var(--bg-sidebar);
        }

        .datos-perfil-sidebar {
            display: flex;
            flex-direction: column;
            min-width: 0;
            line-height: 1.25;
        }

        .datos-perfil-sidebar .nombre-perfil {
            color: var(--text-primary);
            font-weight: 700;
            font-size: 0.85rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .datos-perfil-sidebar .rol-perfil {
            color: var(--text-secondary);
            font-size: 0.72rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        #btn-opciones-perfil {
            margin-left: auto;
            flex-shrink: 0;
            width: 26px;
            height: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: transparent;
            border: none;
            border-radius: 8px;
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition-base);
        }

        #btn-opciones-perfil:hover {
            color: var(--accent);
            background-color: color-mix(in srgb, var(--accent) 12%, transparent);
        }

        .menu-item.menu-padre .icono-flecha-submenu {
            margin-left: auto;
            font-size: 0.75rem;
            width: auto;
            transition: transform 0.25s ease;
        }

        .menu-item.menu-padre[aria-expanded="true"] .icono-flecha-submenu {
            transform: rotate(180deg);
        }

        /* El padre de un hijo activo se marca, pero a media tinta: color de
           acento y una barra lateral translúcida, SIN fondo ni resplandor. Así
           el hijo realmente activo sigue siendo lo más fuerte de la jerarquía y
           el usuario no se pierde de dónde está parado. */
        .menu-item.menu-padre.padre-activo {
            color: var(--accent);
            border-left-color: var(--menu-glow-fuerte);
        }

        .menu-item.menu-padre.padre-activo i:not(.icono-flecha-submenu) {
            color: var(--accent);
        }

        /* Técnica grid-template-rows: anima de 0 al alto real del contenido sin
           necesitar JS que mida el alto en píxeles. */
        .submenu-lateral {
            display: grid;
            grid-template-rows: 0fr;
            transition: grid-template-rows 0.28s ease;
        }

        .submenu-lateral.abierto {
            grid-template-rows: 1fr;
        }

        .submenu-lateral-contenido {
            overflow: hidden;
            min-height: 0;
        }

        /* Quien pidió menos movimiento en su sistema operativo no debería
           recibir escalados ni deslizamientos. El color y el resplandor sí se
           conservan: son información, no animación. */
        @media (prefers-reduced-motion: reduce) {
            /* Todo lo que se mueve se apaga; lo que informa (color, resplandor,
               posición final) se conserva. Las transiciones no se eliminan del
               todo sino que bajan al mínimo, para que los cambios de estado
               sigan siendo perceptibles sin llegar a ser una animación. */
            #sidebar,
            #contenido-principal,
            .menu-item,
            .menu-item i,
            .menu-item > span,
            .submenu-lateral,
            .submenu-lateral .menu-item.submenu-item,
            #btn-colapsar-sidebar,
            #btn-colapsar-sidebar i,
            .tarjeta-perfil {
                transition-duration: 0.01ms;
            }

            /* Animaciones en bucle: fuera, pero el elemento sigue visible en su
               estado base (el punto y la franja no desaparecen). */
            #sidebar .sidebar-header .logo-dot,
            #sidebar .sidebar-header::before {
                animation: none;
                opacity: 1;
            }

            .menu-item:hover {
                transform: none;
            }

            .menu-item:hover i:not(.icono-flecha-submenu) {
                transform: none;
            }

            #btn-colapsar-sidebar:hover,
            #btn-colapsar-sidebar:active {
                transform: none;
            }

            .submenu-lateral .menu-item.submenu-item {
                opacity: 1;
                transform: none;
            }
        }

        /* El margen sigue al ancho del sidebar (que es variable) más el hueco
           flotante de cada lado, así al colapsar el contenido se reacomoda solo. */
        #contenido-principal {
            margin-left: calc(var(--ancho-sidebar) + var(--gap-flotante) * 2);
            transition: margin-left 0.3s ease;
        }

        /* Navbar como píldora flotante: despegado del borde superior y del
           derecho, separado del sidebar, con fondo semitransparente para que se
           sienta por encima del contenido y no como parte del marco. */
        #topbar {
            position: relative;
            z-index: 1020;
            margin: var(--gap-flotante) var(--gap-flotante) 0 0;
            padding: 0.5rem 0.75rem 0.5rem 1.5rem;
            background-color: color-mix(in srgb, var(--bg-card) 82%, transparent);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            /* Resplandor sutil volcado hacia ABAJO: los dos primeros shadows
               llevan desplazamiento vertical positivo, así el halo cae bajo la
               barra en vez de repartirse por igual alrededor. Después, el aro
               tenue que la recorta y la sombra de profundidad en negro. */
            box-shadow:
                0 10px 26px var(--navbar-glow),
                0 2px 10px var(--navbar-glow),
                0 0 0 1px var(--navbar-aro),
                0 18px 40px rgba(0, 0, 0, 0.26);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        #topbar h1 {
            color: var(--text-primary);
            /* Un título largo no debe empujar el bloque de usuario fuera. */
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            min-width: 0;
        }

        /* Separador vertical entre la campana y el bloque de usuario. */
        .separador-topbar {
            width: 1px;
            height: 26px;
            background-color: var(--border-color);
            flex-shrink: 0;
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
            width: 36px;
            height: 36px;
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
            /* Anillo sutil del color del tema. Va como box-shadow y no como
               border para no alterar el tamaño real del círculo, y mezclado con
               transparencia para que acompañe sin competir con las iniciales. */
            box-shadow: 0 0 0 2px color-mix(in srgb, var(--accent) 45%, transparent);
            transition: var(--transition-base);
        }

        .disparador-usuario:hover .avatar-usuario,
        .disparador-usuario[aria-expanded="true"] .avatar-usuario {
            box-shadow: 0 0 0 2px var(--accent), 0 0 10px var(--accent-glow);
        }

        /* Línea 1 del bloque de usuario: nombre + negocio juntos. */
        .linea-identidad {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            min-width: 0;
        }

        .linea-identidad .separador-identidad {
            color: var(--text-muted);
            flex-shrink: 0;
        }

        .linea-identidad .nombre-negocio-topbar {
            color: var(--text-secondary);
            font-size: 0.82rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Línea 2: el rol, en el acento del negocio. */
        .texto-rol-sesion {
            color: var(--accent);
            font-size: 0.68rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-top: 0.1rem;
        }

        .datos-disparador {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            line-height: 1.25;
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

        /* ---------- Campana de stock bajo ---------- */

        /* Círculo con borde, a juego con la píldora del navbar. */
        .disparador-campana {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            flex-shrink: 0;
            background-color: transparent;
            border: 1px solid var(--border-color);
            border-radius: 50%;
            color: var(--text-secondary);
            font-size: 1.05rem;
            transition: var(--transition-base);
        }

        .disparador-campana:hover,
        .disparador-campana[aria-expanded="true"] {
            background-color: color-mix(in srgb, var(--accent) 12%, transparent);
            border-color: var(--accent);
            color: var(--accent);
        }

        .badge-campana {
            position: absolute;
            top: 2px;
            right: 2px;
            min-width: 18px;
            height: 18px;
            padding: 0 4px;
            border-radius: 999px;
            background-color: var(--danger);
            color: var(--text-sobre-accent);
            font-size: 0.68rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        /* El "display: flex" de arriba le gana al [hidden] del navegador, así que
           el ocultamiento hay que declararlo aquí de forma explícita. Sin esto se
           alcanzaba a ver un "0" mientras la consulta de stock bajo iba en camino. */
        .badge-campana[hidden] {
            display: none;
        }

        .panel-campana {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-card);
            padding: 0;
            min-width: 320px;
            max-width: 360px;
            margin-top: 0.4rem;
            overflow: hidden;
        }

        .encabezado-panel-campana {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            font-weight: 700;
            font-size: 0.92rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .encabezado-panel-campana i {
            color: var(--accent);
        }

        .lista-panel-campana {
            max-height: 340px;
            overflow-y: auto;
        }

        .panel-campana-cargando,
        .panel-campana-vacio {
            padding: 1.5rem 1rem;
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.88rem;
        }

        .panel-campana-vacio i {
            display: block;
            font-size: 1.8rem;
            color: var(--success);
            margin-bottom: 0.5rem;
        }

        .item-stock-bajo {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.6rem;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .item-stock-bajo:last-child {
            border-bottom: none;
        }

        .item-stock-bajo .info-producto-bajo {
            min-width: 0;
        }

        .item-stock-bajo .nombre-producto-bajo {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.88rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .item-stock-bajo .detalle-producto-bajo {
            font-size: 0.8rem;
        }

        /* Dos niveles de alerta: sin existencias (--danger) pesa más que haber
           tocado el mínimo (--warning). La franja lateral hace que la diferencia
           se note de un vistazo, sin tener que leer cada renglón. */
        .item-stock-bajo.urgencia-agotado {
            border-left: 3px solid var(--danger);
            background-color: var(--danger-soft);
        }

        .item-stock-bajo.urgencia-agotado .detalle-producto-bajo {
            color: var(--danger);
            font-weight: 600;
        }

        .item-stock-bajo.urgencia-bajo {
            border-left: 3px solid var(--warning);
        }

        .item-stock-bajo.urgencia-bajo .detalle-producto-bajo {
            color: var(--warning);
        }

        .etiqueta-urgencia {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-left: 0.35rem;
        }

        .urgencia-agotado .etiqueta-urgencia {
            color: var(--danger);
        }

        .btn-ingresar-stock-campana {
            background-color: var(--accent-soft);
            border: 1px solid transparent;
            color: var(--accent);
            border-radius: var(--radius-sm);
            padding: 0.35rem 0.6rem;
            font-size: 0.78rem;
            font-weight: 600;
            white-space: nowrap;
            text-decoration: none;
            transition: var(--transition-base);
        }

        .btn-ingresar-stock-campana:hover {
            background-color: var(--accent);
            color: var(--text-sobre-accent);
            text-decoration: none;
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

        /* En pantallas angostas el sidebar va siempre colapsado, usando el mismo
           mecanismo de la variable de ancho: el botón manual no pelea con esto
           porque ambos terminan escribiendo la misma variable. */
        @media (max-width: 991.98px) {
            body {
                --ancho-sidebar: var(--ancho-sidebar-colapsado);
            }

            #sidebar .sidebar-header .nombre-marca,
            .menu-item > span {
                opacity: 0;
                width: 0;
                overflow: hidden;
                white-space: nowrap;
            }

            .etiqueta-seccion,
            .submenu-lateral,
            .icono-flecha-submenu,
            #btn-colapsar-sidebar,
            #btn-opciones-perfil,
            .datos-perfil-sidebar {
                display: none;
            }

            .tarjeta-perfil {
                justify-content: center;
                gap: 0;
                padding: 0.5rem;
            }

            .menu-item {
                justify-content: center;
                gap: 0;
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }

            #sidebar .sidebar-header {
                justify-content: center;
                padding-left: 0.5rem;
                padding-right: 0.5rem;
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

        /* ================= FORMULARIOS MODERNOS (clases reutilizables) =================
         *
         * Piezas sueltas para ir modernizando los formularios de cada módulo sin
         * tocar su estructura: todo se monta DENTRO del modal de Bootstrap que ya
         * se usa. No se reemplaza nada del ciclo de vida de Bootstrap (.fade /
         * .show, el backdrop, cerrar con Escape o clicando fuera): solo se
         * redefine el aspecto de la transición que Bootstrap ya dispara.
         *
         * Ningún formulario las usa todavía; se aplican módulo por módulo.
         */

        /* ---------- 1) Entrada y salida del panel ---------- */

        /* Bootstrap anima .modal-dialog con un translate vertical. Aquí se
           sustituye por la curva elástica que ya usa el resto del panel, sin
           tocar cuándo se agrega o se quita .show.
         *
         * Los selectores repiten .modal y .fade a propósito: la regla de
         * Bootstrap es ".modal.fade .modal-dialog" y le gana en especificidad a
         * un ".modal-moderno .modal-dialog" a secas, así que la transición
         * personalizada no llegaba a aplicarse. */
        .modal.modal-moderno.fade .modal-dialog {
            transform: scale(0.92) translateY(16px);
            opacity: 0;
            transition: transform 0.38s cubic-bezier(.34, 1.56, .64, 1), opacity 0.28s ease;
        }

        .modal.modal-moderno.fade.show .modal-dialog {
            transform: scale(1) translateY(0);
            opacity: 1;
        }

        /* Quien prefiera menos movimiento recibe el mismo cambio, sin el rebote. */
        @media (prefers-reduced-motion: reduce) {
            .modal.modal-moderno.fade .modal-dialog {
                transform: none;
                transition: opacity 0.2s ease;
            }

            .modal.modal-moderno.fade.show .modal-dialog {
                transform: none;
            }
        }

        .modal-moderno .modal-content {
            position: relative;
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-card);
            overflow: hidden;
        }

        /* ---------- 2) Reflejo de vidrio del borde superior ---------- */

        .modal-moderno .modal-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            pointer-events: none;
            z-index: 3;
        }

        /* ---------- 3) Encabezado con aura ---------- */

        .modal-header-moderno {
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.85rem;
            padding: 1.15rem 1.35rem;
            border-bottom: 1px solid var(--border-color);
            overflow: hidden;
        }

        /* El resplandor va detrás de todo y no intercepta clics, para no estorbar
           al botón de cerrar de Bootstrap. */
        .modal-header-moderno::before {
            content: '';
            position: absolute;
            top: -70%;
            left: -10%;
            width: 220px;
            height: 220px;
            background: radial-gradient(circle, var(--accent-soft2), transparent 70%);
            filter: blur(26px);
            pointer-events: none;
            z-index: 0;
            animation: auraEncabezado 14s ease-in-out infinite;
        }

        @keyframes auraEncabezado {
            0%, 100% {
                transform: rotate(0deg) scale(1);
                opacity: 0.75;
            }
            50% {
                transform: rotate(180deg) scale(1.25);
                opacity: 1;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .modal-header-moderno::before {
                animation: none;
            }
        }

        .modal-header-moderno > * {
            position: relative;
            z-index: 1;
        }

        .insignia-encabezado {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--accent-soft), var(--accent-soft2));
            color: var(--accent);
            font-size: 1.15rem;
            flex-shrink: 0;
        }

        .titulo-modal-moderno {
            color: var(--text-primary);
            font-weight: 700;
            font-size: 1.05rem;
            line-height: 1.25;
            margin: 0;
        }

        .subtitulo-modal-moderno {
            color: var(--text-secondary);
            font-size: 0.82rem;
            margin: 0;
        }

        .modal-header-moderno .btn-close {
            margin-left: auto;
            transition: var(--transition-base);
        }

        .modal-header-moderno .btn-close:hover {
            transform: rotate(90deg);
        }

        /* ---------- 4) Tarjetas de sección ---------- */

        .tarjeta-seccion-form {
            background-color: color-mix(in srgb, var(--bg-body) 55%, var(--bg-card));
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 16px;
        }

        .tarjeta-seccion-form + .tarjeta-seccion-form {
            margin-top: 0.85rem;
        }

        .etiqueta-seccion-form {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 0.85rem;
        }

        .etiqueta-seccion-form::before {
            content: '';
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background-color: var(--accent);
            flex-shrink: 0;
        }

        /* ---------- 5) Campos con label flotante (sin JavaScript) ---------- */

        /* Estructura esperada:
         *   <div class="campo-flotante">
         *       <i class="bi bi-person"></i>
         *       <input id="nombre" name="nombre" placeholder=" ">
         *       <label for="nombre">Nombre</label>
         *       <span class="mensaje-error-campo">...</span>
         *   </div>
         *
         * El placeholder DEBE ser un espacio (" "): de ahí sale :placeholder-shown,
         * que es lo que permite detectar "campo vacío" sin JavaScript. El label va
         * DESPUÉS del campo para poder seleccionarlo como hermano (~). */
        .campo-flotante {
            position: relative;
            display: block;
        }

        .campo-flotante + .campo-flotante {
            margin-top: 0.9rem;
        }

        .campo-flotante > i {
            position: absolute;
            left: 0.9rem;
            top: 1.05rem;
            color: var(--text-muted);
            font-size: 1rem;
            pointer-events: none;
            transition: var(--transition-base);
            z-index: 1;
        }

        .campo-flotante > input,
        .campo-flotante > textarea,
        .campo-flotante > select {
            width: 100%;
            background-color: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-size: 0.92rem;
            padding: 1.35rem 0.9rem 0.5rem 2.6rem;
            transition: var(--transition-base);
            appearance: none;
        }

        .campo-flotante > textarea {
            min-height: 92px;
            resize: vertical;
        }

        /* Sin ícono, el texto no necesita el sangrado izquierdo. */
        .campo-flotante.sin-icono > input,
        .campo-flotante.sin-icono > textarea,
        .campo-flotante.sin-icono > select {
            padding-left: 0.9rem;
        }

        .campo-flotante.sin-icono > label {
            left: 0.9rem;
        }

        .campo-flotante > label {
            position: absolute;
            left: 2.6rem;
            top: 0.95rem;
            color: var(--text-muted);
            font-size: 0.92rem;
            pointer-events: none;
            transform-origin: left center;
            transition: var(--transition-base);
        }

        /* El label sube si el campo tiene foco o ya trae contenido. */
        .campo-flotante > input:focus ~ label,
        .campo-flotante > input:not(:placeholder-shown) ~ label,
        .campo-flotante > textarea:focus ~ label,
        .campo-flotante > textarea:not(:placeholder-shown) ~ label,
        .campo-flotante > select:focus ~ label {
            top: 0.35rem;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--accent);
        }

        /* Un <select> siempre muestra una opción, así que su etiqueta tiene que
           vivir arriba desde el principio: si esperara al foco, como hacen los
           campos de texto, se encimaría sobre el valor ya elegido. Solo baja
           cuando el select está en la opción vacía de "Seleccione...", que es
           el único caso en que se comporta como un campo sin contenido. */
        .campo-flotante > select ~ label {
            top: 0.35rem;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .campo-flotante > select:not(:focus):has(option[value=""]:checked) ~ label {
            top: 0.95rem;
            font-size: 0.92rem;
            font-weight: 400;
            color: var(--text-muted);
        }

        .campo-flotante > input:focus,
        .campo-flotante > textarea:focus,
        .campo-flotante > select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-soft);
        }

        .campo-flotante > input:focus ~ i,
        .campo-flotante > textarea:focus ~ i,
        .campo-flotante > select:focus ~ i {
            color: var(--accent);
        }

        /* Estado de error */
        .campo-flotante.con-error > input,
        .campo-flotante.con-error > textarea,
        .campo-flotante.con-error > select {
            border-color: var(--danger);
            animation: sacudidaCampo 0.34s ease;
        }

        .campo-flotante.con-error > input:focus,
        .campo-flotante.con-error > textarea:focus,
        .campo-flotante.con-error > select:focus {
            box-shadow: 0 0 0 3px var(--danger-soft);
        }

        .campo-flotante.con-error > i,
        .campo-flotante.con-error > label {
            color: var(--danger);
        }

        @keyframes sacudidaCampo {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        @media (prefers-reduced-motion: reduce) {
            .campo-flotante.con-error > input,
            .campo-flotante.con-error > textarea,
            .campo-flotante.con-error > select {
                animation: none;
            }
        }

        /* El mensaje solo ocupa espacio cuando el campo está en error. */
        .mensaje-error-campo {
            display: none;
            color: var(--danger);
            font-size: 0.76rem;
            margin-top: 0.3rem;
        }

        .campo-flotante.con-error .mensaje-error-campo {
            display: block;
        }

        /* ---------- 6) Contador (+/-) para cantidades ---------- */

        /* Estructura esperada:
         *   <div class="stepper-campo">
         *       <button type="button" class="btn-stepper" data-paso="-1">...</button>
         *       <input type="number" class="valor-stepper" min="0" ...>
         *       <button type="button" class="btn-stepper" data-paso="1">...</button>
         *   </div>
         * El <input> real se conserva: los formularios lo siguen enviando igual. */
        .stepper-campo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            background-color: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 0.6rem 0.9rem;
        }

        .btn-stepper {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            border: 1px solid var(--border-color);
            background-color: var(--bg-card);
            color: var(--text-secondary);
            font-size: 1rem;
            line-height: 1;
            flex-shrink: 0;
            transition: var(--transition-base);
        }

        .btn-stepper:hover:not(:disabled) {
            background-color: var(--accent);
            border-color: var(--accent);
            color: var(--text-sobre-accent);
        }

        .btn-stepper:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        .stepper-campo .valor-stepper {
            width: 5.5rem;
            background-color: transparent;
            border: none;
            color: var(--text-primary);
            font-size: 1.45rem;
            font-weight: 700;
            text-align: center;
            padding: 0;
        }

        .stepper-campo .valor-stepper:focus {
            outline: none;
        }

        /* Se ocultan las flechas nativas: las reemplazan los botones. */
        .stepper-campo .valor-stepper::-webkit-outer-spin-button,
        .stepper-campo .valor-stepper::-webkit-inner-spin-button {
            appearance: none;
            margin: 0;
        }

        .stepper-campo .valor-stepper[type=number] {
            appearance: textfield;
        }

        /* ---------- 7) Interruptor para booleanos ---------- */

        /* El <input type="checkbox"> real sigue ahí, solo que invisible: el
           formulario se envía igual y el control mantiene foco y teclado. */
        .interruptor-moderno {
            display: inline-flex;
            align-items: center;
            gap: 0.7rem;
            cursor: pointer;
        }

        .interruptor-moderno > input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .interruptor-moderno .pista-interruptor {
            position: relative;
            width: 46px;
            height: 26px;
            border-radius: 999px;
            background-color: var(--bg-input);
            border: 1px solid var(--border-color);
            transition: var(--transition-base);
            flex-shrink: 0;
        }

        .interruptor-moderno .pista-interruptor::after {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background-color: var(--text-secondary);
            transition: var(--transition-base);
        }

        .interruptor-moderno > input[type="checkbox"]:checked + .pista-interruptor {
            background-color: var(--accent);
            border-color: var(--accent);
        }

        .interruptor-moderno > input[type="checkbox"]:checked + .pista-interruptor::after {
            transform: translateX(20px);
            background-color: var(--text-sobre-accent);
        }

        .interruptor-moderno > input[type="checkbox"]:focus-visible + .pista-interruptor {
            box-shadow: 0 0 0 3px var(--accent-soft);
        }

        .interruptor-moderno > input[type="checkbox"]:disabled + .pista-interruptor {
            opacity: 0.5;
        }

        .interruptor-moderno .texto-interruptor {
            color: var(--text-primary);
            font-size: 0.9rem;
        }

        /* ---------- 8) Botón de guardar con estados ---------- */

        .btn-guardar-moderno {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            overflow: hidden;
            border: none;
            border-radius: var(--radius-sm);
            padding: 0.6rem 1.35rem;
            font-weight: 600;
            color: var(--text-sobre-accent);
            background: linear-gradient(135deg, var(--accent), var(--accent-hover));
            transition: var(--transition-base);
        }

        /* Brillo diagonal que cruza el botón al pasar el puntero. */
        .btn-guardar-moderno::after {
            content: '';
            position: absolute;
            top: 0;
            left: -60%;
            width: 40%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.28), transparent);
            transform: skewX(-20deg);
            transition: left 0.55s ease;
            pointer-events: none;
        }

        .btn-guardar-moderno:hover:not(:disabled)::after {
            left: 120%;
        }

        .btn-guardar-moderno:disabled {
            cursor: not-allowed;
        }

        /* Estado "guardando": el spinner lo aporta esta clase, el formulario solo
           agrega/quita .ocupado. */
        .btn-guardar-moderno.ocupado {
            opacity: 0.85;
            pointer-events: none;
        }

        .btn-guardar-moderno.ocupado .icono-guardar,
        .btn-guardar-moderno.exito .icono-guardar {
            display: none;
        }

        .btn-guardar-moderno.ocupado::before {
            content: '';
            width: 15px;
            height: 15px;
            border-radius: 50%;
            border: 2px solid var(--text-sobre-accent);
            border-top-color: transparent;
            animation: giroBotonGuardar 0.7s linear infinite;
            flex-shrink: 0;
        }

        @keyframes giroBotonGuardar {
            to { transform: rotate(360deg); }
        }

        /* Estado "guardado": marca de verificación. */
        .btn-guardar-moderno.exito {
            background: linear-gradient(135deg, var(--success), var(--success));
            pointer-events: none;
        }

        /* \F633 es el glifo de "bi-check-lg" en Bootstrap Icons 1.11.3, el mismo
           que ya se carga por CDN. Se dibuja desde CSS y no como <i> para que el
           formulario solo tenga que alternar la clase .exito. */
        .btn-guardar-moderno.exito::before {
            content: '\F633';
            font-family: 'bootstrap-icons';
            font-size: 1.05rem;
            line-height: 1;
        }

        @media (prefers-reduced-motion: reduce) {
            .btn-guardar-moderno::after,
            .btn-guardar-moderno.ocupado::before {
                animation: none;
                transition: none;
            }
        }
    </style>

    @yield('estilos')
</head>
<body class="modo-{{ session('modo_tema') ?? 'oscuro' }} acento-{{ str_replace('_', '-', session('color_acento') ?? 'rojo') }}">
    <script>
        // Va aquí arriba, y no en el bloque de scripts del final, para que el
        // sidebar ya nazca colapsado si esa era la preferencia guardada: puesto
        // más abajo se vería un parpadeo de ancho completo antes de encogerse.
        // En try/catch porque el navegador puede tener bloqueado el almacenamiento.
        try {
            if (localStorage.getItem('sidebar_colapsado') === '1') {
                document.body.classList.add('sidebar-colapsado');
            }
        } catch (e) {
            // Sin localStorage simplemente arranca expandido.
        }
    </script>

    <div id="loader_proceso">
        <div class="spinner-border" role="status" style="width: 3rem; height: 3rem; color: var(--accent);">
            <span class="visually-hidden">Cargando...</span>
        </div>
    </div>

    @php
        // Estos datos los consumen tanto la tarjeta de perfil del sidebar como
        // el bloque de usuario del navbar, así que se calculan antes de ambos.
        // Iniciales del usuario para el avatar (máximo dos letras).
        $nombreSesion = session('nombre_usuario', 'Invitado');
        $partesNombre = preg_split('/\s+/', trim($nombreSesion));
        $inicialesUsuario = '';
        foreach (array_slice($partesNombre, 0, 2) as $parte) {
            $inicialesUsuario .= mb_strtoupper(mb_substr($parte, 0, 1));
        }
        $inicialesUsuario = $inicialesUsuario ?: 'U';

        $esEmpleadoSesion = \App\Models\Rol::esRolEmpleado(session('id_rol'));
        $esAdminDeNegocio = ! $esEmpleadoSesion && session('tenant_id') !== null;

        // El rol se deduce igual que en el resto del proyecto (el helper
        // del middleware + la ausencia de negocio), no leyendo un
        // nombre_rol suelto que podría variar entre instalaciones.
        $nombreRolSesion = session('tenant_id') === null
            ? 'Super Admin'
            : ($esEmpleadoSesion ? 'Empleado' : 'Administrador');
    @endphp

    <aside id="sidebar">
        <div class="sidebar-header">
            <span class="logo-dot"></span>
            <span class="nombre-marca" id="nombre-negocio-lateral" title="{{ session('nombre_negocio_sesion') ?? 'Plataforma Reservas' }}">
                {{ session('nombre_negocio_sesion') ?? 'Plataforma Reservas' }}
            </span>
            <button type="button" id="btn-colapsar-sidebar" aria-label="Contraer o expandir el menú">
                <i class="bi bi-chevron-left"></i>
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>
        <nav id="menu-lateral">
            @php
                // El rol se determina con el MISMO helper que usa el middleware
                // RestringirEmpleado, para que el menú nunca pueda desalinearse
                // de lo que la seguridad real permite.
                //
                // Ojo: esconder un ítem es solo experiencia de usuario. La única
                // fuente de verdad de seguridad sigue siendo el middleware sobre
                // las rutas backoffice/* y request/*, que no se toca aquí.
                $esEmpleadoMenu = \App\Models\Rol::esRolEmpleado(session('id_rol'));
                $tieneNegocioMenu = session('tenant_id') !== null;
            @endphp
            @if ($esEmpleadoMenu)
                {{-- El empleado ve exclusivamente su propia agenda: cualquier otro
                     enlace le rebotaría en el middleware de todos modos. --}}
                <a href="{{ url('backoffice/mis-citas') }}" class="menu-item @if (request()->is('backoffice/mis-citas')) active @endif">
                    <i class="bi bi-calendar2-check"></i>
                    <span>Mis Citas</span>
                </a>
            @else
            {{-- Rótulos de sección: solo separan visualmente. No cambian el orden
                 de los ítems ni a qué grupo colapsable pertenece cada uno. --}}
            <div class="etiqueta-seccion">Principal</div>
            <a href="{{ url('backoffice/dashboard') }}" class="menu-item @if (request()->is('backoffice/dashboard')) active @endif">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>

            <div class="etiqueta-seccion">Gestión</div>
            <a href="{{ url('backoffice/usuarios') }}" class="menu-item @if (request()->is('backoffice/usuarios')) active @endif">
                <i class="bi bi-people"></i>
                <span>Usuarios</span>
            </a>
            {{-- El super admin no opera un negocio concreto: de aquí en adelante
                 son módulos operativos que no le aplican. --}}
            @if ($tieneNegocioMenu)
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
                <div class="etiqueta-seccion">Herramientas</div>
                @php
                    $enSeccionReportes = request()->is('backoffice/reportes/*');
                @endphp
                <button
                    type="button"
                    class="menu-item menu-padre @if ($enSeccionReportes) padre-activo @endif"
                    data-toggle-submenu="submenu-reportes"
                    aria-expanded="{{ $enSeccionReportes ? 'true' : 'false' }}"
                >
                    <i class="bi bi-graph-up"></i>
                    <span>Reportes</span>
                    <i class="bi bi-chevron-down icono-flecha-submenu"></i>
                </button>
                <div id="submenu-reportes" class="submenu-lateral @if ($enSeccionReportes) abierto @endif">
                    <div class="submenu-lateral-contenido">
                        <a href="{{ url('backoffice/reportes/ventas') }}" class="menu-item submenu-item @if (request()->is('backoffice/reportes/ventas')) active @endif">
                            <i class="bi bi-cash-coin"></i>
                            <span>Ventas</span>
                        </a>
                        <a href="{{ url('backoffice/reportes/servicios') }}" class="menu-item submenu-item @if (request()->is('backoffice/reportes/servicios')) active @endif">
                            <i class="bi bi-pie-chart"></i>
                            <span>Por servicio</span>
                        </a>
                    </div>
                </div>
                <a href="{{ url('backoffice/carga-masiva') }}" class="menu-item @if (request()->is('backoffice/carga-masiva')) active @endif">
                    <i class="bi bi-cloud-upload"></i>
                    <span>Carga Masiva</span>
                </a>
                <a href="{{ url('backoffice/productos') }}" class="menu-item @if (request()->is('backoffice/productos')) active @endif">
                    <i class="bi bi-box-seam"></i>
                    <span>Productos</span>
                </a>
                <a href="{{ url('backoffice/personalizar') }}" class="menu-item @if (request()->is('backoffice/personalizar')) active @endif">
                    <i class="bi bi-palette2"></i>
                    <span>Personalizar</span>
                </a>

                @php
                    // Para sumar un módulo de pago nuevo basta con agregar su ruta
                    // a este array y un <a class="menu-item submenu-item"> dentro
                    // del contenedor de abajo. Nada más hay que tocar.
                    $rutasModulosPago = ['backoffice/comisiones*'];
                    $enModulosPago = request()->is($rutasModulosPago);
                @endphp
                {{-- Sin rótulo de sección propio: el encabezado del grupo ya se
                     llama "Módulos de pago" y repetirlo encima sobra. En su lugar
                     lleva una línea fina que lo separa del bloque anterior. --}}
                <button
                    type="button"
                    class="menu-item menu-padre menu-padre-separado @if ($enModulosPago) padre-activo @endif"
                    data-toggle-submenu="submenu-modulos-pago"
                    aria-expanded="{{ $enModulosPago ? 'true' : 'false' }}"
                >
                    <i class="bi bi-stars"></i>
                    <span>Módulos de pago</span>
                    <i class="bi bi-chevron-down icono-flecha-submenu"></i>
                </button>
                <div id="submenu-modulos-pago" class="submenu-lateral @if ($enModulosPago) abierto @endif">
                    <div class="submenu-lateral-contenido">
                        <a href="{{ url('backoffice/comisiones') }}" class="menu-item submenu-item @if (request()->is('backoffice/comisiones')) active @endif">
                            <i class="bi bi-percent"></i>
                            <span>Comisiones</span>
                        </a>
                        {{-- Próximos módulos de pago (Contenido para redes sociales,
                             Catálogo, Chatbot) van aquí, con este mismo formato. --}}
                    </div>
                </div>
            @endif
            @endif
            <!-- Los enlaces de cada módulo se agregan aquí a medida que se construyen -->
        </nav>

        <div class="pie-sidebar">
            <div class="tarjeta-perfil">
                <span class="avatar-perfil">
                    {{ $inicialesUsuario }}
                    <span class="punto-en-linea" title="En línea"></span>
                </span>
                <span class="datos-perfil-sidebar">
                    <span class="nombre-perfil">{{ $nombreSesion }}</span>
                    <span class="rol-perfil">{{ $nombreRolSesion }}</span>
                </span>

                {{-- Menú propio que se abre hacia arriba: repetir aquí el
                     dropdown del navbar sería desorientador (se abriría lejos
                     del clic), así que este tiene el suyo con las mismas
                     acciones. El botón de salir comparte la clase "item-salir"
                     con el del navbar, que es por donde escucha el JS. --}}
                <div class="dropdown dropup">
                    <button type="button" id="btn-opciones-perfil" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Opciones de la cuenta">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>

                    <ul class="dropdown-menu menu-usuario" aria-labelledby="btn-opciones-perfil">
                        @if ($esAdminDeNegocio)
                            <li>
                                <a class="dropdown-item" href="{{ url('backoffice/configuracion') }}">
                                    <i class="bi bi-gear"></i> Configurar negocio
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                        @endif
                        <li>
                            <button type="button" class="dropdown-item item-salir">
                                <i class="bi bi-box-arrow-right"></i> Cerrar sesión
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </aside>

    <div id="contenido-principal">
        <header id="topbar">
            <h1 class="h5 mb-0">@yield('title')</h1>

            <div class="d-flex align-items-center gap-2">
            @if ($esAdminDeNegocio)
                <div class="dropdown">
                    <button type="button" id="btn-campana-stock" class="disparador-campana" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-bell"></i>
                        <span id="badge-stock-bajo" class="badge-campana" hidden>0</span>
                    </button>

                    <div class="dropdown-menu dropdown-menu-end panel-campana" aria-labelledby="btn-campana-stock">
                        <div class="encabezado-panel-campana">
                            <i class="bi bi-box-seam"></i> Stock bajo
                        </div>
                        <div id="lista-stock-bajo" class="lista-panel-campana">
                            <div class="panel-campana-cargando">Cargando...</div>
                        </div>
                    </div>
                </div>
                <span class="separador-topbar"></span>
            @endif

            <div class="dropdown">
                <button type="button" id="btn-menu-usuario" class="disparador-usuario" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="avatar-usuario">{{ $inicialesUsuario }}</span>
                    <span class="datos-disparador">
                        @if (session('tenant_id') === null)
                            <span class="nombre-usuario">{{ $nombreSesion }}</span>
                            <span class="badge-rol-sesion super-admin">
                                <i class="bi bi-shield-check"></i> Super Admin
                            </span>
                        @else
                            {{-- Línea 1: quién es y en qué negocio. Línea 2: su rol. --}}
                            <span class="linea-identidad">
                                <span class="nombre-usuario">{{ $nombreSesion }}</span>
                                <span class="separador-identidad">·</span>
                                <span class="nombre-negocio-topbar" id="nombre-negocio-sesion">{{ session('nombre_negocio_sesion') ?? 'Negocio' }}</span>
                            </span>
                            <span class="texto-rol-sesion">{{ $nombreRolSesion }}</span>
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
                {{-- Marcador de arranque: el JS lo reemplaza con el conteo real
                     apenas responde el backend. No se escribe un total fijo aquí
                     para que no quede desactualizado al sumar pasos. --}}
                <span class="conteo-pestana" id="conteo-onboarding">···</span>
            </button>

            <div id="panel-onboarding">
                <div class="cabecera-onboarding">
                    <div class="titulo-onboarding">Pon en marcha tu negocio</div>
                    <button type="button" class="btn-cerrar-onboarding" id="btn-cerrar-onboarding" aria-label="Colapsar">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
                <p class="subtitulo-onboarding">
                    Unos pasos rápidos y tu negocio queda listo para recibir clientes.
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>

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
        /**
         * Grupos desplegables del menú lateral (cualquier "menu-padre" con
         * data-toggle-submenu). Genérico: el próximo módulo que necesite
         * subopciones solo repite este mismo patrón de marcado, sin tocar JS.
         */
        /**
         * Colapsar / expandir el sidebar.
         *
         * El estado es preferencia de interfaz de ESTE navegador, no dato de
         * negocio: vive en localStorage, sin backend ni tabla. Todos los accesos
         * van en try/catch porque el navegador puede bloquear el almacenamiento
         * (modo privado, cookies de terceros, políticas corporativas). Si falla,
         * el sidebar sigue colapsando y expandiendo igual: lo único que se pierde
         * es recordar el estado tras recargar.
         */
        // La LECTURA del estado no vive aquí sino en un script al inicio del
        // <body>: tiene que correr antes del primer pintado para que el sidebar
        // no aparezca ancho y se encoja de golpe.
        var CLAVE_SIDEBAR_COLAPSADO = 'sidebar_colapsado';

        function guardarSidebarColapsado(colapsado) {
            try {
                localStorage.setItem(CLAVE_SIDEBAR_COLAPSADO, colapsado ? '1' : '0');
            } catch (e) {
                // Sin almacenamiento disponible no se recuerda entre recargas,
                // pero la interfaz funciona igual. No hay nada que avisarle al
                // usuario por esto.
            }
        }

        jQuery('#btn-colapsar-sidebar').on('click', function () {
            var colapsado = !jQuery('body').hasClass('sidebar-colapsado');

            jQuery('body').toggleClass('sidebar-colapsado', colapsado);
            guardarSidebarColapsado(colapsado);

            // Al colapsar no debe quedar ningún submenú desplegado por debajo.
            if (colapsado) {
                jQuery('.submenu-lateral').removeClass('abierto');
                jQuery('[data-toggle-submenu]').attr('aria-expanded', 'false');
            }
        });

        jQuery('#menu-lateral').on('click', '[data-toggle-submenu]', function () {
            var boton = jQuery(this);
            var submenu = jQuery('#' + boton.data('toggle-submenu'));
            var expandiendo = !submenu.hasClass('abierto');

            submenu.toggleClass('abierto', expandiendo);
            boton.attr('aria-expanded', expandiendo ? 'true' : 'false');
        });
    </script>

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
         * Avatar circular para las tablas: iniciales sobre un color de la paleta.
         *
         * El color sale de un hash del propio nombre, así que es estable: el mismo
         * nombre cae siempre en el mismo color, en cualquier carga y en cualquier
         * pantalla, sin necesidad de guardarlo en base de datos.
         *
         * @param {string} nombre        Texto del que se sacan las iniciales y el color.
         * @param {string} [claseIcono]  Si se pasa, se dibuja ese icono en vez de las
         *                               iniciales (útil para lo que no es una persona).
         */
        function generarAvatar(nombre, claseIcono) {
            var texto = jQuery.trim(nombre || '');

            var hash = 0;
            for (var i = 0; i < texto.length; i++) {
                hash = ((hash << 5) - hash) + texto.charCodeAt(i);
                hash = hash & hash;
            }

            var indiceColor = (Math.abs(hash) % 8) + 1;

            var contenido;

            if (claseIcono) {
                contenido = '<i class="bi ' + claseIcono + '"></i>';
            } else {
                var palabras = texto.split(/\s+/).filter(function (palabra) {
                    return palabra.length > 0;
                });

                var iniciales = palabras.slice(0, 2).map(function (palabra) {
                    return palabra.charAt(0).toUpperCase();
                }).join('');

                // Se escapa por si el nombre trae caracteres con significado en HTML.
                contenido = jQuery('<div>').text(iniciales || '?').html();
            }

            return '<span class="avatar-iniciales" style="background-color: var(--avatar-' + indiceColor + ');">' +
                   contenido +
                   '</span>';
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

        // Se escucha por clase y no por id: el mismo botón de salir existe en el
        // menú del navbar y en la tarjeta de perfil del pie del sidebar.
        jQuery("body").on("click", ".item-salir", function () {
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
         * @param {Function} alCompletar  Opcional. Se ejecuta SOLO si el usuario
         *                         llega al final del tour, nunca si lo salta o lo
         *                         cierra antes. Lo usa el tour de Reportes para
         *                         persistir en servidor que ya se vio completo.
         */
        function iniciarTourContextual(idTour, pasos, alCompletar) {
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

            tour.on('complete', function () {
                marcarTourVisto(idTour);

                if (typeof alCompletar === 'function') {
                    alCompletar();
                }
            });
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

        // Texto y destino de cada paso. El total NO se escribe a mano en ningún
        // lado: sale de la cantidad de pasos que devuelve el backend, así que
        // sumar un paso aquí y en SvcNegocio basta para que el conteo lo siga.
        // El botón de cierre no es un paso: aparece cuando están todos hechos.
        var PASOS_ONBOARDING = {
            personalizar: { texto: 'Personaliza los colores de tu panel', destino: 'backoffice/personalizar' },
            horario: { texto: 'Define tus días y horas de atención', destino: 'backoffice/configuracion' },
            recurso: { texto: 'Registra el primer servicio que ofreces', destino: 'backoffice/recursos' },
            empleado: { texto: 'Suma a alguien de tu equipo', destino: 'backoffice/empleados' },
            cliente: { texto: 'Registra a tu primer cliente', destino: 'backoffice/clientes' },
            reserva: { texto: 'Agenda tu primera reserva', destino: 'backoffice/reservas' },
            inventario: { texto: 'Carga tu primer producto de inventario', destino: 'backoffice/productos' },
            reportes: { texto: 'Descubre tus reportes de ventas', destino: 'backoffice/reportes/ventas' }
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

        /* ================= CONTADOR (+/-) DE LOS FORMULARIOS MODERNOS =================
         *
         * Única pieza de los formularios modernos que necesita JavaScript. Se
         * engancha por delegación sobre todo el documento, así que cada módulo
         * solo tiene que escribir el marcado (.stepper-campo con sus .btn-stepper
         * y su .valor-stepper); no hay que inicializar nada por pantalla, y
         * funciona igual si el campo aparece dentro de un modal abierto después.
         *
         * Los límites salen de los atributos min/max del propio input, que es
         * donde cada formulario ya declara su validación. No se inventa ninguno.
         */

        function limitesStepper(campo) {
            var min = campo.attr('min');
            var max = campo.attr('max');

            return {
                min: (min === undefined || min === '') ? null : parseFloat(min),
                max: (max === undefined || max === '') ? null : parseFloat(max)
            };
        }

        /** Apaga el botón que ya no puede avanzar, para que el tope se vea. */
        function sincronizarStepper(contenedor) {
            var campo = contenedor.find('.valor-stepper');
            var limites = limitesStepper(campo);
            var valor = parseFloat(campo.val());

            if (isNaN(valor)) {
                return;
            }

            contenedor.find('.btn-stepper').each(function () {
                var boton = jQuery(this);
                var paso = parseFloat(boton.data('paso')) || 1;
                var destino = valor + paso;
                var fuera = (limites.min !== null && destino < limites.min) ||
                            (limites.max !== null && destino > limites.max);

                boton.prop('disabled', fuera);
            });
        }

        jQuery(document).on('click', '.btn-stepper', function () {
            var contenedor = jQuery(this).closest('.stepper-campo');
            var campo = contenedor.find('.valor-stepper');

            if (campo.length === 0) {
                return;
            }

            var paso = parseFloat(jQuery(this).data('paso')) || 1;
            var limites = limitesStepper(campo);
            var valor = parseFloat(campo.val());

            // Un campo vacío arranca desde el mínimo declarado, o desde cero.
            if (isNaN(valor)) {
                valor = limites.min !== null ? limites.min : 0;
            }

            var nuevo = valor + paso;

            if (limites.min !== null && nuevo < limites.min) {
                nuevo = limites.min;
            }

            if (limites.max !== null && nuevo > limites.max) {
                nuevo = limites.max;
            }

            campo.val(nuevo);
            sincronizarStepper(contenedor);

            // Se avisa como si lo hubiera tecleado el usuario, para que cualquier
            // validación o cálculo ya enganchado al campo reaccione igual.
            campo.trigger('change');
        });

        // Si escriben el número a mano, los topes se recalculan igual.
        jQuery(document).on('input', '.valor-stepper', function () {
            sincronizarStepper(jQuery(this).closest('.stepper-campo'));
        });

        jQuery(document).ready(function () {
            cargarProgresoOnboarding();

            jQuery('.stepper-campo').each(function () {
                sincronizarStepper(jQuery(this));
            });
        });

        // Un modal puede traer contadores que aún no existían al cargar la página.
        jQuery(document).on('shown.bs.modal', function (evento) {
            jQuery(evento.target).find('.stepper-campo').each(function () {
                sincronizarStepper(jQuery(this));
            });
        });
    </script>

    {{-- Campana de stock bajo: solo se imprime para el admin de un negocio,
         que es el único que ve el botón en el DOM. --}}
    @if ($esAdminDeNegocio)
        <script>
            function pintarPanelCampana(productos) {
                var lista = jQuery('#lista-stock-bajo');
                var badge = jQuery('#badge-stock-bajo');

                if (!productos || productos.length === 0) {
                    badge.prop('hidden', true);
                    lista.html(
                        '<div class="panel-campana-vacio">' +
                        '<i class="bi bi-check-circle"></i>' +
                        'Todo tu inventario está en orden' +
                        '</div>'
                    );

                    return;
                }

                badge.text(productos.length > 99 ? '99+' : productos.length).prop('hidden', false);

                // Lo más urgente arriba: primero lo agotado, que ya frena el
                // trabajo, y después lo que solo tocó el mínimo. Se ordena sobre
                // una copia para no alterar el arreglo que llegó del servidor.
                var ordenados = productos.slice().sort(function (uno, otro) {
                    var pesoUno = uno.urgencia === 'agotado' ? 0 : 1;
                    var pesoOtro = otro.urgencia === 'agotado' ? 0 : 1;

                    return pesoUno - pesoOtro;
                });

                var html = '';
                ordenados.forEach(function (producto) {
                    var agotado = producto.urgencia === 'agotado';
                    var etiqueta = agotado
                        ? '<span class="etiqueta-urgencia"><i class="bi bi-x-octagon-fill"></i> Agotado</span>'
                        : '';

                    html += '<div class="item-stock-bajo ' + (agotado ? 'urgencia-agotado' : 'urgencia-bajo') + '">' +
                        '<div class="info-producto-bajo">' +
                        '<div class="nombre-producto-bajo">' + jQuery('<div>').text(producto.nombre).html() + etiqueta + '</div>' +
                        '<div class="detalle-producto-bajo">' + producto.cantidad_actual + ' de ' + producto.cantidad_minima + ' unidades</div>' +
                        '</div>' +
                        '<a class="btn-ingresar-stock-campana" href="' + UrlGlobal + 'backoffice/productos?producto=' + producto.id_producto + '">Ingresar stock</a>' +
                        '</div>';
                });

                lista.html(html);
            }

            /**
             * Refresca el conteo de la campana. Va siempre sin loader y sin avisar
             * si falla: el usuario no pidió esta consulta, así que un modal de error
             * (y más aún repetido cada minuto por el sondeo) estorbaría en vez de
             * ayudar. Si la sesión expiró, el siguiente intento se encarga.
             */
            function cargarStockBajoCampana() {
                axiosSipleInterno('GET', 'request/producto/stock-bajo', {}, {}, false, function (respuesta) {
                    if (respuesta && respuesta.error == 0) {
                        pintarPanelCampana(respuesta.data.productos_bajos);
                    }
                }, { silenciarError: true });
            }

            /** Cada cuánto se vuelve a consultar el stock bajo, en milisegundos. */
            var INTERVALO_SONDEO_CAMPANA = 60000;

            jQuery(document).ready(function () {
                cargarStockBajoCampana();

                // Mantiene el badge al día sin recargar la página. Este bloque solo
                // se imprime para el admin de un negocio, así que para empleado y
                // super admin el intervalo no llega siquiera a registrarse.
                setInterval(function () {
                    // Con la pestaña en segundo plano nadie está viendo el badge:
                    // se deja pasar el turno y se retoma al volver.
                    if (document.hidden) {
                        return;
                    }

                    cargarStockBajoCampana();
                }, INTERVALO_SONDEO_CAMPANA);
            });
        </script>
    @endif

    @yield('scripts')
</body>
</html>
