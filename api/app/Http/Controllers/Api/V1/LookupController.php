<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LookupResource;
use App\Models\Lookup;
use Illuminate\Http\JsonResponse;

final class LookupController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $grouped = Lookup::query()
            ->where('is_active', true)
            ->orderBy('group')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('group')
            ->map(fn ($items) => LookupResource::collection($items));

        return response()->json(['lookups' => $grouped]);
    }
}
