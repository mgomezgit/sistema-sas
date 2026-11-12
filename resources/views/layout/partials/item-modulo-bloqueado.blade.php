{{--
    Ítem del sidebar para un módulo de pago que el negocio NO tiene activo:
    el mismo enlace del menú, pero con un candado y apuntando a la pantalla de
    "no disponible" del módulo en vez de a su pantalla real. Reutilizable por
    cualquier módulo (Reportes, Productos, Comisiones...).

    Parámetros:
      $clave  → clave del módulo (reportes, inventario, comisiones...).
      $titulo → texto del ítem.
      $icono  → clase del ícono Bootstrap del módulo.

    Esconder o bloquear aquí es solo experiencia de usuario: la seguridad real
    la pone verificar.modulo sobre las rutas. El candado solo avisa.
--}}
<a
    href="{{ url('backoffice/modulo/'.$clave) }}"
    class="menu-item menu-item-bloqueado @if (request()->is('backoffice/modulo/'.$clave)) active @endif"
    title="Disponible como módulo de pago"
>
    <i class="bi {{ $icono }}"></i>
    <span>{{ $titulo }}</span>
    <i class="bi bi-lock-fill icono-candado-menu" aria-hidden="true"></i>
    <span class="visually-hidden">(módulo no disponible)</span>
</a>
