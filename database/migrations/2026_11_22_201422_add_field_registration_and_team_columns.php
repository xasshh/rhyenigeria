<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * registered_by_user_id: the canvasser who signed this member up in person (from the field page) and
     * witnessed their consent. NULL = the member signed up themselves or was imported.
     * added_by_user_id: the coordinator or admin who created this account from the Team page.
     */
    public function up(): void
    {
        Schema::table('member_profiles', function (Blueprint $table) {
            $table->foreignId('registered_by_user_id')->nullable()->after('referred_by_user_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('added_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('member_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('registered_by_user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('added_by_user_id');
        });
    }
};
