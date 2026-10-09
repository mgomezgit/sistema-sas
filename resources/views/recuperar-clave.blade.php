<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plataforma Reservas - Recuperar clave</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    @include('partials.estilos-acceso')
    @include('partials.estilos-toast')
</head>
<body>
    @include('partials.fondo-acceso')

    <div id="loader_proceso">
        <div class="spinner-border" role="status" style="width: 3rem; height: 3rem; color: var(--accent);">
            <span class="visually-hidden">Cargando...</span>
        </div>
    </div>

    {{-- Misma tarjeta que el login (hereda sus estilos por el id). --}}
    <div id="tarjeta-login" class="card-elevada card-acento">
        <div class="login-logo">
            <span class="logo-dot"></span>
            <span>Plataforma Reservas</span>
        </div>

        {{-- PASO 1: pedir el código --}}
        <div id="paso-pedir-codigo" @if ($abrirPasoCodigo) hidden @endif>
            <p class="texto-ayuda-acceso mb-3">
                Escribe el correo de tu cuenta y te enviaremos un código de 6 números para crear una clave nueva.
            </p>
            <div class="mb-4">
                <label class="form-label" for="correo-solicitar">Correo electrónico</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" id="correo-solicitar" class="form-control" placeholder="tucorreo@negocio.com" value="{{ $correo }}">
                </div>
            </div>
            <button type="button" id="btn-pedir-codigo" class="btn-primario-accento w-100">Enviar código</button>
            <div class="text-center mt-3">
                <a href="#" id="enlace-ya-tengo-codigo" class="enlace-acceso">Ya tengo un código</a>
            </div>
        </div>

        {{-- PASO 2: código + clave nueva --}}
        <div id="paso-usar-codigo" @if (! $abrirPasoCodigo) hidden @endif>
            <p class="texto-ayuda-acceso mb-3">
                Escribe el código que te llegó al correo y elige tu clave nueva (mínimo 8 caracteres).
            </p>
            <div class="mb-3">
                <label class="form-label" for="correo-confirmar">Correo electrónico</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" id="correo-confirmar" class="form-control" placeholder="tucorreo@negocio.com" value="{{ $correo }}">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="codigo">Código</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-123"></i></span>
                    <input type="text" id="codigo" class="form-control" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="000000">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="clave-nueva">Clave nueva</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" id="clave-nueva" class="form-control" autocomplete="new-password" placeholder="••••••••">
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label" for="confirmar-clave-nueva">Confirmar clave nueva</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                    <input type="password" id="confirmar-clave-nueva" class="form-control" autocomplete="new-password" placeholder="••••••••">
                </div>
            </div>
            <button type="button" id="btn-cambiar-clave" class="btn-primario-accento w-100">Cambiar clave</button>
            <div class="text-center mt-3">
                <a href="#" id="enlace-pedir-otro" class="enlace-acceso">Pedir un código nuevo</a>
            </div>
        </div>

        <div class="text-center mt-3">
            <a href="{{ url('login') }}" class="enlace-acceso">Volver a iniciar sesión</a>
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
        function mostrarPaso(paso) {
            jQuery('#paso-pedir-codigo').prop('hidden', paso !== 'pedir');
            jQuery('#paso-usar-codigo').prop('hidden', paso !== 'usar');
        }

        jQuery('#enlace-ya-tengo-codigo').on('click', function (e) {
            e.preventDefault();
            jQuery('#correo-confirmar').val(jQuery('#correo-solicitar').val());
            mostrarPaso('usar');
        });

        jQuery('#enlace-pedir-otro').on('click', function (e) {
            e.preventDefault();
            jQuery('#correo-solicitar').val(jQuery('#correo-confirmar').val());
            mostrarPaso('pedir');
        });

        jQuery('#btn-pedir-codigo').on('click', function () {
            var correo = jQuery('#correo-solicitar').val();

            axiosSipleInterno('POST', 'request/recuperacion/solicitar', {}, { correo: correo }, true, function (respuesta) {
                if (respuesta.error == 0) {
                    // Siempre el mismo mensaje, exista o no el correo.
                    notificarUsuario(respuesta.data.mensaje, 'success');
                    jQuery('#correo-confirmar').val(correo);
                    mostrarPaso('usar');
                    jQuery('#codigo').trigger('focus');
                } else {
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        });

        jQuery('#btn-cambiar-clave').on('click', function () {
            var datos = {
                correo: jQuery('#correo-confirmar').val(),
                codigo: jQuery('#codigo').val(),
                clave: jQuery('#clave-nueva').val(),
                confirmar_clave: jQuery('#confirmar-clave-nueva').val()
            };

            axiosSipleInterno('POST', 'request/recuperacion/confirmar', {}, datos, true, function (respuesta) {
                if (respuesta.error == 0) {
                    notificarUsuario(respuesta.data.mensaje, 'success', 'login');
                } else {
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        });
    </script>
</body>
</html>
