<?php

namespace App\Logging;

use App\Support\IdPeticion;
use Illuminate\Routing\Route;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Throwable;

/**
 * Procesador central de Monolog: ningún registro guarda VALORES de columnas.
 *
 * Una QueryException de Laravel trae en su mensaje el SQL con los bindings ya
 * reemplazados (nombre, correo, hash de la clave...), el mensaje del driver
 * puede llevar el valor ("Duplicate entry 'x' for key ..."), y la traza de
 * pila incluye los argumentos de cada llamada si zend.exception_ignore_args
 * está en Off. Este procesador quita todo eso del mensaje y del contexto, sea
 * que la excepción llegue como texto (Log::channel('database')->info($e)) o
 * como objeto en el contexto (el manejador de excepciones de Laravel).
 *
 * CONSERVA: la clase de la excepción, el SQLSTATE y el código del driver, el
 * nombre de la llave o restricción, el archivo y la línea, y las líneas de la
 * traza (sin sus argumentos). Los registros que no son excepciones (auditoría)
 * no coinciden con ningún patrón y salen intactos.
 *
 * AGREGA al "extra" de cada registro solo identificadores y la ruta (ver
 * identificadoresDePeticion()), para cruzar el código de soporte que ve el
 * usuario con la excepción de esa misma petición.
 *
 * Se aplica con App\Logging\SanitizarRegistros como "tap" en config/logging.php.
 */
class ProcesadorSinDatosPersonales implements ProcessorInterface
{
    const OMITIDO = '[omitido]';

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: self::limpiarTexto($record->message),
            context: $this->limpiarContexto($record->context),
            extra: array_merge($record->extra, self::identificadoresDePeticion()),
        );
    }

    /**
     * Lo que se agrega al "extra" de CADA registro: SOLO identificadores y la
     * ruta, nunca valores de campos ni datos de la persona.
     *
     * - id_peticion: el id corto de la petición (App\Support\IdPeticion), el
     *   mismo que lleva el código de soporte que ve el usuario.
     * - tenant_id e id_usuario: de la sesión, si existen (números).
     * - ruta: método y la PLANTILLA de la ruta ("POST request/usuario/crear",
     *   "GET publico/{slug}"), no la URL real: sin query string y sin los
     *   valores de los parámetros (un token de confirmación, por ejemplo).
     *
     * En comandos y procesos sin petición no se agrega nada. Un registro nunca
     * puede fallar por esto, así que cualquier error se ignora.
     */
    public static function identificadoresDePeticion(): array
    {
        try {
            if (! app()->bound('request')) {
                return [];
            }

            $request = app('request');
            $extra = [];

            $id = $request->attributes->get(IdPeticion::ATRIBUTO);

            if (is_string($id)) {
                $extra['id_peticion'] = $id;
            }

            if ($request->hasSession()) {
                foreach (['tenant_id', 'id_usuario'] as $clave) {
                    $valor = $request->session()->get($clave);

                    if (is_int($valor) || (is_string($valor) && ctype_digit($valor))) {
                        $extra[$clave] = (int) $valor;
                    }
                }
            }

            $ruta = $request->route();

            if ($ruta instanceof Route) {
                $extra['ruta'] = $request->method().' '.$ruta->uri();
            }

            return $extra;
        } catch (Throwable) {
            return [];
        }
    }

    /** Limpia un texto con una excepción (o cualquier texto) de valores de columnas. */
    public static function limpiarTexto(string $texto): string
    {
        // 1) "(Connection: x, ..., SQL: insert ... values (...))" de QueryException:
        //    se quita entero, hasta el " in archivo:línea" que agrega __toString,
        //    hasta el salto de línea o hasta el final del texto.
        $texto = preg_replace('/\s*\(Connection: .*?\)(?=( in [^\n]+:\d+)?(\R|$))/s', '', $texto);

        // Por si quedara un "SQL: ..." suelto (mensaje sin el envoltorio de arriba).
        $texto = preg_replace('/,?\s*SQL: [^\n]*/', '', $texto);

        // 2) Valores dentro del mensaje del driver. Se conserva el nombre de la
        //    llave, columna o restricción; solo se tapa el valor.
        $texto = preg_replace("/(Duplicate entry )'.*?'(?= for key)/s", "$1'".self::OMITIDO."'", $texto);
        $texto = preg_replace("/(value: )'.*?'(?= for (column|function))/is", "$1'".self::OMITIDO."'", $texto);
        $texto = preg_replace('/(Key \([^)]*\)=)\(.*?\)(?= (already exists|is not present))/s', '$1('.self::OMITIDO.')', $texto);

        // 3) Argumentos de la traza: "#3 archivo(12): Clase->metodo('valor', Array)"
        //    queda "#3 archivo(12): Clase->metodo()".
        $texto = preg_replace('/^(#\d+ (?:[^\n]*?\(\d+\)|\[internal function\]): [^\n(]+)\(.*\)$/m', '$1()', $texto);

        return $texto;
    }

    /** Excepción como texto, sin valores: clase, código, mensaje, archivo:línea y traza sin argumentos. */
    public static function excepcionComoTexto(Throwable $e): string
    {
        $texto = '[object] ('.get_class($e).'(code: '.$e->getCode().'): '.self::limpiarTexto($e->getMessage())
            .' at '.$e->getFile().':'.$e->getLine().')';

        $lineas = [];

        foreach ($e->getTrace() as $i => $marco) {
            $lugar = isset($marco['file']) ? $marco['file'].'('.($marco['line'] ?? '?').')' : '[internal function]';
            $funcion = ($marco['class'] ?? '').($marco['type'] ?? '').($marco['function'] ?? '');
            $lineas[] = '#'.$i.' '.$lugar.': '.$funcion.'()';
        }

        $texto .= "\n[stacktrace]\n".implode("\n", $lineas);

        if ($e->getPrevious() !== null) {
            $texto .= "\n[previous exception] ".self::excepcionComoTexto($e->getPrevious());
        }

        return $texto;
    }

    private function limpiarContexto(array $contexto): array
    {
        foreach ($contexto as $clave => $valor) {
            if ($valor instanceof Throwable) {
                $contexto[$clave] = self::excepcionComoTexto($valor);
            } elseif (is_string($valor)) {
                $contexto[$clave] = self::limpiarTexto($valor);
            } elseif (is_array($valor)) {
                $contexto[$clave] = $this->limpiarContexto($valor);
            }
        }

        return $contexto;
    }
}
