<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
| Recordatorios de las reservas del día siguiente.
|
| Esto requiere que el Scheduler de Laravel esté corriendo:
|   - En producción: un cron que ejecute cada minuto
|     * * * * * cd /ruta/del/proyecto && php artisan schedule:run >> /dev/null 2>&1
|   - En desarrollo local: ejecutar manualmente "php artisan schedule:run"
|     (solo dispara si es la hora programada), o correr el comando directo
|     para probar: "php artisan reservas:enviar-recordatorios".
|
| Los correos se encolan, así que también debe haber un worker activo:
|   php artisan queue:work
*/
Schedule::command('reservas:enviar-recordatorios')->dailyAt('08:00');
