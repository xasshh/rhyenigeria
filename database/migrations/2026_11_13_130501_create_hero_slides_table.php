<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The homepage header slider, edited by super admins under Admin → Homepage slides.
        Schema::create('hero_slides', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('position')->default(0)->index();
            $table->boolean('is_active')->default(true);
            // `images/...` is a file shipped in public/; anything else is on the public disk.
            $table->string('image_path')->nullable();
            $table->string('image_original_path')->nullable();
            $table->string('image_alt', 255)->nullable();
            $table->string('eyebrow', 80)->nullable();
            $table->string('headline', 160);
            $table->string('body', 400)->nullable();
            $table->string('button_label', 40)->nullable();
            $table->string('button_url', 255)->nullable();
            $table->timestamps();
        });

        // Slide 1 is the Plateau State event flyer; 2–5 are placeholder frames to fill in later.
        $now = now();
        $placeholder = fn (int $n) => [
            'position' => $n, 'is_active' => true, 'image_path' => null, 'image_original_path' => null, 'image_alt' => null,
            'eyebrow' => 'Slide '.$n, 'headline' => 'Headline for slide '.$n,
            'body' => 'A short line about this slide. Edit it, add an image and set the button from Admin → Homepage slides.',
            'button_label' => 'Join the Movement', 'button_url' => '/join', 'created_at' => $now, 'updated_at' => $now,
        ];

        DB::table('hero_slides')->insert([
            [
                'position' => 1, 'is_active' => true,
                'image_path' => 'images/slides/slide-1.webp', 'image_original_path' => 'images/slides/slide-1.jpeg',
                'image_alt' => 'Education Empowerment Initiative flyer: free laptops to students, 13 November 2026, Tamarald Event Centre, Jos, Plateau State',
                'eyebrow' => 'Plateau State · 13 November 2026',
                'headline' => 'Education Empowerment Initiative',
                'body' => 'Free laptops to students. Tamarald Event Centre, Jos. Join the movement to get event updates for your state.',
                'button_label' => 'Join the Movement', 'button_url' => '/join?state=PL',
                'created_at' => $now, 'updated_at' => $now,
            ],
            $placeholder(2), $placeholder(3), $placeholder(4), $placeholder(5),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_slides');
    }
};
