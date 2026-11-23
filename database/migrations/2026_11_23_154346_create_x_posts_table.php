<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Posts on X (Twitter) an admin chose to show on the homepage, by link. */
    public function up(): void
    {
        Schema::create('x_posts', function (Blueprint $table) {
            $table->id();
            // X's post id; it grows with time, so it also orders posts newest first.
            $table->unsignedBigInteger('status_id')->unique();
            $table->string('handle', 15);
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_posts');
    }
};
