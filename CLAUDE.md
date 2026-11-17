# Distrito 21 / Sistema SaaS de gestión de reservas — Reglas del proyecto

Este archivo lo lee Claude Code automáticamente al abrir el proyecto. Contiene las reglas de proceso y arquitectura que NO se pueden inferir solo leyendo el código. Si algo aquí contradice lo que ves en el código, pregúntale a Mateo antes de asumir cuál es la fuente de verdad.

## El proyecto en una frase

Plataforma SaaS de gestión de reservas/citas multi-rubro (spa, y a futuro hotel/finca), modelo freemium: núcleo gratis, add-ons de pago activables por negocio.

## Stack (no cambiar sin aprobación explícita)

- Backend: Laravel 13, PHP 8.4, MySQL 8.4 vía Laragon en Windows.
- Frontend: Bootstrap 5 + jQuery + Axios (vía axiosSipleInterno()) + DataTables + SweetAlert2 + Shepherd.js + FullCalendar (Standard/MIT, NUNCA el plugin Premium) + Chart.js + Bootstrap Icons. Todo por CDN, sin build tools (nada de Vite/Webpack/npm). JAMÁS Tailwind. JAMÁS frameworks JS (React/Vue/Angular).
- El proyecto no tiene package.json, vite.config.js, .npmrc ni @vite en las vistas; el guardián SinViteNiNpmTest lo vigila (y que los scripts de composer no llamen a npm).
- Cualquier librería nueva (JS/CSS por CDN, o paquete de Composer) se propone a Mateo primero, explicando qué hace y por qué ayuda, y se espera su confirmación antes de usarla en un prompt.
- Correo: Laravel Mail vía Mailpit en desarrollo (127.0.0.1:1025, bandeja en localhost:8025). Todos los Mailables implementan ShouldQueue — requiere php artisan queue:work corriendo en una terminal aparte para que se envíen de verdad.
- Colas: QUEUE_CONNECTION=database.
- Si un cambio modifica la firma (constructor/propiedades) de un Mailable o un Job, reinicia el queue:work después de aplicar el cambio (mátalo y vuelve a correrlo, o php artisan queue:restart) — un worker de larga vida mantiene en memoria la versión vieja de la clase y falla con errores confusos de propiedades sin inicializar hasta que se reinicia.
- Scheduler: routes/console.php (Laravel 11+/13, no Kernel.php).

## Arquitectura de capas (orden obligatorio por módulo)

1. Migración → 2. Model (Eloquent) → 3. Service (prefijo Svc) → 4. Controller tipo Request (JSON, rutas request/...) → 5. Controller tipo Vista (Blade, rutas backoffice/...) → 6. Vista Blade.

Backend completo primero, frontend después. No mezclar módulos ni adelantar frontend sin cerrar el backend del mismo módulo.

## Convenciones de Model

- namespace App\Models, protected $table y protected $primaryKey explícitos (PK nunca genérica id, siempre id_nombre_tabla).
- const CREATED_AT = null; const UPDATED_AT = null; (timestamps manuales).
- $fillable explícito, incluyendo la propia PK y las columnas de auditoría (usuario_registra, fecha_registro, estado).
- use HasFactory;
- NO usar los atributos nuevos de Laravel 13 para $table/$fillable — propiedades explícitas siempre.

## Convenciones de Service (Svc)

- namespace App\Service, clase Svc + Entidad (ej. SvcCliente).
- Cada método envuelto en try/catch; en el catch: Log::channel("database")->info($e); y retorno del valor vacío correspondiente (false, [], null).
- listar()/listarById(): select() explícito, NUNCA select *; retornan siempre ->toArray() ?? [].
- crear($info): retorna el ID insertado o false. editar($id, $info, ...): retorna bool. Excepción documentada: SvcReserva::editar() retorna constantes de resultado (igual que cambiarEstado() y eliminar()), porque el Controller necesita distinguir por qué no se hizo el cambio.
- Joins: query builder de Eloquent, alias cortos, AS explícito.
- Toda la lógica de negocio vive aquí, nunca en el controller.
- Incrementos/decrementos: increment()/decrement() o expresión SQL — NUNCA leer-y-reescribir (condición de carrera).

## REGLA MULTI-TENANT — no negociable, la más importante de todas

- Todo método de Service que toque una tabla con tenant_id lo recibe como parámetro explícito — nunca lo lee de session() internamente.
- El Controller extrae session('tenant_id') y se lo pasa. Si es null (super admin), el Service NO filtra; si trae valor, filtra siempre.
- El WHERE de editar()/eliminar() SIEMPRE incluye tenant_id, además del ID del registro.
- En crear(), si la sesión tiene tenant_id, se FUERZA ese valor, ignorando cualquier tenant_id que venga del formulario, del request, o de un archivo importado.
- Excepción documentada: procesos de sistema (comandos del Scheduler sin sesión de usuario) pueden tener métodos sin tenant_id, claramente marcados como tal.

