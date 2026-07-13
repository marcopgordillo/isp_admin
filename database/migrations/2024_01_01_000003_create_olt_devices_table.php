<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('olt_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('brand', ['huawei', 'zte', 'fiberhome', 'other']);
            $table->string('host');
            $table->string('username');
            $table->string('password');
            $table->integer('port')->default(23); // telnet default
            $table->enum('protocol', ['snmp', 'telnet', 'ssh'])->default('telnet');
            $table->boolean('is_connected')->default(false);
            $table->timestamp('last_sync')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('olt_devices');
    }
};
