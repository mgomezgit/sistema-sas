<?php

/*
|--------------------------------------------------------------------------
| Bootstrap de las pruebas
|--------------------------------------------------------------------------
|
| Carga el autoload y hace que las variables fijadas con force="true" en
| phpunit.xml ganen de verdad, también contra el entorno REAL del proceso.
|
| Por qué hace falta: PHPUnit, con force="true", sobrescribe $_ENV y putenv(),
| pero NO $_SERVER, donde PHP ya copió el entorno del sistema. env() de Laravel
| lee $_SERVER primero, así que una variable definida en el entorno del proceso
| (por ejemplo $env:PLATAFORMA_CONTACTO_WHATSAPP en PowerShell, o una variable
| del sistema en el PC de un desarrollador) le ganaba a phpunit.xml. Contra el
| archivo .env no pasaba, pero sí contra el entorno.
|
| Aquí se copia cada valor forzado a $_SERVER, $_ENV y putenv() para que
| phpunit.xml sea la única fuente de verdad de las pruebas. Es infraestructura
| de pruebas: no toca código de producción.
*/

require __DIR__.'/../vendor/autoload.php';

$configuracion = @simplexml_load_file(__DIR__.'/../phpunit.xml');

if ($configuracion !== false && isset($configuracion->php->env)) {
    foreach ($configuracion->php->env as $variable) {
        if ((string) $variable['force'] !== 'true') {
            continue;
        }

        $nombre = (string) $variable['name'];
        $valor = (string) $variable['value'];

        $_SERVER[$nombre] = $valor;
        $_ENV[$nombre] = $valor;
        putenv($nombre.'='.$valor);
    }
}
