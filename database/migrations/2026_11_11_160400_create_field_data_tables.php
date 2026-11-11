<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A form is a named definition of the questions asked in the field. The
        // field list lives in JSON so forms can be defined and changed later
        // without a schema change.
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->jsonb('fields')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('form_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('polling_unit_id')->constrained()->restrictOnDelete();
            // Copied from the polling unit so that area scoping needs no joins.
            $table->foreignId('ward_id')->constrained()->restrictOnDelete();
            $table->foreignId('lga_id')->constrained()->restrictOnDelete();
            $table->foreignId('state_id')->constrained()->restrictOnDelete();
            $table->geometry('location', subtype: 'point', srid: 4326);
            $table->float('accuracy')->nullable();
            $table->timestamp('collected_at');
            $table->jsonb('answers');
            $table->timestamps();

            $table->index(['user_id', 'collected_at']);
            $table->index(['polling_unit_id', 'collected_at']);
            $table->index('collected_at');
            $table->spatialIndex('location');
        });

        Schema::create('submission_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 32);
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_photos');
        Schema::dropIfExists('submissions');
        Schema::dropIfExists('forms');
    }
};
