<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MaskedProfileResource;
use App\Models\EmployeeProfile;
use App\Models\Lookup;
use App\Services\Masking\ProfileVisibilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class CandidateController extends Controller
{
    public function __construct(private readonly ProfileVisibilityService $visibility) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'field' => ['nullable', 'string', 'max:60'],
            'city' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', 'string', 'max:60'],
            'min_experience' => ['nullable', 'integer', 'min:0', 'max:60'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:80'],
            'sort' => ['nullable', 'in:recent,experience'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = EmployeeProfile::query()
            ->browsable()
            ->with(['user', 'field', 'city', 'employmentStatus', 'skills']);

        // ★ عمى صاحب العمل يُطبَّق في **الاستعلام**، لا في الترشيح بعده.
        //   لو رشّحنا بعد العدّ، لسرّبنا وجود الملفّ عبر فرق عدد النتائج.
        $this->applyEmployerBlindness($query, $request);

        $this->applySearch($query, $request);
        $this->applyFilters($query, $request);

        // الترتيب بحقولٍ مكشوفة فقط. لا ترتيب باسمٍ ولا براتبٍ محجوب.
        match ($request->string('sort')->value()) {
            'experience' => $query->orderByDesc('years_experience'),
            default => $query->orderByDesc('published_at'),
        };

        $profiles = $query->paginate((int) $request->integer('per_page', 20))->withQueryString();

        return MaskedProfileResource::collection($profiles);
    }

    public function show(Request $request, EmployeeProfile $profile): MaskedProfileResource
    {
        // 404 لا 403: في هذا المجال، وجود الملفّ نفسه معلومةٌ لا تُمنَح.
        abort_unless(Gate::allows('view', $profile), 404);

        // يرمي ProfileNotVisibleException (404) إن كان الطالب صاحب العمل الحالي.
        // يسبق كل شيء: الوجود نفسه سرّ.
        $this->visibility->visibleFields($request->user(), $profile);

        $profile->load([
            'user', 'field', 'city', 'employmentStatus', 'skills',
            'experiences', 'educations', 'attachments', 'currentEmployer',
        ]);

        return MaskedProfileResource::make($profile)->detailed();
    }

    /** @param Builder<EmployeeProfile> $query */
    private function applyEmployerBlindness(Builder $query, Request $request): void
    {
        $company = $this->visibility->companyOf($request->user());

        if ($company === null || ! (bool) settings('privacy.employer_blind', true)) {
            return;
        }

        $query->where(function (Builder $q) use ($company) {
            $q->whereNull('current_employer_company_id')
                ->orWhere('current_employer_company_id', '!=', $company->id);
        });
    }

    /**
     * ⚠️ البحث يمسّ الحقول **المكشوفة** فقط.
     *
     * لو بحثنا في `users.name` لسرّبنا الاسم عبر الاستعلام: طلبٌ بـ
     * `?q=أحمد` يُرجع نتيجة ⇒ في القاعدة «أحمد». الحجب في الاستجابة
     * وحده لا يكفي — الاستعلام قناة تسريب أيضاً. Brain/06 §4.
     *
     * @param  Builder<EmployeeProfile>  $query
     */
    private function applySearch(Builder $query, Request $request): void
    {
        $term = $request->string('q')->trim()->value();

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
                ->orWhereHas('skills', fn (Builder $s) => $s->where('name', 'like', "%{$term}%"))
                ->orWhereHas('field', fn (Builder $f) => $f->where('label_ar', 'like', "%{$term}%"));
        });
    }

    /** @param Builder<EmployeeProfile> $query */
    private function applyFilters(Builder $query, Request $request): void
    {
        if ($field = $request->string('field')->value()) {
            $query->where('field_id', $this->lookupId(Lookup::FIELD, $field));
        }

        if ($city = $request->string('city')->value()) {
            $query->where('city_id', $this->lookupId(Lookup::CITY, $city));
        }

        if ($status = $request->string('status')->value()) {
            $query->where('employment_status_id', $this->lookupId(Lookup::EMPLOYMENT_STATUS, $status));
        }

        if ($request->filled('min_experience')) {
            $query->where('years_experience', '>=', $request->integer('min_experience'));
        }

        /** @var list<string> $skills */
        $skills = (array) $request->input('skills', []);

        foreach ($skills as $skill) {
            $query->whereHas('skills', fn (Builder $s) => $s->where('name', $skill));
        }
    }

    private function lookupId(string $group, string $key): int
    {
        return (int) Lookup::query()->where('group', $group)->where('key', $key)->value('id');
    }
}
