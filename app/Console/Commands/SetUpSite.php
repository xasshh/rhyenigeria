<?php

namespace App\Console\Commands;

use App\Models\HeroSlide;
use App\Models\NewsPost;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('site:setup {--force : Replace the slides even when some were already changed in the admin}')]
#[Description('Prepare a new site\'s database: its own starting slides (config/site.php) instead of RHYE\'s, and no RHYE news')]
class SetUpSite extends Command
{
    public function handle(): int
    {
        $slides = (array) config('site.slides', []);
        if ($slides === []) {
            $this->components->error('This site has no starting slides in config/site.php.');

            return self::FAILURE;
        }

        // The migrations seed RHYE's first slides; once admins have edited or added slides, leave them alone.
        if (! $this->option('force') && HeroSlide::where('updated_at', '>', DB::raw('created_at'))->exists()) {
            $this->components->error('Slides were already edited in the admin. Use --force to replace them anyway.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($slides) {
            HeroSlide::query()->delete();
            foreach (array_values($slides) as $i => $slide) {
                DB::table('hero_slides')->insert($slide + [
                    'position' => $i + 1, 'is_active' => true, 'image_original_path' => null,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        // The migrations also add RHYE's first news story; other sites start with none.
        if (config('site.key') !== 'rhye') {
            $posts = NewsPost::where('source_label', 'RHYE')->pluck('id');
            DB::table('news_photos')->whereIn('news_post_id', $posts)->delete();
            NewsPost::whereIn('id', $posts)->delete();
        }

        $this->components->info(count($slides).' slides set for '.config('site.name').'.');

        return self::SUCCESS;
    }
}
