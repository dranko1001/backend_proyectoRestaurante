<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('usuario', 'google_id')) {
            Schema::table('usuario', function (Blueprint $table) {
                $table->string('google_id', 64)->nullable()->unique()->after('correo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('usuario', 'google_id')) {
            Schema::table('usuario', function (Blueprint $table) {
                $table->dropUnique(['google_id']);
                $table->dropColumn('google_id');
            });
        }
    }
};
