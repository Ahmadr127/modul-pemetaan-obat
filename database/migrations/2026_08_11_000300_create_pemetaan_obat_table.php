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
        Schema::create('pemetaan_obat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obat_generik_id')->constrained('obat_generik')->onDelete('cascade');
            $table->foreignId('obat_brand_id')->constrained('obat_brand')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['obat_generik_id', 'obat_brand_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pemetaan_obat');
    }
};