<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A news post can also appear on the public site (/news) when a super admin ticks it.
        Schema::table('news_posts', function (Blueprint $table) {
            $table->string('slug', 160)->nullable()->unique();
            $table->boolean('is_public')->default(false)->index();
        });

        Schema::create('news_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_post_id')->constrained()->cascadeOnDelete();
            // `images/...` is a file shipped in public/; anything else is on the public disk.
            $table->string('path');
            $table->string('original_path')->nullable();
            $table->string('alt', 255)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        // The Plateau State Education Empowerment Initiative, held on 13 November 2026.
        $now = now();
        $slug = 'plateau-state-education-empowerment-drive';
        if (DB::table('news_posts')->where('slug', $slug)->exists()) {
            return;
        }

        $body = <<<'TXT'
The Renewed Hope Youth Engagement (RHYE) took its education empowerment drive to Plateau State, distributing free laptops to students to support their academic pursuits, bridge the digital divide and unlock new opportunities for learning and innovation.

The event, which featured the presence of Seyi Tinubu, reflects our commitment to ensuring that young Nigerians are not just beneficiaries of the Renewed Hope agenda but active participants in shaping the nation’s future.

From Plateau to every corner of Nigeria, we are turning hope into opportunities and opportunities into impact.

The future is digital. The future is empowered. The future is Renewed Hope!

#RHYE
#Onward2027
#TinubuShettima2027
#RenewedHope2_0
#RenewedHopeYouthEngagement
TXT;

        $postId = DB::table('news_posts')->insertGetId([
            'title' => 'RHYE takes its education empowerment drive to Plateau State',
            'body' => $body,
            'source_label' => 'RHYE',
            'state_id' => null,
            'ward_id' => null,
            'published_at' => $now,
            'slug' => $slug,
            'is_public' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $alts = [
            'The RHYE team on stage at the Plateau State event',
            'Students receiving laptops at the Education Empowerment Initiative',
            'Laptops being handed over to students in Jos',
            'The crowd at the Renewed Hope Youth Engagement event in Plateau State',
            'The RHYE delegation during a visit in Plateau State',
        ];
        foreach ($alts as $i => $alt) {
            $n = $i + 1;
            DB::table('news_photos')->insert([
                'news_post_id' => $postId,
                'path' => "images/news/plateau-education-drive/photo-{$n}.webp",
                'original_path' => "images/news/plateau-education-drive/photo-{$n}.jpeg",
                'alt' => $alt,
                'position' => $n,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // The event has happened: point its homepage slide at the photos, unless an admin already changed it.
        DB::table('hero_slides')
            ->where('image_path', 'images/slides/slide-1.webp')
            ->where('button_url', '/join?state=PL')
            ->update([
                'eyebrow' => 'Plateau State · 13 November 2026 · Done',
                'body' => 'Free laptops for students in Jos, with Seyi Tinubu. See the photos from the day.',
                'button_label' => 'See the photos',
                'button_url' => '/news/'.$slug,
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('news_photos');
        Schema::table('news_posts', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'is_public']);
        });
    }
};
