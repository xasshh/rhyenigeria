<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\XPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class XPostTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_adds_posts_by_link_and_the_homepage_shows_the_newest(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post('/admin/x-posts', ['link' => "https://x.com/ubasanius/status/1860000000000000000?s=46&t=abc\nhttps://twitter.com/ubasanius/status/1870000000000000000"])
            ->assertSessionHas('status', '2 posts added to the homepage.');
        $this->actingAs($admin)->post('/admin/x-posts', ['link' => 'https://mobile.x.com/ubasanius/status/1860000000000000000'])
            ->assertSessionHas('status', 'That post is already on the homepage.');

        $this->assertSame(2, XPost::count());
        $this->assertSame('2024-11-22', XPost::firstWhere('status_id', 1860000000000000000)->posted_at->toDateString());

        $html = $this->get('/')->assertOk()->assertSee('id="x-feed"', false)->getContent();
        $this->assertLessThan(
            strpos($html, 'https://x.com/ubasanius/status/1860000000000000000'),
            strpos($html, 'https://x.com/ubasanius/status/1870000000000000000'),
        );
    }

    public function test_links_that_are_not_x_posts_are_refused(): void
    {
        $admin = User::factory()->superAdmin()->create();

        foreach (['https://x.com/ubasanius', 'https://example.com/ubasanius/status/123456789', 'javascript:alert(1)'] as $link) {
            $this->actingAs($admin)->post('/admin/x-posts', ['link' => $link])->assertSessionHasErrors('link');
        }
        $this->assertSame(0, XPost::count());
    }

    public function test_removing_a_post_and_the_section_hides_when_empty(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $post = XPost::factory()->create();

        $this->actingAs($admin)->delete("/admin/x-posts/{$post->id}")->assertRedirect();
        $this->assertModelMissing($post);
        $this->get('/')->assertOk()->assertDontSee('id="x-feed"', false);
    }

    public function test_only_super_admins_manage_x_posts(): void
    {
        $this->get('/admin/x-posts')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->coordinator()->create())->post('/admin/x-posts', ['link' => 'https://x.com/a/status/1860000000000000000'])->assertForbidden();
    }
}
