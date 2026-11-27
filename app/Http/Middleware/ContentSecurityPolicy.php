<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Content-Security-Policy in REPORT-ONLY mode: nothing is ever blocked. The browser only reports what
 * a future enforced policy *would* block, to /api/csp-report, so we can complete the allowlist before
 * switching enforcement on. 'unsafe-inline'/'unsafe-eval' are kept because Alpine and the command
 * centre's inline styles need them (removing those is a later step with nonces + Alpine's CSP build).
 */
class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only decorate real HTML pages — never JSON APIs, images or the report endpoint itself.
        if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }

        $policy = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://platform.twitter.com https://cdn.syndication.twimg.com https://challenges.cloudflare.com",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: https://*.twimg.com https://tiles.openfreemap.org",
            "font-src 'self' data:",
            // Recordings of calls (Africa's Talking) and the headset-calling ring tones.
            "media-src 'self' blob: https:",
            "connect-src 'self' https://tiles.openfreemap.org https://cdn.syndication.twimg.com https://syndication.twitter.com https://challenges.cloudflare.com wss:",
            "worker-src 'self' blob:",
            'frame-src https://platform.twitter.com https://syndication.twitter.com https://twitter.com https://x.com',
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
            'report-uri /api/csp-report',
        ]);

        $response->headers->set('Content-Security-Policy-Report-Only', $policy);

        return $response;
    }
}
