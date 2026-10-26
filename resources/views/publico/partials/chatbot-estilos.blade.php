{{-- Estilos del chat del asistente virtual (página pública). Todo color sale de
     las variables de la paleta fija del layout (--oro-fuerte, --apagado y
     --oro-suave incluidas): aquí NO va ningún hexadecimal ni rgb(). Prefijo
     .chat- para no chocar con el resto de la página. --}}
<style>
    .chat-raiz { font-family: 'Work Sans', 'Segoe UI', sans-serif; color: var(--text-primary); }
    .chat-raiz *, .chat-raiz *::before, .chat-raiz *::after { box-sizing: border-box; }
    .chat-raiz button { font-family: inherit; }
    .chat-raiz [hidden] { display: none !important; }

    /* Foco visible en todo. */
    .chat-raiz :focus-visible { outline: 2px solid var(--oro-fuerte); outline-offset: 2px; }

    /* Encima del WhatsApp flotante (56 px a 24 px del borde: su parte alta llega
       a 80 px) y por DEBAJO de Bootstrap (backdrop 1050, modal 1055): el modal de
       agendar siempre queda por encima del chat. */
    .chat-lanzador {
        position: fixed; right: 24px; bottom: 90px; z-index: 30;
        display: inline-flex; align-items: center; gap: 8px;
        height: 48px; min-width: 44px; padding: 0 18px; border: none; border-radius: 999px;
        background: var(--oro-fuerte); color: var(--on-accent);
        font-size: 13px; font-weight: 600; cursor: pointer;
        box-shadow: 0 10px 24px color-mix(in srgb, var(--text-primary) 22%, transparent);
    }
    .chat-lanzador i { font-size: 17px; line-height: 1; }
    .chat-lanzador.chat-sin-wa { bottom: 24px; }
    .chat-abierto .chat-lanzador { display: none; }

    .chat-panel {
        position: fixed; right: 24px; bottom: 90px; z-index: 40;
        width: 348px; height: 492px; max-height: calc(100vh - 110px); max-height: calc(100dvh - 110px);
        display: flex; flex-direction: column; overflow: hidden;
        background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px;
        box-shadow: 0 22px 56px color-mix(in srgb, var(--text-primary) 26%, transparent);
        animation: chat-entrada .22s ease-out;
    }
    .chat-panel.chat-sin-wa { bottom: 24px; max-height: calc(100vh - 44px); max-height: calc(100dvh - 44px); }

    .chat-fondo { display: none; position: fixed; inset: 0; z-index: 39; background: color-mix(in srgb, var(--text-primary) 45%, transparent); }

    /* Encabezado */
    .chat-encabezado { display: flex; align-items: center; gap: 10px; padding: 10px 6px 10px 14px; background: var(--bg-card); border-bottom: 1px solid var(--border-color); flex: 0 0 auto; }
    .chat-avatar {
        flex: 0 0 38px; width: 38px; height: 38px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        border: 2px solid var(--accent); background: var(--oro-suave); color: var(--oro-fuerte);
        font-family: 'Fraunces', serif; font-size: 17px; font-weight: 600;
    }
    .chat-titulos { flex: 1 1 auto; min-width: 0; }
    .chat-titulo { font-family: 'Fraunces', serif; font-size: 15px; font-weight: 600; line-height: 1.25; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .chat-subtitulo { font-size: 11.5px; color: var(--apagado); margin: 1px 0 0; line-height: 1.3; }
    .chat-icono {
        flex: 0 0 44px; width: 44px; height: 44px; display: inline-flex; align-items: center; justify-content: center;
        border: none; border-radius: 12px; background: transparent; color: var(--text-primary); cursor: pointer; font-size: 17px;
    }
    .chat-icono:hover { background: var(--oro-suave); }

    /* Mensajes */
    .chat-log { flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding: 14px; background: var(--bg-page); display: flex; flex-direction: column; gap: 10px; }
    .chat-fila { display: flex; }
    .chat-fila-visitante { justify-content: flex-end; }
    .chat-burbuja {
        max-width: 84%; padding: 9px 12px; border-radius: 14px;
        font-size: 13px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word; overflow-wrap: anywhere;
        display: flex; flex-direction: column; gap: 8px; align-items: flex-start;
    }
    .chat-burbuja-asistente { background: var(--bg-card); border: 1px solid var(--border-color); border-bottom-left-radius: 4px; color: var(--text-primary); }
    .chat-burbuja-visitante { background: var(--oro-fuerte); color: var(--on-accent); border-bottom-right-radius: 4px; }
    .chat-sistema { align-self: center; max-width: 92%; text-align: center; font-size: 11.5px; line-height: 1.4; color: var(--apagado); padding: 2px 8px; }

    .chat-wa {
        display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        min-height: 44px; padding: 0 16px; border-radius: 999px;
        background: var(--oro-fuerte); color: var(--on-accent); text-decoration: none;
        font-size: 12.5px; font-weight: 600; white-space: normal;
    }
    .chat-wa:hover { color: var(--on-accent); background: color-mix(in srgb, var(--oro-fuerte) 88%, var(--text-primary)); }

    /* Indicador de escritura */
    .chat-puntos { display: inline-flex; align-items: center; gap: 5px; height: 21px; }
    .chat-punto { width: 7px; height: 7px; border-radius: 50%; background: var(--apagado); animation: chat-salto 1s infinite ease-in-out; }
    .chat-punto:nth-child(2) { animation-delay: .15s; }
    .chat-punto:nth-child(3) { animation-delay: .3s; }

    /* Sugerencias */
    .chat-sugerencias { flex: 0 0 auto; display: flex; flex-wrap: wrap; gap: 8px; padding: 10px 14px; background: var(--bg-page); border-top: 1px solid var(--border-color); }
    .chat-sugerencias:empty { display: none; }
    .chat-sugerencia {
        min-height: 44px; padding: 0 14px; border-radius: 999px; cursor: pointer;
        border: 1.5px solid var(--accent); background: var(--bg-card); color: var(--oro-fuerte);
        font-size: 12.5px; font-weight: 600;
    }
    .chat-sugerencia:hover { background: var(--oro-suave); }

    /* Entrada */
    .chat-entrada { flex: 0 0 auto; display: flex; gap: 8px; padding: 10px 14px 6px; background: var(--bg-card); border-top: 1px solid var(--border-color); }
    .chat-input {
        flex: 1 1 auto; min-width: 0; height: 44px; padding: 0 12px; border-radius: 12px;
        border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); font-size: 14px;
    }
    .chat-input::placeholder { color: var(--apagado); opacity: 1; }
    .chat-enviar {
        flex: 0 0 44px; width: 44px; height: 44px; display: inline-flex; align-items: center; justify-content: center;
        border: none; border-radius: 12px; background: var(--oro-fuerte); color: var(--on-accent); font-size: 17px; cursor: pointer;
    }
    .chat-enviar:disabled { opacity: .55; cursor: not-allowed; }

    /* Pie */
    .chat-pie { flex: 0 0 auto; display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; padding: 0 14px 10px; background: var(--bg-card); }
    .chat-aviso { margin: 0; font-size: 11px; line-height: 1.4; color: var(--apagado); }
    .chat-contador { flex: 0 0 auto; font-size: 11px; line-height: 1.4; color: var(--apagado); font-variant-numeric: tabular-nums; }

    @keyframes chat-entrada { from { opacity: 0; transform: translateY(10px) scale(.98); } to { opacity: 1; transform: none; } }
    @keyframes chat-salto { 0%, 60%, 100% { transform: translateY(0); opacity: .55; } 30% { transform: translateY(-5px); opacity: 1; } }

    /* Celular: hoja inferior de ancho completo (máx. 85% de la pantalla). Las
       variables --chat-vv-* las pone el JS con visualViewport para que el
       teclado no tape la entrada. */
    @media (max-width: 575.98px) {
        .chat-lanzador { right: 12px; bottom: 90px; }
        .chat-lanzador.chat-sin-wa { bottom: calc(12px + env(safe-area-inset-bottom, 0px)); }
        .chat-abierto .chat-fondo { display: block; }
        .chat-panel, .chat-panel.chat-sin-wa {
            left: 0; right: 0; width: 100%; bottom: var(--chat-vv-bottom, 0px);
            height: 85vh; height: 85dvh; max-height: calc(var(--chat-vv-alto, 100vh) * .85);
            border-radius: 18px 18px 0 0; border-bottom: none;
            padding-bottom: env(safe-area-inset-bottom, 0px);
        }
        .chat-input { font-size: 16px; }
    }

    @media (prefers-reduced-motion: reduce) {
        .chat-panel { animation: none; }
        .chat-punto { animation: none; opacity: .8; }
    }
</style>
