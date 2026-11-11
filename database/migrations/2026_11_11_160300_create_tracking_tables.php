<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'started_at']);
        });

        // An agent can have at most one open shift.
        DB::statement('CREATE UNIQUE INDEX shifts_one_active_per_user ON shifts (user_id) WHERE ended_at IS NULL');

        Schema::create('location_points', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->geometry('location', subtype: 'point', srid: 4326);
            $table->float('accuracy')->nullable();
            $table->timestamp('recorded_at');
            $table->foreignId('ward_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('nearest_polling_unit_id')->nullable()->constrained('polling_units')->nullOnDelete();
            $table->float('nearest_polling_unit_distance_m')->nullable();
            $table->timestamp('context_resolved_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // "This agent's points in this time window".
            $table->index(['user_id', 'recorded_at']);
            // Retention sweep.
            $table->index('recorded_at');
            $table->spatialIndex('location');
        });

        // One row per agent, so the live map never scans the history table.
        Schema::create('agent_positions', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('location_point_id')->nullable()->constrained()->nullOnDelete();
            $table->geometry('location', subtype: 'point', srid: 4326);
            $table->float('accuracy')->nullable();
            $table->timestamp('recorded_at');
            $table->foreignId('ward_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('nearest_polling_unit_id')->nullable()->constrained('polling_units')->nullOnDelete();
            $table->float('nearest_polling_unit_distance_m')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->spatialIndex('location');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_positions');
        Schema::dropIfExists('location_points');
        Schema::dropIfExists('shifts');
    }
};
