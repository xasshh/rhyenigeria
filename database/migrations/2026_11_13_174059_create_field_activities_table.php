<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Work a canvasser logs while on shift: a door-to-door visit, an event check-in or a report.
        // Each is stamped with the phone's GPS position and time, and scoped to the canvasser's state.
        Schema::create('field_activities', function (Blueprint $table) {
            $table->id();
            // Generated on the phone, so a retried upload is stored once.
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('state_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 24)->index();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('households')->nullable();
            $table->string('outcome', 24)->nullable();
            $table->text('note')->nullable();
            $table->geometry('location', subtype: 'point', srid: 4326);
            $table->float('accuracy')->nullable();
            $table->foreignId('ward_id')->nullable()->constrained()->nullOnDelete();
            // Private disk; served only through a scoped route.
            $table->string('photo_path')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['state_id', 'recorded_at']);
            $table->index(['user_id', 'recorded_at']);
            $table->spatialIndex('location');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_activities');
    }
};
