<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentSecurityPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_html_pages_carry_a_report_only_csp_and_never_an_enforcing_one(): void
    {
        $res = $this->get('/');
        $res->assertOk();
        $res->assertHeader('Content-Security-Policy-Report-Only');
        // Must be report-only — an enforcing header would actually block and could break the page.
        $this->assertNull($res->headers->get('Content-Security-Policy'));

        $policy = $res->headers->get('Content-Security-Policy-Report-Only');
        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString('report-uri /api/csp-report', $policy);
        $this->assertStringContainsString('https://platform.twitter.com', $policy);
        $this->assertStringContainsString('https://tiles.openfreemap.org', $policy);
    }

    public function test_json_responses_do_not_get_the_csp_header(): void
    {
        $this->getJson('/geo/states/1/lgas')->assertHeaderMissing('Content-Security-Policy-Report-Only');
    }

    public function test_the_report_endpoint_accepts_a_violation_and_returns_no_content(): void
    {
        $body = json_encode(['csp-report' => [
            'document-uri' => 'https://ubasani2027.com/admin/command',
            'violated-directive' => 'connect-src',
            'blocked-uri' => 'https://example.com/thing.js',
        ]]);

        $this->call('POST', '/api/csp-report', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], $body)
            ->assertNoContent();
    }
}
