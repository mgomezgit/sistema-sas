<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Reserva tu cita')</title>

    {{-- Esta página NO hereda layout.backoffice (es anónima y vive fuera de
         él): tiene su propio armazón, su propia paleta y sus propios
         componentes. No es una omisión, es la decisión ya documentada en
         CLAUDE.md ("Página pública de autogestión"): paleta FIJA oro-rosa +
         blanco para todos los negocios, sin importar el modo_tema o
         color_acento que cada uno eligió para su backoffice. --}}
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&display=swap">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        /* ===== Paleta fija de la página pública (no negociable por negocio) =====
           Único bloque del que puede salir un hexadecimal: todo lo demás en
           este archivo y en publico/pagina.blade.php usa estas variables o
           color-mix() derivado de ellas. */
        :root {
            --bg-page: #fbf8f5;
            --bg-card: #ffffff;
            --text-primary: #2b2622;
            --text-secondary: #8a7f76;
            --border-color: #e6ddd3;
            --bezel: #2c2620;
            --accent: #b76e79;
            --on-accent: #ffffff;
            --on-bezel: #efe9e2;
            --danger-publico: #b3392f;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Work Sans', 'Segoe UI', sans-serif;
            background: var(--bg-page);
            color: var(--text-primary);
        }

        h1, h2, h3, p { margin: 0; }

        .lienzo { position: relative; overflow: hidden; }

        .mancha { position: absolute; border-radius: 50%; z-index: 0; pointer-events: none; }
        .mancha1 { top: -140px; right: -100px; width: 480px; height: 480px; background: color-mix(in srgb, var(--accent) 16%, transparent); }
        .mancha2 { top: 260px; left: -160px; width: 320px; height: 320px; background: color-mix(in srgb, var(--accent) 9%, transparent); }
        .mancha3 { top: 1150px; right: -140px; width: 360px; height: 360px; background: color-mix(in srgb, var(--accent) 8%, transparent); }

        /* ===== Barra superior ===== */
        .nav { position: relative; z-index: 2; display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 22px 5vw; flex-wrap: wrap; }
        .nav-marca { display: flex; align-items: center; gap: 12px; }
        .nav-logo { width: 44px; height: 44px; min-width: 44px; border-radius: 50%; background: var(--bg-page); border: 1.5px solid var(--accent); display: flex; align-items: center; justify-content: center; font-family: 'Fraunces', serif; font-weight: 600; font-size: 19px; color: var(--accent); }
        .nav-nombre { font-family: 'Fraunces', serif; font-size: 19px; font-weight: 600; }
        .nav-links { display: flex; gap: 28px; font-size: 12.5px; color: var(--text-secondary); font-weight: 600; letter-spacing: .02em; flex-wrap: wrap; }
        .nav-links a { color: inherit; text-decoration: none; cursor: pointer; }
        .nav-links a:hover { color: var(--accent); }
        .btn-primario, .nav-cta {
            font-family: 'Work Sans', sans-serif; background: var(--accent); color: var(--on-accent); border: none;
            padding: 13px 26px; border-radius: 999px; font-size: 13px; font-weight: 600; cursor: pointer;
            box-shadow: 0 10px 22px color-mix(in srgb, var(--accent) 30%, transparent);
        }
        .btn-primario { padding: 16px 30px; font-size: 13.5px; }
        .btn-fantasma {
            font-family: 'Work Sans', sans-serif; display: inline-flex; align-items: center; gap: 8px;
            background: var(--bg-page); border: 1.5px solid color-mix(in srgb, var(--accent) 45%, transparent);
            color: var(--accent); padding: 15px 26px; border-radius: 999px; font-size: 13.5px; font-weight: 600;
            cursor: pointer; transition: .2s; text-decoration: none;
        }
        .btn-fantasma:hover { background: color-mix(in srgb, var(--accent) 8%, var(--bg-page)); color: var(--accent); }

        /* ===== Hero ===== */
        .hero { position: relative; z-index: 1; display: grid; grid-template-columns: 1fr 1fr; gap: 40px; align-items: center; padding: 20px 5vw 90px; }
        .hero-saludo { font-family: 'Fraunces', serif; font-size: 44px; font-weight: 600; line-height: 1.18; margin: 0 0 18px; letter-spacing: -.01em; }
        .hero-parrafo { font-size: 15px; color: var(--text-secondary); line-height: 1.7; max-width: 460px; margin: 0 0 28px; }
        .hero-botones { display: flex; gap: 14px; margin-bottom: 34px; flex-wrap: wrap; }
        .fila-valores { display: flex; gap: 30px; flex-wrap: wrap; }
        .valor-item { display: flex; align-items: center; gap: 10px; }
        .valor-icono { width: 34px; height: 34px; min-width: 34px; border-radius: 50%; background: color-mix(in srgb, var(--accent) 10%, var(--bg-page)); border: 1px solid color-mix(in srgb, var(--accent) 30%, transparent); color: var(--accent); display: flex; align-items: center; justify-content: center; }
        .valor-texto { font-size: 12px; font-weight: 600; color: var(--text-secondary); max-width: 100px; line-height: 1.3; }

        .hero-visual { position: relative; height: 420px; border-radius: 8px; background: linear-gradient(150deg, color-mix(in srgb, var(--accent) 18%, var(--bg-page)), color-mix(in srgb, var(--accent) 5%, var(--bg-page))); display: flex; align-items: center; justify-content: center; }
        .hero-visual::before { content: ''; position: absolute; inset: 20px; border: 1px solid color-mix(in srgb, var(--accent) 35%, transparent); border-radius: 4px; }
        .hero-visual-letra { font-family: 'Fraunces', serif; font-size: 150px; font-weight: 600; color: color-mix(in srgb, var(--accent) 45%, var(--bg-page)); }
        .widget-reserva { position: absolute; left: -20px; bottom: -34px; width: 280px; max-width: calc(100% - 20px); background: var(--bg-card); border-radius: 14px; padding: 20px; box-shadow: 0 20px 44px rgba(0,0,0,.16); border: 1px solid var(--border-color); }
        .widget-titulo { font-family: 'Fraunces', serif; font-size: 15px; font-weight: 600; margin: 0 0 12px; }
        .widget-dias, .widget-horas { display: flex; gap: 6px; margin-bottom: 12px; flex-wrap: wrap; }
        .chip-dia, .chip-hora { flex: 1; min-width: 30px; text-align: center; padding: 8px 2px; border-radius: 8px; background: var(--bg-page); font-size: 11px; font-weight: 600; color: var(--text-secondary); }
        .chip-dia.activo { background: var(--accent); color: var(--on-accent); }
        .btn-widget { font-family: 'Work Sans', sans-serif; width: 100%; background: var(--accent); color: var(--on-accent); border: none; padding: 12px; border-radius: 8px; font-size: 12.5px; font-weight: 600; cursor: pointer; margin-top: 4px; }

        .divisor-ondulado { position: relative; z-index: 1; margin-top: -1px; line-height: 0; }

        /* ===== Secciones ===== */
        .seccion { position: relative; z-index: 1; padding: 10px 5vw 60px; }
        .titulo-seccion { font-family: 'Fraunces', serif; font-size: 28px; font-weight: 600; margin: 0 0 8px; text-align: center; }
        .subtitulo-seccion { text-align: center; font-size: 13.5px; color: var(--text-secondary); margin: 0 0 40px; }

        .seccion-promos { background: color-mix(in srgb, var(--accent) 7%, var(--bg-page)); padding-top: 30px; }

        .historias { display: flex; justify-content: center; gap: 22px; margin-bottom: 34px; flex-wrap: wrap; }
        .historia { display: flex; flex-direction: column; align-items: center; gap: 8px; cursor: pointer; border: none; background: none; padding: 0; }
        .aro-historia { width: 74px; height: 74px; border-radius: 50%; padding: 4px; background: var(--border-color); transition: transform .2s, background .2s; }
        .aro-historia.activo { background: var(--accent); }
        .historia:hover .aro-historia { transform: scale(1.05); }
        .aro-interior { width: 100%; height: 100%; border-radius: 50%; background: var(--bg-page); display: flex; align-items: center; justify-content: center; color: var(--accent); font-size: 11px; font-weight: 700; text-align: center; line-height: 1.15; padding: 4px; overflow: hidden; }
        .historia-texto { font-size: 11.5px; font-weight: 600; color: var(--text-secondary); max-width: 80px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .banner-grande { position: relative; max-width: 980px; margin: 0 auto; min-height: 200px; border-radius: 8px; overflow: hidden; display: flex; align-items: center; padding: 30px 6vw; background-size: cover; background-position: center; }
        .banner-grande::before { content: ''; position: absolute; inset: 0; background: linear-gradient(120deg, color-mix(in srgb, black 45%, transparent), color-mix(in srgb, black 15%, transparent)); }
        .banner-contenido { position: relative; z-index: 1; color: var(--on-accent); max-width: 560px; }
        .banner-titulo { font-family: 'Fraunces', serif; font-size: 26px; font-weight: 600; margin: 0 0 10px; }
        .banner-texto { font-size: 14px; opacity: .92; margin: 0; }

        /* ===== Servicios ===== */
        .titulo-categoria-servicio { font-family: 'Fraunces', serif; font-size: 16px; font-weight: 600; margin: 28px 0 14px; max-width: 1100px; margin-left: auto; margin-right: auto; }
        .titulo-categoria-servicio:first-child { margin-top: 0; }
        .grid-servicios { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px; max-width: 1100px; margin: 0 auto; }
        .tarjeta-servicio { display: flex; align-items: center; gap: 14px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 10px; padding: 18px; transition: transform .2s, box-shadow .2s; }
        .tarjeta-servicio:hover { transform: translateY(-4px); box-shadow: 0 14px 28px rgba(0,0,0,.08); }
        .globo-icono { width: 42px; height: 42px; min-width: 42px; border-radius: 50%; background: color-mix(in srgb, var(--accent) 10%, var(--bg-page)); border: 1px solid color-mix(in srgb, var(--accent) 30%, transparent); color: var(--accent); display: flex; align-items: center; justify-content: center; }
        .servicio-nombre { font-size: 14.5px; font-weight: 600; margin: 0; }
        .servicio-duracion { font-size: 11.5px; color: var(--text-secondary); margin: 2px 0 0; }
        .servicio-precio { margin-left: auto; font-family: 'Fraunces', serif; font-weight: 600; color: var(--accent); font-size: 16px; white-space: nowrap; }

        /* ===== Equipo ===== */
        .grid-equipo { display: flex; justify-content: center; gap: 26px; max-width: 1000px; margin: 0 auto; flex-wrap: wrap; }
        .tarjeta-persona { flex: 1 1 200px; max-width: 240px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 10px; padding: 26px 18px; text-align: center; }
        .avatar-persona { width: 70px; height: 70px; border-radius: 50%; border: 1.5px solid var(--accent); color: var(--accent); display: flex; align-items: center; justify-content: center; font-family: 'Fraunces', serif; font-weight: 600; font-size: 24px; margin: 0 auto 14px; }
        .persona-nombre { font-family: 'Fraunces', serif; font-size: 15.5px; font-weight: 600; margin: 0; }

        /* ===== Franja de horario/contacto ===== */
        .franja-info { position: relative; z-index: 1; display: flex; justify-content: center; gap: 32px; flex-wrap: wrap; padding: 0 5vw 40px; font-size: 13px; color: var(--text-secondary); }
        .franja-info .item-info { display: flex; align-items: center; gap: 8px; }
        .franja-info .item-info i { color: var(--accent); }

        /* ===== Pie ===== */
        .pie { position: relative; z-index: 1; background: var(--bezel); color: var(--on-bezel); padding: 56px 5vw 40px; text-align: center; }
        .pie-titulo { font-family: 'Fraunces', serif; font-size: 24px; font-weight: 600; margin: 0 0 10px; }
        .pie-sub { font-size: 13px; opacity: .8; margin: 0 0 26px; }
        .pie-politica { font-size: 12px; opacity: .65; max-width: 560px; margin: 0 auto 22px; white-space: pre-line; }
        .pie-copy { font-size: 11.5px; opacity: .55; }

        /* ===== WhatsApp flotante ===== */
        .btn-whatsapp {
            position: fixed; right: 24px; bottom: 24px; width: 56px; height: 56px; border-radius: 50%;
            background: var(--bg-page); border: 1.5px solid var(--accent); color: var(--accent);
            display: flex; align-items: center; justify-content: center; box-shadow: 0 12px 26px rgba(0,0,0,.18);
            cursor: pointer; z-index: 25; text-decoration: none;
        }

        /* ===== Modal de agendar =====
           No reutiliza el kit .modal-moderno de layout/backoffice.blade.php:
           esta página no carga ese layout ni su CSS (es un armazón aparte a
           propósito, ver nota arriba), así que el modal sigue la misma
           paleta e identidad tipográfica del resto de esta página. */
        .modal-publico .modal-content { border-radius: 16px; border: none; overflow: hidden; }
        .modal-publico .modal-header { background: var(--bezel); color: var(--on-bezel); border: none; padding: 22px 26px; }
        .modal-publico .modal-title { font-family: 'Fraunces', serif; font-weight: 600; }
        .modal-publico .btn-close { filter: invert(1) grayscale(1) brightness(2); }
        .modal-publico .modal-body { padding: 26px; background: var(--bg-page); }
        .modal-publico label { font-size: 12.5px; font-weight: 600; color: var(--text-secondary); margin-bottom: 4px; display: block; }
        .modal-publico .form-control, .modal-publico .form-select {
            border-radius: 8px; border: 1px solid var(--border-color); padding: 10px 12px; font-size: 14px;
        }
        .modal-publico .form-control:focus, .modal-publico .form-select:focus {
            border-color: var(--accent); box-shadow: 0 0 0 .2rem color-mix(in srgb, var(--accent) 20%, transparent);
        }
        .modal-publico .campo-agendar { margin-bottom: 16px; }
        .aviso-fecha-publica { display: none; font-size: 12px; color: var(--danger-publico); margin-top: 6px; }
        .aviso-fecha-publica.visible { display: block; }
        .aviso-error-agendar { display: none; font-size: 13px; color: var(--danger-publico); background: color-mix(in srgb, var(--danger-publico) 10%, transparent); border-radius: 8px; padding: 10px 12px; margin-bottom: 16px; }
        .aviso-error-agendar.visible { display: block; }
        .panel-exito-agendar { display: none; text-align: center; padding: 20px 0; }
        .panel-exito-agendar.visible { display: block; }
        .panel-exito-agendar i { font-size: 44px; color: var(--accent); margin-bottom: 12px; display: block; }
        #campo-trampa-agendar { position: absolute; left: -9999px; top: -9999px; }

        @media (max-width: 860px) {
            .hero { grid-template-columns: 1fr; padding-bottom: 130px; }
            .hero-visual { height: 320px; order: -1; }
            .hero-visual-letra { font-size: 110px; }
            .nav { justify-content: center; text-align: center; }
            .nav-links { justify-content: center; }
        }
    </style>

    @yield('estilos')
</head>
<body>
    @yield('content')

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios@1.7.7/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/utilidades.js') }}"></script>
    <script src="{{ asset('js/validacion-horario.js') }}"></script>

    @yield('scripts')
</body>
</html>
