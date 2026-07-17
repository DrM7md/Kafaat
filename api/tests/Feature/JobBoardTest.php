<?php

declare(strict_types=1);

use App\Enums\JobStatus;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\CandidateReveal;
use App\Models\CompanyProfile;
use App\Models\EmployeeProfile;
use App\Models\JobInterest;
use App\Models\JobPosting;
use App\Models\Payment;
use App\Services\Payments\ManualGateway;
use App\Services\Settings\SettingsRepository;
use App\Support\SettingKeys;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Notifications\DatabaseNotification;

/**
 * لوحة الوظائف، والإعجاب، وحصّة الخانات.
 *
 * ★★ الخطر الأوّل — وهو **وجودي** لا تجميلي: أن يكشف الإعجابُ الهويّة.
 *
 *    لو رأت الشركةُ اسمَ من أُعجب بوظيفتها مجّاناً، لانهار نموذج الربح كلّه:
 *    تكفيها أن تنشر وظيفةً فيأتيها الناس بأسمائهم وأرقامهم بلا ريال واحد.
 *    وأسوأ: شركاتٌ تنشر وظائف وهمية لحصاد الأسماء.
 *
 *    فالإعجاب يبيع **النيّة** لا الاسم: بطاقةٌ محجوبة وزرُّ كشف.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(SettingSeeder::class);
    $this->seed(LookupSeeder::class);
    app(SettingsRepository::class)->flush();
});

function openJob(CompanyProfile $company, array $attributes = []): JobPosting
{
    return JobPosting::query()->forceCreate([
        'company_profile_id' => $company->id,
        'title' => 'مهندس كهربائي',
        'description' => 'مطلوب مهندس كهربائي لمشاريع البنية التحتية.',
        'status' => JobStatus::Open,
        'published_at' => now(),
        'currency' => 'QAR',
        'min_experience' => 3,
        ...$attributes,
    ]);
}

/**
 * يُرسل webhook دفعٍ ناجح موقَّعاً — نفس مسار الإنتاج حرفياً.
 *
 * ★ لا نستدعي الخدمة مباشرةً: المُختبَر هو أنّ **الـ webhook** يعرف غرضَ
 *   الدفعة. وهو ما كان ينهار قبل `purpose`.
 */
