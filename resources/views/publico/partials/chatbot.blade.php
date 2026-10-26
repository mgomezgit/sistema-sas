{{--
    Chat del asistente virtual (chatbot, versión 1) de la página pública.

    Se incluye SOLO desde publico/pagina.blade.php. Los datos del negocio llegan
    al JS por atributos data-* escritos con {{ }} (escapados por Blade). Espera
    $slug, $negocio y $whatsappLink de la página (el enlace de WhatsApp ya viene
    armado con el número en dígitos y el mismo mensaje precargado del botón
    flotante).

    SEGURIDAD: el JS pinta TODO como texto (createElement + textContent): lo
    que escribe el visitante, las respuestas de la IA, los mensajes de error del
    servidor y el nombre del negocio. Nunca como HTML.
--}}
@php
    $chatTieneWhatsapp = ! empty($whatsappLink);
    $chatTienePolitica = ! empty($negocio['politica_cancelacion']);
    $chatMaximo = (int) config('chatbot.longitud_maxima_mensaje');
@endphp
<div id="chat-raiz" class="chat-raiz" hidden
     data-slug="{{ $slug }}"
     data-nombre="{{ $negocio['nombre_negocio'] }}"
     @if ($chatTieneWhatsapp) data-whatsapp-url="{{ $whatsappLink }}" @endif
     data-tiene-politica="{{ $chatTienePolitica ? '1' : '0' }}"
     data-vida-minutos="{{ (int) config('chatbot.vida_conversacion_minutos') }}"
     data-max-mensaje="{{ $chatMaximo }}"
     data-url-estado="{{ url('publico/'.$slug.'/chatbot/estado') }}"
     data-url-mensaje="{{ url('publico/'.$slug.'/chatbot/mensaje') }}"
     data-url-terminar="{{ url('publico/'.$slug.'/chatbot/terminar') }}">

    <button type="button" id="chat-lanzador" class="chat-lanzador {{ $chatTieneWhatsapp ? '' : 'chat-sin-wa' }}" aria-expanded="false" aria-controls="chat-panel">
        <i class="bi bi-chat-dots" aria-hidden="true"></i>
        <span>¿Tienes dudas? Pregúntame</span>
    </button>

    <div id="chat-fondo" class="chat-fondo"></div>

    <section id="chat-panel" class="chat-panel {{ $chatTieneWhatsapp ? '' : 'chat-sin-wa' }}" role="dialog" aria-label="Asistente virtual" hidden>
        <header class="chat-encabezado">
            <div id="chat-avatar" class="chat-avatar" aria-hidden="true"></div>
            <div class="chat-titulos">
                <h2 id="chat-titulo" class="chat-titulo"></h2>
                <p class="chat-subtitulo">Te cuenta sobre servicios, precios y horarios</p>
            </div>
            <button type="button" id="chat-nueva" class="chat-icono" aria-label="Nueva conversación" title="Nueva conversación">
                <i class="bi bi-arrow-clockwise" aria-hidden="true"></i>
            </button>
            <button type="button" id="chat-cerrar" class="chat-icono" aria-label="Cerrar asistente" title="Cerrar">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </header>

        <div id="chat-log" class="chat-log" role="log" aria-live="polite" aria-label="Conversación con el asistente"></div>

        <div id="chat-sugerencias" class="chat-sugerencias"></div>

        <div class="chat-entrada">
            <input type="text" id="chat-input" class="chat-input" maxlength="{{ $chatMaximo }}" placeholder="Escribe tu pregunta" aria-label="Escribe tu pregunta" autocomplete="off" enterkeyhint="send">
            <button type="button" id="chat-enviar" class="chat-enviar" aria-label="Enviar pregunta">
                <i class="bi bi-send" aria-hidden="true"></i>
            </button>
        </div>

        <div class="chat-pie">
            <p class="chat-aviso">
                @if ($chatTieneWhatsapp)
                    Asistente virtual con IA. Puede equivocarse: para confirmar, escríbenos por WhatsApp.
                @else
                    Asistente virtual con IA. Puede equivocarse: para confirmar, contacta directamente al negocio.
                @endif
            </p>
            <span id="chat-contador" class="chat-contador" aria-hidden="true">0/{{ $chatMaximo }}</span>
        </div>
    </section>
</div>

