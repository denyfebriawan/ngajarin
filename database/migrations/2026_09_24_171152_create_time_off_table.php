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
        Schema::create('time_off', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('user_id');
            // Exact moments (stored in UTC): leave happens on real calendar dates, unlike weekly
            // hours. The end is exclusive: 24-26 Dec ends at 27 Dec 00:00 local time.
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'ends_at']);

            // The teacher must be a member of the same workspace; leaving it removes their leave.
            $table->foreign(['tenant_id', 'user_id'])
                ->references(['tenant_id', 'user_id'])->on('tenant_user')
                ->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE time_off ADD CONSTRAINT time_off_order_check CHECK (ends_at > starts_at)');

        // A teacher's leave periods never overlap. tstzrange is half-open [start, end), so leave
        // ending at 27 Dec 00:00 and leave starting then do not overlap. This is the same shape as
        // feature 3's no-double-booking constraint.
        DB::statement(<<<'SQL'
            ALTER TABLE time_off ADD CONSTRAINT time_off_no_overlap
            EXCLUDE USING gist (
                tenant_id WITH =,
                user_id WITH =,
                tstzrange(starts_at, ends_at) WITH &&
            )
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_off');
    }
};
