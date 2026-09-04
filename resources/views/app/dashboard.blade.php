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

        /* El componente base reparte el contenido con space-between, lo que en la
           tarjeta alta empujaba el badge hasta el fondo y lo dejaba a más de 200px
           del valor, mientras en las bajas quedaba pegado. Agrupándolos arriba, el
           bloque "valor + badge" conserva la misma alineación en todas las alturas. */
        .bento-grid .kpi-tile {
            justify-content: flex-start;
        }

        .bento-grid .kpi-tile .badge-proximamente {
            margin-top: 0.85rem;
        }

        /* ---------- Próximas citas de hoy (tarjeta grande) ---------- */
        .lista-proximas {
            margin-top: 1.1rem;
            border-top: 1px solid var(--border-color);
        }

        .fila-proxima {
            display: flex;
            align-items: baseline;
            gap: 0.75rem;
            padding: 0.6rem 0.4rem;
            border-bottom: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            transition: var(--transition-base);
        }

        .fila-proxima:last-child {
            border-bottom: none;
        }

        .fila-proxima:hover {
            background-color: var(--bg-card-hover);
        }

        .fila-proxima .hora-proxima {
            color: var(--accent);
            font-weight: 700;
            font-size: 0.88rem;
            min-width: 48px;
        }

        .fila-proxima .cliente-proxima {
            color: var(--text-primary);
            font-size: 0.88rem;
            font-weight: 500;
        }

        .fila-proxima .servicio-proxima {
            color: var(--text-secondary);
            font-size: 0.82rem;
            margin-left: auto;
            text-align: right;
        }

        .enlace-calendario {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            margin-top: 0.85rem;
            color: var(--accent);
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition-base);
        }

        .enlace-calendario:hover {
            gap: 0.6rem;
            color: var(--accent-hover);
        }

        .sin-proximas {
            margin-top: 1.1rem;
            color: var(--text-secondary);
            font-size: 0.88rem;
        }

        /* ---------- Variación mensual de clientes ---------- */
        .variacion-clientes {
            display: inline-flex;
            align-items: center;
            gap: 0.2rem;
            margin-top: 0.5rem;
            font-size: 0.82rem;
            font-weight: 600;
        }

        .variacion-clientes.sube { color: var(--success); }
        .variacion-clientes.baja { color: var(--danger); }
        .variacion-clientes.igual { color: var(--text-secondary); }

        .variacion-clientes i {
            font-size: 1.1rem;
        }

        /* ---------- Barra de ocupación ---------- */
        .barra-ocupacion {
            margin-top: 0.85rem;
            height: 8px;
            width: 100%;
            background-color: var(--border-color);
            border-radius: 999px;
            overflow: hidden;
        }

        .barra-ocupacion .relleno-ocupacion {
            height: 100%;
            background-color: var(--accent);
            border-radius: 999px;
            transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
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
                <div class="kpi-valor">{{ $reservasHoy === null ? '—' : $reservasHoy }}</div>

                @if ($reservasHoy !== null)
                    @if (count($proximasCitas) > 0)
                        <div class="lista-proximas">
                            @foreach ($proximasCitas as $cita)
                                <div class="fila-proxima">
                                    <span class="hora-proxima">{{ substr($cita['hora_inicio'], 0, 5) }}</span>
                                    <span class="cliente-proxima">{{ $cita['nombre_cliente'] }}</span>
                                    <span class="servicio-proxima">{{ $cita['nombre_recurso'] }}</span>
                                </div>
                            @endforeach
                        </div>

                        <a href="{{ url('backoffice/reservas') }}" class="enlace-calendario">
                            Ver todas en el calendario <i class="bi bi-arrow-right"></i>
                        </a>
                    @else
                        <div class="sin-proximas">No tienes más citas pendientes por hoy.</div>
                    @endif
                @endif
            </div>
            {{-- Solo el super admin, que no pertenece a un negocio, se queda sin dato. --}}
            @if ($reservasHoy === null)
                <span class="badge-proximamente align-self-start">
                    <i class="bi bi-hourglass-split"></i> Próximamente
                </span>
            @endif
        </div>

        <div class="card-elevada kpi-tile tile-pequena">
            <div>
                <div class="kpi-icono"><i class="bi bi-people"></i></div>
                <div class="kpi-label">Clientes activos</div>
                <div class="kpi-valor">{{ $clientesActivos === null ? '—' : $clientesActivos }}</div>

                @if ($variacionClientes !== null)
                    @if ($variacionClientes > 0)
                        <div class="variacion-clientes sube">
                            <i class="bi bi-arrow-up-short"></i> +{{ $variacionClientes }} este mes
                        </div>
                    @elseif ($variacionClientes < 0)
                        <div class="variacion-clientes baja">
                            <i class="bi bi-arrow-down-short"></i> {{ $variacionClientes }} este mes
                        </div>
                    @else
                        <div class="variacion-clientes igual">Sin cambios este mes</div>
                    @endif
                @endif
            </div>
            {{-- Solo el super admin, que no pertenece a un negocio, se queda sin dato. --}}
            @if ($clientesActivos === null)
                <span class="badge-proximamente align-self-start">
                    <i class="bi bi-hourglass-split"></i> Próximamente
                </span>
            @endif
        </div>

        <div class="card-elevada kpi-tile tile-pequena">
            <div>
                <div class="kpi-icono"><i class="bi bi-cash-stack"></i></div>
                <div class="kpi-label">Ingresos del mes</div>
                <div class="kpi-valor">{{ $ingresosMes === null ? '—' : '$' . number_format($ingresosMes, 0, ',', '.') }}</div>
            </div>
            {{-- Solo el super admin, que no pertenece a un negocio, se queda sin dato. --}}
            @if ($ingresosMes === null)
                <span class="badge-proximamente align-self-start">
                    <i class="bi bi-hourglass-split"></i> Próximamente
                </span>
            @endif
        </div>

        <div class="card-elevada kpi-tile tile-media">
            <div>
                <div class="kpi-icono"><i class="bi bi-graph-up"></i></div>
                <div class="kpi-label">Ocupación</div>
                <div class="kpi-valor">{{ $ocupacionHoy === null ? '—' : $ocupacionHoy . '%' }}</div>

                @if ($ocupacionHoy !== null)
                    {{-- Se acota a 100 para que la barra no se desborde si un día
                         se agenda por encima de la jornada configurada. --}}
                    <div class="barra-ocupacion">
                        <div class="relleno-ocupacion" style="width: {{ min($ocupacionHoy, 100) }}%;"></div>
                    </div>
                @endif
            </div>
            {{-- Solo el super admin, que no pertenece a un negocio, se queda sin dato. --}}
            @if ($ocupacionHoy === null)
                <span class="badge-proximamente align-self-start">
                    <i class="bi bi-hourglass-split"></i> Próximamente
                </span>
            @endif
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
