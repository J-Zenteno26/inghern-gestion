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
        Schema::create('documento_vinculos', function (Blueprint $table) {
            $table->id();
            $table
                ->foreignId('documento_id')
                ->constrained('documentos')
                ->cascadeOnDelete();
            $table->morphs('vinculable');
            $table->timestamps();

            $table->unique(
                ['documento_id', 'vinculable_type', 'vinculable_id'],
                'documento_vinculos_unico',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documento_vinculos');
    }
};
