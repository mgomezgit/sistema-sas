@extends('layout.backoffice')

@section('title', 'Dashboard')

@section('estilos')
    <style>
        .saludo-dashboard h2 {
            color: var(--text-primary);
            font-weight: 700;
            font-size: 1.6rem;
            margin-bottom: 0.15rem;
        }

        .saludo-dashboard .fecha-hoy {
            color: var(--text-secondary);
            font-size: 0.9rem;
            text-transform: capitalize;
        }

        .bento-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            /* Altura mínima que crece con el contenido: con una altura fija los
               badges se salían de la tarjeta. */
            grid-auto-rows: minmax(180px, auto);
            gap: 1rem;
            margin-top: 1.5rem;
            align-items: stretch;
        }

        .bento-grid .tile-grande {
            grid-column: span 2;
            grid-row: span 2;
        }

        .bento-grid .tile-pequena {
            grid-column: span 1;
            grid-row: span 1;
        }

        /* Ocupa las dos columnas libres de la segunda fila para que el grid no
           quede con un hueco vacío al lado de la tarjeta grande. */
        .bento-grid .tile-media {
            grid-column: span 2;
            grid-row: span 1;
        }

        .bento-grid .tile-ancha {
            grid-column: 1 / -1;
            grid-row: span 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .tile-ancha .texto-onboarding h3 {
            color: var(--text-primary);
            font-size: 1.15rem;
            font-weight: 600;
            margin-bottom: 0.35rem;
        }

        .tile-ancha .texto-onboarding p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 0;
        }

        @media (min-width: 768px) and (max-width: 991.98px) {
            .bento-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 767.98px) {
            .bento-grid {
                grid-template-columns: 1fr;
                grid-auto-rows: auto;
            }

            .bento-grid .tile-grande,
            .bento-grid .tile-pequena,
            .bento-grid .tile-media {
                grid-column: span 1;
                grid-row: span 1;
                min-height: 150px;
            }
        }
    </style>
@endsection

@section('content')
    <div class="saludo-dashboard">
        <h2>Hola, {{ session('nombre_usuario') }}</h2>
        <div class="fecha-hoy">{{ $fechaHoy }}</div>
    </div>

    <div class="bento-grid">
        <div class="card-elevada card-acento kpi-tile tile-grande">
            <div>
                <div class="kpi-icono"><i class="bi bi-calendar-check"></i></div>
                <div class="kpi-label">Reservas de hoy</div>
                <div class="kpi-valor">—</div>
            </div>
            <span class="badge-proximamente align-self-start">
                <i class="bi bi-hourglass-split"></i> Próximamente
            </span>
        </div>

        <div class="card-elevada kpi-tile tile-pequena">
            <div>
                <div class="kpi-icono"><i class="bi bi-people"></i></div>
                <div class="kpi-label">Clientes activos</div>
                <div class="kpi-valor">—</div>
            </div>
            <span class="badge-proximamente align-self-start">
                <i class="bi bi-hourglass-split"></i> Próximamente
            </span>
        </div>

        <div class="card-elevada kpi-tile tile-pequena">
            <div>
                <div class="kpi-icono"><i class="bi bi-cash-stack"></i></div>
                <div class="kpi-label">Ingresos del mes</div>
                <div class="kpi-valor">—</div>
            </div>
            <span class="badge-proximamente align-self-start">
                <i class="bi bi-hourglass-split"></i> Próximamente
            </span>
        </div>

        <div class="card-elevada kpi-tile tile-media">
            <div>
                <div class="kpi-icono"><i class="bi bi-graph-up"></i></div>
                <div class="kpi-label">Ocupación</div>
                <div class="kpi-valor">—</div>
            </div>
            <span class="badge-proximamente align-self-start">
                <i class="bi bi-hourglass-split"></i> Próximamente
            </span>
        </div>

        <div class="card-elevada tile-ancha">
            @if (\App\Models\Rol::esRolEmpleado(session('id_rol')))
                <div class="texto-onboarding">
                    <h3>Consulta tu agenda del día</h3>
                    <p>Este panel se irá llenando con métricas e indicadores a medida que se construyan los módulos. Mientras tanto, revisa las citas que tienes asignadas.</p>
                </div>
                <a href="{{ url('backoffice/mis-citas') }}" class="btn-primario-accento text-decoration-none">
                    <i class="bi bi-calendar2-check"></i> Ir a Mis Citas
                </a>
            @else
                <div class="texto-onboarding">
                    <h3>Empieza por organizar tu equipo</h3>
                    <p>Este panel se irá llenando con métricas e indicadores a medida que se construyan los módulos de reservas, clientes y caja. Mientras tanto, administra los usuarios de tu negocio.</p>
                </div>
                <a href="{{ url('backoffice/usuarios') }}" class="btn-primario-accento text-decoration-none">
                    <i class="bi bi-people"></i> Ir a Usuarios
                </a>
            @endif
        </div>
    </div>
@endsection
