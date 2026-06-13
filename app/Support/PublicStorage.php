<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PublicStorage
{
    /**
     * Ruta relativa dentro del disco public (sin prefijo /storage).
     */
    public static function storeTenantLogo(string $tenantSlug, UploadedFile $file): string
    {
        $slug = strtolower(trim($tenantSlug));

        return $file->store('tenants/'.$slug.'/branding', 'public');
    }

    public static function storePlatformNequiQr(UploadedFile $file): string
    {
        return $file->store('platform/billing', 'public');
    }

    /**
     * Normaliza lo guardado en BD: siempre ruta relativa (ej. tenants/sena/branding/logo.png).
     */
    public static function normalizeStoredPath(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        $value = trim($stored);

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            $path = parse_url($value, PHP_URL_PATH);
            if (is_string($path) && str_contains($path, '/storage/')) {
                $relative = substr($path, strpos($path, '/storage/') + strlen('/storage/'));

                return $relative !== '' ? $relative : null;
            }
        }

        return ltrim($value, '/');
    }

    /**
     * URL pública relativa para el frontend (mismo origen + proxy /storage en Vite).
     */
    public static function publicUrl(?string $stored): ?string
    {
        $path = self::normalizeStoredPath($stored);
        if ($path === null) {
            return null;
        }

        return '/storage/'.$path;
    }

    public static function deleteIfStored(?string $stored): void
    {
        $path = self::normalizeStoredPath($stored);
        if ($path === null) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