<script>
    /* El layout carga axios DESPUÉS del contenido: se arranca cuando el
       documento terminó de cargar (los scripts síncronos ya corrieron). */
    document.addEventListener('DOMContentLoaded', function () {
        'use strict';

        var raiz = document.getElementById('chat-raiz');

        if (!raiz || typeof axios === 'undefined') {
            return;
        }

        /* ---------- Datos del negocio (atributos data-*, como texto) ---------- */
        var nombre = raiz.getAttribute('data-nombre') || '';
        var urlWhatsapp = raiz.getAttribute('data-whatsapp-url') || '';
        var tienePolitica = raiz.getAttribute('data-tiene-politica') === '1';
        var vidaMs = (parseInt(raiz.getAttribute('data-vida-minutos'), 10) || 15) * 60000;
        var maximoMensaje = parseInt(raiz.getAttribute('data-max-mensaje'), 10) || 500;
        var urlEstado = raiz.getAttribute('data-url-estado');
        var urlMensaje = raiz.getAttribute('data-url-mensaje');
        var urlTerminar = raiz.getAttribute('data-url-terminar');
        var claveAlmacen = 'chat:' + (raiz.getAttribute('data-slug') || '');

        // Solo enlaces de WhatsApp con el formato de la página: cualquier otra
        // cosa se descarta y el chat funciona sin botón de WhatsApp.
        if (urlWhatsapp.indexOf('https://wa.me/') !== 0) {
            urlWhatsapp = '';
        }

        var TEXTO_SALUDO = 'Hola, soy el asistente virtual de ' + nombre + '. Puedo contarte sobre servicios, precios y horarios.';
        var TEXTO_RED = 'No pude conectarme. Inténtalo de nuevo o escríbenos por WhatsApp.';
        var TEXTO_SILENCIO = '¿Te ayudo con algo más? También puedes escribirnos por WhatsApp.';
        var TEXTO_INACTIVIDAD = 'Tu conversación anterior terminó por inactividad.';
        var ESPERA_SILENCIO_MS = 120000;
        var MAXIMO_GUARDADOS = 40;
        var CABECERAS = { headers: { 'Accept': 'application/json' } };

        var lanzador = document.getElementById('chat-lanzador');
        var panel = document.getElementById('chat-panel');
        var registro = document.getElementById('chat-log');
        var zonaSugerencias = document.getElementById('chat-sugerencias');
        var entrada = document.getElementById('chat-input');
        var botonEnviar = document.getElementById('chat-enviar');
        var contador = document.getElementById('chat-contador');

        /* ---------- Estado ---------- */
        var estado = { id: null, mensajes: [], ultima: 0, aviso: false };
        var abierto = false;
        var esperando = false;
        var generacion = 0;
        var filaEscribiendo = null;
        var temporizadorSilencio = null;

        /* ---------- sessionStorage: siempre dentro de try/catch ---------- */
        function leerGuardado() {
            try {
                var texto = window.sessionStorage.getItem(claveAlmacen);

                if (!texto) {
                    return null;
                }

                var dato = JSON.parse(texto);

                if (!dato || typeof dato !== 'object' || !Array.isArray(dato.mensajes)) {
                    return null;
                }

                var mensajes = [];

                dato.mensajes.slice(-MAXIMO_GUARDADOS).forEach(function (m) {
                    if (m && (m.rol === 'asistente' || m.rol === 'visitante') && typeof m.texto === 'string') {
                        mensajes.push({ rol: m.rol, texto: m.texto, wa: m.wa === true });
                    }
                });

                return {
                    id: (typeof dato.id === 'string' && /^[A-Za-z0-9]{40}$/.test(dato.id)) ? dato.id : null,
                    mensajes: mensajes,
                    ultima: (typeof dato.ultima === 'number' && isFinite(dato.ultima)) ? dato.ultima : 0,
                    aviso: dato.aviso === true
                };
            } catch (e) {
                return null;
            }
        }

        function guardar() {
            try {
                window.sessionStorage.setItem(claveAlmacen, JSON.stringify({
                    id: estado.id,
                    mensajes: estado.mensajes.slice(-MAXIMO_GUARDADOS),
                    ultima: estado.ultima,
                    aviso: estado.aviso
                }));
            } catch (e) {
                // Bloqueado o lleno: el chat sigue funcionando sin persistir.
            }
        }

        function borrarGuardado() {
            try {
                window.sessionStorage.removeItem(claveAlmacen);
            } catch (e) {
                // Igual que arriba.
            }
        }

        /* ---------- Pintado (todo createElement + textContent) ---------- */
        function bajar() {
            registro.scrollTop = registro.scrollHeight;
        }

        function crearBotonWhatsapp() {
            var enlace = document.createElement('a');
            enlace.className = 'chat-wa';
            enlace.href = urlWhatsapp;
            enlace.target = '_blank';
            enlace.rel = 'noopener noreferrer';

            var icono = document.createElement('i');
            icono.className = 'bi bi-whatsapp';
            icono.setAttribute('aria-hidden', 'true');

            var texto = document.createElement('span');
            texto.textContent = 'Escribir por WhatsApp';

            enlace.appendChild(icono);
            enlace.appendChild(texto);

            return enlace;
        }

        function pintarBurbuja(rol, texto, conWhatsapp) {
            var fila = document.createElement('div');
            fila.className = 'chat-fila chat-fila-' + (rol === 'visitante' ? 'visitante' : 'asistente');

            var burbuja = document.createElement('div');
            burbuja.className = 'chat-burbuja chat-burbuja-' + (rol === 'visitante' ? 'visitante' : 'asistente');

            var contenido = document.createElement('span');
            contenido.className = 'chat-texto';
            contenido.textContent = texto;
            burbuja.appendChild(contenido);

            if (conWhatsapp && urlWhatsapp) {
                burbuja.appendChild(crearBotonWhatsapp());
            }

            fila.appendChild(burbuja);
            registro.appendChild(fila);
            bajar();

            return fila;
        }

        function pintarLineaSistema(texto) {
            var linea = document.createElement('div');
            linea.className = 'chat-sistema';
            linea.textContent = texto;
            registro.appendChild(linea);
            bajar();
        }

        function mostrarEscribiendo() {
            quitarEscribiendo();

            var fila = document.createElement('div');
            fila.className = 'chat-fila chat-fila-asistente';

            var burbuja = document.createElement('div');
            burbuja.className = 'chat-burbuja chat-burbuja-asistente';
            burbuja.setAttribute('aria-hidden', 'true');

            var puntos = document.createElement('span');
            puntos.className = 'chat-puntos';

            for (var i = 0; i < 3; i++) {
                var punto = document.createElement('span');
                punto.className = 'chat-punto';
                puntos.appendChild(punto);
            }

            burbuja.appendChild(puntos);
            fila.appendChild(burbuja);
            registro.appendChild(fila);
            registro.setAttribute('aria-busy', 'true');
            filaEscribiendo = fila;
            bajar();
        }

        function quitarEscribiendo() {
            if (filaEscribiendo && filaEscribiendo.parentNode) {
                filaEscribiendo.parentNode.removeChild(filaEscribiendo);
            }

            filaEscribiendo = null;
            registro.removeAttribute('aria-busy');
        }

        function repintarTodo(lineaInactividad) {
            while (registro.firstChild) {
                registro.removeChild(registro.firstChild);
            }

            filaEscribiendo = null;
            pintarBurbuja('asistente', TEXTO_SALUDO, false);

            if (lineaInactividad) {
                pintarLineaSistema(TEXTO_INACTIVIDAD);
            }

            estado.mensajes.forEach(function (m) {
                pintarBurbuja(m.rol, m.texto, m.wa);
            });

            actualizarSugerencias();
        }

        function actualizarSugerencias() {
            while (zonaSugerencias.firstChild) {
                zonaSugerencias.removeChild(zonaSugerencias.firstChild);
            }

            // Solo mientras la única burbuja es el saludo.
            if (estado.mensajes.length > 0) {
                return;
            }

            var textos = ['¿Qué servicios tienen?', '¿Cuál es el horario?'];

            if (tienePolitica) {
                textos.push('Política de cancelación');
            }

            textos.forEach(function (texto) {
                var boton = document.createElement('button');
                boton.type = 'button';
                boton.className = 'chat-sugerencia';
                boton.textContent = texto;
                boton.addEventListener('click', function () {
                    enviar(texto);
                });
                zonaSugerencias.appendChild(boton);
            });
        }

        function actualizarContador() {
            contador.textContent = entrada.value.length + '/' + maximoMensaje;
        }

        /* ---------- Vencimiento por inactividad ---------- */
        function reiniciarLocal(conLineaInactividad) {
            generacion++;
            estado = { id: null, mensajes: [], ultima: 0, aviso: false };
            esperando = false;
            botonEnviar.disabled = false;
            clearTimeout(temporizadorSilencio);
            borrarGuardado();
            repintarTodo(conLineaInactividad);
        }

        function revisarVencimiento() {
            var conActividad = estado.id !== null || estado.mensajes.length > 0;

            if (conActividad && estado.ultima > 0 && (Date.now() - estado.ultima) > vidaMs) {
                reiniciarLocal(true);

                return true;
            }

            return false;
        }

        /* ---------- Aviso de silencio (solo local, una vez por conversación) ---------- */
        function hayIntercambio() {
            var visitante = false;
            var asistente = false;

            estado.mensajes.forEach(function (m) {
                if (m.rol === 'visitante') {
                    visitante = true;
                } else {
                    asistente = true;
                }
            });

            return visitante && asistente;
        }

        function armarSilencio() {
            clearTimeout(temporizadorSilencio);

            if (!abierto || estado.aviso || !hayIntercambio()) {
                return;
            }

            temporizadorSilencio = setTimeout(avisarSilencio, ESPERA_SILENCIO_MS);
        }

        function avisarSilencio() {
            if (!abierto || estado.aviso || esperando || !hayIntercambio()) {
                return;
            }

            estado.aviso = true;
            estado.mensajes.push({ rol: 'asistente', texto: TEXTO_SILENCIO, wa: urlWhatsapp !== '' });
            pintarBurbuja('asistente', TEXTO_SILENCIO, urlWhatsapp !== '');
            guardar();
        }

        /* ---------- Enviar ---------- */
        function textoDelServidor(datos) {
            if (!datos || typeof datos !== 'object' || datos.mensaje === undefined || datos.mensaje === null) {
                return null;
            }

            var texto = Array.isArray(datos.mensaje) ? datos.mensaje.join(' ') : String(datos.mensaje);

            return texto.trim() === '' ? null : texto;
        }

        function terminarEspera() {
            esperando = false;
            botonEnviar.disabled = false;
            quitarEscribiendo();
        }

        function agregarRespuesta(texto, conWhatsapp) {
            estado.mensajes.push({ rol: 'asistente', texto: texto, wa: conWhatsapp });
            pintarBurbuja('asistente', texto, conWhatsapp);
        }

        function enviar(textoDirecto) {
            var texto = (typeof textoDirecto === 'string' ? textoDirecto : entrada.value).trim();

            if (texto === '' || esperando) {
                return;
            }

            revisarVencimiento();

            var turno = generacion;
            var filaVisitante = pintarBurbuja('visitante', texto, false);

            estado.mensajes.push({ rol: 'visitante', texto: texto });
            esperando = true;
            botonEnviar.disabled = true;

            if (typeof textoDirecto !== 'string') {
                entrada.value = '';
                actualizarContador();
            }

            actualizarSugerencias();
            mostrarEscribiendo();
            clearTimeout(temporizadorSilencio);

            var cuerpo = { mensaje: texto };

            if (estado.id) {
                cuerpo.conversacion_id = estado.id;
            }

            axios.post(urlMensaje, cuerpo, CABECERAS).then(function (respuesta) {
                if (turno !== generacion) {
                    return;
                }

                terminarEspera();
                estado.ultima = Date.now();

                var datos = respuesta.data;

                if (datos && datos.error == 0 && datos.data && typeof datos.data.respuesta === 'string') {
                    if (typeof datos.data.conversacion_id === 'string' && /^[A-Za-z0-9]{40}$/.test(datos.data.conversacion_id)) {
                        estado.id = datos.data.conversacion_id;
                    }

                    agregarRespuesta(datos.data.respuesta, datos.data.derivar_whatsapp === true && urlWhatsapp !== '');
                } else {
                    var motivo = textoDelServidor(datos);

                    agregarRespuesta(motivo !== null ? motivo : TEXTO_RED, motivo === null && urlWhatsapp !== '');
                }

                guardar();
                armarSilencio();
            }).catch(function (error) {
                if (turno !== generacion) {
                    return;
                }

                terminarEspera();

                var motivo = (error && error.response) ? textoDelServidor(error.response.data) : null;

                if (motivo !== null) {
                    // El servidor contestó con su propio mensaje (por ejemplo el 429).
                    estado.ultima = Date.now();
                    agregarRespuesta(motivo, false);
                } else {
                    // Sin conexión (o una respuesta que no es nuestra): el mensaje
                    // no salió, así que se retira y se devuelve a la entrada.
                    if (filaVisitante.parentNode) {
                        filaVisitante.parentNode.removeChild(filaVisitante);
                    }

                    estado.mensajes.pop();
                    entrada.value = texto;
                    actualizarContador();
                    actualizarSugerencias();
                    agregarRespuesta(TEXTO_RED, urlWhatsapp !== '');
                    entrada.focus();
                }

                guardar();
                armarSilencio();
            });
        }

        /* ---------- Nueva conversación ---------- */
        function nuevaConversacion() {
            var idAnterior = estado.id;

            if (idAnterior) {
                // Sin esperar la respuesta y sin mostrar errores.
                axios.post(urlTerminar, { conversacion_id: idAnterior }, CABECERAS).catch(function () {});
            }

            reiniciarLocal(false);
            entrada.value = '';
            actualizarContador();
            entrada.focus();
        }

        /* ---------- Teclado en celular: que no tape la entrada ---------- */
        function ajustarViewport() {
            var vv = window.visualViewport;

            if (!vv || !abierto || !window.matchMedia('(max-width: 575.98px)').matches) {
                panel.style.removeProperty('--chat-vv-alto');
                panel.style.removeProperty('--chat-vv-bottom');

                return;
            }

            panel.style.setProperty('--chat-vv-alto', vv.height + 'px');
            panel.style.setProperty('--chat-vv-bottom', Math.max(0, window.innerHeight - vv.height - vv.offsetTop) + 'px');
        }

        /* ---------- Abrir y cerrar ---------- */
        function abrir() {
            revisarVencimiento();
            abierto = true;
            panel.hidden = false;
            raiz.classList.add('chat-abierto');
            lanzador.setAttribute('aria-expanded', 'true');
            ajustarViewport();
            bajar();
            entrada.focus();
            armarSilencio();
        }

        function cerrar() {
            abierto = false;
            panel.hidden = true;
            raiz.classList.remove('chat-abierto');
            lanzador.setAttribute('aria-expanded', 'false');
            clearTimeout(temporizadorSilencio);
            ajustarViewport();
            lanzador.focus();
        }

        lanzador.addEventListener('click', abrir);
        document.getElementById('chat-cerrar').addEventListener('click', cerrar);
        document.getElementById('chat-fondo').addEventListener('click', cerrar);
        document.getElementById('chat-nueva').addEventListener('click', nuevaConversacion);
        botonEnviar.addEventListener('click', function () {
            enviar();
        });

        entrada.addEventListener('keydown', function (evento) {
            if (evento.key === 'Enter' && !evento.isComposing) {
                evento.preventDefault();
                enviar();
            }
        });

        entrada.addEventListener('input', function () {
            actualizarContador();
            armarSilencio();
        });

        document.addEventListener('keydown', function (evento) {
            // Con el modal de agendar abierto, Esc es del modal.
            if (evento.key === 'Escape' && abierto && !document.body.classList.contains('modal-open')) {
                cerrar();
            }
        });

        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', ajustarViewport);
            window.visualViewport.addEventListener('scroll', ajustarViewport);
        }

        /* ---------- Arranque ---------- */
        document.getElementById('chat-avatar').textContent = (Array.from(nombre.trim())[0] || '?').toUpperCase();
        document.getElementById('chat-titulo').textContent = 'Asistente de ' + nombre;
        actualizarContador();

        var guardado = leerGuardado();
        var lineaInactividad = false;

        if (guardado) {
            var vencido = guardado.ultima > 0 && (Date.now() - guardado.ultima) > vidaMs;

            if (vencido || (guardado.id === null && guardado.mensajes.length === 0)) {
                borrarGuardado();
                lineaInactividad = vencido;
            } else {
                estado = guardado;
            }
        }

        repintarTodo(lineaInactividad);

        // El lanzador solo aparece si el servidor dice que el chat está disponible.
        // Cualquier fallo (404, red) deja la página como si no hubiera chat, sin
        // escribir nada en la consola.
        axios.get(urlEstado, CABECERAS).then(function (respuesta) {
            var datos = respuesta.data;

            if (datos && datos.error == 0 && datos.data && datos.data.disponible === true) {
                raiz.hidden = false;
            }
        }).catch(function () {});
    });
</script>
