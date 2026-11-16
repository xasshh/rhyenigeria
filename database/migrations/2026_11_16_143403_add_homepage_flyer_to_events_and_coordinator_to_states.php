<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Events can carry a flyer and appear on the public homepage (upcoming events list, and a slide when they
     * have a flyer). States carry their coordinator's name and photo for the public "WhatsApp groups" page.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('is_public')->default(true);
            // Public disk: the original upload and a 1600 px WebP copy.
            $table->string('flyer_path')->nullable();
            $table->string('flyer_original_path')->nullable();
        });

        Schema::table('states', function (Blueprint $table) {
            $table->string('coordinator_name', 120)->nullable();
            $table->string('coordinator_photo_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn(['is_public', 'flyer_path', 'flyer_original_path']));
        Schema::table('states', fn (Blueprint $table) => $table->dropColumn(['coordinator_name', 'coordinator_photo_path']));
    }
};
