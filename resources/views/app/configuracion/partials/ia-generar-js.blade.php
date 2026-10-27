{{--
    Código del componente "Generar con IA". Existe UNA sola vez y sirve a todas
    las ubicaciones (formulario del banner y tarjeta de la frase): cada
    .ia-generar se engancha por sus atributos data-*.

    Regla de oro: lo que llega de la IA o del servidor se pinta SOLO con
    textContent / .text() / .val(), nunca como HTML. Solo propone: elegir una
    opción rellena campos y NO guarda nada.
--}}
<script>
    var IaGenerar = (function () {
        var MENSAJE_LIMITE = @json($iaMensajeLimite);
        var MENSAJE_NO_DISPONIBLE = @json($iaMensajeNoDisponible);
        var MENSAJE_RED = 'No pudimos comunicarnos con el servidor. Revisa tu conexión e inténtalo de nuevo.';

        // Deja pasar la respuesta 429 (con su mensaje real) en vez de tratarla como falla de red.
        var OPCIONES_PETICION = {
            silenciarError: true,
            validateStatus: function (estado) {
                return (estado >= 200 && estado < 300) || estado === 429;
            }
        };

        function textoDe(mensaje) {
            if (Array.isArray(mensaje)) {
                return mensaje.join(' ');
            }

            return String(mensaje || '');
        }

        function montar(raiz) {
            var tipo = raiz.attr('data-ia-tipo');
            var campoTitulo = raiz.attr('data-ia-campo-titulo') || '';
            var campoTexto = raiz.attr('data-ia-campo-texto');
            var boton = raiz.find('.ia-btn-abrir');
            var panel = raiz.find('.ia-panel');
            var estadoEl = raiz.find('.ia-estado');
            var botonGenerar = raiz.find('.ia-btn-generar');
            var textoGenerar = raiz.find('.ia-texto-generar');
            var resultados = raiz.find('.ia-resultados');

            var estadoCargado = false;
            var disponible = true;
            var ocupado = false;

            function mostrarMensaje(texto, informativo) {
                var caja = jQuery('<div>').addClass('ia-mensaje' + (informativo ? ' ia-info' : '')).text(texto);

                resultados.empty().append(caja);
            }

            function activarGenerar(activo) {
                botonGenerar.prop('disabled', !activo).attr('aria-disabled', activo ? 'false' : 'true');
            }

            function pintarEstado(datos) {
                disponible = datos.disponible === true;

                if (disponible) {
                    var restantes = datos.restantes;

                    estadoEl.text(restantes === 1
                        ? 'Te queda 1 generación este mes'
                        : 'Te quedan ' + restantes + ' generaciones este mes');
                } else {
                    // restantes 0 = el límite del negocio; cualquier otro caso es "no disponible".
                    estadoEl.text(datos.restantes === 0 ? MENSAJE_LIMITE : MENSAJE_NO_DISPONIBLE);
                }

                activarGenerar(disponible && !ocupado);
            }

            function cargarEstado() {
                axiosSipleInterno('GET', 'request/ia/estado', {}, {}, false, undefined, OPCIONES_PETICION).then(function (respuesta) {
                    if (!respuesta) {
                        estadoEl.text(MENSAJE_RED);

                        return;
                    }

                    estadoCargado = true;

                    if (respuesta.error != 0) {
                        disponible = false;
                        estadoEl.text(textoDe(respuesta.mensaje));
                        activarGenerar(false);

                        return;
                    }

                    pintarEstado(respuesta.data);
                });
            }

            function elegir(opcion, tarjeta) {
                var titulo = campoTitulo !== '' ? jQuery(campoTitulo) : null;

                // .val(): texto plano al campo; nada se guarda hasta que el admin pulse Guardar.
                if (titulo && opcion.titulo !== undefined) {
                    titulo.val(opcion.titulo).trigger('input');
                }

                jQuery(campoTexto).val(opcion.texto).trigger('input');

                resultados.find('.ia-opcion').attr('aria-pressed', 'false');
                tarjeta.attr('aria-pressed', 'true');
                estadoEl.text('Opción elegida. Revísala y pulsa Guardar para aplicarla: todavía no se guardó.');
            }

            function pintarOpciones(opciones) {
                resultados.empty();

                opciones.forEach(function (opcion) {
                    var tarjeta = jQuery('<button>').attr({ type: 'button', 'aria-pressed': 'false' }).addClass('ia-opcion');

                    if (opcion.titulo !== undefined) {
                        jQuery('<span>').addClass('ia-opcion-titulo').text(opcion.titulo).appendTo(tarjeta);
                    }

                    jQuery('<span>').addClass('ia-opcion-texto').text(opcion.texto).appendTo(tarjeta);

                    if (opcion.advertencia) {
                        var aviso = jQuery('<span>').addClass('ia-advertencia');
                        jQuery('<i>').addClass('bi bi-exclamation-triangle').attr('aria-hidden', 'true').appendTo(aviso);
                        jQuery('<span>').text(opcion.advertencia).appendTo(aviso);
                        aviso.appendTo(tarjeta);
                    }

                    tarjeta.on('click', function () {
                        elegir(opcion, tarjeta);
                    });

                    resultados.append(tarjeta);
                });
            }

            function ocuparGenerar(valor) {
                ocupado = valor;
                resultados.attr('aria-busy', valor ? 'true' : 'false');
                activarGenerar(!valor && disponible);
                textoGenerar.empty();

                if (valor) {
                    jQuery('<span>').addClass('ia-spinner').attr('aria-hidden', 'true').appendTo(textoGenerar);
                    textoGenerar.append(document.createTextNode('Generando…'));
                } else {
                    textoGenerar.text(resultados.children('.ia-opcion').length > 0 ? 'Generar de nuevo' : 'Generar');
                }
            }

            boton.on('click', function () {
                var abrir = panel.prop('hidden');

                panel.prop('hidden', !abrir);
                boton.attr('aria-expanded', abrir ? 'true' : 'false');

                if (abrir) {
                    raiz.find('.ia-tono').trigger('focus');

                    if (!estadoCargado) {
                        cargarEstado();
                    }
                }
            });

            botonGenerar.on('click', function () {
                if (ocupado || !disponible) {
                    return;
                }

                var cuerpo = { tipo: tipo, tono: raiz.find('.ia-tono').val() };
                var idea = jQuery.trim(raiz.find('.ia-idea').val());

                if (idea !== '') {
                    cuerpo.tema = idea;
                }

                resultados.empty();
                ocuparGenerar(true);

                axiosSipleInterno('POST', 'request/ia/generar', {}, cuerpo, false, undefined, OPCIONES_PETICION).then(function (respuesta) {
                    ocuparGenerar(false);

                    if (!respuesta) {
                        mostrarMensaje(MENSAJE_RED);

                        return;
                    }

                    if (respuesta.error != 0) {
                        mostrarMensaje(textoDe(respuesta.mensaje));
                        // El cupo o la disponibilidad pueden haber cambiado: se vuelve a consultar.
                        cargarEstado();

                        return;
                    }

                    pintarOpciones(respuesta.data.opciones);
                    pintarEstado({ disponible: respuesta.data.restantes > 0, restantes: respuesta.data.restantes });
                    textoGenerar.text('Generar de nuevo');
                });
            });
        }

        // Deja el componente como nuevo (al cerrar el modal del banner).
        function reiniciar(raiz) {
            raiz.find('.ia-resultados').empty();
            raiz.find('.ia-idea').val('');
            raiz.find('.ia-texto-generar').text('Generar');
        }

        jQuery(function () {
            jQuery('.ia-generar').each(function () {
                montar(jQuery(this));
            });

            jQuery(document).on('hidden.bs.modal', '.modal', function () {
                jQuery(this).find('.ia-generar').each(function () {
                    reiniciar(jQuery(this));
                });
            });
        });

        return { reiniciar: reiniciar };
    })();
</script>