## Convenciones de Controller base (abstracto, App\Http\Controllers\Controller)

- $respuesta = ["error" => 1, "mensaje" => "", "data" => []].
- $this->request y $this->requestData poblados en el constructor, pero refrescados por petición desde callAction(), no solo en el constructor (el router de Laravel cachea la instancia; con Octane o varias peticiones en el mismo proceso se filtraría estado de una petición a otra).
- setRequestValidationRules(array $rules, array $messages = []) y validateRequestRules() usando Validator. El parámetro $messages es opcional y retrocompatible (mensajes personalizados de validación).
- agregarError($mensaje), respSinError(), existeErrores(), setDataResponse($valor, $nombreVar = ""), setDataLog(...), sendResponse().

## Controller tipo "Request" (App\Http\Controllers\Request)

- Constructor con inyección de Services (properties private tipadas), parent::__construct() primero.
- Flujo estándar: setRequestValidationRules([...]) → si !validateRequestRules() retorna sendResponse() → $datosFormulario = $this->getRequestData() → arma array limpio → llama al Service → si falla: agregarError() + sendResponse() → si OK: respSinError() + sendResponse().
- Métodos comunes: crear(), editar(), eliminar(), listar(). Rutas bajo request/nombre_modulo/....

## Controller tipo "Vista" (App\Http\Controllers)

- Arma $templateView["clave"] = valor y retorna view("app.nombre_vista", $templateView). Nunca JSON. Rutas bajo backoffice/....

## Reglas de interacción del frontend (absolutas, sin excepción)

- JAMÁS method="" en una etiqueta <form>.
- JAMÁS rutas con nombre (Route::name(), ->name(), la función route()). Todas las URLs son cadenas literales: url('backoffice/...') o url('request/...').
- Todos los botones son type="button". Ninguna acción usa submit nativo.
- Toda interacción con el backend vía JS llama a axiosSipleInterno(metodo, url, parametros, cuerpo, mostrarLoader, callback, extraOptions). Nunca fetch nativo, nunca $.ajax directo (excepción justificada: FullCalendar u otra librería que exige su propia función, o subida de archivos con FormData/multipart).
- Los atributos name="" en los <input> hijos SÍ se usan (los necesita getDataJson()/serializeObject()) — la prohibición es sobre el name/method de la etiqueta <form> misma.
- Un checkbox de estado que puede desmarcarse (activo→inactivo) va SIN name, y su valor 0/1 se inyecta explícitamente antes de enviar — serializeObject() omite un checkbox desmarcado del payload, así que dejarle name pierde la desactivación en silencio.
- Layout base layout.backoffice. Utilidades globales ya definidas: axiosSipleInterno, getDataJson, notificarUsuario(mensaje, icono, urlRedireccion), system_validarcampos, Mostrarloader()/Ocultarloader(), colorVariable(nombre), generarAvatar(nombre), dispararConfeti(cantidad, origenX).
- Todo dato escrito por una persona se pinta escapado. La función global es escaparTexto() (public/js/utilidades.js, la ÚNICA función de escape del proyecto): escapa & < > y también las comillas " y ', así que sirve tanto para contenido HTML como dentro del valor de un atributo armado a mano (data-*="...", title="..."); las columnas de DataTables que muestran texto usan { render: renderTextoSeguro }; notificarUsuario() escapa su mensaje antes de pasarlo a SweetAlert2. En destinos que no son HTML (.text(), .val(), .attr(nombre, valor), "text" de SweetAlert2) NO se escapa: se verían entidades literales. OJO: .data() y .attr() decodifican las entidades al leer un atributo, así que un valor leído así vuelve a ser texto crudo y, si se reinserta como HTML, se escapa de nuevo en ese punto.
- notificarUsuario(mensaje, icono, urlRedireccion) muestra un TOAST (arriba a la derecha, debajo de la barra superior), no un SweetAlert. Dura 5 s (los de tipo error, 8 s), máximo 4 a la vez, y no devuelve promesa (devuelve { cerrar() }). SweetAlert2 se usa SOLO para confirmaciones o decisiones (showCancelButton, input, elegir). A notificarUsuario() nunca se le pasa HTML ni texto ya escapado: inserta con textContent, así que un "&amp;" escapado de antemano se vería literal.
- Exportaciones: toda exportación nueva que lleve texto escrito por usuarios extiende App\Exports\BinderCeldasSeguras, que neutraliza la inyección de fórmulas de Excel (un valor que empieza con =, +, -, @, tabulador o retorno de carro se guarda como texto explícito con "quote prefix").

## Sistema de diseño y theming (crítico)

