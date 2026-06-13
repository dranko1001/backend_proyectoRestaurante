<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('reserva', 'motivo_cancelacion')) {
            return;
        }

        Schema::table('reserva', function (Blueprint $table) {
            $table->string('motivo_cancelacion', 500)->nullable()->after('notas');
        });
    }

    public function down(): void
    {
        Schema::table('reserva', function (Blueprint $table) {
            $table->dropColumn('motivo_cancelacion');
        });
    }
};
