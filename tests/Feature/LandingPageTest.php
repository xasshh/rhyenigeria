<?php

namespace Tests\Feature;

use Tests\TestCase;

class LandingPageTest extends TestCase
{
    public function test_the_landing_page_renders(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Renewed Hope Youth Engagement')
            ->assertSee('Join the Movement')
            ->assertSee('5,000+ youth empowered');
    }
}
