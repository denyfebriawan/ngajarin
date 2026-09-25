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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id');
            $table->foreignId('teacher_id');
            $table->foreignId('student_id');
            // Exact moments, stored in UTC. The end is exclusive: a 10:00-11:00 lesson frees 11:00.
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->enum('status', ['confirmed', 'cancelled'])->default('confirmed');
            // The subject's price when booked, so later price changes never rewrite history.
            $table->unsignedBigInteger('price');
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestamps();

            // The subject belongs to this workspace. No cascade: a subject with lessons can't be
            // deleted (the default NO ACTION is checked at the end of the statement, so deleting
            // the whole workspace, which removes its bookings too, still works).
            $table->foreign(['tenant_id', 'subject_id'])
                ->references(['tenant_id', 'id'])->on('subjects');
            // Teacher and student are members of this workspace; deleting a membership (e.g. an
            // account being deleted) removes that person's bookings here.
            $table->foreign(['tenant_id', 'teacher_id'])
                ->references(['tenant_id', 'user_id'])->on('tenant_user')
                ->cascadeOnDelete();
            $table->foreign(['tenant_id', 'student_id'])
                ->references(['tenant_id', 'user_id'])->on('tenant_user')
                ->cascadeOnDelete();

            $table->index(['tenant_id', 'starts_at']);
        });

        // The lesson as one range value, computed by Postgres from starts_at and ends_at, so it
        // can never disagree with them. '[)' makes it half-open, like every range in this app.
        DB::statement("ALTER TABLE bookings ADD COLUMN period tstzrange GENERATED ALWAYS AS (tstzrange(starts_at, ends_at, '[)')) STORED");

        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_order_check CHECK (ends_at > starts_at)');
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_price_check CHECK (price >= 0)');
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_student_is_not_teacher CHECK (student_id <> teacher_id)');

        // No double-booking, enforced by the database. Two requests for the same slot can both
        // pass every check in PHP; only one INSERT can satisfy these. Cancelled lessons don't
        // count (WHERE ...), so cancelling frees the time. There is deliberately no tenant_id:
        // a person teaching or studying in two workspaces still can't be in two lessons at once.
        DB::statement(<<<'SQL'
            ALTER TABLE bookings ADD CONSTRAINT bookings_teacher_no_overlap
            EXCLUDE USING gist (teacher_id WITH =, period WITH &&)
            WHERE (status = 'confirmed')
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE bookings ADD CONSTRAINT bookings_student_no_overlap
            EXCLUDE USING gist (student_id WITH =, period WITH &&)
            WHERE (status = 'confirmed')
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
