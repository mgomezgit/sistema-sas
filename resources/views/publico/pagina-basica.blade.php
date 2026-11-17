@extends('layout.publico')

{{--
    Página pública BÁSICA: la que ve un negocio sin el módulo "pagina_publica".

    Recibe SOLO la lista blanca de SvcPaginaPublica::informacionBasica()
    (nombre, días y horas de atención, WhatsApp) y no pinta nada más: ni
    servicios, ni equipo, ni promociones, ni frase, ni política, ni teléfono,
    ni el formulario de cita, ni el chat. Todo texto del negocio va con {{ }}.
    Si falta el horario o el número, esa parte no se pinta (sin huecos).
--}}

@section('titulo', $negocio['nombre_negocio'])

{{-- Misma paleta fija y tipografías que la página completa (layout.publico);
     solo variables de ese bloque, sin hexadecimales. Pensada primero para
     celular. Contraste: el título y el horario en --text-primary sobre
     blanco; el rótulo en --apagado (5,4:1); el botón con letra blanca sobre
     --oro-fuerte (5,6:1), porque sobre --accent no llega a 4,5:1. --}}
@section('estilos')
    <style>
        .lienzo-basico {
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
        }

        .basica {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 520px;
        }

        .basica-tarjeta {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            padding: 36px 24px 32px;
            text-align: center;
            box-shadow: 0 18px 40px color-mix(in srgb, var(--accent) 14%, transparent);
        }

        .basica-logo {
            width: 64px;
            height: 64px;
            margin: 0 auto 18px;
            border-radius: 50%;
            border: 1.5px solid var(--accent);
            background: var(--bg-page);
            color: var(--oro-fuerte);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Fraunces', serif;
            font-weight: 600;
            font-size: 28px;
        }

        .basica-nombre {
            font-family: 'Fraunces', serif;
            font-size: 30px;
            font-weight: 600;
            line-height: 1.2;
            color: var(--text-primary);
            overflow-wrap: anywhere;
        }

        .basica-horario {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }

        .basica-subtitulo {
            font-size: 12.5px;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: var(--apagado);
            margin-bottom: 8px;
        }

        .basica-texto-horario {
            display: inline-flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 16px;
            line-height: 1.5;
            color: var(--text-primary);
            overflow-wrap: anywhere;
        }

        .basica-texto-horario i {
            color: var(--oro-fuerte);
            margin-top: 2px;
        }

        .basica-whatsapp {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            min-height: 52px;
            margin-top: 28px;
            padding: 12px 20px;
            border-radius: 999px;
            background: var(--oro-fuerte);
            color: var(--on-accent);
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 10px 22px color-mix(in srgb, var(--oro-fuerte) 28%, transparent);
        }

        .basica-whatsapp:hover {
            color: var(--on-accent);
            background: color-mix(in srgb, var(--oro-fuerte) 88%, var(--text-primary));
        }

        .basica-whatsapp:focus-visible {
            outline: 3px solid var(--text-primary);
            outline-offset: 3px;
        }

        .basica-whatsapp i {
            font-size: 20px;
        }

        @media (min-width: 600px) {
            .basica-tarjeta {
                padding: 48px 44px 40px;
            }

            .basica-nombre {
                font-size: 36px;
            }
        }
    </style>
@endsection

@php
    $inicialNegocio = mb_strtoupper(mb_substr(trim($negocio['nombre_negocio']), 0, 1)) ?: '?';

    $resumenDias = \App\Support\HorarioPublico::resumenDias(\App\Support\HorarioPublico::diasActivos($negocio['dias_atencion'] ?? null));
    $horaApertura = \App\Support\HorarioPublico::hora($negocio['hora_apertura'] ?? null);
    $horaCierre = \App\Support\HorarioPublico::hora($negocio['hora_cierre'] ?? null);
    $horas = ($horaApertura && $horaCierre) ? 'de '.$horaApertura.' a '.$horaCierre : null;

    // Una sola frase: "Lunes a Sábado, de 9:00 AM a 7:00 PM" (o la parte que haya).
    $textoHorario = $resumenDias && $horas ? $resumenDias.', '.$horas : ($resumenDias ?: ($horas ? ucfirst($horas) : null));

    $whatsappLink = \App\Support\HorarioPublico::whatsappLink($negocio['whatsapp_numero'] ?? null, 'Hola, quiero hacerles una consulta');
@endphp

@section('content')
<div class="lienzo lienzo-basico">
    <div class="mancha mancha1"></div>
    <div class="mancha mancha2"></div>

    <main class="basica" aria-labelledby="nombre-negocio-basica">
        <div class="basica-tarjeta" id="pagina-basica">
            <div class="basica-logo" aria-hidden="true">{{ $inicialNegocio }}</div>

            <h1 class="basica-nombre" id="nombre-negocio-basica">{{ $negocio['nombre_negocio'] }}</h1>

            @if ($textoHorario)
                <div class="basica-horario" id="horario-basica">
                    <h2 class="basica-subtitulo">Horario de atención</h2>
                    <p class="basica-texto-horario">
                        <i class="bi bi-clock" aria-hidden="true"></i>
                        <span>{{ $textoHorario }}</span>
                    </p>
                </div>
            @endif

            @if ($whatsappLink)
                <a href="{{ $whatsappLink }}" class="basica-whatsapp" id="whatsapp-basica" target="_blank" rel="noopener noreferrer">
                    <i class="bi bi-whatsapp" aria-hidden="true"></i>
                    Escríbenos por WhatsApp
                </a>
            @endif
        </div>
    </main>
</div>
@endsection