- TODO color vive en variables CSS (--accent, --accent-hover, --accent-soft, --bg-*, --text-*, --border-color, --danger, --success, etc.) o en color-mix(in srgb, var(--accent) N%, transparent) derivado de ellas. JAMÁS hexadecimales sueltos en las vistas.
- Dos capas combinables en <body class="modo-{claro|oscuro} acento-{nombre}">. Agregar un acento nuevo = un bloque de 3 variables (--accent, --accent-hover, --accent-soft). Agregar un modo nuevo = un bloque de las demás variables.
- Antes de cerrar cualquier módulo visual: auditar con grep que no queden hexadecimales sueltos fuera de :root/bloques de modo, y verificar contraste real en AMBOS modos.
- La landing pública (/) es negro/rojo fijo (identidad de plataforma) — no confundir con la página pública de autogestión por negocio (publico/{slug}), que usa su propia paleta fija (oro-rosa + blanco, ver sección aparte).

## Kit de formularios reutilizable (ya construido en layout/backoffice.blade.php)

Aplicar estas clases a cualquier modal/formulario nuevo o que se rediseñe — no reinventar el patrón:

- .modal-moderno — sobre .modal de Bootstrap. Entrada elástica + reflejo de vidrio superior. Convive con .fade.
- .modal-header-moderno + .insignia-encabezado — encabezado con aura giratoria y badge de ícono.
- .tarjeta-seccion-form + .etiqueta-seccion-form — agrupa campos relacionados.
- .campo-flotante — envoltorio de input/textarea/select + ícono + label flotante (CSS puro, sin JS). El placeholder debe ser " " y el <label> va DESPUÉS del campo en el HTML. Variante .campo-flotante.sin-icono.
- .con-error + .mensaje-error-campo — estado de error con sacudida.
- .stepper-campo + .btn-stepper[data-paso] + .valor-stepper — contador +/−, lee min/max nativos del input (NO data-min/data-max), ya cableado por delegación en el layout.
- .interruptor-moderno + .pista-interruptor — toggle sobre un <input type="checkbox"> real (ver regla del checkbox sin name arriba).
- .btn-guardar-moderno + .icono-guardar — degradado con brillo diagonal en hover. Modificadores .ocupado (spinner) y .exito (check + fondo verde).

## Patrón de reactivación (activo/inactivo) por módulo

Para cualquier entidad con baja lógica (estado), el patrón ya establecido en Productos/Clientes/Recursos/Empleados/Usuarios/Comisiones es:

- Svc*::listar($tenantId, $incluirInactivos = false) — filtra estado = 1 salvo que se pida lo contrario.
- El interruptor de estado SOLO aparece en modo EDICIÓN (nunca en creación, donde crear() fuerza estado = 1 sin leerlo del request — mostrar el control ahí sería un interruptor que el backend ignora en silencio).
- Toggle "Mostrar inactivos" en la tabla, mismo texto/ubicación en todos los módulos.

## Cascadas de estado entre Empleados y Usuarios (ya construidas)

- Desactivar un EMPLEADO con acceso vinculado (empleados.id_usuario) desactiva automáticamente su USUARIO (pierde acceso al sistema).
- Desactivar un USUARIO con empleado vinculado desactiva automáticamente ese EMPLEADO (deja de ser asignable a reservas).
- Ninguna reactivación es automática en el sentido inverso — siempre requiere una acción explícita separada del admin.
- Ambas direcciones escriben con update() DIRECTO sobre la columna estado del otro modelo — NUNCA invocando el método editar() completo del otro Service, para evitar un bucle de recursión entre las dos cascadas. No "simplificar" esto a una llamada de servicio sin releer esta regla primero.
- Solo la transición 1→0 dispara la cascada (nunca "si estado es 0", porque guardar un registro ya inactivo por otra razón no debe re-disparar nada).

## Página pública de autogestión (publico/{slug})

