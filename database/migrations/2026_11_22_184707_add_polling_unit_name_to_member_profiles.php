<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A polling unit the member typed because it is not in our list (yet). Used only when polling_unit_id is empty. */
    public function up(): void
    {
        Schema::table('member_profiles', function (Blueprint $table) {
            $table->string('polling_unit_name', 120)->nullable()->after('polling_unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('member_profiles', function (Blueprint $table) {
            $table->dropColumn('polling_unit_name');
        });
    }
};
