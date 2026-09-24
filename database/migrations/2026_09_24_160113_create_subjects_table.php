<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_minutes');
            // Whole rupiah: IDR has no smaller unit in practice, and integers avoid rounding errors.
            $table->unsignedBigInteger('price');
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        // Postgres has no unsigned types, so the database enforces the ranges itself.
        DB::statement('ALTER TABLE subjects ADD CONSTRAINT subjects_duration_check CHECK (duration_minutes BETWEEN 15 AND 480)');
        DB::statement('ALTER TABLE subjects ADD CONSTRAINT subjects_price_check CHECK (price >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