function payJobSlot(Payment $payment): void
{
    $body = json_encode(
        ['type' => 'payment.succeeded', 'provider_ref' => $payment->provider_ref, 'amount' => $payment->amount],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    );

    $signature = hash_hmac('sha256', (string) $body, app(ManualGateway::class)->secret());

    test()->call('POST', '/api/v1/payments/webhook/manual', [], [], [],
        ['HTTP_X_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], (string) $body)
        ->assertOk();
}

function draftJob(CompanyProfile $company): JobPosting
{
    return JobPosting::query()->forceCreate([
        'company_profile_id' => $company->id,
        'title' => 'مسودّة',
        'description' => 'وصف.',
        'status' => JobStatus::Draft,
        'currency' => 'QAR',
    ]);
}

it('★★ 1 — الإعجاب لا يكشف الهويّة: الشركة ترى بطاقةً محجوبة وزرَّ كشف', function () {
    $company = CompanyProfile::factory()->create();
    $job = openJob($company);

    $employee = EmployeeProfile::factory()->create();
    $name = $employee->user->name;

    $this->actingAs($employee->user)
        ->postJson("/api/v1/jobs/{$job->ref}/interest")
        ->assertOk();

    $card = $this->actingAs($company->user)
        ->getJson("/api/v1/my-jobs/{$job->ref}/interested")
        ->assertOk()
        ->json('data.0');

    // ★★ الحقول المعرِّفة **غائبة من JSON** لا فارغةٌ فيه (ADR-003).
    expect($card)->not->toHaveKey('name')
        ->and($card)->not->toHaveKey('phone')
        ->and($card)->not->toHaveKey('email')
        ->and($card['is_revealed'])->toBeFalse();

    // ولا يتسرّب الاسم في أي موضعٍ من الاستجابة.
    $raw = $this->actingAs($company->user)
        ->getJson("/api/v1/my-jobs/{$job->ref}/interested")
        ->getContent();

    expect($raw)->not->toContain($name);

    // وما يُباع هو النيّة: بطاقةٌ فيها المسمّى والخبرة وزرُّ كشفٍ بسعره.
    expect($card)->toHaveKey('title')
        ->and($card)->toHaveKey('reveal_fee');
});

it('★★ 2 — ولا يتسرّب الاسم في الإشعار', function () {
    $company = CompanyProfile::factory()->create();
    $job = openJob($company);

    $employee = EmployeeProfile::factory()->create();

    $this->actingAs($employee->user)->postJson("/api/v1/jobs/{$job->ref}/interest")->assertOk();

    $notification = DatabaseNotification::query()
        ->where('notifiable_id', $company->user_id)
        ->firstOrFail();

    $payload = json_encode($notification->data, JSON_UNESCAPED_UNICODE) ?: '';

    // الإشعار صفٌّ **دائم**: اسمٌ يُخزَّن فيه يتسرّب إلى الأبد.
    expect($payload)->not->toContain($employee->user->name)
        ->and($notification->data['key'])->toBe('job.interest')
        ->and($notification->data['link'])->toBe("/jobs/{$job->ref}/interested");
});

it('3 — بعد دفع رسم الكشف تظهر هويّة المعجب — لا قبله', function () {
    $company = CompanyProfile::factory()->create();
    $job = openJob($company);
    $employee = EmployeeProfile::factory()->create();

    $this->actingAs($employee->user)->postJson("/api/v1/jobs/{$job->ref}/interest")->assertOk();

    // كشفٌ مدفوع مكتمل.
    CandidateReveal::factory()->completed()->create([
        'company_profile_id' => $company->id,
        'employee_profile_id' => $employee->id,
    ]);

    $card = $this->actingAs($company->user)
        ->getJson("/api/v1/my-jobs/{$job->ref}/interested")
        ->json('data.0');

    expect($card['is_revealed'])->toBeTrue()
        ->and($card['name'])->toBe($employee->user->name)
        ->and($card)->toHaveKey('phone');
});

it('★ 4 — عمى صاحب العمل: الموظّف لا يرى وظائف شركته الحالية', function () {
    $employer = CompanyProfile::factory()->create();
    $other = CompanyProfile::factory()->create();

    openJob($employer, ['title' => 'وظيفة في شركته']);
    openJob($other, ['title' => 'وظيفة في شركةٍ أخرى']);

    $employee = EmployeeProfile::factory()->create(['current_employer_company_id' => $employer->id]);

    $titles = array_column(
        $this->actingAs($employee->user)->getJson('/api/v1/jobs')->assertOk()->json('data'),
        'title',
    );

    // لو ظهرت وأُعجب بها، لوصل شركتَه إشعارٌ بأنّ «مرشّحاً» يريد الرحيل — ثم
    // لا تستطيع كشفَه (الحجب يمنعها). بطاقةٌ عمياء تُنذرها: تسريبٌ بالاستدلال.
    expect($titles)->toContain('وظيفة في شركةٍ أخرى')
        ->and($titles)->not->toContain('وظيفة في شركته');
});

it('★ 5 — ولا يُعجَب بها ولو بمعرّفها المباشر', function () {
    $employer = CompanyProfile::factory()->create();
    $job = openJob($employer);

    $employee = EmployeeProfile::factory()->create(['current_employer_company_id' => $employer->id]);

    $this->actingAs($employee->user)
        ->postJson("/api/v1/jobs/{$job->ref}/interest")
        ->assertNotFound();

    expect(JobInterest::query()->count())->toBe(0);
});

it('6 — الضغط مرّتين لا يُنشئ إعجابين ولا إشعارين', function () {
    $company = CompanyProfile::factory()->create();
    $job = openJob($company);
    $employee = EmployeeProfile::factory()->create();

    for ($click = 0; $click < 3; $click++) {
        $this->actingAs($employee->user)->postJson("/api/v1/jobs/{$job->ref}/interest")->assertOk();
    }

    expect(JobInterest::query()->count())->toBe(1)
        ->and(DatabaseNotification::query()->where('notifiable_id', $company->user_id)->count())->toBe(1);
});

it('7 — سحب الإعجاب يُخفي المرشّح من قائمة الشركة، ولا يحذف الصفّ', function () {
    $company = CompanyProfile::factory()->create();
    $job = openJob($company);
    $employee = EmployeeProfile::factory()->create();

    $this->actingAs($employee->user)->postJson("/api/v1/jobs/{$job->ref}/interest")->assertOk();
    $this->actingAs($employee->user)->deleteJson("/api/v1/jobs/{$job->ref}/interest")->assertOk();

    expect($this->actingAs($company->user)->getJson("/api/v1/my-jobs/{$job->ref}/interested")->json('data'))
        ->toBeEmpty();

    // الصفّ باقٍ: الشركة قد تكون دفعت رسم كشفٍ بناءً عليه، فمحوُه يجعل
    // فاتورةً بلا سبب.
    expect(JobInterest::query()->count())->toBe(1);
});

it('8 — الوظيفة المغلقة أو المسودّة لا تظهر للموظّفين', function () {
    $company = CompanyProfile::factory()->create();

    draftJob($company);
    openJob($company, ['status' => JobStatus::Closed, 'closed_at' => now()]);
    openJob($company, ['title' => 'المفتوحة وحدها']);

    $titles = array_column(
        $this->actingAs(EmployeeProfile::factory()->create()->user)->getJson('/api/v1/jobs')->json('data'),
        'title',
    );

    expect($titles)->toBe(['المفتوحة وحدها']);
});

it('9 — شركةٌ لا ترى وظيفة منافسها ولا معجبيها — 404', function () {
    $mine = CompanyProfile::factory()->create();
    $rival = CompanyProfile::factory()->create();

    $job = openJob($rival);

    $this->actingAs($mine->user)
        ->getJson("/api/v1/my-jobs/{$job->ref}/interested")
        ->assertNotFound();

    $this->actingAs($mine->user)
        ->postJson("/api/v1/my-jobs/{$job->ref}/close")
        ->assertNotFound();
});

// ═══ حصّة الخانات ═══

it('★★ 10 — الحصّة تُعدّ المفتوحة وحدها، والفتح فوقها يُردّ بـ 402', function () {
    app(SettingsRepository::class)->set(SettingKeys::JOB_FREE_SLOTS, 2);

    $company = CompanyProfile::factory()->create();

    openJob($company);
    openJob($company);

    $third = draftJob($company);

    $response = $this->actingAs($company->user)
        ->postJson("/api/v1/my-jobs/{$third->ref}/open")
        ->assertStatus(402);

    expect($response->json('slots.total'))->toBe(2)
        ->and($response->json('slots.used'))->toBe(2)
        ->and($response->json('slots.remaining'))->toBe(0)
        ->and($response->json('slots.extra_fee'))->toBe(150);

    expect($third->fresh()->status)->toBe(JobStatus::Draft);
});

it('★ 11 — إغلاق وظيفةٍ يحرّر خانتها فوراً', function () {
    app(SettingsRepository::class)->set(SettingKeys::JOB_FREE_SLOTS, 1);

    $company = CompanyProfile::factory()->create();

    $first = openJob($company);
    $second = draftJob($company);

    $this->actingAs($company->user)->postJson("/api/v1/my-jobs/{$second->ref}/open")->assertStatus(402);

    $this->actingAs($company->user)->postJson("/api/v1/my-jobs/{$first->ref}/close")->assertOk();

    // نظّف لوحتك تكسب خانة — فلا يمتلئ الموقع بوظائف ميتة.
    $this->actingAs($company->user)->postJson("/api/v1/my-jobs/{$second->ref}/open")->assertOk();

    expect($second->fresh()->status)->toBe(JobStatus::Open);
});

it('★★ 12 — لا خانةَ بلا دفع: الشراء يُنشئ دفعةً معلَّقة ولا يفتح شيئاً', function () {
    app(SettingsRepository::class)->set(SettingKeys::JOB_FREE_SLOTS, 0);

    $company = CompanyProfile::factory()->create();
    $job = draftJob($company);

    $this->actingAs($company->user)
        ->withHeader('Idempotency-Key', 'slot-'.uniqid())
        ->postJson('/api/v1/job-slots/purchase')
        ->assertStatus(202)
        ->assertJsonPath('amount', 150);

    $payment = Payment::query()->firstOrFail();

    expect($payment->purpose)->toBe(PaymentPurpose::JobSlot)
        ->and($payment->status)->toBe(PaymentStatus::Pending);

    // الدفعة معلَّقة ⇒ لا خانة بعد.
    $this->actingAs($company->user)->postJson("/api/v1/my-jobs/{$job->ref}/open")->assertStatus(402);
});

it('★★ 13 — استقرارُ المال يمنح الخانة، والوظيفة تُفتح — عبر webhook لا متحكّم', function () {
    app(SettingsRepository::class)->set(SettingKeys::JOB_FREE_SLOTS, 0);

    $company = CompanyProfile::factory()->create();
    $job = draftJob($company);

    $this->actingAs($company->user)
        ->withHeader('Idempotency-Key', 'slot-'.uniqid())
        ->postJson('/api/v1/job-slots/purchase')
        ->assertStatus(202);

    $payment = Payment::query()->firstOrFail();

    // ★ الـ webhook هو من يمنح — لا متحكّم. وهو ما كان ينهار قبل `purpose`:
    //   كان يبحث عن كشفٍ لكل دفعة، فيرمي **بعد** أن يدخل المال.
    payJobSlot($payment);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);

    $this->actingAs($company->user)->postJson("/api/v1/my-jobs/{$job->ref}/open")->assertOk();

    $job->refresh();

    expect($job->status)->toBe(JobStatus::Open)
        ->and($job->is_paid_slot)->toBeTrue()
        ->and($job->payment_id)->toBe($payment->id);
});

it('★ 14 — لا تُدفَع مرّتان: إغلاق الوظيفة المدفوعة يُبقي خانتها', function () {
    app(SettingsRepository::class)->set(SettingKeys::JOB_FREE_SLOTS, 0);

    $company = CompanyProfile::factory()->create();
    $paid = draftJob($company);

    $this->actingAs($company->user)
        ->withHeader('Idempotency-Key', 'slot-'.uniqid())
        ->postJson('/api/v1/job-slots/purchase')->assertStatus(202);

    payJobSlot(Payment::query()->firstOrFail());

    $this->actingAs($company->user)->postJson("/api/v1/my-jobs/{$paid->ref}/open")->assertOk();
    $this->actingAs($company->user)->postJson("/api/v1/my-jobs/{$paid->ref}/close")->assertOk();

    // الخانة دُفع ثمنها مرّةً — فوظيفةٌ أخرى تُفتح بلا دفعٍ ثانٍ.
    $another = draftJob($company);

    $this->actingAs($company->user)->postJson("/api/v1/my-jobs/{$another->ref}/open")->assertOk();

    expect($another->fresh()->status)->toBe(JobStatus::Open);
});

it('15 — الحصّة تُضبط من اللوحة بلا نشر كود', function () {
    $company = CompanyProfile::factory()->create();

    app(SettingsRepository::class)->set(SettingKeys::JOB_FREE_SLOTS, 1);

    openJob($company);
    $second = draftJob($company);

    $this->actingAs($company->user)->postJson("/api/v1/my-jobs/{$second->ref}/open")->assertStatus(402);

    app(SettingsRepository::class)->set(SettingKeys::JOB_FREE_SLOTS, 5);

    $this->actingAs($company->user)->postJson("/api/v1/my-jobs/{$second->ref}/open")->assertOk();
});

it('16 — شركةٌ غير موثَّقة لا تنشر وظيفةً تستدرج بها الناس', function () {
    $company = CompanyProfile::factory()->pending()->create();

    $this->actingAs($company->user)
        ->postJson('/api/v1/my-jobs', ['title' => 'وظيفة', 'description' => 'وصف كافٍ.'])
        ->assertForbidden();
});

it('17 — ملفٌّ غير منشور لا يُعجَب: الشركة لن ترى شيئاً حين تفتحه', function () {
    $company = CompanyProfile::factory()->create();
    $job = openJob($company);

    $employee = EmployeeProfile::factory()->unpublished()->create();

    $this->actingAs($employee->user)
        ->postJson("/api/v1/jobs/{$job->ref}/interest")
        ->assertStatus(422);
});
