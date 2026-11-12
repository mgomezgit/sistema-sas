@extends('layout.backoffice')

@section('title', 'Módulo no disponible')

@section('estilos')
    <style>
        .envoltorio-bloqueo {
            display: flex;
            justify-content: center;
            padding: 1.5rem 0;
        }

        .tarjeta-bloqueo {
            width: 100%;
            max-width: 560px;
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            padding: 2.25rem 2rem;
            text-align: center;
            box-shadow: 0 10px 30px color-mix(in srgb, var(--accent) 8%, transparent);
        }

        .insignia-bloqueo {
            width: 76px;
            height: 76px;
            margin: 0 auto 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: color-mix(in srgb, var(--accent) 14%, transparent);
            color: var(--accent);
            font-size: 2rem;
        }

        .titulo-bloqueo {
            color: var(--text-primary);
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .descripcion-bloqueo {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1.25rem;
        }

        .chip-plan {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: color-mix(in srgb, var(--accent) 12%, transparent);
            color: var(--accent);
            border: 1px solid color-mix(in srgb, var(--accent) 35%, transparent);
            border-radius: 999px;
            padding: 0.4rem 0.9rem;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 1.25rem;
        }

        .aviso-vencido {
            background: color-mix(in srgb, var(--danger) 10%, transparent);
            color: var(--danger);
            border: 1px solid color-mix(in srgb, var(--danger) 30%, transparent);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 0.9rem;
            margin-bottom: 1.25rem;
        }

        .bloque-contacto {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            align-items: center;
            margin-top: 0.5rem;
        }

        .btn-whatsapp-bloqueo {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            background: var(--accent);
            color: var(--text-sobre-accent);
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            transition: background 0.15s ease;
        }

        .btn-whatsapp-bloqueo:hover,
        .btn-whatsapp-bloqueo:focus-visible {
            background: var(--accent-hover);
            color: var(--text-sobre-accent);
            outline: 2px solid color-mix(in srgb, var(--accent) 45%, transparent);
            outline-offset: 2px;
        }

        .correo-bloqueo {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .correo-bloqueo a {
            color: var(--accent);
            font-weight: 600;
        }

        .contacto-generico {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
    </style>
@endsection

@section('content')
    <div class="envoltorio-bloqueo">
        <div class="tarjeta-bloqueo">
            <div class="insignia-bloqueo">
                <i class="bi bi-lock-fill"></i>
            </div>

            <h1 class="titulo-bloqueo">{{ $nombreModulo }}</h1>

            <p class="descripcion-bloqueo">{{ $descripcionModulo }}</p>

            @if ($fechaVencido !== null)
                <div class="aviso-vencido">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    Tu módulo venció el {{ $fechaVencido }}. Para renovarlo, escríbenos.
                </div>
            @endif

            @if ($plan !== null)
                <div>
                    <span class="chip-plan">
                        <i class="bi bi-stars"></i>
                        Disponible en el plan {{ $plan }}
                    </span>
                </div>
            @endif

            <div class="bloque-contacto">
                @if ($whatsappLink !== '')
                    <a href="{{ $whatsappLink }}" class="btn-whatsapp-bloqueo" target="_blank" rel="noopener noreferrer">
                        <i class="bi bi-whatsapp"></i>
                        Escríbenos por WhatsApp
                    </a>
                @endif

                @if ($correoContacto !== '')
                    <span class="correo-bloqueo">
                        o al correo <a href="mailto:{{ $correoContacto }}">{{ $correoContacto }}</a>
                    </span>
                @endif

                @unless ($tieneContacto)
                    <span class="contacto-generico">Comunícate con soporte para activarlo.</span>
                @endunless
            </div>
        </div>
    </div>
@endsection
