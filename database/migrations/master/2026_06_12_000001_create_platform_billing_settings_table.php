<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'master';

    public function up(): void
    {
        Schema::connection('master')->create('platform_billing_settings', function (Blueprint $table) {
            $table->id();
            $table->string('nequi_key', 40)->nullable();
            $table->string('nequi_qr_path')->nullable();
            $table->text('payment_instructions')->nullable();
            $table->unsignedInteger('price_per_month_cop')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('master')->dropIfExists('platform_billing_settings');
    }
};
