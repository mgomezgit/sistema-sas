# Distrito 21 / Sistema SaaS de gestión de reservas — Reglas del proyecto

Este archivo lo lee Claude Code automáticamente al abrir el proyecto. Contiene las reglas de proceso y arquitectura que NO se pueden inferir solo leyendo el código. Si algo aquí contradice lo que ves en el código, pregúntale a Mateo antes de asumir cuál es la fuente de verdad.

## El proyecto en una frase

Plataforma SaaS de gestión de reservas/citas multi-rubro (spa, y a futuro hotel/finca), modelo freemium: núcleo gratis, add-ons de pago activables por negocio.

## Stack (no cambiar sin aprobación explícita)

- Backend: Laravel 13, PHP 8.4, MySQL 8.4 vía Laragon en Windows.
- Frontend: Bootstrap 5 + jQuery + Axios (vía axiosSipleInterno()) + DataTables + SweetAlert2 + Shepherd.js + FullCalendar (Standard/MIT, NUNCA el plugin Premium) + Chart.js + Bootstrap Icons. Todo por CDN, sin build tools (nada de Vite/Webpack/npm). JAMÁS Tailwind. JAMÁS frameworks JS (React/Vue/Angular).
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
- Las reservas creadas desde ahí son SOLICITUDES, no citas confirmadas: nacen con estado = 'pendiente', id_empleado = null, origen = 'publico'. NO disparan el correo de "reserva confirmada" al cliente (ese correo vive únicamente en ReservaController::crear(), no en el Service — ver crearSolicitudPublica() para el patrón correcto de crear sin ese efecto secundario). El admin las confirma y asigna empleado desde el modal de edición normal, que sí dispara el correo al cambiar el estado.
- Throttle + campo honeypot en el endpoint de agendar. CSRF excluido solo en esa ruta (no hay sesión que proteger; el throttle+honeypot son la protección real).

## Seguridad y autenticación

- Sesión manual de Laravel (session()), NUNCA Auth:: nativo ni middleware auth.
- Claves de sesión: id_usuario, usuario, nombre_usuario, email, tenant_id, id_rol, id_empleado, rubro_negocio, nombre_negocio_sesion, modo_tema, color_acento, app_sesion, version_sesion.
- version_sesion: copia, al iniciar sesión, de usuarios.version_sesion. VerificarSesion la compara contra la de la base en cada petición; si no coincide, corta la sesión (mensaje "La clave de tu cuenta cambió..."). Es el mecanismo para cerrar TODAS las sesiones abiertas de una cuenta cuando su clave cambia (recuperación de clave, o un admin editando la clave de un usuario) — el login es manual y sessions no sabe a qué usuario pertenece cada sesión, así que no hay otra forma de invalidarlas.
- Contraseñas con Hash::make()/Hash::check(), nunca texto plano.
- Middleware VerificarSesion (sesion.activa) y RestringirEmpleado (restringir.empleado) bloquean TANTO vistas backoffice/* COMO endpoints request/* — nunca solo la pantalla.
- Email y usuario (login) son ÚNICOS GLOBALMENTE (no por tenant) — es un requisito del login de pantalla única, no una opción de diseño. Un usuario INACTIVO libera su correo/usuario para reutilizarse (columnas generadas email_activo_unico/ usuario_activo_unico con índice único condicional a nivel de base de datos, no solo validación de código).
- Roles: admin (dueño de negocio), empleado (solo sus citas), super_admin (tenant_id null, no gestiona datos operativos de negocios individuales).

## Pruebas automatizadas (OBLIGATORIO, sin excepción)

- Módulo NUEVO: pruebas PHPUnit completas, incluyendo aislamiento de tenant con su prueba de mutación (romper el filtro a propósito, confirmar que la prueba lo detecta, restaurar, confirmar que vuelve a pasar — documentar el proceso en el test).
- Módulo EXISTENTE que se modifica: correr la SUITE COMPLETA (no solo la del módulo tocado), para confirmar que el cambio no rompió aislamiento en otro lado.
- Si se encuentra un módulo viejo SIN su prueba de mutación de tenant: agregarla antes de seguir, no dejarla pendiente.
- Preferir pruebas que recorran el camino real (HTTP con withSession(), archivos reales para importaciones) en vez de mocks de capas intermedias.
- Las pruebas corren sobre SQLite en memoria (phpunit.xml) — la base real nunca se toca.
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

## Al terminar cada tarea, siempre reportar

- Lista de archivos creados/modificados.
- Antes de migrar: confirmar php artisan db:show (Laragon a veces queda apagado).
- Resultado de PHPUnit (todas verdes) — filtro específico y luego suite completa si se tocó un módulo existente.
- Cuando se toquen datos sensibles (multi-tenant, permisos, dinero, imágenes/archivos subidos): confirmación EXPLÍCITA de que el aislamiento se respetó, con evidencia (no "creo que está bien").
- NO hacer commit salvo que el prompt lo pida explícitamente.
