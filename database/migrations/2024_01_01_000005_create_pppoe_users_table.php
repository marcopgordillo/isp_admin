<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pppoe_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mikrotik_server_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('username')->unique();
            $table->string('password');
            $table->string('profile')->default('default');
            $table->string('ip_address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_blocked')->default(false);
            $table->timestamp('last_connection')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'is_blocked']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pppoe_users');
    }
};
