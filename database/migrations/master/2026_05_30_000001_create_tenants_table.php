<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'master';

    public function up(): void
    {
        Schema::connection('master')->create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 63)->unique();
            $table->string('db_name', 64)->unique();
            $table->string('contact_email', 190);
            $table->string('nombre_comercial', 160)->nullable();
            $table->enum('status', ['pending', 'provisioning', 'active', 'failed', 'suspended'])->default('pending');
            $table->text('provision_error')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('master')->dropIfExists('tenants');
    }
};
