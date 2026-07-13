<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('olt_device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('serial_number')->unique();
            $table->string('pon_port')->nullable();
            $table->integer('ont_index')->nullable();
            $table->string('description')->nullable();
            $table->enum('status', ['active', 'inactive', 'blocked'])->default('inactive');
            $table->string('optical_power')->nullable(); // e.g., \"-22.5 dBm\"
            $table->timestamp('last_sync')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onus');
    }
};
