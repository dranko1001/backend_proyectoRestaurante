<?php

namespace App\Support\Tenancy;

class TenantSlug
{
    public static function normalize(string $input): string
    {
        $s = mb_strtolower(trim($input), 'UTF-8');
        $s = str_replace('ñ', 'n', $s);
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        $s = preg_replace('/[^a-z0-9-]+/', '-', $s) ?? '';
        $s = preg_replace('/-+/', '-', $s) ?? '';
        $s = trim($s, '-');

        return $s;
    }

    public static function validationMessage(string $input): ?string
    {
        $raw = trim($input);

        if ($raw === '') {
            return 'El subdominio es obligatorio.';
        }

        if (preg_match('/\p{Extended_Pictographic}/u', $raw)) {
            return 'No se permiten emojis en el subdominio.';
        }

        if (preg_match('/\s/u', $raw)) {
            return 'No uses espacios; separa con guiones (ej. mi-restaurante).';
        }

        if (preg_match('/[^a-zA-Z0-9ñÑáéíóúÁÉÍÓÚüÜ-]/u', $raw)) {
            return 'Solo letras (incluida ñ), números y guiones. Sin símbolos como @, # o %.';
        }

        if (str_starts_with($raw, '-') || str_ends_with($raw, '-') || str_contains($raw, '--')) {
            return 'Los guiones no pueden ir al inicio, al final ni repetidos.';
        }

        $normalized = self::normalize($raw);

        if (strlen($normalized) < 2) {
            return 'El subdominio debe tener al menos 2 caracteres válidos.';
        }

        if (strlen($normalized) > 40) {
            return 'El subdominio no puede superar 40 caracteres.';
        }

        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $normalized)) {
            return 'Formato inválido. Usa solo letras minúsculas, números y guiones.';
        }

        return null;
    }
}
