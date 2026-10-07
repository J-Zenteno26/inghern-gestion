<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_historial', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('registrable');
            $table
                ->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('evento', 80);
            $table->string('descripcion');
            $table->json('cambios')->nullable();
            $table->timestamps();
            $table->index(['created_at', 'evento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_historial');
    }
};
