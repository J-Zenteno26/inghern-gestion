<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revision_servicios', function (Blueprint $table) {
            $table->id();
            $table
                ->foreignId('cotizacion_revision_id')
                ->constrained('cotizacion_revisiones')
                ->cascadeOnDelete();
            $table
                ->foreignId('servicio_id')
                ->nullable()
                ->constrained('servicios')
                ->nullOnDelete();
            $table->string('titulo', 180);
            $table->text('descripcion')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->unique(['cotizacion_revision_id', 'orden']);
        });

        Schema::table('partidas_cotizacion', function (Blueprint $table) {
            $table
                ->foreignId('revision_servicio_id')
                ->nullable()
                ->after('cotizacion_revision_id')
                ->constrained('revision_servicios')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('partidas_cotizacion', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revision_servicio_id');
        });
        Schema::dropIfExists('revision_servicios');
    }
};
