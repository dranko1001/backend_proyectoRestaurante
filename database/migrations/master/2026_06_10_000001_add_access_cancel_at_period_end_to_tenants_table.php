<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'master';

    public function up(): void
    {
        Schema::connection('master')->table('tenants', function (Blueprint $table) {
            if (! Schema::connection('master')->hasColumn('tenants', 'access_cancel_at_period_end')) {
                $table->boolean('access_cancel_at_period_end')->default(false)->after('access_expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('master')->table('tenants', function (Blueprint $table) {
            if (Schema::connection('master')->hasColumn('tenants', 'access_cancel_at_period_end')) {
                $table->dropColumn('access_cancel_at_period_end');
            }
        });
    }
};
