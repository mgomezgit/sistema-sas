{{--
    Código de la tarjeta "Perfil para el asistente creativo". Se incluye solo
    con el módulo 'ia' activo y DESPUÉS de ia-generar-js (usa IaGenerar).

    Todo texto entra y sale con .val() / .text(): el perfil lo escribe una
    persona, nunca se pinta como HTML.
--}}
<script>
    var IaPerfil = (function () {
        var MAXIMO_ESPECIALIDAD = {{ \App\Service\SvcIA::LARGO_MAXIMO_ESPECIALIDAD }};
        var MAXIMO_PUBLICO = {{ \App\Service\SvcIA::LARGO_MAXIMO_PUBLICO }};
        var ESPERA_EXITO_MS = 700;

        function actualizarContadores() {
            jQuery('#contador-ia-especialidad').text(jQuery('#ia_especialidad').val().length + '/' + MAXIMO_ESPECIALIDAD);
            jQuery('#contador-ia-publico').text(jQuery('#ia_publico').val().length + '/' + MAXIMO_PUBLICO);
        }

        function textoDe(mensaje) {
            return Array.isArray(mensaje) ? mensaje.join(' ') : String(mensaje || '');
        }

        function estadoBoton(estado) {
            var boton = jQuery('#btn-guardar-perfil-ia');

            boton.removeClass('ocupado exito').prop('disabled', estado === 'ocupado');

            if (estado === 'ocupado') {
                boton.addClass('ocupado');
                jQuery('#texto-btn-guardar-perfil-ia').text('Guardando');
            } else if (estado === 'exito') {
                boton.addClass('exito');
                jQuery('#texto-btn-guardar-perfil-ia').text('Guardado');
            } else {
                jQuery('#texto-btn-guardar-perfil-ia').text('Guardar');
            }
        }

        function pintar(perfil) {
            var especialidad = perfil.especialidad || '';
            var publico = perfil.publico || '';
            var tiene = especialidad !== '' || publico !== '';

            jQuery('#ia_especialidad').val(especialidad);
            jQuery('#ia_publico').val(publico);
            actualizarContadores();
            jQuery('#btn-quitar-perfil-ia').prop('disabled', !tiene);
            // La línea del panel "Generar con IA" se actualiza sin recargar.
            IaGenerar.marcarPerfil(tiene);
        }

        jQuery('#ia_especialidad, #ia_publico').on('input', actualizarContadores);

        jQuery('#btn-guardar-perfil-ia').on('click', function () {
            var especialidad = jQuery.trim(jQuery('#ia_especialidad').val());
            var publico = jQuery.trim(jQuery('#ia_publico').val());

            if (especialidad === '' && publico === '') {
                notificarUsuario('Escribe al menos uno de los dos datos, o usa Quitar para borrar tu perfil.', 'error');
                jQuery('#ia_especialidad').trigger('focus');

                return;
            }

            if (especialidad.length > MAXIMO_ESPECIALIDAD) {
                notificarUsuario('Lo que hace especial a tu negocio puede tener hasta ' + MAXIMO_ESPECIALIDAD + ' caracteres.', 'error');

                return;
            }

            if (publico.length > MAXIMO_PUBLICO) {
                notificarUsuario('A quién le hablas puede tener hasta ' + MAXIMO_PUBLICO + ' caracteres.', 'error');

                return;
            }

            estadoBoton('ocupado');

            axiosSipleInterno('POST', 'request/ia/perfil', {}, {
                especialidad: especialidad,
                publico: publico
            }, false, function (respuesta) {
                if (!respuesta || respuesta.error != 0) {
                    estadoBoton('normal');

                    if (respuesta) {
                        notificarUsuario(textoDe(respuesta.mensaje), 'error');
                    }

                    return;
                }

                estadoBoton('exito');
                pintar(respuesta.data.perfil);
                notificarUsuario('Perfil guardado correctamente', 'success');

                setTimeout(function () {
                    estadoBoton('normal');
                }, ESPERA_EXITO_MS);
            }).then(function (resultado) {
                // Falla de red: axiosSipleInterno ya avisó y no llamó al callback.
                if (resultado === false) {
                    estadoBoton('normal');
                }
            });
        });

        jQuery('#btn-quitar-perfil-ia').on('click', function () {
            Swal.fire({
                title: '¿Quitar tu perfil?',
                text: 'El asistente volverá a escribir solo con el nombre y el rubro de tu negocio.',
                icon: 'warning',
                background: colorVariable('--bg-card'),
                color: colorVariable('--text-primary'),
                confirmButtonColor: colorVariable('--accent'),
                showCancelButton: true,
                confirmButtonText: 'Sí, quitar',
                cancelButtonText: 'Cancelar'
            }).then(function (resultado) {
                if (!resultado.isConfirmed) {
                    return;
                }

                // Los dos campos vacíos equivalen a quitar el perfil.
                axiosSipleInterno('POST', 'request/ia/perfil', {}, { especialidad: '', publico: '' }, true, function (respuesta) {
                    if (respuesta.error != 0) {
                        notificarUsuario(textoDe(respuesta.mensaje), 'error');

                        return;
                    }

                    pintar(respuesta.data.perfil);
                    notificarUsuario('Perfil quitado', 'success');
                });
            });
        });

        actualizarContadores();

        return { pintar: pintar };
    })();
</script>
