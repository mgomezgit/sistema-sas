{{-- Estilos del componente "Generar con IA". Cero hexadecimales: todo con las
     variables del tema (modo claro, oscuro y acentos) o color-mix() de ellas. --}}
<style>
    .ia-generar {
        margin-top: 1rem;
    }

    .ia-generar :focus-visible {
        outline: 2px solid var(--accent);
        outline-offset: 2px;
    }

    .ia-btn-abrir {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        min-height: 44px;
        padding: 0.5rem 1rem;
        border: 1.5px solid var(--accent);
        border-radius: 10px;
        background: var(--accent-soft);
        color: var(--text-primary);
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
    }

    .ia-btn-abrir i {
        color: var(--accent);
    }

    .ia-btn-abrir:hover {
        background: color-mix(in srgb, var(--accent) 22%, transparent);
    }

    .ia-panel {
        margin-top: 0.75rem;
        padding: 1rem;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        background: color-mix(in srgb, var(--accent) 5%, var(--bg-card));
    }

    .ia-panel[hidden] {
        display: none;
    }

    .ia-estado {
        margin: 0 0 0.75rem;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .ia-estado:empty {
        display: none;
    }

    /* No usa el relleno blanco-sobre-acento de .btn-primario-accento: con
       varios acentos (verde, oro rosa) no llega a 4,5:1. Texto del tema sobre
       un tinte del acento, que cumple con cualquier acento y en ambos modos. */
    .ia-generar .ia-btn-generar {
        min-height: 44px;
        border: 1.5px solid var(--accent);
        background: color-mix(in srgb, var(--accent) 28%, var(--bg-card));
        color: var(--text-primary);
        font-weight: 700;
    }

    .ia-generar .ia-btn-generar:hover:not([disabled]) {
        background: color-mix(in srgb, var(--accent) 40%, var(--bg-card));
        color: var(--text-primary);
    }

    .ia-generar .ia-btn-generar i {
        color: var(--accent);
    }

    .ia-btn-generar[disabled] {
        opacity: 0.55;
        cursor: not-allowed;
    }

    .ia-resultados {
        margin-top: 1rem;
        display: grid;
        gap: 0.6rem;
    }

    .ia-resultados:empty {
        display: none;
    }

    .ia-opcion {
        display: block;
        width: 100%;
        min-height: 44px;
        padding: 0.7rem 0.9rem;
        text-align: left;
        border: 1.5px solid var(--border-color);
        border-radius: 10px;
        background: var(--bg-card);
        color: var(--text-primary);
        cursor: pointer;
    }

    .ia-opcion:hover {
        border-color: var(--accent);
    }

    .ia-opcion[aria-pressed="true"] {
        border-color: var(--accent);
        background: var(--accent-soft);
    }

    .ia-opcion-titulo {
        display: block;
        font-weight: 700;
        margin-bottom: 0.15rem;
    }

    .ia-opcion-texto {
        display: block;
        font-size: 0.9rem;
    }

    .ia-advertencia {
        display: flex;
        align-items: flex-start;
        gap: 0.4rem;
        margin-top: 0.5rem;
        padding: 0.4rem 0.6rem;
        border-left: 3px solid var(--warning);
        border-radius: 6px;
        background: var(--warning-soft);
        color: var(--text-primary);
        font-size: 0.8rem;
        font-weight: 600;
    }

    .ia-mensaje {
        padding: 0.7rem 0.9rem;
        border: 1px solid var(--danger);
        border-radius: 10px;
        background: var(--danger-soft);
        color: var(--text-primary);
        font-size: 0.88rem;
    }

    .ia-mensaje.ia-info {
        border-color: var(--border-color);
        background: transparent;
        color: var(--text-secondary);
    }

    .ia-spinner {
        display: inline-block;
        width: 1rem;
        height: 1rem;
        border: 2px solid currentColor;
        border-right-color: transparent;
        border-radius: 50%;
        animation: ia-giro 0.8s linear infinite;
        vertical-align: -0.15em;
        margin-right: 0.4rem;
    }

    @keyframes ia-giro {
        to {
            transform: rotate(360deg);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .ia-spinner {
            animation: none;
        }
    }
</style>
