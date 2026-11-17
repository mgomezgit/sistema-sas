{{--
    Tarjeta "Página pública" (módulo de pago 'pagina_publica').

    Solo informa. Con el módulo: la página completa está activa. Sin él:
    candado con lo que hoy ven las clientas (nombre, horario y WhatsApp) y lo
    que verían con el plan, con el WhatsApp y el enlace compartidos de
    Plataforma::datosCandado. El botón "Ver mi página" de Datos generales
    sigue abriendo la página (básica o completa). Todo texto con {{ }}.
--}}
<section class="card-elevada mb-4 tarjeta-correos-clientas" id="tarjeta-pagina-publica" aria-labelledby="titulo-pagina-publica">
    @if ($paginaPublicaCompleta)
        <div class="tarjeta-seccion-form mb-0">
            <div class="encabezado-correos">
                <span class="icono-correos activo" aria-hidden="true">
                    <i class="bi bi-window-stack"></i>
                </span>
                <div>
                    <h3 class="etiqueta-seccion-form mb-1" id="titulo-pagina-publica">Página pública</h3>
                    <span class="chip-estado-correos activo">
                        <i class="bi bi-check-circle-fill" aria-hidden="true"></i> Completa
                    </span>
                </div>
            </div>
            <p class="ayuda-bloque mb-0" id="texto-pagina-publica-completa">
                Tus clientas ven tus servicios, tu equipo y tus promociones, y te piden cita desde ahí.
            </p>
        </div>
    @else
        <h3 class="visually-hidden" id="titulo-pagina-publica">Página pública</h3>
        @include('partials.candado-modulo', [
            'candado' => $candadoPaginaPublica,
            'idCandado' => 'candado-pagina-publica',
            'titulo' => 'Página pública: básica',
            'texto' => 'Hoy tus clientas ven solo tu nombre, tu horario y tu WhatsApp. Con el plan '.($candadoPaginaPublica['plan'] ?? 'Pro').' ven tus servicios, tu equipo y tus promociones, y te piden cita desde ahí.',
            'compacto' => true,
        ])
    @endif
</section>
