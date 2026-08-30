<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plataforma Reservas - Crear cuenta</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

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
            --radius-sm: 10px;
        }

        body {
            background-color: var(--bg);
            color: var(--white);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
            padding: 3rem 0;
        }

        h1, h2, h3 { font-family: 'Playfair Display', Georgia, serif; font-weight: 700; }

        .fondo-glow {
            position: fixed;
            top: 0; left: 50%;
            transform: translateX(-50%);
            width: min(900px, 120vw);
            height: min(900px, 120vw);
            background: radial-gradient(circle, rgba(225, 29, 46, 0.14) 0%, transparent 65%);
            filter: blur(80px);
            pointer-events: none;
            z-index: 0;
        }

        .contenido { position: relative; z-index: 1; }

        .marca-registro {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            color: var(--white);
            text-decoration: none;
            font-weight: 600;
            font-size: 1.05rem;
            margin-bottom: 2rem;
        }

        .marca-registro .punto {
            width: 9px; height: 9px;
            border-radius: 50%;
            background: var(--accent);
            box-shadow: 0 0 12px rgba(225, 29, 46, 0.6);
        }

        .titulo-paso { font-size: clamp(1.7rem, 4vw, 2.4rem); margin-bottom: 0.7rem; }

        .subtitulo-paso {
            color: var(--muted);
            font-size: 1rem;
            font-weight: 300;
            max-width: 560px;
            margin: 0 auto 2.8rem;
            line-height: 1.65;
        }

        .card-rubro {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 2.2rem 1.7rem;
            height: 100%;
            text-align: center;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
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

        .icono-rubro { font-size: 2.5rem; color: var(--accent); margin-bottom: 1rem; display: block; }
        .card-rubro h3 { font-size: 1.35rem; margin-bottom: 0.45rem; }
        .card-rubro p { color: var(--muted); font-size: 0.9rem; font-weight: 300; margin-bottom: 1rem; }

        .badge-proximamente {
            display: inline-flex; align-items: center; gap: 0.35rem;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid var(--border);
            color: var(--muted);
            border-radius: 999px;
            padding: 0.28rem 0.8rem;
            font-size: 0.72rem; font-weight: 600;
        }

        .badge-disponible {
            display: inline-flex; align-items: center; gap: 0.35rem;
            background: rgba(225, 29, 46, 0.15);
            border: 1px solid rgba(225, 29, 46, 0.4);
            color: var(--accent-soft);
            border-radius: 999px;
            padding: 0.28rem 0.8rem;
            font-size: 0.72rem; font-weight: 600;
        }

        #paso-formulario { display: none; }

        #paso-formulario.abierto {
            display: block;
            animation: aparecer 0.45s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes aparecer {
            from { opacity: 0; transform: translateY(18px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .tarjeta-formulario {
            background: var(--card);
            border: 1px solid var(--border);
            border-top: 3px solid var(--accent);
            border-radius: 20px;
            padding: 2.4rem;
            max-width: 620px;
            margin: 0 auto;
        }

        .form-label { color: var(--muted); font-size: 0.85rem; font-weight: 500; }

        .form-control {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border);
            color: var(--white);
            border-radius: 12px;
            padding: 0.8rem 1rem;
            font-size: 0.94rem;
            transition: all 0.25s ease;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.04);
            border-color: var(--accent);
            color: var(--white);
            box-shadow: 0 0 0 0.2rem rgba(225, 29, 46, 0.14);
        }

        .form-control::placeholder { color: rgba(163, 163, 163, 0.6); }

        .input_vacio { border-color: var(--accent) !important; }

        .btn-accent {
            background: var(--accent);
            color: var(--white);
            border: 1px solid var(--accent);
            border-radius: var(--radius-sm);
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-accent:hover:not(:disabled) {
            background: var(--accent-soft);
            border-color: var(--accent-soft);
            color: var(--white);
            transform: translateY(-2px);
            box-shadow: 0 10px 26px rgba(225, 29, 46, 0.35);
        }

        .btn-accent:disabled { opacity: 0.7; }

        .enlace-secundario { color: var(--muted); text-decoration: none; font-size: 0.92rem; }
        .enlace-secundario:hover { color: var(--accent); }

        .nota-rubro {
            background: rgba(225, 29, 46, 0.08);
            border: 1px solid rgba(225, 29, 46, 0.25);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            color: var(--muted);
            font-size: 0.87rem;
            margin-bottom: 1.6rem;
        }

        #loader_proceso {
            display: none;
            position: fixed;
            inset: 0;
            background-color: rgba(10, 10, 10, 0.78);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>

    <div class="fondo-glow"></div>

    <div id="loader_proceso">
        <div class="spinner-border" role="status" style="width: 3rem; height: 3rem; color: var(--accent);">
            <span class="visually-hidden">Cargando...</span>
        </div>
    </div>

    <div class="container contenido">
        <div class="text-center">
            <a href="{{ url('/') }}" class="marca-registro">
                <span class="punto"></span>
                <span>Plataforma Reservas</span>
            </a>
        </div>

        <!-- PASO 1: elegir rubro -->
        <div id="paso-rubro" class="text-center">
            <h1 class="titulo-paso">¿Qué tipo de negocio tienes?</h1>
            <p class="subtitulo-paso">
                Elige tu rubro para crear tu cuenta. Hoy la plataforma está disponible para spa;
                los demás rubros llegarán pronto.
            </p>

            <div class="row g-4 justify-content-center">
                <div class="col-md-6 col-lg-4">
                    <div class="card-rubro activa" id="card-spa" onclick="seleccionarSpa()">
                        <i class="bi bi-flower1 icono-rubro"></i>
                        <h3>Spa</h3>
                        <p>Masajes, faciales y tratamientos con agenda por profesional.</p>
                        <span class="badge-disponible"><i class="bi bi-check-circle-fill"></i> Disponible</span>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card-rubro bloqueada">
                        <i class="bi bi-building icono-rubro"></i>
                        <h3>Hotel</h3>
                        <p>Habitaciones, huéspedes y estadías por noche.</p>
                        <span class="badge-proximamente"><i class="bi bi-hourglass-split"></i> Próximamente</span>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card-rubro bloqueada">
                        <i class="bi bi-tree icono-rubro"></i>
                        <h3>Finca</h3>
                        <p>Alquiler de fincas y espacios para eventos por días.</p>
                        <span class="badge-proximamente"><i class="bi bi-hourglass-split"></i> Próximamente</span>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <a href="{{ url('login') }}" class="enlace-secundario">¿Ya tienes cuenta? Inicia sesión</a>
            </div>
        </div>

        <!-- PASO 2: datos de la cuenta -->
        <div id="paso-formulario" class="mt-5">
            <div class="text-center">
                <h2 class="titulo-paso">Crea tu cuenta</h2>
                <p class="subtitulo-paso">
                    Con estos datos creamos tu negocio y tu usuario administrador.
                </p>
            </div>

            <div class="tarjeta-formulario">
                <div class="nota-rubro">
                    <i class="bi bi-flower1"></i> Estás creando una cuenta de tipo <strong>Spa</strong>.
                    <a href="#" class="enlace-secundario ms-1" onclick="volverARubros(); return false;">Cambiar</a>
                </div>

                <form id="contenedor-form-registro">
                    <input type="hidden" id="rubro" name="rubro" value="spa">

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Nombre del negocio</label>
                            <input type="text" id="nombre_negocio" name="nombre_negocio" maxlength="150" class="form-control system_validador_vacio" placeholder="Ej. Spa Serenidad">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Teléfono de contacto</label>
                            <input type="text" id="telefono_contacto" name="telefono_contacto" maxlength="30" class="form-control system_validador_vacio" placeholder="Ej. 3001234567">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Tu nombre</label>
                            <input type="text" id="nombre" name="nombre" maxlength="100" class="form-control system_validador_vacio" placeholder="Ej. María Pérez">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Correo electrónico</label>
                            <input type="email" id="email" name="email" maxlength="150" class="form-control system_validador_vacio system_validador_email" placeholder="tucorreo@negocio.com">
                            <small style="color: var(--muted); font-size: 0.8rem;">Con este correo entrarás a la plataforma.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Contraseña</label>
                            <input type="password" id="clave" name="clave" maxlength="255" class="form-control system_validador_vacio" placeholder="••••••••">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Confirmar contraseña</label>
                            <input type="password" id="confirmar_clave" name="confirmar_clave" maxlength="255" class="form-control system_validador_vacio" placeholder="••••••••">
                        </div>
                    </div>
                </form>

                <div class="d-grid mt-4">
                    <button type="button" id="btn-crear-cuenta" class="btn-accent">
                        <i class="bi bi-rocket-takeoff"></i> Crear mi cuenta
                    </button>
                </div>

                <div class="text-center mt-3">
                    <a href="{{ url('login') }}" class="enlace-secundario">¿Ya tienes cuenta? Inicia sesión</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios@1.7.7/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        const UrlGlobal = "{{ url('/') }}/";
    </script>
    <script src="{{ asset('js/utilidades.js') }}"></script>
    <script src="{{ asset('js/validador.js') }}"></script>

    <script>
        function seleccionarSpa() {
            jQuery('#card-spa').addClass('seleccionada');
            jQuery('#paso-formulario').addClass('abierto');

            setTimeout(function () {
                document.getElementById('paso-formulario').scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 60);
        }

        function volverARubros() {
            jQuery('#paso-formulario').removeClass('abierto');
            jQuery('#card-spa').removeClass('seleccionada');
            document.getElementById('paso-rubro').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        jQuery('#btn-crear-cuenta').on('click', function () {
            if (!system_validarcampos('contenedor-form-registro', 1)) {
                return;
            }

            var datos = getDataJson('contenedor-form-registro');

            if (datos.clave !== datos.confirmar_clave) {
                notificarUsuario('Las claves no coinciden. Verifica que ambas contraseñas sean iguales.', 'error');
                return;
            }

            axiosSipleInterno('POST', 'request/registro-publico/crear', {}, datos, true, function (respuesta) {
                if (respuesta.error == 0) {
                    location.href = UrlGlobal + 'login?registrado=1';
                } else {
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        });
    </script>
</body>
</html>
