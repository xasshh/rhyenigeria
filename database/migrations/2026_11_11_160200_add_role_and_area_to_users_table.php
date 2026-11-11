<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->default('field_agent')->index();
            $table->string('phone', 32)->nullable();
            $table->boolean('is_active')->default(true);

            // The area a coordinator is scoped to, or a field agent is assigned to.
            // The deepest level that is set is the effective one; parents are kept
            // filled in so that scoping is a single indexed comparison.
            $table->foreignId('state_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lga_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamp('tracking_consent_accepted_at')->nullable();
            $table->string('tracking_consent_version', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('state_id');
            $table->dropConstrainedForeignId('lga_id');
            $table->dropConstrainedForeignId('ward_id');
            $table->dropColumn(['role', 'phone', 'is_active', 'tracking_consent_accepted_at', 'tracking_consent_version']);
        });
    }
};
