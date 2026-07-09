<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CandidateReveal;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RevealListController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $company = $user->companyProfile;

        abort_if($company === null, 403, 'متاح لحسابات الشركات فقط.');

        $reveals = CandidateReveal::query()
            ->where('company_profile_id', $company->id)
            ->with('employeeProfile:id,ref,title')
            ->orderByDesc('created_at')
            ->paginate(30);

        return response()->json([
            'data' => array_map(
                fn (CandidateReveal $reveal): array => [
                    'id' => $reveal->id,
                    'status' => $reveal->status->value,

                    // ADR-006: المبلغ المجمّد وقت الكشف، لا رسم اليوم.
                    'amount' => $reveal->amount,
                    'currency' => $reveal->currency,

                    'revealed_at' => $reveal->revealed_at?->toIso8601String(),
                    'employee_profile' => [
                        'ref' => $reveal->employeeProfile->ref,
                        'title' => $reveal->employeeProfile->title,
                    ],
                ],
                $reveals->items(),
            ),
            'meta' => [
                'total' => $reveals->total(),
                'current_page' => $reveals->currentPage(),
                'last_page' => $reveals->lastPage(),
                'per_page' => $reveals->perPage(),
            ],
        ]);
    }
}
