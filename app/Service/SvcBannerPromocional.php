<?php

namespace App\Service;

use App\Models\BannerPromocional;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Banners promocionales de la página pública de cada negocio.
 *
 * Las imágenes viven en el disco "public", bajo banners/{tenant_id}/. Esa
 * carpeta por negocio no es decorativa: mantiene separados los archivos de
 * cada tenant, igual que la columna tenant_id separa sus filas.
 *
 * En la base se guarda SOLO la ruta relativa; la URL completa se arma al leer.
 * Si mañana el disco cambia de sitio (o se mueve a S3), no hay que reescribir
 * ninguna fila.
 */
class SvcBannerPromocional
{
    /** Lo que el navegador puede mostrar sin sorpresas. */
    const EXTENSIONES_VALIDAS = ['jpg', 'jpeg', 'png', 'webp'];

    const PESO_MAXIMO_KB = 2048;

    const DISCO = 'public';

    /**
     * Guarda la imagen en el disco y devuelve su ruta relativa.
     *
     * Valida ANTES de mover: un archivo que no pasa el filtro no llega a
     * tocar el disco. El nombre se genera aquí y no se toma del que subió el
     * usuario, que además de poder chocar con otro es texto que él controla.
     *
     * @return string|false La ruta relativa, o false si el archivo no sirve.
     */
    private function guardarImagen($tenantId, UploadedFile $archivo)
    {
        $extension = strtolower($archivo->getClientOriginalExtension());

        if (! in_array($extension, self::EXTENSIONES_VALIDAS, true)) {
            return false;
        }

        // getSize() llega en bytes; el límite se piensa en kilobytes.
        if ($archivo->getSize() > self::PESO_MAXIMO_KB * 1024) {
            return false;
        }

        $nombre = Str::uuid()->toString().'.'.$extension;

        return $archivo->storeAs('banners/'.$tenantId, $nombre, self::DISCO);
    }

    /**
     * Borra del disco un archivo que ya no referencia ningún banner.
     *
     * Se llama solo al reemplazar una imagen: sin esto, cada cambio dejaría
     * el archivo anterior ocupando espacio para siempre.
     */
    private function borrarImagen(?string $rutaRelativa): void
    {
        if (empty($rutaRelativa)) {
            return;
        }

        if (Storage::disk(self::DISCO)->exists($rutaRelativa)) {
            Storage::disk(self::DISCO)->delete($rutaRelativa);
        }
    }

