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
            if (! Schema::connection('master')->hasColumn('tenants', 'license_months')) {
                $table->unsignedTinyInteger('license_months')->nullable()->after('access_cancel_at_period_end');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('master')->table('tenants', function (Blueprint $table) {
            if (Schema::connection('master')->hasColumn('tenants', 'license_months')) {
                $table->dropColumn('license_months');
            }
        });
    }
};
