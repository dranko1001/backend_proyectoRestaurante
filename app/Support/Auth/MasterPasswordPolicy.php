<?php

namespace App\Support\Auth;

class MasterPasswordPolicy
{
    public static function isStrong(string $password): bool
    {
        return strlen($password) >= 12
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[0-9]/', $password) === 1;
    }

    public static function assertForEnvironment(string $password): void
    {
        if (app()->environment('local', 'testing')) {
            return;
        }

        if (! self::isStrong($password)) {
            throw new \InvalidArgumentException(
                'MASTER_ADMIN_PASSWORD debe tener al menos 12 caracteres e incluir mayúsculas, minúsculas y números.'
            );
        }
    }

    public static function isKnownWeakDefault(string $password): bool
    {
        return in_array($password, ['master123', 'password', '12345678', 'admin123'], true);
    }
}
