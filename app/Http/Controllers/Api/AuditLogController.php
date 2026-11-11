<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'action' => ['nullable', 'string', 'max:64'],
            'user_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'between:1,200'],
        ]);

        $logs = AuditLog::query()
            ->when($data['action'] ?? null, fn ($q, $action) => $q->where('action', $action))
            ->when($data['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($data['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', Carbon::parse($from)->utc()))
            ->when($data['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', Carbon::parse($to)->utc()))
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 50);

        return response()->json([
            'data' => $logs->items(),
            'meta' => ['current_page' => $logs->currentPage(), 'last_page' => $logs->lastPage(), 'per_page' => $logs->perPage(), 'total' => $logs->total()],
        ]);
    }
}
