<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Members imported in bulk from a group's list (e.g. "Team Kaka") have only a name and a phone number.
     * They sign in with their phone, get a temporary password by SMS, and complete the rest of their profile
     * (gender, age range, consent) on first sign-in, so those columns may be empty until then.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            // Set when a temporary password is sent by SMS; cleared once the member picks their own.
            $table->boolean('must_change_password')->default(false);
        });

        Schema::table('member_profiles', function (Blueprint $table) {
            $table->string('gender', 16)->nullable()->change();
            $table->string('age_range', 16)->nullable()->change();
            $table->timestamp('consent_accepted_at')->nullable()->change();
            $table->string('consent_version', 32)->nullable()->change();

            // The list a member was imported from; NULL = signed up on the website.
            $table->string('import_source', 64)->nullable()->index();
            $table->timestamp('imported_at')->nullable();
            // When the sign-in SMS (phone + temporary password) was last sent.
            $table->timestamp('credentials_sent_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('member_profiles', function (Blueprint $table) {
            $table->dropColumn(['import_source', 'imported_at', 'credentials_sent_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
