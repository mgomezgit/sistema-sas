{{--
    Tarjeta "Perfil para el asistente creativo": SOLO el marcado. Se incluye
    únicamente si el módulo 'ia' está activo (@if ($iaActiva) en quien la
    incluye): sin el módulo no se renderiza nada, ni siquiera oculta.

    El perfil es PRIVADO: no se muestra en la página pública, no entra al
    contexto del chatbot ni al historial de IA. Los valores actuales llegan
    escapados con doble llave (nunca sin escapar) y el JS los lee y escribe con
    .val(). Es un div y no un form: los botones son type="button" y Enter no
    envía nada.
--}}
<div class="card-elevada mb-4" id="tarjeta-perfil-ia" tabindex="-1">
    <div class="tarjeta-seccion-form">
        <div class="etiqueta-seccion-form">Perfil para el asistente creativo</div>

        <p class="ayuda-bloque mb-3">
            Estos datos no se muestran en tu página. Solo ayudan al asistente a escribir textos más tuyos.
        </p>

        <div class="campo-flotante">
            <i class="bi bi-stars" aria-hidden="true"></i>
            <input type="text" id="ia_especialidad" maxlength="{{ \App\Service\SvcIA::LARGO_MAXIMO_ESPECIALIDAD }}" placeholder=" " autocomplete="off" value="{{ $iaPerfil['especialidad'] }}">
            <label for="ia_especialidad">¿Qué hace especial a tu negocio?</label>
        </div>
        <span class="contador-frase" id="contador-ia-especialidad" aria-hidden="true">0/{{ \App\Service\SvcIA::LARGO_MAXIMO_ESPECIALIDAD }}</span>
        <div class="ayuda-campo">Por ejemplo: "Especialistas en uñas y cejas, ambiente familiar".</div>

        <div class="campo-flotante mt-3">
            <i class="bi bi-people" aria-hidden="true"></i>
            <input type="text" id="ia_publico" maxlength="{{ \App\Service\SvcIA::LARGO_MAXIMO_PUBLICO }}" placeholder=" " autocomplete="off" value="{{ $iaPerfil['publico'] }}">
            <label for="ia_publico">¿A quién le hablas?</label>
        </div>
        <span class="contador-frase" id="contador-ia-publico" aria-hidden="true">0/{{ \App\Service\SvcIA::LARGO_MAXIMO_PUBLICO }}</span>
        <div class="ayuda-campo">Por ejemplo: "Mujeres jóvenes que buscan cuidarse sin complicaciones".</div>

        <div class="acciones-frase">
            <button type="button" id="btn-guardar-perfil-ia" class="btn-guardar-moderno">
                <i class="bi bi-check2 icono-guardar"></i>
                <span id="texto-btn-guardar-perfil-ia">Guardar</span>
            </button>
            <button type="button" id="btn-quitar-perfil-ia" class="btn btn-outline-primary" @if (! $iaTienePerfil) disabled @endif>
                <i class="bi bi-x-lg" aria-hidden="true"></i> Quitar
            </button>
        </div>
    </div>
</div>
