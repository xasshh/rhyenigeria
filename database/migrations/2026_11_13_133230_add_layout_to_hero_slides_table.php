<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // `classic` is the original landing-page hero (white, round portrait, stat badges); `image` is a picture beside the text.
        Schema::table('hero_slides', function (Blueprint $table) {
            $table->string('layout', 16)->default('image')->after('is_active');
        });

        // New order: 1 the original hero, 2 Seyi Tinubu, 3 the event flyer, 4–5 placeholders.
        // Only untouched placeholder slides are reused, so nothing an admin has edited is overwritten.
        $now = now();
        $placeholders = DB::table('hero_slides')->whereNull('image_path')->where('headline', 'like', 'Headline for slide %')->orderBy('position')->pluck('id')->all();

        $classic = [
            'position' => 1, 'is_active' => true, 'layout' => 'classic',
            'image_path' => 'images/hero-portrait.webp', 'image_original_path' => null,
            'image_alt' => '', 'eyebrow' => 'Youth for PBAT', 'headline' => 'Hope Renewed, Nation Rebuilt.',
            'body' => 'Sign up once and get a digital membership card, updates from your state, and a direct line to your ward team.',
            'button_label' => 'Join the Movement', 'button_url' => '/join', 'updated_at' => $now,
        ];
        $patron = [
            'position' => 2, 'is_active' => true, 'layout' => 'image',
            'image_path' => 'images/slides/seyi-tinubu.webp', 'image_original_path' => null,
            'image_alt' => 'Portrait of Seyi Tinubu', 'eyebrow' => 'Our patron', 'headline' => 'Seyi Tinubu',
            'body' => 'Patron of Renewed Hope Youth Engagement, standing with young Nigerians in every state and ward.',
            'button_label' => 'Join the Movement', 'button_url' => '/join', 'updated_at' => $now,
        ];

        foreach ([$classic, $patron] as $slide) {
            $id = array_shift($placeholders);
            $id === null
                ? DB::table('hero_slides')->insert($slide + ['created_at' => $now])
                : DB::table('hero_slides')->where('id', $id)->update($slide);
        }

        DB::table('hero_slides')->where('image_path', 'images/slides/slide-1.webp')->update(['position' => 3, 'updated_at' => $now]);

        // Keep two placeholders as slides 4 and 5; any further untouched ones go.
        foreach ([4, 5] as $position) {
            $id = array_shift($placeholders);
            if ($id !== null) {
                DB::table('hero_slides')->where('id', $id)->update([
                    'position' => $position, 'eyebrow' => 'Slide '.$position, 'headline' => 'Headline for slide '.$position, 'updated_at' => $now,
                ]);
            }
        }
        DB::table('hero_slides')->whereIn('id', $placeholders)->delete();
    }

    public function down(): void
    {
        Schema::table('hero_slides', function (Blueprint $table) {
            $table->dropColumn('layout');
        });
    }
};
