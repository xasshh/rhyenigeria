<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->geometry('boundary', subtype: 'multipolygon', srid: 4326)->nullable();
            $table->timestamps();

            $table->spatialIndex('boundary');
        });

        Schema::create('lgas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->geometry('boundary', subtype: 'multipolygon', srid: 4326)->nullable();
            $table->timestamps();

            $table->unique(['state_id', 'code']);
            $table->spatialIndex('boundary');
        });

        Schema::create('wards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lga_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->geometry('boundary', subtype: 'multipolygon', srid: 4326)->nullable();
            $table->timestamps();

            $table->unique(['lga_id', 'code']);
            $table->spatialIndex('boundary');
        });

        Schema::create('polling_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ward_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->geometry('location', subtype: 'point', srid: 4326)->nullable();
            $table->geometry('boundary', subtype: 'multipolygon', srid: 4326)->nullable();
            $table->timestamps();

            $table->unique(['ward_id', 'code']);
            $table->spatialIndex('location');
            $table->spatialIndex('boundary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polling_units');
        Schema::dropIfExists('wards');
        Schema::dropIfExists('lgas');
        Schema::dropIfExists('states');
    }
};
