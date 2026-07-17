<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\EmployeeProfile;
use App\Models\Lookup;
use App\Models\User;
use App\Support\Permissions;
use App\Support\Roles;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;

/**
 * خيار «أخرى» والقوائم المرجعية المرنة.
 *
 * ★ الخطر الذي تحرسه هذه الاختبارات: «أخرى» حقلٌ نصّيّ حرّ يكتبه أيُّ موظّف
 *   مسجَّل. لو تسرّب ما يكتبه إلى قائمة الجميع بلا مراجعة، لصار بابَ حقنٍ
 *   مفتوحاً — إعلاناتٍ وشتائمَ وقيماً مكرّرة في نموذج كل مستخدم.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(SettingSeeder::class);
    $this->seed(LookupSeeder::class);
});

function employee(): EmployeeProfile
{
    return EmployeeProfile::factory()->create();
}

function lookupAdmin(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(Permissions::LOOKUP_MANAGE);

    return $user;
}

function qatar(): Lookup
{
    return Lookup::query()->where('group', Lookup::COUNTRY)->where('key', 'qa')->firstOrFail();
}

it('1 — «أخرى» تُنشئ اقتراحاً معلَّقاً لا خياراً رسمياً', function () {
    $profile = employee();

    $this->actingAs($profile->user)
        ->putJson('/api/v1/profile', ['city_other' => 'الشحانية', 'country_id' => qatar()->id])
        ->assertOk();

    $suggestion = Lookup::query()->where('group', Lookup::CITY)->where('label_ar', 'الشحانية')->firstOrFail();

    expect($suggestion->status)->toBe(Lookup::STATUS_PENDING)
        ->and($suggestion->is_active)->toBeFalse()
        ->and($suggestion->suggested_by_user_id)->toBe($profile->user_id)
        ->and($suggestion->parent_id)->toBe(qatar()->id);

    // ★ الملفّ يشير إليها من فوره — صاحبها يراها ولا ينتظر أحداً.
    expect($profile->fresh()->city_id)->toBe($suggestion->id);
});

it('★ 2 — الاقتراح المعلَّق لا يتسرّب إلى القائمة العامّة', function () {
    $profile = employee();

    $this->actingAs($profile->user)
        ->putJson('/api/v1/profile', ['field_other' => 'صيد اللؤلؤ'])
        ->assertOk();

    $cities = $this->actingAs($profile->user)
        ->getJson('/api/v1/lookups')
        ->assertOk()
        ->json('lookups.field');

    $labels = array_column($cities, 'label');

    // لو ظهرت هنا لصار كل موظّفٍ قادراً على حقن قيمةٍ في نموذج كل مستخدم.
    expect($labels)->not->toContain('صيد اللؤلؤ');
});

it('3 — اعتماد المشرف يفتح الاقتراح للجميع بلا نقل بيان', function () {
    $profile = employee();

    $this->actingAs($profile->user)
        ->putJson('/api/v1/profile', ['field_other' => 'الطيران'])
        ->assertOk();

    $suggestion = Lookup::query()->where('label_ar', 'الطيران')->firstOrFail();

    $this->actingAs(lookupAdmin())
        ->postJson("/api/v1/admin/lookups/{$suggestion->id}/approve")
        ->assertOk();

    $labels = array_column(
        $this->actingAs($profile->user)->getJson('/api/v1/lookups')->json('lookups.field'),
        'label',
    );

    expect($labels)->toContain('الطيران');

    // ★ لم يتغيّر `field_id`: الصفّ نفسه هو الذي صار معتمَداً.
    expect($profile->fresh()->field_id)->toBe($suggestion->id);

    expect(AuditLog::where('action', 'lookup.suggestion_approved')->count())->toBe(1);
});

it('4 — اقتراحان بالتسمية نفسها صفٌّ واحد لا صفّان', function () {
    $one = employee();
    $two = employee();

    foreach ([$one, $two] as $profile) {
        $this->actingAs($profile->user)
            ->putJson('/api/v1/profile', ['field_other' => 'الضيافة'])
            ->assertOk();
    }

    // لو كتبها عشرة موظّفين لاعتمدها المشرف عشر مرّات — وهذا ما نمنعه.
    expect(Lookup::query()->where('label_ar', 'الضيافة')->count())->toBe(1);

    // وكلا الملفّين يشيران إلى الصفّ نفسه.
    expect($one->fresh()->field_id)->toBe($two->fresh()->field_id);
});

it('5 — الرفض لا يحذف الصفّ: حقل صاحبه لا يُفرَّغ بلا إنذار', function () {
    $profile = employee();

    $this->actingAs($profile->user)
        ->putJson('/api/v1/profile', ['field_other' => 'قيمة مرفوضة'])
        ->assertOk();

    $suggestion = Lookup::query()->where('label_ar', 'قيمة مرفوضة')->firstOrFail();

    $this->actingAs(lookupAdmin())
        ->postJson("/api/v1/admin/lookups/{$suggestion->id}/reject")
        ->assertOk();

    expect(Lookup::query()->find($suggestion->id))->not->toBeNull()
        ->and($suggestion->fresh()->status)->toBe(Lookup::STATUS_REJECTED)
        ->and($profile->fresh()->field_id)->toBe($suggestion->id);
});

it('6 — من لا يملك lookup.manage لا يُدير القوائم', function () {
    $profile = employee();

    $this->actingAs($profile->user)->getJson('/api/v1/admin/lookups')->assertForbidden();
    $this->actingAs($profile->user)
        ->postJson('/api/v1/admin/lookups', ['group' => 'field', 'label_ar' => 'حقن'])
        ->assertForbidden();

    expect(Lookup::query()->where('label_ar', 'حقن')->exists())->toBeFalse();
});

it('★ 7 — العملة تُشتقّ من الدولة ولا يفرضها العميل', function () {
    $profile = employee();

    $uae = Lookup::query()->where('group', Lookup::COUNTRY)->where('key', 'ae')->firstOrFail();

    $this->actingAs($profile->user)
        ->putJson('/api/v1/profile', [
            'country_id' => $uae->id,
            'expected_salary_min' => 20000,

            // العميل يحاول فرض عملةٍ لا تخصّ الدولة — يتجاهلها الخادم.
            'currency' => 'USD',
        ])
        ->assertOk();

    expect($profile->fresh()->currency)->toBe('AED');
});

it('8 — المدن تتبع دولتها', function () {
    $doha = Lookup::query()->where('group', Lookup::CITY)->where('key', 'doha')->firstOrFail();

    expect($doha->parent_id)->toBe(qatar()->id);

    // وقطر وحدها نشطة اليوم — البقيّة مهيّأة بانتظار قرارٍ إداري.
    $active = array_column(
        $this->actingAs(employee()->user)->getJson('/api/v1/lookups')->json('lookups.country'),
        'label',
    );

    expect($active)->toBe(['قطر']);
});

it('9 — المشرف يضيف قيمةً فتصير خياراً فوراً، بأثر تدقيق', function () {
    $this->actingAs(lookupAdmin())
        ->postJson('/api/v1/admin/lookups', ['group' => Lookup::FIELD, 'label_ar' => 'الذكاء الاصطناعي'])
        ->assertCreated();

    $labels = array_column(
        $this->actingAs(employee()->user)->getJson('/api/v1/lookups')->json('lookups.field'),
        'label',
    );

    expect($labels)->toContain('الذكاء الاصطناعي')
        ->and(AuditLog::where('action', 'lookup.created')->count())->toBe(1);
});

it('10 — تعطيل قيمة يُخفيها من النماذج', function () {
    $marketing = Lookup::query()->where('group', Lookup::FIELD)->where('key', 'marketing')->firstOrFail();

    $this->actingAs(lookupAdmin())
        ->putJson("/api/v1/admin/lookups/{$marketing->id}", ['is_active' => false])
        ->assertOk();

    $labels = array_column(
        $this->actingAs(employee()->user)->getJson('/api/v1/lookups')->json('lookups.field'),
        'label',
    );

    expect($labels)->not->toContain('التسويق');
});

it('11 — الاعتماد لا يصيب إلّا المعلَّق', function () {
    $approved = Lookup::query()->where('group', Lookup::FIELD)->where('key', 'it')->firstOrFail();

    $this->actingAs(lookupAdmin())
        ->postJson("/api/v1/admin/lookups/{$approved->id}/approve")
        ->assertStatus(422);
});

it('12 — المشرف العام يبلغ القوائم عبر Gate::before بلا إسنادٍ صريح', function () {
    $super = User::factory()->create();
    $super->assignRole(Roles::SUPER_ADMIN);

    $this->actingAs($super)->getJson('/api/v1/admin/lookups')->assertOk();
});

/**
 * ★ عطبٌ وجده تشغيلٌ حيّ لا اختبار.
 *
 * كان المسار يُرسل `Cache-Control: max-age=300, public`. لم يضرّ ذلك يوم كانت
 * القوائم بذرةً ثابتة. أمّا وقد صارت تُدار من اللوحة، فمعناه أن يعتمد المشرف
 * مدينةً ولا يراها الناس خمس دقائق — بل ويخدمها وسيطٌ مشترك (`public`)
 * لمستخدمين آخرين.
 */
it('★ 13 — القائمة لا تُخزَّن مؤقّتاً: اعتماد المشرف يظهر فوراً', function () {
    $response = $this->actingAs(employee()->user)->getJson('/api/v1/lookups')->assertOk();

    $cacheControl = $response->headers->get('Cache-Control') ?? '';

    expect($cacheControl)->toContain('must-revalidate')
        ->and($cacheControl)->not->toContain('public')

        // الـ ETag يبقى: التحقّق رخيص (304 بلا جسم)، والطزاجة مضمونة.
        ->and($response->headers->get('ETag'))->not->toBeNull();
});