    public function crear($tenantId, $info, UploadedFile $archivoImagen)
    {
        try {
            $rutaImagen = $this->guardarImagen($tenantId, $archivoImagen);

            if ($rutaImagen === false) {
                return false;
            }

            $banner = BannerPromocional::create([
                'tenant_id' => $tenantId,
                'imagen_path' => $rutaImagen,
                'titulo' => $info['titulo'] ?? null,
                'texto' => $info['texto'] ?? null,
                'fecha_inicio' => $info['fecha_inicio'] ?? null,
                'fecha_fin' => $info['fecha_fin'] ?? null,
                'orden' => $info['orden'] ?? 0,
                'usuario_registra' => $info['usuario_registra'] ?? null,
                'fecha_registro' => date('Y-m-d H:i:s'),
                'estado' => 1,
            ]);

            return $banner->id_banner;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    /**
     * Edita el banner y, si llega una imagen nueva, reemplaza la anterior.
     *
     * El archivo viejo se borra DESPUÉS de que el nuevo quedó guardado y la
     * fila actualizada: si algo falla por el camino, el banner se queda con
     * una imagen que sigue existiendo, en vez de apuntar a un hueco.
     */
    public function editar($id, $tenantId, $info, ?UploadedFile $archivoImagen = null): bool
    {
        try {
            $banner = BannerPromocional::where('id_banner', $id)
                ->where('tenant_id', $tenantId)
                ->first();

            if ($banner === null) {
                return false;
            }

            $campos = [
                'titulo' => $info['titulo'] ?? null,
                'texto' => $info['texto'] ?? null,
                'fecha_inicio' => $info['fecha_inicio'] ?? null,
                'fecha_fin' => $info['fecha_fin'] ?? null,
                'orden' => $info['orden'] ?? 0,
            ];

            if (array_key_exists('estado', $info)) {
                $campos['estado'] = (int) $info['estado'];
            }

            $imagenAnterior = null;

            if ($archivoImagen !== null) {
                $rutaNueva = $this->guardarImagen($tenantId, $archivoImagen);

                if ($rutaNueva === false) {
                    return false;
                }

                $imagenAnterior = $banner->imagen_path;
                $campos['imagen_path'] = $rutaNueva;
            }

            BannerPromocional::where('id_banner', $id)
                ->where('tenant_id', $tenantId)
                ->update($campos);

            $this->borrarImagen($imagenAnterior);

            return true;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    /**
     * Baja lógica. El archivo NO se toca a propósito: el banner puede
     * reactivarse desde el modal, y entonces tiene que seguir teniendo imagen.
     */
    public function eliminar($id, $tenantId): bool
    {
        try {
            $query = BannerPromocional::where('id_banner', $id)->where('tenant_id', $tenantId);

            if (! $query->exists()) {
                return false;
            }

            $query->update(['estado' => 0]);

            return true;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    /**
     * Listado del panel de administración.
     *
     * Por defecto solo trae los activos; $incluirInactivos es lo que alimenta
     * el filtro "Mostrar inactivos" de la pestaña, que es la única vía para
     * volver a abrir uno dado de baja y reactivarlo.
     */
    public function listar($tenantId, $incluirInactivos = false)
    {
        try {
            $query = BannerPromocional::select(
                'id_banner',
                'imagen_path',
                'titulo',
                'texto',
                'fecha_inicio',
                'fecha_fin',
                'orden',
                'estado'
            )
                ->where('tenant_id', $tenantId);

            if (! $incluirInactivos) {
                $query->where('estado', 1);
            }

            return $query->orderBy('orden')
                ->orderBy('id_banner')
                ->get()
                ->map(function ($banner) {
                    return [
                        'id_banner' => $banner->id_banner,
                        'imagen_url' => Storage::disk(self::DISCO)->url($banner->imagen_path),
                        'titulo' => $banner->titulo,
                        'texto' => $banner->texto,
                        'fecha_inicio' => $banner->fecha_inicio,
                        'fecha_fin' => $banner->fecha_fin,
                        'orden' => (int) $banner->orden,
                        'estado' => (int) $banner->estado,
                    ];
                })
                ->all();
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }

    /**
     * Los banners que hoy corresponde mostrar en la página pública.
     *
     * ⚠️ ESTE MÉTODO ALIMENTA UNA RESPUESTA QUE VE CUALQUIERA EN INTERNET.
     * Devuelve solo lo que se pinta en el carrusel. Las fechas de vigencia, el
     * estado, el tenant y quién lo registró son datos de gestión del negocio:
     * se usan para DECIDIR qué sale, pero no salen ellos mismos.
     *
     * Vigencia con extremos abiertos: sin fecha_inicio vale desde siempre, sin
     * fecha_fin vale para siempre, y el día del extremo cuenta como vigente.
     */
    public function listarVigentes($tenantId)
    {
        try {
            $hoy = Carbon::today()->toDateString();

            return BannerPromocional::select('imagen_path', 'titulo', 'texto', 'orden')
                ->where('tenant_id', $tenantId)
                ->where('estado', 1)
                ->where(function ($query) use ($hoy) {
                    $query->whereNull('fecha_inicio')->orWhere('fecha_inicio', '<=', $hoy);
                })
                ->where(function ($query) use ($hoy) {
                    $query->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $hoy);
                })
                ->orderBy('orden')
                ->orderBy('id_banner')
                ->get()
                ->map(function ($banner) {
                    return [
                        'imagen_url' => Storage::disk(self::DISCO)->url($banner->imagen_path),
                        'titulo' => $banner->titulo,
                        'texto' => $banner->texto,
                        'orden' => (int) $banner->orden,
                    ];
                })
                ->all();
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return [];
        }
    }
}
