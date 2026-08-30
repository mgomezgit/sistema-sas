<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plataforma Reservas - Iniciar sesión</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root {
            --bg-body: #0a0a0d;
            --bg-card: #17171c;
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
            --radius-card: 16px;
            --radius-sm: 10px;
            --shadow-card: 0 1px 2px rgba(0,0,0,0.4), 0 8px 24px rgba(0,0,0,0.25);
            --shadow-glow: 0 0 0 1px var(--accent-soft), 0 4px 20px var(--accent-glow);
            --transition-base: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
        }

        .fondo-login {
            position: fixed;
            inset: 0;
            overflow: hidden;
            z-index: 0;
            pointer-events: none;
        }

        .fondo-malla {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            opacity: 0.09;
        }

        .login-glow {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: clamp(620px, 55vw, 900px);
            height: clamp(620px, 55vw, 900px);
            background: radial-gradient(circle, var(--accent-glow) 0%, transparent 70%);
            filter: blur(70px);
            opacity: 0.9;
        }

        .fondo-blob {
            position: absolute;
            border-radius: 58% 42% 38% 62% / 55% 35% 65% 45%;
            filter: blur(90px);
            will-change: transform;
        }

        .fondo-blob-1 {
            top: -16%;
            left: -12%;
            width: clamp(480px, 42vw, 820px);
            height: clamp(480px, 42vw, 820px);
            background: var(--accent-glow);
            opacity: 0.4;
            animation: derivaBlob1 22s ease-in-out infinite alternate;
        }

        .fondo-blob-2 {
            bottom: -18%;
            right: -10%;
            width: clamp(560px, 48vw, 920px);
            height: clamp(560px, 48vw, 920px);
            background: var(--text-muted);
            opacity: 0.28;
            animation: derivaBlob2 26s ease-in-out infinite alternate;
        }

        .fondo-blob-3 {
            bottom: 4%;
            left: -10%;
            width: clamp(320px, 28vw, 560px);
            height: clamp(320px, 28vw, 560px);
            background: var(--accent-glow);
            opacity: 0.32;
            animation: derivaBlob3 19s ease-in-out infinite alternate;
        }

        @keyframes derivaBlob1 {
            from { transform: translate(0, 0) scale(1); }
            to { transform: translate(30px, 22px) scale(1.05); }
        }

        @keyframes derivaBlob2 {
            from { transform: translate(0, 0) scale(1); }
            to { transform: translate(-26px, -16px) scale(1.03); }
        }

        @keyframes derivaBlob3 {
            from { transform: translate(0, 0); }
            to { transform: translate(18px, -22px); }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(14px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card-elevada {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-card);
            padding: 2.25rem;
        }

        .card-acento {
            border-top: 3px solid var(--accent);
            box-shadow: var(--shadow-glow), var(--shadow-card);
        }

        #tarjeta-login {
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 1;
            animation: fadeInUp 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .login-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            margin-bottom: 1.75rem;
        }

        .login-logo .logo-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background-color: var(--accent);
            box-shadow: 0 0 10px var(--accent-glow);
            flex-shrink: 0;
        }

        .login-logo span {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 1.1rem;
            letter-spacing: 0.2px;
        }

        .form-label {
            color: var(--text-secondary);
            font-size: 0.85rem;
            font-weight: 500;
        }

        .form-control {
            background-color: var(--bg-input);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            transition: var(--transition-base);
        }

        .form-control:focus {
            background-color: var(--bg-input);
            border-color: var(--accent);
            color: var(--text-primary);
            box-shadow: 0 0 0 0.2rem var(--accent-soft);
        }

        .form-control::placeholder {
            color: var(--text-muted);
        }

        .input-group-text {
            background-color: var(--bg-input);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            transition: var(--transition-base);
        }

        .input-group:focus-within .input-group-text {
            color: var(--accent);
            border-color: var(--accent);
        }

        .btn-primario-accento {
            background-color: var(--accent);
            border: 1px solid var(--accent);
            color: #fff;
            border-radius: var(--radius-sm);
            font-weight: 600;
            padding: 0.6rem 1.15rem;
            transition: var(--transition-base);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
        }

        .btn-primario-accento:hover:not(:disabled) {
            background-color: var(--accent-hover);
            border-color: var(--accent-hover);
            color: #fff;
            transform: translateY(-1px);
        }

        .btn-primario-accento:disabled {
            opacity: 0.75;
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
    </style>
</head>
<body>

    <div class="fondo-login" aria-hidden="true">
        <svg class="fondo-malla" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <pattern id="patron-malla-login" width="42" height="42" patternUnits="userSpaceOnUse">
                    <path d="M 42 0 L 0 0 0 42" fill="none" style="stroke: var(--border-color-strong);" stroke-width="1.1"></path>
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#patron-malla-login)"></rect>
        </svg>

        <div class="fondo-blob fondo-blob-1"></div>
        <div class="fondo-blob fondo-blob-2"></div>
        <div class="fondo-blob fondo-blob-3"></div>

        <div class="login-glow"></div>
    </div>

    <div id="loader_proceso">
        <div class="spinner-border" role="status" style="width: 3rem; height: 3rem; color: var(--accent);">
            <span class="visually-hidden">Cargando...</span>
        </div>
    </div>

    <div id="tarjeta-login" class="card-elevada card-acento">
        <div class="login-logo">
            <span class="logo-dot"></span>
            <span>Plataforma Reservas</span>
        </div>

        <div id="contenedor-login">
            <div class="mb-3">
                <label class="form-label">Correo electrónico</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" id="email" class="form-control" placeholder="tucorreo@negocio.com">
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label">Contraseña</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" id="clave" class="form-control" placeholder="••••••••">
                </div>
            </div>
            <button type="button" id="btn-ingresar" class="btn-primario-accento w-100">Ingresar</button>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios@1.7.7/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        const UrlGlobal = "{{ url('/') }}/";
    </script>
    <script src="{{ asset('js/utilidades.js') }}"></script>

    <script>
        // Aviso de bienvenida cuando se llega desde el registro público.
        @if (request()->query('registrado') == '1')
            Swal.fire({
                title: 'Cuenta creada correctamente',
                text: 'Inicia sesión para continuar.',
                icon: 'success',
                background: '#17171c',
                color: '#f4f4f5',
                confirmButtonColor: '#e11d2e',
                confirmButtonText: 'Entendido'
            });
        @endif

        jQuery("#btn-ingresar").on("click", function () {
            var $boton = jQuery(this);
            var textoOriginal = $boton.html();
            var email = jQuery("#email").val();
            var clave = jQuery("#clave").val();

            $boton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Ingresando...');

            axiosSipleInterno('POST', 'request/autenticacion/login', {}, { email: email, clave: clave }, false, function (respuesta) {
                if (respuesta.error == 0) {
                    location.href = UrlGlobal + 'backoffice/dashboard';
                } else {
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            }).then(function () {
                if ($boton.is(':disabled')) {
                    $boton.prop('disabled', false).html(textoOriginal);
                }
            });
        });
    </script>
</body>
</html>
