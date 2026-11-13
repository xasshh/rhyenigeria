<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** The President's slide uses a rectangular crop so every slide image has the same 4:5 frame. */
    public function up(): void
    {
        DB::table('hero_slides')->where('image_path', 'images/hero-portrait.webp')
            ->update(['image_path' => 'images/slides/tinubu-portrait.webp', 'image_alt' => 'Portrait of President Bola Ahmed Tinubu', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('hero_slides')->where('image_path', 'images/slides/tinubu-portrait.webp')
            ->update(['image_path' => 'images/hero-portrait.webp']);
    }
};
