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
        Schema::create('pago_factura', function (Blueprint $table) {
            $table->id();
            $table
                ->foreignId('pago_id')
                ->constrained('pagos')
                ->cascadeOnDelete();
            $table
                ->foreignId('factura_id')
                ->constrained('facturas')
                ->cascadeOnDelete();
            $table->decimal('monto_asignado', 15, 2);
            $table->timestamps();

            $table->unique(['pago_id', 'factura_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pago_factura');
    }
};
