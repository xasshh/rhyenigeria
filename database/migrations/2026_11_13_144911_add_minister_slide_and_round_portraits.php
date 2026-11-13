<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Slide 3 becomes "Bar. Seyi Tinubu" in a round portrait, and slide 4 (an untouched placeholder) becomes the
     * Minister, Comrade Ayodele Olawande. Slides are found by image or placeholder text, so admin edits are kept.
     */
    public function up(): void
    {
        $now = now();

        DB::table('hero_slides')->where('image_path', 'images/slides/seyi-tinubu.webp')->update(['layout' => 'portrait', 'updated_at' => $now]);
        DB::table('hero_slides')->where('image_path', 'images/slides/seyi-tinubu.webp')->where('headline', 'Seyi Tinubu')
            ->update(['headline' => 'Bar. Seyi Tinubu', 'image_alt' => 'Portrait of Bar. Seyi Tinubu']);

        if (DB::table('hero_slides')->where('image_path', 'images/slides/ayodele-olawande.webp')->exists()) {
            return;
        }

        $minister = [
            'is_active' => true, 'layout' => 'portrait',
            'image_path' => 'images/slides/ayodele-olawande.webp', 'image_original_path' => 'images/slides/ayodele-olawande.jpeg',
            'image_alt' => 'Portrait of Comrade Ayodele Olawande', 'eyebrow' => 'Honourable Minister',
            'headline' => 'Comrade Ayodele Olawande',
            'body' => 'Minister of Youth Development, Federal Republic of Nigeria.',
            'button_label' => 'Join the Movement', 'button_url' => '/join', 'updated_at' => $now,
        ];

        $placeholder = DB::table('hero_slides')->whereNull('image_path')->where('headline', 'like', 'Headline for slide %')->orderBy('position')->first();
        if ($placeholder !== null) {
            DB::table('hero_slides')->where('id', $placeholder->id)->update($minister + ['position' => 4]);
        } else {
            DB::table('hero_slides')->where('position', '>=', 4)->increment('position');
            DB::table('hero_slides')->insert($minister + ['position' => 4, 'created_at' => $now]);
        }

        // The remaining placeholder follows as slide 5.
        DB::table('hero_slides')->whereNull('image_path')->where('headline', 'like', 'Headline for slide %')
            ->update(['position' => 5, 'eyebrow' => 'Slide 5', 'headline' => 'Headline for slide 5']);
    }

    public function down(): void
    {
        // Content changes; nothing to undo.
    }
};
