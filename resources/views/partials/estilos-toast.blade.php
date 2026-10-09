{{-- Estilos de los toast (avisos de notificarUsuario). Un solo lugar: el layout del backoffice y las pantallas de acceso (login, recuperar clave, registro) lo incluyen. Todo el color sale de variables del tema: --success, --warning, --danger, --accent, --bg-card, --border-color, --text-primary, --text-secondary, --shadow-card. Cero hexadecimales aquí. --}}
    <style>
        /* ---------- Toast (avisos que no interrumpen) ---------- */

        /* El contenedor lo crea utilidades.js la primera vez que hace falta y le
           pone el "top" justo debajo de la barra superior flotante. No captura
           clics: solo los toast, así las campanas y el resto siguen clicables.
           z-index por encima de los modales de Bootstrap (1055) y de SweetAlert
           (1060), del loader (2000) y de la bienvenida (2500). */
        .toast-app-pila {
            position: fixed;
            top: var(--gap-flotante, 16px);
            right: var(--gap-flotante, 16px);
            width: 372px;
            max-width: calc(100vw - 24px);
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 2600;
            pointer-events: none;
        }

        .toast-app {
            --tc: var(--accent);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 40px 17px 14px;
            background: var(--bg-card);
            border: 1px solid color-mix(in srgb, var(--tc) 35%, var(--border-color));
            border-radius: 14px;
            box-shadow:
                0 0 0 1px color-mix(in srgb, var(--tc) 10%, transparent),
                var(--shadow-card),
                0 0 26px color-mix(in srgb, var(--tc) 14%, transparent);
            color: var(--text-primary);
            pointer-events: auto;
            animation: toast-app-entrar 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .toast-app-exito { --tc: var(--success); }
        .toast-app-aviso { --tc: var(--warning); }
        .toast-app-error { --tc: var(--danger); }
        .toast-app-info { --tc: var(--accent); }

        .toast-app-saliendo {
            opacity: 0;
            transform: translateX(24px);
            transition: opacity 0.18s ease, transform 0.18s ease;
        }

        .toast-app-icono {
            width: 34px;
            height: 34px;
            min-width: 34px;
            border-radius: 10px;
            background: color-mix(in srgb, var(--tc) 16%, transparent);
            color: var(--tc);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .toast-app-texto {
            min-width: 0;
        }

        .toast-app-titulo {
            font-size: 13.5px;
            font-weight: 700;
            margin: 1px 0 2px;
            color: var(--text-primary);
        }

        .toast-app-mensaje {
            font-size: 12.5px;
            color: var(--text-secondary);
            margin: 0;
            line-height: 1.45;
            /* Respeta los saltos de línea (listas de errores) y parte las
               palabras larguísimas sin desbordar el aviso. */
            white-space: pre-line;
            overflow-wrap: anywhere;
        }

        .toast-app-cerrar {
            position: absolute;
            top: 9px;
            right: 9px;
            width: 24px;
            height: 24px;
            border-radius: 7px;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            padding: 0;
        }

        .toast-app-cerrar:hover,
        .toast-app-cerrar:focus-visible {
            background: var(--bg-body);
            color: var(--text-primary);
        }

        .toast-app-cerrar:focus-visible {
            outline: 2px solid var(--tc);
            outline-offset: 1px;
        }

        .toast-app-progreso {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 3px;
            background: var(--tc);
            transform-origin: left;
            animation: toast-app-reducir 5s linear forwards;
        }

        @keyframes toast-app-entrar {
            from { opacity: 0; transform: translateX(46px) scale(0.96); }
            to { opacity: 1; transform: translateX(0) scale(1); }
        }

        @keyframes toast-app-reducir {
            from { transform: scaleX(1); }
            to { transform: scaleX(0); }
        }

        /* Celular: ancho completo con 12px de margen. */
        @media (max-width: 575.98px) {
            .toast-app-pila {
                left: 12px;
                right: 12px;
                width: auto;
                max-width: none;
            }
        }

        /* Sin animación de entrada ni barra animada; el tiempo de cierre se
           mantiene (lo controla el JS, no la animación). */
        @media (prefers-reduced-motion: reduce) {
            .toast-app {
                animation: none;
            }

            .toast-app-saliendo {
                transition: none;
            }

            .toast-app-progreso {
                display: none;
            }
        }
    </style>