- Un slug único GLOBAL por negocio (negocios.slug), generado automáticamente al crear el negocio, y que NUNCA se regenera solo al cambiar el nombre — solo cambia si el admin lo edita a mano en Configuración.
- Rutas y Controller completamente separados del backoffice (PublicoController), SIN sesión. Todo método que exponga datos usa una LISTA BLANCA de campos explícita (nunca select('*') ni ->toArray() del modelo completo) — hay pruebas que verifican esto por mutación, no romperlas.
- Paleta FIJA para todos los negocios: oro-rosa (#b76e79) + blanco/crema, tipografía Fraunces (títulos) + Work Sans (texto) vía Google Fonts CDN. NO usa el color_acento/modo_tema que el negocio eligió para su propio backoffice — es una decisión de marca de la plataforma, no de personalización por negocio.
- Las reservas creadas desde ahí son SOLICITUDES, no citas confirmadas: nacen con estado = 'pendiente', id_empleado = null, origen = 'publico'. NO disparan el correo de "reserva confirmada" al cliente (ese correo sale únicamente de SvcNotificacionReserva::notificarReservaCreada(), que solo llama ReservaController::crear(); nunca de SvcReserva — ver crearSolicitudPublica() para el patrón correcto de crear sin ese efecto secundario). El admin las confirma y asigna empleado desde el modal de edición normal, que sí dispara el correo al cambiar el estado.
- Throttle + campo honeypot en el endpoint de agendar. CSRF excluido solo en esa ruta (no hay sesión que proteger; el throttle+honeypot son la protección real).

## Chatbot de la página pública (módulo de pago "chatbot")

- Versión 1: SOLO responde preguntas. El agente (App\Ai\Agents\AsistenteNegocio, SDK laravel/ai) NO tiene herramientas ni acciones: no agenda, no cancela, no lee nada por su cuenta. Cualquier cambio que le dé herramientas o acciones requiere una revisión de seguridad aparte antes de construirse.
- El contexto que recibe el modelo sale ÚNICAMENTE de SvcPaginaPublica (la misma lista blanca de la página pública), vía SvcChatbot::construirContexto(). Nada que la página pública no muestre llega al modelo. El texto escrito por el negocio va en un bloque delimitado y marcado como DATOS.
- El historial vive SOLO en la caché del servidor, bajo una clave con el tenant y un id aleatorio. Nunca se acepta historial enviado por el cliente, ni se usa la memoria de conversación del SDK (sus migraciones no se publican). Lo único permanente son los contadores de chatbot_uso.
- La conversación vence por INACTIVIDAD a los 15 minutos (configurable; el plazo se renueva con cada mensaje). El visitante puede terminarla cuando quiera (POST publico/{slug}/chatbot/terminar) y el servidor olvida el historial de inmediato. Una limpieza horaria (chatbot:limpiar-cache-vencida) borra de la caché las filas vencidas, porque la caché tipo database no las borra sola.
- La respuesta se pinta como TEXTO PLANO (textContent), nunca como HTML.
- El chat del frontend (publico/partials/chatbot.blade.php, solo en la página pública) pinta TODO como texto plano: lo que escribe el visitante, las respuestas de la IA, los errores del servidor y el nombre del negocio, con createElement + textContent y jamás con innerHTML/.html(). La conversación se guarda SOLO en el sessionStorage de la pestaña (todo acceso dentro de try/catch; el chat funciona sin él) y la vida de la conversación llega del servidor por un atributo data (config('chatbot.vida_conversacion_minutos')), nunca escrita aparte en el JS.
- Límite mensual por negocio, tope global de la plataforma y limitador por IP se comprueban ANTES de llamar a la IA. La respuesta pública nunca revela uso, límites ni restantes.
- Nunca una clave de API en el repositorio, en logs ni en reportes: vive solo en .env (OPENAI_API_KEY). Los fallos del proveedor se registran sin el texto de la excepción ni el mensaje del visitante.

## Asistente creativo (módulo de pago "ia")

- La IA SOLO PROPONE: devuelve opciones de texto (banner, frase, descripción de servicio) y nunca guarda ni publica nada. SvcIA no escribe en banners, servicios ni ninguna tabla de contenido; el admin decide qué copiar y dónde. El agente (App\Ai\Agents\RedactorCreativo) no tiene herramientas ni memoria.
- El texto generado se trata como texto escrito por una persona: el servidor lo limpia a texto plano (sin HTML, URLs, menciones ni emojis) y el frontend lo pinta como texto (textContent/.text() o escaparTexto()), nunca como HTML.
- Ningún dato de clientas sale hacia la IA. Recibe solo nombre y rubro del negocio, el tema que escribe el admin y, para descripciones, nombre/categoría/duración de UN servicio activo de ESE negocio (sin precio). Todo dato escrito por personas va en el bloque <DATOS> del mensaje, nunca en el system prompt.
- El módulo 'ia' nace APAGADO y lo activa el super admin por negocio. Límite mensual por negocio (negocios.ia_limite_mensual o config('ia.limite_mensual')) y tope global de la plataforma, ambos comprobados y reservados ANTES de llamar a la IA; un fallo del proveedor o una salida sin opciones válidas no gasta cupo. Limitador por usuario en request/ia/generar.
- El historial (ia_actividad) guarda SOLO metadatos: quién, cuándo, tipo, tono, estado y tokens. Nunca el texto generado ni el tema.
- Cualquier cambio que le dé al asistente acciones o herramientas (guardar, publicar, leer datos por su cuenta) requiere una revisión de seguridad aparte antes de construirse.

## Frase destacada y botón "Generar con IA"

- Frase destacada: UN solo espacio por negocio (negocios.frase_destacada, hasta 140 caracteres, y frase_hasta opcional, inclusive). Se ve como una franja bajo el encabezado de la página pública y vence sola: SvcNegocio::fraseVigente() es la ÚNICA regla de vigencia (la usan la página pública y el estado del panel). NO depende del módulo 'ia': cualquier admin la gestiona (request/negocio/guardar-frase y quitar-frase, tenant siempre de la sesión). Se guarda tal cual la escribió el admin y se pinta SIEMPRE escapada ({{ }} en Blade, .val()/.text() en el panel); SvcPaginaPublica solo la expone si está vigente y nunca expone frase_hasta. No entra al contexto del chatbot.
- El componente "Generar con IA" (partials app/configuracion/partials/ia-generar*.blade.php; el código JS existe una sola vez) solo PROPONE: rellena los campos del formulario y NUNCA guarda; el admin decide con Guardar. Solo se renderiza si el módulo 'ia' está activo para el negocio (con el módulo apagado no hay ni rastro, ni oculto por CSS). Todo texto de la IA o del servidor se pinta con textContent/.text()/.val(), jamás como HTML.
- Perfil del asistente creativo (negocios.ia_especialidad, hasta 200, e ia_publico, hasta 120; POST request/ia/perfil, con los mismos middlewares que el resto de request/ia/*): es PRIVADO. No se muestra en la página pública, no entra a SvcPaginaPublica ni al contexto del chatbot, y no se guarda en el historial (ia_actividad) ni en ninguna tabla fuera de negocios. Solo viaja a la IA dentro del bloque DATOS del mensaje (JSON con < > & codificados, solo las claves con valor, bajo "perfil") y se trata como DATO, nunca como orden ni dentro del system prompt. Se guarda tal cual lo escribió el admin (el escape se hace al pintar).
- Descripción pública del servicio (recursos_reservables.descripcion_publica, hasta 200; columna distinta de `descripcion`, que es una NOTA INTERNA y nunca sale a la web, al chatbot ni a la IA): es PÚBLICA y la edita cualquier admin del negocio a mano (no depende del módulo 'ia'; solo el botón "Generar con IA" depende). Se guarda tal cual y se pinta SIEMPRE escapada ({{ }}); en la página pública la tarjeta con descripción es desplegable (button con aria-expanded/aria-controls, cerrada por defecto) y la que no tiene queda como siempre. El botón de IA en Recursos solo PROPONE (rellena el campo, nunca guarda), solo funciona al editar un servicio existente y activo, y la IA recibe nombre y categoría del servicio pero NO su duración ni precio y tiene prohibido mencionarlos (la tarjeta ya los muestra). No entra al contexto del chatbot.

## Vigencia de módulos de pago

- SvcModulo::estaActivo incluye la vigencia EN TIEMPO REAL: activo = 1 Y (vigente_hasta es null O vigente_hasta >= Carbon::today()). El acceso se corta solo al día siguiente de la fecha aunque el proceso diario (modulos:procesar-vencimientos) no haya corrido; ese proceso solo limpia el estado y avisa.
- origen y nota de negocio_modulos son PRIVADOS del super admin: nunca salen en una respuesta que vea un admin de negocio. Orígenes 'cortesia' y 'pagado_fuera' son seleccionables; 'plan' y 'pasarela' quedan RESERVADOS (no seleccionables) para cuando existan planes y cobro automático.
- Los avisos de vencimiento (7 días, 1 día y vencido) salen UNA sola vez por fecha de vigencia (banderas aviso_7_enviado_en / aviso_1_enviado_en / vencido_avisado_en); al cambiar vigente_hasta se limpian. Van solo a los administradores activos de ese negocio; los negocios suspendidos no reciben correos pero sus módulos sí se apagan. Los correos no mencionan precios.
- Existe config/plataforma.php (nombre, contacto_whatsapp, contacto_correo leídos del .env, vacíos por defecto) para el contacto de renovación que muestran los correos y los mensajes de bloqueo; sin contacto usan un texto genérico. App\Support\Plataforma centraliza su lectura.

## Módulos apagables con candado (Reportes, Inventario)

- Reportes e Inventario son módulos de pago apagables por negocio (claves 'reportes' e 'inventario'), igual que Comisiones. Los negocios que YA existían al correr su migración quedaron activos como cortesía (origen 'cortesia', sin vencimiento); los negocios NUEVOS nacen SIN ellos (ningún camino los activa al crear el negocio).
- Toda ruta de un módulo de pago lleva verificar.modulo:<clave> (vista Y endpoint), nunca solo la pantalla. Una vista bloqueada redirige a backoffice/modulo/{clave} (pantalla de "no disponible"); un endpoint bloqueado responde el error JSON de siempre con el mensaje del módulo. El super admin (tenant null) nunca queda bloqueado. Apagar o vencer un módulo NO borra datos.
- El sidebar muestra el ítem del módulo SIEMPRE: con enlace normal si está activo, o con candado (parcial layout/partials/item-modulo-bloqueado) que apunta a la pantalla de "no disponible" si no. El grupo "Módulos de pago" se muestra siempre. El empleado no ve ninguno de estos módulos.
- Los textos de plan y descripción de cada módulo viven en config/plataforma.php (clave 'modulos') y son SOLO de presentación: aún no existen planes en el sistema. El enlace de WhatsApp de la pantalla de bloqueo usa el número normalizado a solo dígitos (App\Support\Plataforma::whatsappLink).
- Los pasos de onboarding 'reportes' e 'inventario' solo cuentan si el negocio tiene ese módulo activo; sin él se omiten del total y del progreso (SvcNegocio::obtenerProgresoOnboarding), para que el onboarding se pueda completar igual.
- Toda prueba que ejercite un módulo de pago debe activarlo primero en su preparación (trait Tests\Concerns\ActivaModulos), porque los negocios de prueba nacen sin módulos.
- Apagar el módulo 'inventario' en un negocio apaga TODO su inventario a la vez: el ítem "Productos" del sidebar pasa a candado, las rutas request/producto/* y backoffice/productos quedan bloqueadas, la campana de stock del navbar NO se renderiza (ni su botón ni su sondeo, para no consultar en bucle un endpoint bloqueado), el comando productos:enviar-resumen-stock-bajo OMITE a ese negocio (comprueba SvcModulo::estaActivo por negocio, con la vigencia), la carga masiva rechaza el tipo 'productos' y su plantilla (los demás tipos no se tocan) y el paso 'inventario' del onboarding se omite. Los datos de productos se CONSERVAN y reaparecen al reactivar. El comando productos:asignar-sku-faltantes es de mantenimiento manual (no está en el Scheduler) y NO comprueba el módulo a propósito: es una herramienta de operador, no un flujo del negocio.
- El módulo 'correos' (Correos a tus clientas) controla SOLO los tres correos a la clienta (ReservaConfirmada, ReservaEstadoActualizado, ReservaRecordatorio) y se decide al despachar con SvcModulo::estaActivo (con la vigencia): los correos por evento solo salen desde SvcNotificacionReserva (ReservaConfirmada vía notificarReservaCreada, que solo llama ReservaController::crear) y el recordatorio desde reservas:enviar-recordatorios; los correos del sistema (registro, recuperación de clave, comprobante, avisos de vencimiento, NuevaSolicitudPublica, ResumenStockBajo) nunca dependen de él, y GuardianCorreosClientasTest vigila que nadie más despache esos tres correos.

## Libro de pagos y comprobante (interno del super admin)

- El libro de pagos (pagos_plataforma) es un registro INTERNO que solo ve el super admin; ningún admin de negocio ni empleado accede a él. Mateo cobra por fuera: no hay pasarela, webhook, factura electrónica ni PDF.
- El documento que recibe el negocio es un "Comprobante de pago", NUNCA una "Factura"; su pie dice "Este documento es un comprobante de pago y no reemplaza una factura de venta".
- El consecutivo (CP-0001) es atómico y ÚNICO, y NUNCA se reutiliza, ni siquiera el de un registro anulado. Se asigna dentro de la misma transacción que activa o renueva el módulo y crea el registro (si algo falla, no se activa, no se registra y no se gasta consecutivo). El comprobante se encola DESPUÉS de confirmar la transacción.
- Anular un registro (solo super admin, motivo de 5 a 200) conserva el consecutivo, lo excluye de los totales y NO cambia la vigencia del módulo (para corregir la vigencia se ajusta en Módulos); no envía correo y no se anula dos veces.

## Seguridad y autenticación

- Sesión manual de Laravel (session()), NUNCA Auth:: nativo ni middleware auth.
- Claves de sesión: id_usuario, usuario, nombre_usuario, email, tenant_id, id_rol, id_empleado, rubro_negocio, nombre_negocio_sesion, modo_tema, color_acento, app_sesion, version_sesion.
- version_sesion: copia, al iniciar sesión, de usuarios.version_sesion. VerificarSesion la compara contra la de la base en cada petición; si no coincide, corta la sesión (mensaje "La clave de tu cuenta cambió..."). Es el mecanismo para cerrar TODAS las sesiones abiertas de una cuenta cuando su clave cambia (recuperación de clave, o un admin editando la clave de un usuario) — el login es manual y sessions no sabe a qué usuario pertenece cada sesión, así que no hay otra forma de invalidarlas.
- Contraseñas con Hash::make()/Hash::check(), nunca texto plano.
- Los registros (logs) nunca contienen valores de columnas, SQL con bindings ni argumentos de la traza: un procesador central (App\Logging\ProcesadorSinDatosPersonales, aplicado con "tap" a todos los canales en config/logging.php) los quita, y RegistrosSinDatosPersonalesTest lo vigila. En producción, zend.exception_ignore_args debe estar en On en php.ini.
- El código de soporte que ve el usuario (agregarErrorSistema/agregarErrorNoDisponible) lleva el id corto de la petición (USR-CREAR-7F3A, App\Support\IdPeticion, asignado en Controller::callAction) y se escribe en el canal database ("CODIGO DE SOPORTE ...") junto con la excepción de esa misma petición; el procesador central agrega al extra de cada línea SOLO ids (id_peticion, tenant_id, id_usuario) y la ruta (método + plantilla, sin query string). CodigoSoporteRastreableTest lo vigila.
- Middleware VerificarSesion (sesion.activa) y RestringirEmpleado (restringir.empleado) bloquean TANTO vistas backoffice/* COMO endpoints request/* — nunca solo la pantalla.
- Email y usuario (login) son ÚNICOS GLOBALMENTE (no por tenant) — es un requisito del login de pantalla única, no una opción de diseño. Un usuario INACTIVO libera su correo/usuario para reutilizarse (columnas generadas email_activo_unico/ usuario_activo_unico con índice único condicional a nivel de base de datos, no solo validación de código).
- Roles: admin (dueño de negocio), empleado (solo sus citas), super_admin (tenant_id null, no gestiona datos operativos de negocios individuales).
- Seeders: RolSeeder es el de producción (crea solo los tres roles con ids fijos admin 1, empleado 2, super_admin 3, idempotente y sin sobrescribir; es lo único que llama DatabaseSeeder). UsuarioDemoSeeder crea cuentas de demostración con claves conocidas y se niega a correr fuera de APP_ENV=local.
- La cuenta super admin de un servidor nuevo se crea con php artisan plataforma:crear-super-admin, interactivo: la clave se pide oculta y confirmada, NUNCA por argumento, opción ni variable de entorno, y no se imprime ni se registra (ni su hash). Se niega si ya existe una cuenta super admin (activa o inactiva) o si faltan los roles. Usa SvcUsuario::crearCuentaDePlataforma, el único camino que crea un super_admin.
- Solo el comando plataforma:crear-super-admin puede crear la cuenta de plataforma: GuardianCuentaPlataformaTest falla si otro archivo de app/, routes/, database/ o config/ llama a crearCuentaDePlataforma, o si el rol super_admin aparece fuera de su lista blanca documentada (cada uso con su motivo).
- Cuenta de plataforma: existe UNA sola cuenta con rol super_admin ACTIVA en toda la plataforma, y SIEMPRE sin negocio (tenant_id null). Nadie la crea, ni la duplica, ni le cambia el rol, ni le pone negocio, ni la da de baja o elimina — ni siquiera el propio super admin (solo puede editar su nombre, correo y clave). La regla vive en SvcUsuario y está respaldada en la base con columnas generadas más índice único condicional (mismo patrón que correo/usuario): la columna que depende de tenant_id va VIRTUAL, no STORED, porque la FK de tenant_id es ON DELETE SET NULL y MySQL prohíbe basar una columna STORED en ella.
- Un admin de negocio solo asigna los roles admin y empleado, y SOLO en su negocio (el tenant sale siempre de la sesión). El rol super_admin NUNCA aparece como opción en ningún selector ni en respuestas que alimenten selectores. La lista blanca de roles es por nombre (Rol::ROLES_DE_NEGOCIO), no "que el id exista".
- Defensa en profundidad del panel de plataforma: SoloSuperAdmin exige a la vez rol super_admin Y sesión sin negocio (tenant_id null); VerificarSesion corta de inmediato cualquier sesión con rol super_admin y negocio (combinación anómala).
- El dashboard (backoffice/dashboard y los datos que lo alimentan) es SOLO para admin: muestra métricas del negocio (ingresos, ocupación). El empleado rebota a Mis Citas (vistas) o recibe el error de siempre (endpoints), igual que en el resto del backoffice; el super admin sigue redirigido a su propio dashboard de plataforma.

## Pruebas automatizadas (OBLIGATORIO, sin excepción)

- Módulo NUEVO: pruebas PHPUnit completas, incluyendo aislamiento de tenant con su prueba de mutación (romper el filtro a propósito, confirmar que la prueba lo detecta, restaurar, confirmar que vuelve a pasar — documentar el proceso en el test).
- Módulo EXISTENTE que se modifica: correr la SUITE COMPLETA (no solo la del módulo tocado), para confirmar que el cambio no rompió aislamiento en otro lado.
- Si se encuentra un módulo viejo SIN su prueba de mutación de tenant: agregarla antes de seguir, no dejarla pendiente.
- Preferir pruebas que recorran el camino real (HTTP con withSession(), archivos reales para importaciones) en vez de mocks de capas intermedias.
- Las pruebas corren sobre SQLite en memoria (phpunit.xml) — la base real nunca se toca.
- Las pruebas no dependen del .env: las variables que pueden alterar resultados se fijan con force en phpunit.xml. Toda variable de entorno nueva que afecte una prueba se agrega ahí.
- No correr Pint (ni ningún formateador) sobre todo el proyecto: solo sobre los archivos que se crean o modifican en la tarea, con rutas concretas o --dirty. Nunca reformatear archivos ajenos a la tarea: contamina el diff y puede chocar con el trabajo del otro PC.
- Ante un bug que persiste tras un primer intento de corrección: el siguiente intento DEBE exigir diagnóstico de causa raíz antes de corregir, no otro parche a ciegas.
- Las pruebas no deben depender de la fecha ni de la hora real en que se corren. Si una prueba toca horarios de atención, reservas, recordatorios o vigencias, congela el reloj con Carbon::setTestNow() en un día y una hora conocidos (en los que el negocio de prueba atienda) y restaurarlo al terminar.

## Aislamiento entre negocios: regla sin excepciones

- Un SaaS que mezcla datos entre negocios es un problema legal y de confianza. La seguridad de los datos de los clientes de Mateo no es negociable.
- TODA tarea que toque backend (migración, Model, Service, Controller, middleware, comando, ruta pública o privada) DEBE incluir pruebas de aislamiento entre negocios, aunque el prompt no lo pida. Si el prompt sugiere omitirlas, esta regla prevalece.
- Como mínimo, por cada método o endpoint que lea o escriba datos de un negocio:
  1. El negocio A no puede LEER datos del negocio B.
  2. El negocio A no puede MODIFICAR ni ELIMINAR datos del negocio B adivinando un ID.
  3. Un tenant_id enviado en el cuerpo, en la query o en un archivo importado se IGNORA: manda siempre el de la sesión.
  4. Empleado y super_admin son rechazados donde corresponda.
  5. En endpoints públicos: lista blanca de campos verificada por prueba, sin select('*') ni toArray() del modelo completo.
- Las pruebas recorren el camino real: peticiones HTTP con withSession(), no mocks de capas intermedias.
- Prueba de mutación obligatoria: quitar a propósito el filtro de tenant, confirmar que la prueba falla, restaurar y confirmar que vuelve a pasar. Una prueba de seguridad que pasa a la primera se considera sospechosa hasta que se muta.
- Si al revisar código existente se encuentra un endpoint sin filtro de tenant, reportarlo y corregirlo antes de continuar con la tarea.
- Todo reporte final termina con una línea explícita: "Aislamiento entre negocios: verificado en [lista de pruebas], sin fugas". Si no se puede afirmar con evidencia, decirlo tal cual en vez de omitirlo.
- Una reserva con id_pago_comision no se puede eliminar ni cambiar de servicio, empleado, fecha u hora, ni cambiar de estado. Todo camino nuevo que altere reservas debe respetar esta regla. La única forma de liberarla es anular el pago con SvcComision::anularPago.

## Flujo de Git

- Rama de trabajo: `main`.
- El nombre del remoto NO es fijo: es configuración local de cada clon y no viaja con el repositorio, así que puede ser `origin` en una máquina y otro nombre en otra. Nunca lo asumas — ni por lo que diga este archivo, ni por lo que se haya usado en una sesión anterior, ni porque `origin` sea el nombre habitual.
- Antes de cualquier push, ejecuta `git remote -v` e identifica el remoto cuya URL apunta al repositorio del proyecto. Descarta cualquiera que apunte a `laravel/laravel` (el repo base del framework). Si hay varios candidatos, ninguno claro, o cualquier duda, pregúntale a Mateo antes de empujar.
- Cada sesión de trabajo puede terminar en commit con mensaje descriptivo en español, SIN TILDES (problemas de encoding en la shell de Windows).
- El PUSH solo se hace con confirmación EXPLÍCITA de Mateo en el mensaje — nunca automático, nunca porque "parece un buen momento".
- Nunca reescribir historial ya publicado (--amend/--force sobre commits ya subidos).
- No uses git stash (ni git checkout -- ni git restore sobre archivos con cambios sin commitear) sin preguntar antes a Mateo.

## Al terminar cada tarea, siempre reportar

- Lista de archivos creados/modificados.
- Antes de migrar: confirmar php artisan db:show (Laragon a veces queda apagado).
- Resultado de PHPUnit (todas verdes) — filtro específico y luego suite completa si se tocó un módulo existente.
- Cuando se toquen datos sensibles (multi-tenant, permisos, dinero, imágenes/archivos subidos): confirmación EXPLÍCITA de que el aislamiento se respetó, con evidencia (no "creo que está bien").
- NO hacer commit salvo que el prompt lo pida explícitamente.
