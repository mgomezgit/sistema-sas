<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plataforma Reservas - Gestiona tu negocio sin complicaciones</title>
    <meta name="description" content="Software de reservas para spa, hotel y finca. Agenda, clientes y equipo en un solo lugar.">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg: #0a0a0a;
            --bg-alt: #0d0d0d;
            --accent: #e11d2e;
            --accent-soft: #ff2e42;
            --white: #ffffff;
            --muted: #a3a3a3;
            --card: rgba(255, 255, 255, 0.05);
            --border: rgba(255, 255, 255, 0.1);
        }

        * { scroll-behavior: smooth; }

        body {
            background-color: var(--bg);
            color: var(--white);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            overflow-x: hidden;
        }

        h1, h2, h3, .fuente-serif {
            font-family: 'Playfair Display', Georgia, serif;
            font-weight: 700;
        }

        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background: var(--bg); }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 999px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--accent); }

        /* ---------- Navbar ---------- */
        #navbar-landing {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 1030;
            padding: 1.25rem 0;
            background: transparent;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }

        #navbar-landing.scrolled {
            padding: 0.75rem 0;
            background: rgba(10, 10, 10, 0.92);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.5);
        }

        .marca-landing {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            color: var(--white);
            text-decoration: none;
            font-weight: 600;
            font-size: 1.05rem;
            letter-spacing: 0.2px;
        }

        .marca-landing .punto {
            width: 9px; height: 9px;
            border-radius: 50%;
            background: var(--accent);
            box-shadow: 0 0 12px rgba(225, 29, 46, 0.6);
            flex-shrink: 0;
        }

        .enlace-nav {
            color: var(--muted);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            margin: 0 0.9rem;
            position: relative;
            transition: color 0.25s ease;
        }

        .enlace-nav::after {
            content: '';
            position: absolute;
            bottom: -5px; left: 0;
            width: 0; height: 2px;
            background: var(--accent);
            transition: width 0.28s ease;
        }

        .enlace-nav:hover { color: var(--white); }
        .enlace-nav:hover::after { width: 100%; }

        .btn-accent {
            background: var(--accent);
            color: var(--white);
            border: 1px solid var(--accent);
            border-radius: 10px;
            padding: 0.6rem 1.4rem;
            font-weight: 600;
            font-size: 0.92rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-accent:hover {
            background: var(--accent-soft);
            border-color: var(--accent-soft);
            color: var(--white);
            transform: translateY(-2px);
            box-shadow: 0 10px 26px rgba(225, 29, 46, 0.35);
        }

        .btn-linea {
            background: transparent;
            color: var(--white);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 0.6rem 1.4rem;
            font-weight: 600;
            font-size: 0.92rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-linea:hover {
            border-color: var(--accent);
            color: var(--white);
            transform: translateY(-2px);
        }

        /* ---------- Hero ---------- */
        #hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            padding: 7rem 0 4rem;
            overflow: hidden;
        }

        .hero-glow {
            position: absolute;
            top: 45%; left: 50%;
            transform: translate(-50%, -50%);
            width: min(1100px, 120vw);
            height: min(1100px, 120vw);
            background: radial-gradient(circle, rgba(225, 29, 46, 0.16) 0%, transparent 65%);
            filter: blur(80px);
            pointer-events: none;
            z-index: 0;
        }

        .hero-malla {
            position: absolute;
            inset: 0;
            opacity: 0.05;
            pointer-events: none;
            z-index: 0;
        }

        #hero .container { position: relative; z-index: 1; }

        .etiqueta-hero {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(225, 29, 46, 0.12);
            border: 1px solid rgba(225, 29, 46, 0.3);
            color: var(--accent-soft);
            border-radius: 999px;
            padding: 0.4rem 1rem;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 1.75rem;
        }

        #hero h1 {
            font-size: clamp(2.4rem, 6vw, 4.4rem);
            line-height: 1.1;
            margin-bottom: 1.4rem;
        }

        #hero h1 .resaltado {
            color: var(--accent);
            font-style: italic;
        }

        #hero p.subtitulo {
            color: var(--muted);
            font-size: clamp(1rem, 2vw, 1.2rem);
            max-width: 620px;
            margin: 0 auto 2.4rem;
            line-height: 1.7;
            font-weight: 300;
        }

        /* ---------- Secciones ---------- */
        /* scroll-margin-top evita que el navbar fijo tape el inicio de la sección
           al llegar por un enlace ancla. */
        .seccion, #panel-spa, #hero { scroll-margin-top: 90px; }
        .seccion { padding: 6.5rem 0; position: relative; }
        .seccion-alt { background: var(--bg-alt); }

        .titulo-seccion {
            font-size: clamp(1.9rem, 4vw, 2.9rem);
            margin-bottom: 0.9rem;
        }

        .subtitulo-seccion {
            color: var(--muted);
            font-size: 1.02rem;
            max-width: 620px;
            margin: 0 auto 3.5rem;
            line-height: 1.7;
            font-weight: 300;
        }

        .etiqueta-seccion {
            color: var(--accent);
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            margin-bottom: 0.85rem;
            display: block;
        }

        /* ---------- Cards de características ---------- */
        .card-caracteristica {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 2.1rem 1.8rem;
            height: 100%;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card-caracteristica:hover {
            transform: translateY(-8px);
            border-color: rgba(225, 29, 46, 0.45);
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.5);
        }

        .icono-caracteristica {
            width: 54px; height: 54px;
            border-radius: 14px;
            background: rgba(225, 29, 46, 0.12);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1.35rem;
            transition: all 0.35s ease;
        }

        .card-caracteristica:hover .icono-caracteristica {
            background: var(--accent);
            color: var(--white);
            transform: scale(1.06);
        }

        .card-caracteristica h3 {
            font-size: 1.2rem;
            margin-bottom: 0.6rem;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
        }

        .card-caracteristica p {
            color: var(--muted);
            font-size: 0.93rem;
            line-height: 1.65;
            margin-bottom: 0;
            font-weight: 300;
        }

        /* ---------- Pasos ---------- */
        .paso { text-align: center; position: relative; padding: 0 1rem; }

        .numero-paso {
            width: 72px; height: 72px;
            border-radius: 50%;
            background: var(--bg);
            border: 2px solid var(--accent);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            font-weight: 700;
            font-family: 'Playfair Display', serif;
            margin: 0 auto 1.5rem;
            position: relative;
            z-index: 2;
            transition: all 0.35s ease;
        }

        .paso:hover .numero-paso {
            background: var(--accent);
            color: var(--white);
            box-shadow: 0 0 32px rgba(225, 29, 46, 0.45);
        }

        .linea-pasos {
            position: absolute;
            top: 36px; left: 0; right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--border) 15%, var(--border) 85%, transparent);
            z-index: 1;
        }

        .paso h3 {
            font-size: 1.15rem;
            margin-bottom: 0.6rem;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
        }

        .paso p {
            color: var(--muted);
            font-size: 0.93rem;
            line-height: 1.65;
            font-weight: 300;
        }

        /* ---------- Banner destacado ---------- */
        #banner {
            padding: 6rem 0;
            background: linear-gradient(135deg, rgba(225, 29, 46, 0.14) 0%, rgba(10, 10, 10, 1) 55%, rgba(225, 29, 46, 0.09) 100%);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            text-align: center;
        }

        #banner h2 {
            font-size: clamp(1.8rem, 4.2vw, 3rem);
            margin-bottom: 1.1rem;
        }

        .contador-dato {
            font-size: clamp(2.6rem, 6vw, 4rem);
            font-weight: 800;
            color: var(--accent);
            font-family: 'Playfair Display', serif;
            line-height: 1;
        }

        .contador-etiqueta {
            color: var(--muted);
            font-size: 0.85rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            margin-top: 0.5rem;
        }

        /* ---------- Rubros ---------- */
        .card-rubro {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 2.4rem 1.9rem;
            height: 100%;
            text-align: center;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .card-rubro.activa { cursor: pointer; }

        .card-rubro.activa:hover {
            transform: translateY(-8px);
            border-color: var(--accent);
            box-shadow: 0 22px 50px rgba(225, 29, 46, 0.2);
        }

        .card-rubro.activa.seleccionada {
            border-color: var(--accent);
            background: rgba(225, 29, 46, 0.08);
        }

        .card-rubro.bloqueada {
            opacity: 0.42;
            cursor: not-allowed;
            filter: grayscale(0.6);
        }

        .icono-rubro {
            font-size: 2.6rem;
            color: var(--accent);
            margin-bottom: 1.1rem;
            display: block;
        }

        .card-rubro h3 {
            font-size: 1.4rem;
            margin-bottom: 0.5rem;
        }

        .card-rubro p {
            color: var(--muted);
            font-size: 0.92rem;
            font-weight: 300;
            margin-bottom: 1rem;
        }

        .badge-proximamente {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid var(--border);
            color: var(--muted);
            border-radius: 999px;
            padding: 0.28rem 0.8rem;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.03em;
        }

        .badge-disponible {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: rgba(225, 29, 46, 0.15);
            border: 1px solid rgba(225, 29, 46, 0.4);
            color: var(--accent-soft);
            border-radius: 999px;
            padding: 0.28rem 0.8rem;
            font-size: 0.72rem;
            font-weight: 600;
        }

        /* ---------- Panel de detalle del rubro ---------- */
        #panel-spa {
            display: none;
            margin-top: 3rem;
            background: var(--card);
            border: 1px solid rgba(225, 29, 46, 0.32);
            border-radius: 22px;
            padding: 2.6rem;
        }

        #panel-spa.abierto {
            display: block;
            animation: aparecerPanel 0.45s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes aparecerPanel {
            from { opacity: 0; transform: translateY(18px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .titulo-bloque-panel {
            font-size: 1.05rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            margin-bottom: 1.2rem;
            display: flex;
            align-items: center;
            gap: 0.55rem;
        }

        .lista-panel { list-style: none; padding: 0; margin: 0; }

        .lista-panel li {
            display: flex;
            align-items: flex-start;
            gap: 0.7rem;
            padding: 0.62rem 0;
            color: var(--muted);
            font-size: 0.93rem;
            font-weight: 300;
            border-bottom: 1px solid rgba(255, 255, 255, 0.045);
        }

        .lista-panel li:last-child { border-bottom: none; }
        .lista-panel li i.bi-check-circle-fill { color: var(--accent); flex-shrink: 0; margin-top: 2px; }
        .lista-panel li i.bi-lock-fill { color: var(--muted); flex-shrink: 0; margin-top: 2px; }
        .lista-panel li .texto-modulo { flex: 1; }

        .btn-deshabilitado {
            position: relative;
            background: transparent;
            color: var(--muted);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 0.6rem 1.4rem;
            font-weight: 600;
            font-size: 0.92rem;
            cursor: not-allowed;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            opacity: 0.72;
        }

        /* ---------- Contacto ---------- */
        .form-control-landing {
            background: var(--card);
            border: 1px solid var(--border);
            color: var(--white);
            border-radius: 12px;
            padding: 0.85rem 1.1rem;
            font-size: 0.94rem;
            transition: all 0.25s ease;
        }

        .form-control-landing:focus {
            background: var(--card);
            border-color: var(--accent);
            color: var(--white);
            box-shadow: 0 0 0 0.2rem rgba(225, 29, 46, 0.14);
            outline: none;
        }

        .form-control-landing::placeholder { color: rgba(163, 163, 163, 0.65); }

        .item-contacto {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.1rem 0;
            border-bottom: 1px solid var(--border);
        }

        .item-contacto:last-child { border-bottom: none; }

        .item-contacto .icono {
            width: 46px; height: 46px;
            border-radius: 12px;
            background: rgba(225, 29, 46, 0.12);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .item-contacto .etiqueta {
            color: var(--muted);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .item-contacto .valor { color: var(--white); font-size: 0.98rem; font-weight: 500; }

        /* ---------- Footer ---------- */
        footer {
            background: var(--bg-alt);
            border-top: 1px solid var(--border);
            padding: 3.5rem 0 2rem;
        }

        .enlace-footer {
            color: var(--muted);
            text-decoration: none;
            font-size: 0.9rem;
            margin: 0 0.85rem;
            transition: color 0.25s ease;
        }

        .enlace-footer:hover { color: var(--accent); }

        .copyright {
            color: var(--muted);
            font-size: 0.85rem;
            border-top: 1px solid var(--border);
            margin-top: 2.2rem;
            padding-top: 1.8rem;
        }

        /* ---------- Reveal on scroll ---------- */
        .reveal {
            opacity: 0;
            transform: translateY(28px);
            transition: opacity 0.7s cubic-bezier(0.4, 0, 0.2, 1), transform 0.7s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .reveal.visible { opacity: 1; transform: translateY(0); }

        @media (prefers-reduced-motion: reduce) {
            .reveal { opacity: 1; transform: none; transition: none; }
            * { scroll-behavior: auto; }
        }

        @media (max-width: 991.98px) {
            .linea-pasos { display: none; }
            .enlaces-nav-escritorio { display: none !important; }
        }

        @media (max-width: 767.98px) {
            .seccion { padding: 4.5rem 0; }
            #panel-spa { padding: 1.7rem; }
        }
    </style>
</head>
<body>

    <!-- ================= NAVBAR ================= -->
    <nav id="navbar-landing">
        <div class="container d-flex align-items-center justify-content-between">
            <a href="#hero" class="marca-landing">
                <span class="punto"></span>
                <span>Plataforma Reservas</span>
            </a>
            <div class="d-flex align-items-center">
                <div class="enlaces-nav-escritorio d-none d-lg-flex align-items-center me-3">
                    <a href="#caracteristicas" class="enlace-nav">Características</a>
                    <a href="#como-funciona" class="enlace-nav">Cómo funciona</a>
                    <a href="#rubros" class="enlace-nav">Rubros</a>
                    <a href="#contacto" class="enlace-nav">Contacto</a>
                </div>
                <a href="{{ url('login') }}" class="btn-accent">
                    <i class="bi bi-box-arrow-in-right"></i> Iniciar sesión
                </a>
            </div>
        </div>
    </nav>

    <!-- ================= HERO ================= -->
    <header id="hero">
        <div class="hero-glow"></div>
        <svg class="hero-malla" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <pattern id="malla-hero" width="46" height="46" patternUnits="userSpaceOnUse">
                    <path d="M 46 0 L 0 0 0 46" fill="none" style="stroke: rgba(255,255,255,0.55);" stroke-width="1"></path>
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#malla-hero)"></rect>
        </svg>

        <div class="container text-center">
            <span class="etiqueta-hero">
                <i class="bi bi-stars"></i> Software de reservas para tu negocio
            </span>
            <h1>
                Gestiona tu negocio,<br>
                <span class="resaltado">sin complicaciones</span>
            </h1>
            <p class="subtitulo">
                Agenda, clientes y equipo en un solo lugar. Deja el cuaderno y los chats sueltos:
                organiza tus reservas desde cualquier dispositivo.
            </p>
            <div class="d-flex flex-wrap gap-3 justify-content-center">
                <a href="{{ url('login') }}" class="btn-accent">
                    <i class="bi bi-rocket-takeoff"></i> Empieza gratis
                </a>
                <a href="#caracteristicas" class="btn-linea">
                    <i class="bi bi-arrow-down-circle"></i> Ver características
                </a>
            </div>
        </div>
    </header>

    <!-- ================= CARACTERÍSTICAS ================= -->
    <section id="caracteristicas" class="seccion seccion-alt">
        <div class="container">
            <div class="text-center reveal">
                <span class="etiqueta-seccion">Incluido sin costo</span>
                <h2 class="titulo-seccion">Todo lo esencial, desde el primer día</h2>
                <p class="subtitulo-seccion">
                    El núcleo de la plataforma es gratuito. Estas son las herramientas con las que
                    empiezas a trabajar apenas creas tu cuenta.
                </p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-4 reveal">
                    <div class="card-caracteristica">
                        <div class="icono-caracteristica"><i class="bi bi-person-vcard"></i></div>
                        <h3>Gestión de clientes</h3>
                        <p>Guarda los datos de cada cliente, su historial y sus notas. Encuentra a cualquiera en segundos.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 reveal">
                    <div class="card-caracteristica">
                        <div class="icono-caracteristica"><i class="bi bi-calendar3"></i></div>
                        <h3>Calendario y disponibilidad</h3>
                        <p>Consulta la agenda del día por persona y evita choques de horario de forma automática.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 reveal">
                    <div class="card-caracteristica">
                        <div class="icono-caracteristica"><i class="bi bi-calendar-check"></i></div>
                        <h3>Motor de reservas</h3>
                        <p>Agenda una cita en pocos clics: el sistema calcula la duración y valida quién está libre.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 reveal">
                    <div class="card-caracteristica">
                        <div class="icono-caracteristica"><i class="bi bi-envelope-check"></i></div>
                        <h3>Notificaciones por correo</h3>
                        <p>Mantén informado a tu equipo y a tus clientes sobre las citas y sus cambios de estado.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 reveal">
                    <div class="card-caracteristica">
                        <div class="icono-caracteristica"><i class="bi bi-shield-lock"></i></div>
                        <h3>Multi-negocio</h3>
                        <p>Cada cuenta trabaja con su propia información, completamente aislada de las demás.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 reveal">
                    <div class="card-caracteristica">
                        <div class="icono-caracteristica"><i class="bi bi-speedometer2"></i></div>
                        <h3>Panel de control</h3>
                        <p>Un tablero con los indicadores de tu operación para saber cómo va tu negocio de un vistazo.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= CÓMO FUNCIONA ================= -->
    <section id="como-funciona" class="seccion">
        <div class="container">
            <div class="text-center reveal">
                <span class="etiqueta-seccion">Puesta en marcha</span>
                <h2 class="titulo-seccion">Empieza en tres pasos</h2>
                <p class="subtitulo-seccion">
                    Sin instalaciones ni configuraciones complicadas. Entras y empiezas a trabajar.
                </p>
            </div>

            <div class="row g-4 position-relative">
                <div class="linea-pasos d-none d-lg-block"></div>

                <div class="col-md-4 reveal">
                    <div class="paso">
                        <div class="numero-paso">1</div>
                        <h3>Crea tu cuenta gratis</h3>
                        <p>Regístrate en minutos. No necesitas tarjeta de crédito para comenzar a usar el núcleo.</p>
                    </div>
                </div>
                <div class="col-md-4 reveal">
                    <div class="paso">
                        <div class="numero-paso">2</div>
                        <h3>Configura tu negocio</h3>
                        <p>Agrega los servicios que ofreces y las personas de tu equipo que atenderán las citas.</p>
                    </div>
                </div>
                <div class="col-md-4 reveal">
                    <div class="paso">
                        <div class="numero-paso">3</div>
                        <h3>Empieza a recibir reservas</h3>
                        <p>Agenda desde el panel y lleva el control de cada cita, desde que se crea hasta que se completa.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= BANNER DESTACADO ================= -->
    <section id="banner">
        <div class="container">
            <div class="reveal">
                <div class="contador-dato" id="contador-rubros">0</div>
                <div class="contador-etiqueta">Rubros de negocio contemplados</div>

                <h2 class="mt-4">Moderniza la forma en que atiendes</h2>
                <p class="subtitulo-seccion mb-4">
                    Menos tiempo cuadrando horarios, más tiempo con tus clientes.
                </p>
                <a href="{{ url('login') }}" class="btn-accent">
                    <i class="bi bi-arrow-right-circle"></i> Comenzar ahora
                </a>
            </div>
        </div>
    </section>

    <!-- ================= RUBROS ================= -->
    <section id="rubros" class="seccion seccion-alt">
        <div class="container">
            <div class="text-center reveal">
                <span class="etiqueta-seccion">Para tu tipo de negocio</span>
                <h2 class="titulo-seccion">Elige el tipo de negocio que tienes</h2>
                <p class="subtitulo-seccion">
                    La plataforma se adapta al rubro. Hoy está disponible para spa, y los demás
                    llegarán pronto.
                </p>
            </div>

            <div class="row g-4 justify-content-center">
                <div class="col-md-6 col-lg-4 reveal">
                    <div class="card-rubro activa" id="card-spa" onclick="abrirPanelSpa()">
                        <i class="bi bi-flower1 icono-rubro"></i>
                        <h3>Spa</h3>
                        <p>Masajes, faciales y tratamientos con agenda por profesional.</p>
                        <span class="badge-disponible"><i class="bi bi-check-circle-fill"></i> Disponible</span>
                        <div class="mt-3">
                            <small style="color: var(--muted);">Toca para ver el detalle</small>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 reveal">
                    <div class="card-rubro bloqueada">
                        <i class="bi bi-building icono-rubro"></i>
                        <h3>Hotel</h3>
                        <p>Habitaciones, huéspedes y estadías por noche.</p>
                        <span class="badge-proximamente"><i class="bi bi-hourglass-split"></i> Próximamente</span>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 reveal">
                    <div class="card-rubro bloqueada">
                        <i class="bi bi-tree icono-rubro"></i>
                        <h3>Finca</h3>
                        <p>Alquiler de fincas y espacios para eventos por días.</p>
                        <span class="badge-proximamente"><i class="bi bi-hourglass-split"></i> Próximamente</span>
                    </div>
                </div>
            </div>

            <!-- Panel de detalle del rubro Spa -->
            <div id="panel-spa">
                <div class="text-center mb-4">
                    <span class="etiqueta-seccion">Plan para spa</span>
                    <h3 class="fuente-serif" style="font-size: 1.8rem;">Lo que incluye tu cuenta</h3>
                </div>

                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="titulo-bloque-panel">
                            <i class="bi bi-gift" style="color: var(--accent);"></i>
                            Gratis para siempre
                        </div>
                        <ul class="lista-panel">
                            <li><i class="bi bi-check-circle-fill"></i><span class="texto-modulo">Base de clientes con historial y notas</span></li>
                            <li><i class="bi bi-check-circle-fill"></i><span class="texto-modulo">Calendario diario con disponibilidad por profesional</span></li>
                            <li><i class="bi bi-check-circle-fill"></i><span class="texto-modulo">Reservas con validación automática de horarios</span></li>
                            <li><i class="bi bi-check-circle-fill"></i><span class="texto-modulo">Avisos por correo de citas y cambios de estado</span></li>
                            <li><i class="bi bi-check-circle-fill"></i><span class="texto-modulo">Tu información aislada del resto de negocios</span></li>
                            <li><i class="bi bi-check-circle-fill"></i><span class="texto-modulo">Panel con los indicadores de tu operación</span></li>
                        </ul>
                    </div>

                    <div class="col-lg-6">
                        <div class="titulo-bloque-panel">
                            <i class="bi bi-box-seam" style="color: var(--muted);"></i>
                            Módulos adicionales
                        </div>
                        <ul class="lista-panel">
                            <li>
                                <i class="bi bi-lock-fill"></i>
                                <span class="texto-modulo">Contenido para redes sociales</span>
                                <span class="badge-proximamente">Próximamente</span>
                            </li>
                            <li>
                                <i class="bi bi-lock-fill"></i>
                                <span class="texto-modulo">Cálculo de comisiones para tu equipo</span>
                                <span class="badge-proximamente">Próximamente</span>
                            </li>
                            <li>
                                <i class="bi bi-lock-fill"></i>
                                <span class="texto-modulo">Catálogo de productos y extras</span>
                                <span class="badge-proximamente">Próximamente</span>
                            </li>
                            <li>
                                <i class="bi bi-lock-fill"></i>
                                <span class="texto-modulo">Chatbot de soporte con IA</span>
                                <span class="badge-proximamente">Próximamente</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-3 justify-content-center mt-4 pt-3" style="border-top: 1px solid var(--border);">
                    <a href="{{ url('login') }}" class="btn-accent">
                        <i class="bi bi-rocket-takeoff"></i> Empezar gratis
                    </a>
                    <button type="button" class="btn-deshabilitado" disabled
                            data-bs-toggle="tooltip" title="Disponible pronto">
                        <i class="bi bi-lock-fill"></i>
                        Quiero membresía completa
                        <span class="badge-proximamente ms-1">Próximamente</span>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= CONTACTO ================= -->
    <section id="contacto" class="seccion">
        <div class="container">
            <div class="text-center reveal">
                <span class="etiqueta-seccion">Hablemos</span>
                <h2 class="titulo-seccion">¿Tienes preguntas?</h2>
                <p class="subtitulo-seccion">
                    Escríbenos y te contamos cómo la plataforma se ajusta a tu negocio.
                </p>
            </div>

            <div class="row g-5 align-items-start">
                <div class="col-lg-7 reveal">
                    <div id="formulario-contacto">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <input type="text" id="contacto-nombre" class="form-control form-control-landing" placeholder="Tu nombre">
                            </div>
                            <div class="col-md-6">
                                <input type="email" id="contacto-email" class="form-control form-control-landing" placeholder="Tu correo electrónico">
                            </div>
                            <div class="col-12">
                                <textarea id="contacto-mensaje" rows="5" class="form-control form-control-landing" placeholder="Cuéntanos sobre tu negocio"></textarea>
                            </div>
                            <div class="col-12">
                                <button type="button" id="btn-enviar-contacto" class="btn-accent">
                                    <i class="bi bi-send"></i> Enviar mensaje
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5 reveal">
                    <!-- Datos de contacto temporales/ficticios, reemplazar cuando existan los reales -->
                    <div class="item-contacto">
                        <div class="icono"><i class="bi bi-whatsapp"></i></div>
                        <div>
                            <div class="etiqueta">WhatsApp</div>
                            <div class="valor">+57 300 123 4567</div>
                        </div>
                    </div>
                    <div class="item-contacto">
                        <div class="icono"><i class="bi bi-envelope"></i></div>
                        <div>
                            <div class="etiqueta">Correo</div>
                            <div class="valor">hola@plataformareservas.test</div>
                        </div>
                    </div>
                    <div class="item-contacto">
                        <div class="icono"><i class="bi bi-clock"></i></div>
                        <div>
                            <div class="etiqueta">Horario de atención</div>
                            <div class="valor">Lunes a viernes, 8:00 a 18:00</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= FOOTER ================= -->
    <footer>
        <div class="container text-center">
            <a href="#hero" class="marca-landing justify-content-center mb-3">
                <span class="punto"></span>
                <span>Plataforma Reservas</span>
            </a>

            <div class="mb-3">
                <a href="#caracteristicas" class="enlace-footer">Características</a>
                <a href="#como-funciona" class="enlace-footer">Cómo funciona</a>
                <a href="#rubros" class="enlace-footer">Rubros</a>
                <a href="#contacto" class="enlace-footer">Contacto</a>
                <a href="{{ url('login') }}" class="enlace-footer">Iniciar sesión</a>
            </div>

            <div class="copyright">
                &copy; {{ date('Y') }} Plataforma Reservas. Todos los derechos reservados.
            </div>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios@1.7.7/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Navbar: fondo sólido al pasar los 50px de scroll.
        jQuery(window).on('scroll', function () {
            if (jQuery(window).scrollTop() > 50) {
                jQuery('#navbar-landing').addClass('scrolled');
            } else {
                jQuery('#navbar-landing').removeClass('scrolled');
            }
        });

        // Aparición progresiva de las secciones al entrar en pantalla.
        (function () {
            var elementos = document.querySelectorAll('.reveal');

            if (!('IntersectionObserver' in window)) {
                elementos.forEach(function (el) { el.classList.add('visible'); });
                return;
            }

            var observador = new IntersectionObserver(function (entradas) {
                entradas.forEach(function (entrada, indice) {
                    if (entrada.isIntersecting) {
                        setTimeout(function () {
                            entrada.target.classList.add('visible');
                        }, indice * 90);
                        observador.unobserve(entrada.target);
                    }
                });
            }, { threshold: 0.12, rootMargin: '0px 0px -60px 0px' });

            elementos.forEach(function (el) { observador.observe(el); });
        })();

        // Contador del banner: cuenta hasta la cantidad de rubros contemplados.
        (function () {
            var destino = document.getElementById('contador-rubros');
            if (!destino) { return; }

            var valorFinal = 3;
            var yaAnimado = false;

            function animarContador() {
                var actual = 0;
                var intervalo = setInterval(function () {
                    actual++;
                    destino.textContent = actual;
                    if (actual >= valorFinal) { clearInterval(intervalo); }
                }, 220);
            }

            if (!('IntersectionObserver' in window)) {
                destino.textContent = valorFinal;
                return;
            }

            new IntersectionObserver(function (entradas, obs) {
                entradas.forEach(function (entrada) {
                    if (entrada.isIntersecting && !yaAnimado) {
                        yaAnimado = true;
                        animarContador();
                        obs.unobserve(entrada.target);
                    }
                });
            }, { threshold: 0.5 }).observe(destino);
        })();

        // Panel de detalle del rubro Spa.
        function abrirPanelSpa() {
            var panel = jQuery('#panel-spa');
            jQuery('#card-spa').addClass('seleccionada');

            if (!panel.hasClass('abierto')) {
                panel.addClass('abierto');
            }

            setTimeout(function () {
                document.getElementById('panel-spa').scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 60);
        }

        // Formulario de contacto: por ahora solo confirma visualmente, no hay backend.
        jQuery('#btn-enviar-contacto').on('click', function () {
            var nombre = jQuery('#contacto-nombre').val().trim();
            var email = jQuery('#contacto-email').val().trim();
            var mensaje = jQuery('#contacto-mensaje').val().trim();

            if (nombre === '' || email === '' || mensaje === '') {
                Swal.fire({
                    title: 'Faltan datos',
                    text: 'Completa tu nombre, tu correo y el mensaje para poder responderte.',
                    icon: 'warning',
                    background: '#0d0d0d',
                    color: '#ffffff',
                    confirmButtonColor: '#e11d2e',
                    confirmButtonText: 'Entendido'
                });
                return;
            }

            Swal.fire({
                title: '¡Gracias por escribirnos!',
                text: 'Recibimos tus datos y te contactaremos muy pronto.',
                icon: 'success',
                background: '#0d0d0d',
                color: '#ffffff',
                confirmButtonColor: '#e11d2e',
                confirmButtonText: 'Cerrar'
            });

            jQuery('#contacto-nombre').val('');
            jQuery('#contacto-email').val('');
            jQuery('#contacto-mensaje').val('');
        });

        // Tooltips del botón de membresía.
        jQuery(function () {
            var lista = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            lista.map(function (el) { return new bootstrap.Tooltip(el); });
        });
    </script>
</body>
</html>
