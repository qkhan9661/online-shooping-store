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
        Schema::create('fit_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('garment_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('completed');
            $table->string('recommended_size', 16)->nullable();
            $table->string('fit_notes')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->json('measurement_snapshot')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'garment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fit_recommendations');
    }
};
