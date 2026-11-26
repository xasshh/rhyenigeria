<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/** Receives browser CSP violation reports (report-only phase) and logs a compact line. */
class CspReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        // CSP reports arrive as application/csp-report, which $request->json() does not parse.
        $data = json_decode($request->getContent() ?: '[]', true);
        $report = is_array($data) ? ($data['csp-report'] ?? $data) : [];

        if (is_array($report) && $report !== []) {
            Log::channel('single')->warning('CSP report-only violation', [
                'directive' => $report['effective-directive'] ?? $report['violated-directive'] ?? null,
                'blocked' => isset($report['blocked-uri']) ? mb_substr((string) $report['blocked-uri'], 0, 300) : null,
                'document' => isset($report['document-uri']) ? mb_substr((string) $report['document-uri'], 0, 300) : null,
            ]);
        }

        return response()->noContent();
    }
}
