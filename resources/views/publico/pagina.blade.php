<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $nombreNegocio }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Esta página NO hereda el layout del backoffice (es anónima y vive
           fuera de él), así que declara sus propios tokens en vez de repetir
           colores sueltos. Aquí es donde encajará la personalización del
           negocio —modo_tema y color_acento ya viajan en el endpoint de
           información— cuando se construya el diseño de la página. */
        :root {
            --pp-fondo: #f6f5f4;
            --pp-texto: #2a2320;
            --pp-texto-tenue: #6b625d;
            --pp-ancho: 760px;
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            background-color: var(--pp-fondo);
            color: var(--pp-texto);
        }

        .envoltorio-publico {
            max-width: var(--pp-ancho);
            margin: 0 auto;
            padding: 2.5rem 1.25rem 4rem;
        }

        .cargando-publico {
            color: var(--pp-texto-tenue);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    {{-- Armazón de la página pública. El contenido lo traen los endpoints de
         publico/{slug}/..., que son los que deciden qué dato es público. El
         diseño y el flujo de agendar se construyen en su propio prompt. --}}
    <div class="envoltorio-publico" id="pagina-publica" data-slug="{{ $slug }}">
        <h1 id="nombre-negocio">{{ $nombreNegocio }}</h1>
        <p class="cargando-publico" id="estado-carga">Cargando información…</p>

        <section id="seccion-informacion" hidden></section>
        <section id="seccion-servicios" hidden></section>
        <section id="seccion-equipo" hidden></section>
    </div>

    <script>
        (function () {
            var slug = document.getElementById('pagina-publica').dataset.slug;
            var base = '{{ url('publico') }}/' + encodeURIComponent(slug) + '/';

            Promise.all(['informacion', 'servicios', 'equipo'].map(function (recurso) {
                return fetch(base + recurso, { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); });
            })).then(function (respuestas) {
                document.getElementById('estado-carga').hidden = true;

                window.datosPublicos = {
                    informacion: respuestas[0].data.negocio,
                    servicios: respuestas[1].data.servicios,
                    equipo: respuestas[2].data.equipo
                };
            }).catch(function () {
                document.getElementById('estado-carga').textContent = 'No fue posible cargar la información en este momento.';
            });
        })();
    </script>
</body>
</html>
