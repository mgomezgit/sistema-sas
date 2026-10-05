function getDataJson(obj) {
    return jQuery("#" + obj).serializeObject();
}

function Mostrarloader() {
    jQuery("#loader_proceso").css("display", "flex");
}

function Ocultarloader() {
    jQuery("#loader_proceso").css("display", "none");
}

/**
 * Escapa texto para insertarlo en HTML armado a mano.
 *
 * ÚNICA función de escape del proyecto: todo texto que escribe una persona
 * (un cliente desde la página pública, un admin, una fila de un Excel
 * importado) y que se concatena dentro de un string de HTML tiene que pasar
 * por aquí. Si el destino es un nodo concreto, mejor todavía .text().
 *
 * No hace falta en: .text(), .val(), textContent, la opción "text" de
 * SweetAlert2. SÍ hace falta en: .html(), .append()/.prepend() con strings,
 * los render de DataTables, y las opciones "title" y "html" de SweetAlert2
 * (las dos interpretan HTML).
 */
function escaparTexto(texto) {
    if (texto === null || texto === undefined) {
        return "";
    }

    return jQuery("<div>").text(String(texto)).html();
}

/**
 * Render seguro para columnas de DataTables que muestran texto.
 *
 * DataTables 1.13 mete el valor de la columna como innerHTML: sin esto, un
 * cliente que agende desde la página pública con el nombre
 * "<img src=x onerror=alert(1)>" ejecutaría código en la sesión del admin
 * al abrir la tabla. Solo se escapa lo que se muestra ("display"); el orden
 * y la búsqueda siguen trabajando sobre el valor original.
 */
function renderTextoSeguro(data, tipo) {
    return tipo === "display" ? escaparTexto(data) : data;
}

/**
 * Badge visual para un estado de reserva (agenda, mis citas, reportes de
 * ventas, historial). ÚNICA copia: antes había 4 idénticas repetidas en cada
 * vista, con riesgo de que divergieran entre sí.
 *
 * estadoReserva hoy solo recibe un vocabulario fijo (pendiente/confirmada/
 * completada/cancelada), pero por si algún día llega otro valor: escaparTexto()
 * no alcanza para la clase CSS, porque no escapa comillas y un valor con '"'
 * podría romper el atributo class="..." e inyectar HTML ahí mismo (escaparTexto
 * está pensado para contenido de texto, no para dentro de un atributo). Por
 * eso la clase se limita siempre a un valor conocido ('desconocido' si no lo
 * es); la etiqueta de respaldo, que sí es contenido de texto, pasa por
 * escaparTexto().
 */
function badgeEstadoReserva(estadoReserva) {
    var iconos = {
        pendiente: "bi-hourglass-split",
        confirmada: "bi-check-circle-fill",
        completada: "bi-check2-all",
        cancelada: "bi-x-circle-fill"
    };
    var etiquetas = {
        pendiente: "Pendiente",
        confirmada: "Confirmada",
        completada: "Completada",
        cancelada: "Cancelada"
    };
    var esConocido = Object.prototype.hasOwnProperty.call(etiquetas, estadoReserva);

    var clase = esConocido ? estadoReserva : "desconocido";
    var icono = iconos[estadoReserva] || "bi-question-circle";
    var etiqueta = esConocido ? etiquetas[estadoReserva] : escaparTexto(estadoReserva);

    return '<span class="badge-reserva badge-reserva-' + clase + '"><i class="bi ' + icono + '"></i> ' + etiqueta + "</span>";
}

async function notificarUsuario(Mensaje = "", icono = "info", urlRedireccion = "") {
    // SweetAlert2 en su forma corta es Swal.fire(title, html, icon): las DOS
    // posiciones interpretan HTML, y varios mensajes del backend repiten lo
    // que escribió el usuario (por ejemplo, 'El usuario "..." ya está en
    // uso'). Por eso el mensaje se escapa siempre. El único HTML propio es el
    // <br> con que se unen los mensajes de un arreglo de errores.
    // El largo que decide si va como título o como cuerpo se mide sobre el
    // texto original, no sobre el escapado: escapar alarga el texto ("&" pasa
    // a "&amp;") y eso no debe cambiar cómo se ve el aviso.
    var largoOriginal;

    if (Array.isArray(Mensaje)) {
        var TempMensaje = "";
        var textoPlano = "";
        for (var i = 0; i < Mensaje.length; i++) {
            TempMensaje = "- " + escaparTexto(Mensaje[i]) + "<br>" + TempMensaje;
            textoPlano = "- " + Mensaje[i] + "<br>" + textoPlano;
        }
        largoOriginal = textoPlano.length;
        Mensaje = TempMensaje;
    } else {
        largoOriginal = String(Mensaje).length;
        Mensaje = escaparTexto(Mensaje);
    }

    if (largoOriginal > 20) {
        return await Swal.fire("", Mensaje, icono).then(function () {
            if (urlRedireccion === "reload") {
                window.location.reload();
            } else if (urlRedireccion !== "") {
                location.href = UrlGlobal + urlRedireccion;
            }
        });
    } else {
        return await Swal.fire(Mensaje, "", icono).then(function () {
            if (urlRedireccion === "reload") {
                window.location.reload();
            } else if (urlRedireccion !== "") {
                location.href = UrlGlobal + urlRedireccion;
            }
        });
    }
}

const axiosSipleInterno = async (metodo = "GET", url, parametros = {}, cuerpo = {}, MostrarLoader = false, CallBack = undefined, extraOptions = {}) => {
    // "silenciarError" no es una opción de axios: se separa antes de pasarle el resto.
    // Sirve para peticiones de fondo que el usuario no pidió (por ejemplo un sondeo
    // periódico), donde avisar del fallo una y otra vez molestaría más que ayudar.
    // Por defecto va en false, así que las llamadas de siempre no cambian.
    const { silenciarError = false, ...opcionesAxios } = extraOptions;

    const metodoFormato = metodo.toLocaleLowerCase();
    const options = {
        url: UrlGlobal + url,
        method: metodoFormato,
        params: parametros,
        data: cuerpo
    };
    try {
        MostrarLoader ? Mostrarloader() : null;
        const respuesta = await axios({...options, ...opcionesAxios});
        MostrarLoader ? Ocultarloader() : null;
        CallBack !== undefined ? CallBack(respuesta.data) : null;
        return respuesta.data;
    } catch (error) {
        MostrarLoader ? Ocultarloader() : null;

        if (silenciarError) {
            return false;
        }

        var mensajeFalla = 'No pudimos comunicarnos con el servidor. Revisa tu conexión a internet e inténtalo de nuevo. Si el problema continúa, comunícate con soporte e indica este código: CONEXION';

        if (error && error.response && error.response.status) {
            mensajeFalla = 'Ocurrió un problema técnico y la acción no se completó. Vuelve a intentarlo en unos segundos. Si el problema continúa, comunícate con soporte e indica este código: HTTP-' + error.response.status;
        }

        await notificarUsuario(mensajeFalla, 'error');
        return false;
    }
};

jQuery.fn.serializeObject = function () {
    var obj = {};
    jQuery.each(this.serializeArray(), function () {
        obj[this.name] = this.value;
    });
    return obj;
};
