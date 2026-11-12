<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Members sign in with their email, but the phone number identifies a
        // person too, so it is kept unique (NULLs are allowed many times).
        Schema::table('users', function (Blueprint $table) {
            $table->unique('phone');
        });

        Schema::table('states', function (Blueprint $table) {
            // Set by a super admin; shown on the welcome screen and member dashboard.
            $table->string('whatsapp_group_url', 255)->nullable();
        });

        Schema::create('member_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('member_number', 32)->unique();
            // Put in the QR code; never the member number or ID.
            $table->string('verify_token', 64)->unique();

            $table->string('gender', 16)->index();
            $table->string('age_range', 16)->index();
            $table->string('occupation', 120)->nullable();
            $table->foreignId('polling_unit_id')->nullable()->constrained()->nullOnDelete();

            $table->boolean('has_pvc')->default(false)->index();
            // Encrypted by the model (Laravel `encrypted` cast), so stored as text.
            $table->text('vin')->nullable();
            $table->text('nin')->nullable();

            $table->string('referral_code', 32)->unique();
            $table->foreignId('referred_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // NULL = never applied. See App\Enums\CanvasserStatus.
            $table->string('canvasser_status', 16)->nullable()->index();
            $table->jsonb('canvasser_roles')->nullable();
            $table->string('whatsapp_phone', 32)->nullable();
            $table->timestamp('canvasser_applied_at')->nullable();
            $table->foreignId('canvasser_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('canvasser_reviewed_at')->nullable();
            $table->text('canvasser_review_note')->nullable();

            $table->decimal('signup_lat', 9, 6)->nullable();
            $table->decimal('signup_lng', 9, 6)->nullable();

            $table->timestamp('consent_accepted_at');
            $table->string('consent_version', 32);

            // NULL = not answered yet.
            $table->boolean('empowerment_benefited')->nullable();

            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('starts_at')->index();
            $table->string('venue')->nullable();
            // NULL = national.
            $table->foreignId('state_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('news_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('source_label', 64)->nullable();
            // Both NULL = national; state only = whole state; ward = that ward only.
            $table->foreignId('state_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('published_at')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('empowerment_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('programme');
            $table->date('received_on')->nullable();
            $table->text('what_received');
            // On the private "local" disk; served only through a scoped controller.
            $table->string('photo_path')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        DB::statement('CREATE INDEX users_role_state_idx ON users (role, state_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_role_state_idx');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('empowerment_reports');
        Schema::dropIfExists('news_posts');
        Schema::dropIfExists('events');
        Schema::dropIfExists('member_profiles');

        Schema::table('states', fn (Blueprint $table) => $table->dropColumn('whatsapp_group_url'));
        Schema::table('users', fn (Blueprint $table) => $table->dropUnique(['phone']));
    }
};
