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
        // Lets a GiST index compare plain values with `=` (needed for user_id and weekday below).
        // Feature 3's no-double-booking constraint needs it too.
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::create('availability_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('user_id');
            // ISO weekday: 1 = Monday ... 7 = Sunday.
            $table->unsignedSmallInteger('weekday');
            // Local wall-clock times in the tenant's timezone, e.g. 09:00 to 12:00.
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();

            // The teacher must be a member of the same workspace; leaving it removes their hours.
            $table->foreign(['tenant_id', 'user_id'])
                ->references(['tenant_id', 'user_id'])->on('tenant_user')
                ->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE availability_rules ADD CONSTRAINT availability_rules_weekday_check CHECK (weekday BETWEEN 1 AND 7)');
        DB::statement('ALTER TABLE availability_rules ADD CONSTRAINT availability_rules_order_check CHECK (ends_at > starts_at)');

        // No two blocks of one teacher may overlap on the same weekday in the same workspace.
        // Postgres has no range type for plain times, so each block is placed on a fixed dummy date
        // to form a timestamp range. Ranges are half-open [start, end), so 09:00-12:00 and
        // 12:00-15:00 touch without overlapping.
        DB::statement(<<<'SQL'
            ALTER TABLE availability_rules ADD CONSTRAINT availability_rules_no_overlap
            EXCLUDE USING gist (
                tenant_id WITH =,
                user_id WITH =,
                weekday WITH =,
                tsrange(DATE '2000-01-01' + starts_at, DATE '2000-01-01' + ends_at) WITH &&
            )
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // btree_gist stays installed: other tables will depend on it.
        Schema::dropIfExists('availability_rules');
    }
};
