{{--
    Tarjeta "Correos a tus clientas" (módulo de pago 'correos').

    Solo informa: no hay nada que guardar aquí. Con el módulo activo dice qué
    correos salen; sin él (apagado, vencido o nunca activado) muestra el
    candado, el plan y el mismo contacto de la pantalla "no disponible"
    (backoffice/modulo/correos). Todo texto pasa por {{ }}.
--}}
<section class="card-elevada mb-4 tarjeta-correos-clientas" id="tarjeta-correos-clientas" aria-labelledby="titulo-correos-clientas">
    <div class="tarjeta-seccion-form mb-0">
        <div class="encabezado-correos">
            <span class="icono-correos {{ $correosActivos ? 'activo' : 'bloqueado' }}" aria-hidden="true">
                <i class="bi {{ $correosActivos ? 'bi-envelope-check' : 'bi-lock-fill' }}"></i>
            </span>
            <div>
                <h3 class="etiqueta-seccion-form mb-1" id="titulo-correos-clientas">{{ $correosNombreModulo }}</h3>
                @if ($correosActivos)
                    <span class="chip-estado-correos activo">
                        <i class="bi bi-check-circle-fill" aria-hidden="true"></i> Activo
                    </span>
                @else
                    <span class="chip-estado-correos bloqueado">
                        <i class="bi bi-stars" aria-hidden="true"></i>
                        @if ($correosPlan !== null)
                            Disponible en el plan {{ $correosPlan }}
                        @else
                            No incluido en tu plan
                        @endif
                    </span>
                @endif
            </div>
        </div>

        @if ($correosActivos)
            <p class="ayuda-bloque mb-2" id="texto-correos-activo">
                Tus clientas con correo registrado reciben automáticamente:
            </p>
            <ul class="lista-correos">
                <li><i class="bi bi-calendar-check" aria-hidden="true"></i> La confirmación de cada reserva que creas.</li>
                <li><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Un aviso cuando cambias el estado de su reserva (confirmada, completada o cancelada).</li>
                <li><i class="bi bi-alarm" aria-hidden="true"></i> Un recordatorio el día anterior a su cita.</li>
            </ul>
        @else
            <p class="ayuda-bloque mb-3" id="texto-correos-bloqueado">
                Con este módulo tus clientas reciben por correo la confirmación de su reserva, los cambios de estado y un recordatorio el día anterior. Hoy tu negocio no envía estos correos; todo lo demás funciona igual.
            </p>
            <div class="acciones-correos">
                @if ($correosWhatsappLink !== '')
                    <a href="{{ $correosWhatsappLink }}" class="btn-whatsapp-correos" id="btn-whatsapp-correos" target="_blank" rel="noopener noreferrer">
                        <i class="bi bi-whatsapp" aria-hidden="true"></i>
                        Escríbenos por WhatsApp
                    </a>
                @endif

                @if ($correosCorreoContacto !== '')
                    <span class="contacto-correos">
                        o al correo <a href="mailto:{{ $correosCorreoContacto }}">{{ $correosCorreoContacto }}</a>
                    </span>
                @endif

                @unless ($correosTieneContacto)
                    <span class="contacto-correos" id="contacto-generico-correos">Comunícate con soporte para activarlo.</span>
                @endunless

                <a href="{{ url('backoffice/modulo/correos') }}" class="enlace-mas-correos">
                    Más información
                </a>
            </div>
        @endif
    </div>
</section>
