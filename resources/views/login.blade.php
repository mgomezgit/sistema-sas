<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plataforma Reservas - Iniciar sesión</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    @include('partials.estilos-acceso')
</head>
<body>

    @include('partials.fondo-acceso')

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
            <div class="text-center mt-3">
                <a href="{{ url('recuperar-clave') }}" id="enlace-olvide-clave" class="enlace-acceso">¿Olvidaste tu contraseña?</a>
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

        // Motivo por el que se cerró la sesión (por ejemplo, negocio suspendido).
        // La directiva json de Blade lo entrega escapado como cadena de JS. (No
        // escribir aquí su nombre con arroba: Blade la compila también dentro
        // de un comentario de JS y rompe la vista.)
        @if (session('aviso_login'))
            notificarUsuario(@json(session('aviso_login')), 'warning');
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
