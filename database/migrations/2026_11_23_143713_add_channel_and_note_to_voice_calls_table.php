<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Calls made from an operator's own phone, and calls into an ordinary hotline that someone
     * typed in, are logged too (channel "phone"); "line" calls go through Africa's Talking.
     */
    public function up(): void
    {
        Schema::table('voice_calls', function (Blueprint $table) {
            $table->string('channel', 10)->default('line')->after('direction');
            $table->string('note', 255)->nullable()->after('hangup_cause');
        });
    }

    public function down(): void
    {
        Schema::table('voice_calls', function (Blueprint $table) {
            $table->dropColumn(['channel', 'note']);
        });
    }
};
