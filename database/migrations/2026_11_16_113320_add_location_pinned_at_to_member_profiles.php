<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Members can pin (and re-pin) their location on the members map, at sign-up or later
     * from their dashboard. `signup_lat`/`signup_lng` hold the pin; this records when it was set.
     */
    public function up(): void
    {
        Schema::table('member_profiles', function (Blueprint $table) {
            $table->timestamp('location_pinned_at')->nullable();
        });

        DB::update('UPDATE member_profiles SET location_pinned_at = created_at WHERE signup_lat IS NOT NULL AND signup_lng IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('member_profiles', fn (Blueprint $table) => $table->dropColumn('location_pinned_at'));
    }
};
