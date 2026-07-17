<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\RegisterUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterCompanyRequest;
use App\Http\Requests\Auth\RegisterEmployeeRequest;
use App\Http\Resources\MeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

final class RegisterController extends Controller
{
    public function __construct(private readonly RegisterUserAction $register) {}

    public function employee(RegisterEmployeeRequest $request): JsonResponse
    {
        /** @var array{name: string, email: string, phone?: string|null, password: string, title: string} $data */
        $data = $request->safe()->only(['name', 'email', 'phone', 'password', 'title']);

        $user = $this->register->employee($data);

        Auth::login($user);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return MeResource::make($user)->response()->setStatusCode(201);
    }

    public function company(RegisterCompanyRequest $request): JsonResponse
    {
        /** @var array{name: string, email: string, phone?: string|null, password: string, legal_name: string, cr_number: string} $data */
        $data = $request->safe()->only(['name', 'email', 'phone', 'password', 'legal_name', 'cr_number']);

        $user = $this->register->company($data);

        Auth::login($user);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return MeResource::make($user)->response()->setStatusCode(201);
    }
}
