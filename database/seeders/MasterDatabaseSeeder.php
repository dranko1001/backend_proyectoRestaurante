<?php

namespace Database\Seeders;

use App\Models\Master\MasterUser;
use App\Support\Auth\MasterPasswordPolicy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class MasterDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'email' => strtolower(trim((string) env('MASTER_ADMIN_EMAIL', 'master@local.test'))),
                'name' => (string) env('MASTER_ADMIN_NAME', 'Master Admin'),
                'password' => (string) env('MASTER_ADMIN_PASSWORD', 'master123'),
            ],
            [
                'email' => strtolower(trim((string) env('MASTER_SECOND_EMAIL', 'cesarrodas113@gmail.com'))),
                'name' => (string) env('MASTER_SECOND_NAME', 'Cesar Rodas'),
                'password' => (string) env('MASTER_SECOND_PASSWORD', 'admin123'),
            ],
        ];

        foreach ($accounts as $account) {
            if ($account['email'] === '') {
                continue;
            }

            MasterPasswordPolicy::assertForEnvironment($account['password']);

            if (app()->environment('local', 'testing') && MasterPasswordPolicy::isKnownWeakDefault($account['password'])) {
                Log::warning('Master seed password uses a known weak default.', [
                    'email' => $account['email'],
                ]);
            }

            MasterUser::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => $account['password'],
                    'activo' => true,
                ],
            );
        }
    }
}
