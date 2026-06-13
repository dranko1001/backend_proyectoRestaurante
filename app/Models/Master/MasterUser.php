<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes'])]
class MasterUser extends Authenticatable
{
    use HasApiTokens, Notifiable, TwoFactorAuthenticatable;

    protected $connection = 'master';

    protected $table = 'master_users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'activo' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
