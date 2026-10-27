{{--
    Componente "Generar con IA": SOLO el marcado. El código vive UNA sola vez
    (ia-generar-js.blade.php) y se engancha a cada .ia-generar por sus
    atributos data-*; los estilos están en ia-generar-estilos.blade.php.

    Se incluye únicamente si el módulo 'ia' está activo para el negocio
    (@if ($iaActiva) en quien lo incluye): con el módulo apagado no se
    renderiza nada, ni siquiera oculto.

    Parámetros:
      $iaTipo         'banner' | 'frase'
      $iaCampoTitulo  selector del campo Título a rellenar (solo banner)
      $iaCampoTexto   selector del campo Texto a rellenar

    Solo PROPONE: elegir una opción rellena los campos del formulario y nada
    se guarda hasta que el admin pulse Guardar. Los campos de aquí no llevan
    name: no viajan con el formulario.
--}}
@php($iaId = 'ia-'.$iaTipo)
<div class="ia-generar" data-ia-tipo="{{ $iaTipo }}" data-ia-campo-titulo="{{ $iaCampoTitulo ?? '' }}" data-ia-campo-texto="{{ $iaCampoTexto }}">
    <button type="button" class="ia-btn-abrir" aria-expanded="false" aria-controls="{{ $iaId }}-panel">
        <i class="bi bi-stars" aria-hidden="true"></i>
        <span>Generar con IA</span>
    </button>

    <div class="ia-panel" id="{{ $iaId }}-panel" hidden>
        <p class="ia-estado" aria-live="polite"></p>

        {{-- Se actualiza sin recargar al guardar o quitar el perfil (IaGenerar.marcarPerfil). --}}
        <p class="ia-perfil-nota">
            <span class="ia-perfil-usa" @if (! $iaTienePerfil) hidden @endif><i class="bi bi-person-check" aria-hidden="true"></i> Usando tu perfil</span>
            <a href="#tarjeta-perfil-ia" class="ia-perfil-enlace" @if ($iaTienePerfil) hidden @endif>Completa tu perfil para textos más personalizados</a>
        </p>

        <div class="campo-flotante">
            <i class="bi bi-emoji-smile" aria-hidden="true"></i>
            <select class="ia-tono" id="{{ $iaId }}-tono">
                <option value="calido">Cálido</option>
                <option value="elegante">Elegante</option>
                <option value="divertido">Divertido</option>
                <option value="profesional">Profesional</option>
            </select>
            <label for="{{ $iaId }}-tono">Tono</label>
        </div>

        <div class="campo-flotante mt-3">
            <i class="bi bi-lightbulb" aria-hidden="true"></i>
            <input type="text" class="ia-idea" id="{{ $iaId }}-idea" maxlength="200" placeholder=" " autocomplete="off">
            <label for="{{ $iaId }}-idea">Tu idea (opcional)</label>
        </div>
        <div class="ayuda-campo">Por ejemplo: "20% en manicure este mes". Solo se mencionan descuentos o fechas si los escribes aquí.</div>

        <button type="button" class="btn-primario-accento ia-btn-generar mt-3">
            <i class="bi bi-magic" aria-hidden="true"></i>
            <span class="ia-texto-generar">Generar</span>
        </button>

        <div class="ia-resultados" aria-live="polite" aria-busy="false"></div>
    </div>
</div>
