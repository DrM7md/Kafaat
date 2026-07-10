<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ApprovalStatus;
use App\Enums\AttachmentType;
use App\Http\Controllers\Controller;
use App\Http\Resources\AttachmentResource;
use App\Models\EmployeeProfile;
use App\Models\Skill;
use App\Models\User;
use App\Support\SettingKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $profile = $this->ownProfile($request);

        $profile->load(['skills', 'experiences', 'educations', 'attachments', 'field', 'city', 'employmentStatus']);

        // صاحب الملفّ يرى كل شيء — لا حجب على النفس.
        return response()->json(['profile' => $profile]);
    }

    public function update(Request $request): JsonResponse
    {
        $profile = $this->ownProfile($request);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:160'],
            'field_id' => ['nullable', 'exists:lookups,id'],
            'years_experience' => ['sometimes', 'integer', 'min:0', 'max:60'],
            'city_id' => ['nullable', 'exists:lookups,id'],
            'employment_status_id' => ['nullable', 'exists:lookups,id'],
            'expected_salary_min' => ['nullable', 'integer', 'min:0'],
            'expected_salary_max' => ['nullable', 'integer', 'min:0', 'gte:expected_salary_min'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'notice_period_clause' => ['nullable', 'string', 'max:2000'],
            'penalty_amount' => ['nullable', 'integer', 'min:0'],

            // ★ يُدخله الموظّف لنمنع شركته من رؤية ملفّه — لا لنعرضه.
            'current_employer_company_id' => ['nullable', 'exists:company_profiles,id'],
            'current_employer_name' => ['nullable', 'string', 'max:160'],

            'visibility' => ['sometimes', 'in:public,hidden'],
            'skills' => ['sometimes', 'array', 'max:20'],
            'skills.*' => ['string', 'max:80'],
        ]);

        $profile->fill(Arr::except($data, 'skills'));
        $profile->save();

        if (isset($data['skills'])) {
            /** @var list<string> $names */
            $names = $data['skills'];

            $skillIds = array_map(
                fn (string $name): int => Skill::firstOrCreate(
                    ['name' => $name],
                    ['slug' => Str::slug($name) ?: Str::random(8)],
                )->id,
                $names,
            );

            $profile->skills()->sync($skillIds);
        }

        // بعد مزامنة المهارات: الاكتمال يعتمد عليها.
        // forceFill لأن `completeness` محسوبة من النظام لا من مدخلات المستخدم.
        $profile->forceFill(['completeness' => $this->completeness($profile)])->save();

        return response()->json(['profile' => $profile->fresh(['skills'])]);
    }

    /**
     * تقديم الملفّ للاعتماد.
     *
     * النشر رغبةُ الموظّف، والاعتماد قرارُ المنصّة. الملفّ لا يظهر لأي شركة
     * قبل موافقة مشرفٍ يملك `profile.approve`.
     */
    public function publish(Request $request): JsonResponse
    {
        $profile = $this->ownProfile($request);

        // الطلب المقدَّم أصلاً لا يُعاد التحقّق منه ولا يُعاد ختمه بتاريخٍ جديد:
        // وإلّا قفز صاحبُه إلى مقدّمة طابور المراجعة بضغطة زرّ.
        if ($profile->isAwaitingApproval()) {
            return response()->json([
                'message' => 'طلبك قيد المراجعة بالفعل.',
                'ref' => $profile->ref,
                'approval_status' => $profile->approval_status->value,
            ]);
        }

        if ($this->completeness($profile) < 60) {
            throw ValidationException::withMessages([
                'profile' => 'أكمل ملفّك (٦٠٪ على الأقل) قبل تقديمه للاعتماد.',
            ]);
        }

        $profile->forceFill([
            'published_at' => now(),
            'approval_status' => ApprovalStatus::Pending,
            'submitted_at' => now(),
            'rejection_reason' => null,
        ])->save();

        return response()->json([
            'message' => 'قُدِّم ملفّك للاعتماد. سيظهر للشركات بعد موافقة المشرف.',
            'ref' => $profile->ref,
            'approval_status' => $profile->approval_status->value,
        ]);
    }

    public function unpublish(Request $request): JsonResponse
    {
        // السحب يُعيد الملفّ مسودّةً: إعادة النشر تعني طلباً جديداً.
        $this->ownProfile($request)->forceFill([
            'published_at' => null,
            'approval_status' => ApprovalStatus::Draft,
            'submitted_at' => null,
        ])->save();

        return response()->json(['message' => 'سُحب ملفّك من التصفّح.']);
    }

    public function uploadAttachment(Request $request): JsonResponse
    {
        $profile = $this->ownProfile($request);

        $data = $request->validate([
            'type' => ['required', 'in:cv,certificate,good_conduct'],
            'file' => ['required', 'file', 'max:5120', 'mimes:pdf,doc,docx,png,jpg'],
        ]);

        $type = AttachmentType::from((string) $data['type']);

        // ADR-013: الميزة معطّلة افتراضياً حتى مراجعة قانونية.
        if ($type === AttachmentType::GoodConduct && ! (bool) settings(SettingKeys::FEATURE_GOOD_CONDUCT_UPLOAD, false)) {
            throw ValidationException::withMessages([
                'type' => 'رفع شهادة حُسن السيرة معطّل حالياً.',
            ]);
        }

        $file = $request->file('file');

        // قرص خاص. لا `public`. رابطٌ مباشر واحد يتجاوز الحجب كلّه.
        $path = $file->store('attachments/'.$profile->id, 'private');

        $attachment = $profile->attachments()->create([
            'type' => $type,
            'disk' => 'private',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'is_private' => true,
        ]);

        return response()->json(['attachment' => AttachmentResource::make($attachment)], 201);
    }

    private function ownProfile(Request $request): EmployeeProfile
    {
        /** @var User $user */
        $user = $request->user();

        $profile = $user->employeeProfile;

        abort_if($profile === null, 403, 'هذا الحساب ليس حساب موظّف.');

        return $profile;
    }

    private function completeness(EmployeeProfile $profile): int
    {
        $checks = [
            $profile->title !== '',
            $profile->field_id !== null,
            $profile->city_id !== null,
            $profile->employment_status_id !== null,
            $profile->years_experience > 0,
            $profile->summary !== null,
            $profile->skills()->exists(),
            $profile->attachments()->where('type', AttachmentType::Cv)->exists(),
        ];

        return (int) round(count(array_filter($checks)) / count($checks) * 100);
    }
}
