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
        // A foreign key must point at a unique column set, so make (tenant_id, id) one.
        // `id` alone is already unique; this only lets the pair be referenced below.
        Schema::table('subjects', function (Blueprint $table) {
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('subject_teacher', function (Blueprint $table) {
            $table->foreignId('tenant_id');
            $table->foreignId('subject_id');
            $table->foreignId('user_id')->index();

            $table->primary(['subject_id', 'user_id']);

            // Both keys share tenant_id, so a subject can only be linked to a member of the same
            // workspace. Deleting the subject, or removing the member, removes the link.
            $table->foreign(['tenant_id', 'subject_id'])
                ->references(['tenant_id', 'id'])->on('subjects')
                ->cascadeOnDelete();
            $table->foreign(['tenant_id', 'user_id'])
                ->references(['tenant_id', 'user_id'])->on('tenant_user')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subject_teacher');

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'id']);
        });
    }
};
