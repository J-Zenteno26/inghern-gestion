<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo_servicios', function (Blueprint $table) {
            $table
                ->decimal('precio_base', 15, 2)
                ->nullable()
                ->after('descripcion');
            $table
                ->char('moneda_precio', 3)
                ->default('CLP')
                ->after('precio_base');
            $table
                ->string('unidad_precio')
                ->nullable()
                ->after('moneda_precio');
        });
    }

    public function down(): void
    {
        Schema::table('catalogo_servicios', function (Blueprint $table) {
            $table->dropColumn([
                'precio_base',
                'moneda_precio',
                'unidad_precio',
            ]);
        });
    }
};
