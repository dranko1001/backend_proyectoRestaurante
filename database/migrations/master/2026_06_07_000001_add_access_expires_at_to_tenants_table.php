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
            if (! Schema::connection('master')->hasColumn('tenants', 'access_expires_at')) {
                $table->timestamp('access_expires_at')->nullable()->after('onboarding_completed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('master')->table('tenants', function (Blueprint $table) {
            if (Schema::connection('master')->hasColumn('tenants', 'access_expires_at')) {
                $table->dropColumn('access_expires_at');
            }
        });
    }
};
