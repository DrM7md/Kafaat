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
        // المعتمَد وحده. الاقتراح المعلَّق لا يتسرّب إلى قائمة الجميع —
        // وإلّا لأصبح خيار «أخرى» حقناً مباشراً بلا مراجعة.
        $grouped = Lookup::query()
            ->approved()
            ->orderBy('group')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('group')
            ->map(fn ($items) => LookupResource::collection($items));

        return response()->json(['lookups' => $grouped]);
    }
}
