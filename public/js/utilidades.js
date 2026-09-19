function getDataJson(obj) {
    return jQuery("#" + obj).serializeObject();
}

function Mostrarloader() {
    jQuery("#loader_proceso").css("display", "flex");
}

function Ocultarloader() {
    jQuery("#loader_proceso").css("display", "none");
}

async function notificarUsuario(Mensaje = "", icono = "info", urlRedireccion = "") {
    if (Array.isArray(Mensaje)) {
        var TempMensaje = "";
        for (i = 0; i < Mensaje.length; i++) {
            TempMensaje = "- " + Mensaje[i] + "<br>" + TempMensaje;
        }
        Mensaje = TempMensaje;
    }

    if (Mensaje.length > 20) {
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
