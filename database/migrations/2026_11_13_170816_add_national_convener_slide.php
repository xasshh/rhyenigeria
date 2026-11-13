<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Slide 5: the National Convener, Abdulazeez Kaka. Takes the last untouched placeholder, or is added at the end. */
    public function up(): void
    {
        if (DB::table('hero_slides')->where('image_path', 'images/slides/abdulazeez-kaka.webp')->exists()) {
            return;
        }

        $now = now();
        $slide = [
            'is_active' => true, 'layout' => 'portrait',
            'image_path' => 'images/slides/abdulazeez-kaka.webp', 'image_original_path' => 'images/slides/abdulazeez-kaka.jpeg',
            'image_alt' => 'Portrait of Abdulazeez Kaka', 'eyebrow' => 'National Convener',
            'headline' => 'Abdulazeez Kaka',
            'body' => 'National Convener, Renewed Hope Youth Engagement.',
            'button_label' => 'Join the Movement', 'button_url' => '/join', 'updated_at' => $now,
        ];

        $placeholder = DB::table('hero_slides')->whereNull('image_path')->where('headline', 'like', 'Headline for slide %')->orderBy('position')->first();

        $placeholder !== null
            ? DB::table('hero_slides')->where('id', $placeholder->id)->update($slide)
            : DB::table('hero_slides')->insert($slide + ['position' => (int) DB::table('hero_slides')->max('position') + 1, 'created_at' => $now]);
    }

    public function down(): void
    {
        // Content change; nothing to undo.
    }
};
