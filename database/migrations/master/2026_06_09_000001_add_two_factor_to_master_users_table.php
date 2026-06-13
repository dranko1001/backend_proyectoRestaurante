<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'master';

    public function up(): void
    {
        Schema::connection('master')->table('master_users', function (Blueprint $table) {
            if (! Schema::connection('master')->hasColumn('master_users', 'two_factor_secret')) {
                $table->text('two_factor_secret')->nullable()->after('password');
            }
            if (! Schema::connection('master')->hasColumn('master_users', 'two_factor_recovery_codes')) {
                $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            }
            if (! Schema::connection('master')->hasColumn('master_users', 'two_factor_confirmed_at')) {
                $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('master')->table('master_users', function (Blueprint $table) {
            $cols = ['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'];
            foreach ($cols as $col) {
                if (Schema::connection('master')->hasColumn('master_users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
