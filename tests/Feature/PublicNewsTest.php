<?php

namespace Tests\Feature;

use App\Models\NewsPost;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicNewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_plateau_story_is_published_with_its_gallery_and_the_slide_points_to_it(): void
    {
        $post = NewsPost::where('slug', 'plateau-state-education-empowerment-drive')->sole();
        $this->assertTrue($post->is_public);
        $this->assertCount(5, $post->photos);
        foreach ($post->photos as $photo) {
            $this->assertFileExists(public_path($photo->path));
        }

        $this->get('/')->assertOk()->assertSee('From the field')->assertSee($post->title)->assertSee(route('news.show', $post->slug));
        $this->get('/news')->assertOk()->assertSee($post->title)->assertSee('5 photos');
        $this->get('/news/'.$post->slug)->assertOk()
            ->assertSee('featured the presence of Seyi Tinubu')
            ->assertSee('#TinubuShettima2027')
            ->assertSee('Gallery')
            ->assertSee('property="og:image"', false);
        $this->assertDatabaseHas('hero_slides', ['button_url' => '/news/'.$post->slug, 'button_label' => 'See the photos']);
    }

    public function test_members_only_news_never_appears_on_the_public_site(): void
    {
        $private = new NewsPost(['title' => 'Members only briefing', 'body' => 'Internal', 'published_at' => now()]);
        $private->slug = 'members-only-briefing';
        $private->save();

        $this->get('/news')->assertOk()->assertDontSee('Members only briefing');
        $this->get('/')->assertDontSee('Members only briefing');
        $this->get('/news/members-only-briefing')->assertNotFound();
    }

    public function test_a_super_admin_posts_a_public_story_with_photos(): void
    {
        Storage::fake('public');
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post('/admin/posts/news', [
            'title' => 'Kano youth summit', 'body' => 'It was a great day.', 'is_public' => '1',
            'photos' => [UploadedFile::fake()->image('a.jpg', 2400, 1600), UploadedFile::fake()->image('b.png', 800, 600)],
        ])->assertRedirect();

        $post = NewsPost::where('title', 'Kano youth summit')->sole();
        $this->assertTrue($post->is_public);
        $this->assertSame('kano-youth-summit', $post->slug);
        $this->assertCount(2, $post->photos);
        Storage::disk('public')->assertExists([$post->photos[0]->path, $post->photos[0]->original_path]);
        $this->get('/news/kano-youth-summit')->assertOk()->assertSee('It was a great day.');

        $photo = $post->photos[1];
        $this->actingAs($admin)->delete("/admin/posts/news/{$post->id}/photos/{$photo->id}")->assertRedirect();
        Storage::disk('public')->assertMissing($photo->path);
        $this->assertCount(1, $post->fresh()->photos);
    }

    public function test_coordinators_cannot_publish_to_the_public_site(): void
    {
        Storage::fake('public');
        $coordinator = User::factory()->coordinator(State::factory()->create())->create();

        $this->actingAs($coordinator)->post('/coordinator/posts/news', [
            'title' => 'State rally recap', 'body' => 'Photos from the rally.', 'is_public' => '1',
            'photos' => [UploadedFile::fake()->image('rally.jpg', 1200, 800)],
        ])->assertRedirect();

        $post = NewsPost::where('title', 'State rally recap')->sole();
        $this->assertFalse($post->is_public);
        $this->assertCount(1, $post->photos);
        $this->get('/news')->assertDontSee('State rally recap');
    }
}
