<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Calls on the campaign phone line (Africa's Talking): calls the command centre places
     * (it rings the operator, then connects the contact) and calls people make to the line.
     */
    public function up(): void
    {
        Schema::create('voice_calls', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 100)->nullable()->unique();
            $table->string('direction', 10); // inbound | outbound
            $table->string('status', 20)->index(); // ringing, connected, completed, missed, voicemail, failed
            // The person outside the team: who we called, or who called us.
            $table->string('contact_phone', 20)->index();
            $table->foreignId('contact_user_id')->nullable()->constrained('users')->nullOnDelete();
            // The operator: who placed the call, and which phone was rung for them.
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('operator_phone', 20)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('recording_url', 500)->nullable();
            $table->decimal('cost', 10, 4)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('hangup_cause', 100)->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            // A missed call someone in the command centre has dealt with.
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_calls');
    }
};
