<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\RevealStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\LookupResource;
use App\Http\Resources\MaskedProfileResource;
use App\Models\EmployeeProfile;
use App\Models\Lookup;
use App\Services\Audit\AuditLogger;
use App\Services\Masking\ProfileVisibilityService;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class CandidateController extends Controller
{
    public function __construct(
        private readonly ProfileVisibilityService $visibility,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'field' => ['nullable', 'string', 'max:60'],
            'city' => ['nullable', 'string', 'max:60'],
            'nationality' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', 'string', 'max:60'],
            'min_experience' => ['nullable', 'integer', 'min:0', 'max:60'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:80'],
            'sort' => ['nullable', 'in:recent,experience'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = EmployeeProfile::query()
            ->browsable()
            ->with(['user', 'field', 'city', 'nationality', 'employmentStatus', 'skills'])

            // عدّ الكشوف المكتملة في الاستعلام لا في الـ Resource: بلا هذا
            // استعلامٌ لكل مرشّح في الصفحة. المعلَّق والمستردّ لا يُحسبان —
            // الطلب مالٌ دُفع، لا نيّةٌ تُركت.
            ->withCount(['reveals as reveals_count' => fn ($q) => $q->where('status', RevealStatus::Completed)]);

        // ★ عمى صاحب العمل يُطبَّق في **الاستعلام**، لا في الترشيح بعده.
        //   لو رشّحنا بعد العدّ، لسرّبنا وجود الملفّ عبر فرق عدد النتائج.
        $this->applyEmployerBlindness($query, $request);

        $this->applySearch($query, $request);
        $this->applyFilters($query, $request);

        // الترتيب بحقولٍ مكشوفة فقط. لا ترتيب باسمٍ ولا براتبٍ محجوب.
        //
        // الافتراضي: الأحدث إدراجاً أوّلاً — بنفس التاريخ الذي تعرضه البطاقة
        // («مسجّل منذ …»). لو رتّبنا بـ `published_at` وعرضنا `approved_at`
        // لظهر «منذ يومين» تحت «منذ شهر» فبدا الترتيب مكسوراً.
        match ($request->string('sort')->value()) {
            'experience' => $query->orderByDesc('years_experience'),
            default => $query->orderByRaw('COALESCE(approved_at, published_at) DESC'),
        };

        $profiles = $query->paginate((int) $request->integer('per_page', 20))->withQueryString();

        // استعلامٌ واحد لكل الصفحة بدل استعلامٍ لكل مرشّح داخل الـ Resource.
        $this->visibility->primeReveals(
            $this->visibility->companyOf($request->user()),
            $profiles->items(),
        );

        $this->auditModeratorListing($request, $profiles->items());

        return MaskedProfileResource::collection($profiles)
            ->additional(['facets' => ['nationality' => $this->nationalityFacet($request)]]);
    }

    /**
     * الجنسيات **الموجودة فعلاً** بين الكفاءات المعروضة — لا كتالوج القوائم.
     *
     * ★ خيارُ فلترةٍ لا نتيجةَ له وعدٌ كاذب: الشركة تختار «نيبالي» فتجد فراغاً،
     *   فتظنّ العطبَ في المنصّة لا في السوق. الكتالوج مئةٌ ونصف — عرضُها كلَّها
     *   في مِصفاةٍ يجعل الاختيارَ بحثاً في العدم. القائمة هنا تنمو مع من يسجّل،
     *   ولا تعرض إلّا ما يُثمر.
     *
     * ⚠️ مِصفاةُ المستخدم **لا تُطبَّق** على هذا الاستعلام عمداً: لو طُبِّقت
     *    لاختفى كلُّ خيارٍ سوى المختار بمجرّد اختياره — فلا يستطيع تبديله.
     *
     * أمّا عمى صاحب العمل فيسري: جنسيةٌ لا يقابلها إلّا موظّفو الشركة الطالبة
     * تُفشي وجودَهم في المنصّة — وهو ما يُخفيه الاستعلام أصلاً.
     *
     * @return array<int, mixed>
     */
    private function nationalityFacet(Request $request): array
    {
        $query = EmployeeProfile::query()->browsable()->whereNotNull('nationality_id');

        $this->applyEmployerBlindness($query, $request);

        $ids = $query->distinct()->pluck('nationality_id');

        $lookups = Lookup::query()
            ->whereIn('id', $ids)
            ->orderBy('sort_order')
            ->get();

        return LookupResource::collection($lookups)->resolve();
    }

    public function show(Request $request, EmployeeProfile $profile): MaskedProfileResource
    {
        // 404 لا 403: في هذا المجال، وجود الملفّ نفسه معلومةٌ لا تُمنَح.
        abort_unless(Gate::allows('view', $profile), 404);

        // تُستدعى `visibleFields()` مرّتين في هذا الطلب (هنا، ثم داخل الـ
        // Resource). استعلامٌ واحد يكفيهما.
        $this->visibility->primeReveals($this->visibility->companyOf($request->user()), [$profile]);

        // يرمي ProfileNotVisibleException (404) إن كان الطالب صاحب العمل الحالي.
        // يسبق كل شيء: الوجود نفسه سرّ.
        $fields = $this->visibility->visibleFields($request->user(), $profile);

        // صفٌّ واحد للمعاينة الواحدة. التسجيل هنا لا في خدمة الرؤية، لأنها
        // تُستدعى مرّتين في هذا الطلب (هنا، ثم داخل الـ Resource).
        if ($this->isModerator($request) && $fields->isFullyVisible()) {
            $this->audit->log(AuditLogger::PROFILE_VIEWED_AS_MODERATOR, $profile, [
                'ref' => $profile->ref,
            ]);
        }

        $profile->load([
            'user', 'field', 'city', 'nationality', 'employmentStatus', 'skills',
            'experiences', 'educations', 'attachments', 'currentEmployer',
        ]);

        // نفس عدّ القائمة: بلا هذا تختفي شارة الطلب عند فتح المرشّح، فيظنّ
        // من رآها في الشبكة أنّها زالت.
        $profile->loadCount([
            'reveals as reveals_count' => fn ($q) => $q->where('status', RevealStatus::Completed),
        ]);

        return MaskedProfileResource::make($profile)->detailed();
    }

    /**
     * صفٌّ واحد للطلب، لا صفٌّ لكل مرشّح: مشرفٌ يفتح `?per_page=50` كان
     * يكتب خمسين صفّاً، فيغرق أخطر فعلٍ غير مالي في ضجيجٍ لا يُقرأ.
     *
     * @param  array<int, EmployeeProfile>  $profiles  ما تُعيده `paginate()->items()`
     */
    private function auditModeratorListing(Request $request, array $profiles): void
    {
        if ($profiles === [] || ! $this->isModerator($request)) {
            return;
        }

        $this->audit->log(AuditLogger::PROFILE_LISTED_AS_MODERATOR, null, [
            'count' => count($profiles),
            'refs' => array_map(static fn (EmployeeProfile $p): string => $p->ref, $profiles),
        ]);
    }

    private function isModerator(Request $request): bool
    {
        return $request->user()?->can(Permissions::PROFILE_MODERATE) === true;
    }

    /**
     * عمى صاحب العمل — منطقُه كلُّه في `EmployeeProfile::scopeBlindTo` كي
     * يُستدعى من كل استعلامٍ يُري ملفّاتِ الناس لشركة (هنا وفي قائمة المعجبين)
     * فلا يتباعد التطبيقان.
     *
     * @param  Builder<EmployeeProfile>  $query
     */
    private function applyEmployerBlindness(Builder $query, Request $request): void
    {
        $query->blindTo($this->visibility->companyOf($request->user()));
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

        // `%` و`_` محارف بدلٍ في LIKE. `?q=%` كان يُطابق كل شيء، و`?q=_`
        // يُطابق كل مسمّى بحرفٍ واحد: قناة استكشافٍ لا بحث. تُعامَل حرفياً.
        $term = addcslashes($term, '%_\\');

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

        if ($nationality = $request->string('nationality')->value()) {
            $query->where('nationality_id', $this->lookupId(Lookup::NATIONALITY, $nationality));
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
