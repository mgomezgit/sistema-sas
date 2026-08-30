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
    const metodoFormato = metodo.toLocaleLowerCase();
    const options = {
        url: UrlGlobal + url,
        method: metodoFormato,
        params: parametros,
        data: cuerpo
    };
    try {
        MostrarLoader ? Mostrarloader() : null;
        const respuesta = await axios({...options, ...extraOptions});
        MostrarLoader ? Ocultarloader() : null;
        CallBack !== undefined ? CallBack(respuesta.data) : null;
        return respuesta.data;
    } catch (error) {
        MostrarLoader ? Ocultarloader() : null;
        await notificarUsuario('Fallas en el servidor, intente nuevamente');
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
