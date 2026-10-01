/**
 * Validación de fecha/hora contra el horario de atención de un negocio.
 *
 * La usa el calendario del admin (reservas/listado.blade.php) y el formulario
 * de la página pública (publico/pagina.blade.php): misma regla exacta, en un
 * solo lugar, para que nunca diverjan entre sí. Esto es solo una capa de
 * prevención en el frontend, para que quien reserva no llegue a "Guardar" o
 * "Agendar" con una fecha que el backend va a rechazar igual (la validación
 * real sigue en el servidor: SvcNegocio::estaDentroDelHorario()).
 */

function fechaDeHoy() {
    return formatearFechaISO(new Date());
}

function formatearFechaISO(fecha) {
    var mes = String(fecha.getMonth() + 1).padStart(2, '0');
    var dia = String(fecha.getDate()).padStart(2, '0');
    return fecha.getFullYear() + '-' + mes + '-' + dia;
}

function esFechaPasada(fechaTexto) {
    return fechaTexto < fechaDeHoy();
}

/**
 * Día de la semana en la convención del negocio: 1=lunes ... 7=domingo, la
 * misma que usa dias_atencion y que valida el backend.
 *
 * @param {string} fechaTexto "AAAA-MM-DD".
 * @param {string|null} diasAtencion "1,2,3..." tal como llega del negocio, o
 *                                    null/vacío si no hay restricción.
 */
function negocioAtiendeEseDia(fechaTexto, diasAtencion) {
    var dias = diasAtencion
        ? String(diasAtencion).split(',').map(function (d) { return parseInt(jQuery.trim(d), 10); })
        : null;

    // Sin días configurados no se restringe nada (igual que el backend).
    if (dias === null || dias.length === 0) {
        return true;
    }

    var partes = fechaTexto.split('-');
    var fecha = new Date(partes[0], partes[1] - 1, partes[2]);
    var diaSemana = fecha.getDay() === 0 ? 7 : fecha.getDay();

    return dias.indexOf(diaSemana) !== -1;
}

/**
 * Devuelve el motivo por el que una fecha no está disponible, o null si sí lo
 * está.
 *
 * @param {string} fechaTexto "AAAA-MM-DD".
 * @param {string|null} fechaOriginal Permite editar una reserva antigua sin
 *                                    moverla de día, igual que lo permite el
 *                                    backend.
 * @param {string|null} diasAtencion "1,2,3..." del negocio.
 * @param {string} textoNoAtiende Mensaje cuando el día no está habilitado;
 *                                cambia según quién pregunta (el admin ve "Tu
 *                                negocio...", la página pública ve "El
 *                                negocio...").
 */
function motivoFechaNoDisponibleBase(fechaTexto, fechaOriginal, diasAtencion, textoNoAtiende) {
    if (!fechaTexto) {
        return null;
    }

    var seMueveLaFecha = !fechaOriginal || fechaTexto !== fechaOriginal;

    if (seMueveLaFecha && esFechaPasada(fechaTexto)) {
        return 'Esta fecha ya pasó, elige un día de hoy en adelante';
    }

    if (!negocioAtiendeEseDia(fechaTexto, diasAtencion)) {
        return textoNoAtiende;
    }

    return null;
}
