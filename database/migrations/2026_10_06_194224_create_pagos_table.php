<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->date('fecha_pago');
            $table->decimal('monto_total', 15, 2);
            $table->string('medio_pago', 40);
            $table->string('referencia', 120)->nullable();
            $table->text('observacion')->nullable();
            $table
                ->foreignId('usuario_creador_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();

            $table->index('fecha_pago');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
