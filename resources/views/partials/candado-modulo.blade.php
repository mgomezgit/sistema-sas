{{--
    Candado de un módulo de pago dentro del panel (no la pantalla completa de
    "no disponible", que es backoffice/modulo/{clave}).

    Recibe:
      $candado    Plataforma::datosCandado($clave, $nombreNegocio)
      $idCandado  id único del bloque en la página
      $titulo     qué está bloqueado ("Banners de tu página")
      $texto      una frase de qué gana el spa con el plan
      $compacto   (opcional) versión de una línea para dentro de un formulario

    Todo texto va con {{ }}; el enlace de WhatsApp ya viene con solo dígitos y el
    mensaje codificado. Estilos en partials/candado-modulo-estilos.
--}}
@php($compacto = $compacto ?? false)
<div class="candado-modulo {{ $compacto ? 'compacto' : '' }}" id="{{ $idCandado }}" role="group" aria-labelledby="{{ $idCandado }}-titulo">
    <div class="candado-encabezado">
        <span class="candado-icono" aria-hidden="true"><i class="bi bi-lock-fill"></i></span>
        <div class="candado-titulos">
            <p class="candado-titulo" id="{{ $idCandado }}-titulo">{{ $titulo }}</p>
            <span class="candado-plan">
                <i class="bi bi-stars" aria-hidden="true"></i>
                @if ($candado['plan'] !== null)
                    Disponible en el plan {{ $candado['plan'] }}
                @else
                    No incluido en tu plan
                @endif
            </span>
        </div>
    </div>

    <p class="candado-texto">{{ $texto }}</p>

    <div class="candado-acciones">
        @if ($candado['whatsapp_link'] !== '')
            <a href="{{ $candado['whatsapp_link'] }}" class="candado-whatsapp" target="_blank" rel="noopener noreferrer">
                <i class="bi bi-whatsapp" aria-hidden="true"></i>
                Escríbenos por WhatsApp
            </a>
        @endif

        @if ($candado['correo'] !== '')
            <span class="candado-contacto">
                o al correo <a href="mailto:{{ $candado['correo'] }}">{{ $candado['correo'] }}</a>
            </span>
        @endif

        @unless ($candado['tiene_contacto'])
            <span class="candado-contacto candado-generico">Comunícate con soporte para activarlo.</span>
        @endunless

        <a href="{{ $candado['url_no_disponible'] }}" class="candado-mas">Más información</a>
    </div>
</div>
