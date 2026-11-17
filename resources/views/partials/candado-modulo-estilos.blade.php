{{-- Estilos del candado de módulo (partials/candado-modulo). Solo variables
     del tema: el texto va en --text-primary para pasar 4,5:1 con cualquier
     acento y en los dos modos; elementos tocables de 44 px y foco visible. --}}
<style>
    .candado-modulo {
        background-color: var(--bg-card);
        border: 1px dashed color-mix(in srgb, var(--accent) 45%, transparent);
        border-radius: var(--radius-card);
        padding: 1.25rem;
        color: var(--text-primary);
    }

    .candado-encabezado {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        margin-bottom: 0.75rem;
    }

    .candado-icono {
        flex: 0 0 auto;
        width: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: var(--accent-soft);
        color: var(--accent);
        font-size: 1.2rem;
    }

    .candado-titulos {
        min-width: 0;
    }

    .candado-titulo {
        margin: 0 0 0.25rem;
        font-weight: 700;
        color: var(--text-primary);
        overflow-wrap: anywhere;
    }

    .candado-plan {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        border-radius: 999px;
        padding: 0.2rem 0.75rem;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-primary);
        background: var(--accent-soft);
        border: 1px solid color-mix(in srgb, var(--accent) 35%, transparent);
    }

    .candado-texto {
        margin: 0 0 0.85rem;
        color: var(--text-primary);
    }

    .candado-acciones {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem 1rem;
    }

    .candado-whatsapp,
    .candado-mas,
    .candado-contacto a {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        min-height: 44px;
        font-weight: 600;
        color: var(--text-primary);
    }

    .candado-whatsapp {
        padding: 0 1.1rem;
        border-radius: 12px;
        background: var(--accent-soft);
        border: 1px solid color-mix(in srgb, var(--accent) 55%, transparent);
        text-decoration: none;
    }

    .candado-whatsapp i {
        color: var(--success);
    }

    .candado-whatsapp:hover {
        border-color: var(--accent);
        color: var(--text-primary);
    }

    .candado-mas,
    .candado-contacto a {
        padding: 0 0.5rem;
        text-decoration: underline;
        text-underline-offset: 3px;
    }

    .candado-contacto {
        color: var(--text-primary);
    }

    .candado-whatsapp:focus-visible,
    .candado-mas:focus-visible,
    .candado-contacto a:focus-visible {
        outline: 3px solid var(--accent);
        outline-offset: 3px;
    }

    /* Dentro de un formulario: sin borde propio y más apretado. */
    .candado-modulo.compacto {
        padding: 0.75rem 0 0;
        border: 0;
        background: transparent;
    }

    .candado-modulo.compacto .candado-encabezado {
        margin-bottom: 0.4rem;
    }

    .candado-modulo.compacto .candado-texto {
        margin-bottom: 0.4rem;
        font-size: 0.9rem;
    }

    /* En celular el ícono va arriba: así el chip del plan no se parte en tres renglones. */
    @media (max-width: 576px) {
        .candado-encabezado {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .candado-whatsapp {
            width: 100%;
            justify-content: center;
        }
    }
</style>
