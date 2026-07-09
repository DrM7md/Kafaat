<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Models\User;
use Illuminate\Http\Request;

final class MeController extends Controller
{
    public function __invoke(Request $request): MeResource
    {
        /** @var User $user */
        $user = $request->user();

        return MeResource::make($user->loadMissing(['employeeProfile', 'companyProfile']));
    }
}
