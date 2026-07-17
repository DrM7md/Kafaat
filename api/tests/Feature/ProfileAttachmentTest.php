<?php

declare(strict_types=1);

use App\Enums\AttachmentType;
use App\Models\Attachment;
use App\Models\CompanyProfile;
use App\Models\EmployeeProfile;
use App\Services\Settings\SettingsRepository;
use App\Support\SettingKeys;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * السيرة الذاتية وشهادة حُسن السيرة.
 *
 * ★ ADR-013: رفع شهادة حُسن السيرة **معطّل** بمفتاح `features.good_conduct_upload`
 *   حتى مراجعةٍ قانونية (بيانات ذات طبيعة خاصّة، قانون 13). الاختباران 3 و4
 *   يحرسان الطرفين: مرفوضٌ وهو معطّل، مقبولٌ حين يُفعّله مشرف — بلا نشر كود.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(SettingSeeder::class);
    app(SettingsRepository::class)->flush();

    Storage::fake('private');
});

it('1 — الموظّف يرفع سيرته الذاتية، وتُخزَّن على قرصٍ خاصّ', function () {
    $profile = EmployeeProfile::factory()->create();

    $this->actingAs($profile->user)
        ->postJson('/api/v1/profile/attachments', [
            'type' => 'cv',
            'file' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
        ])
        ->assertCreated()
        ->assertJsonPath('attachment.type', 'cv')

        // ⚠️ لا مسارَ تخزينٍ ولا قرصَ يغادر الخادم — رابطٌ مباشر يتجاوز الحجب.
        ->assertJsonMissingPath('attachment.path')
        ->assertJsonMissingPath('attachment.disk');

    $attachment = Attachment::query()->firstOrFail();

    expect($attachment->disk)->toBe('private')
        ->and($attachment->is_private)->toBeTrue();

    Storage::disk('private')->assertExists($attachment->path);
});

it('2 — DOCX وPNG وJPG مقبولة، والتنفيذيّ مرفوض', function () {
    $profile = EmployeeProfile::factory()->create();

    foreach (['cv.docx', 'cert.png', 'cert.jpg'] as $name) {
        $this->actingAs($profile->user)
            ->postJson('/api/v1/profile/attachments', [
                'type' => 'cv',
                'file' => UploadedFile::fake()->create($name, 50),
            ])
            ->assertCreated();
    }

    // `mimes` يفحص المحتوى لا الامتداد.
    $this->actingAs($profile->user)
        ->postJson('/api/v1/profile/attachments', [
            'type' => 'cv',
            'file' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'),
        ])
        ->assertStatus(422);
});

it('★ 3 — حُسن السيرة مرفوض ما دام المفتاح معطّلاً (ADR-013)', function () {
    $profile = EmployeeProfile::factory()->create();

    expect(settings(SettingKeys::FEATURE_GOOD_CONDUCT_UPLOAD))->toBeFalse();

    $this->actingAs($profile->user)
        ->postJson('/api/v1/profile/attachments', [
            'type' => 'good_conduct',
            'file' => UploadedFile::fake()->create('conduct.pdf', 50, 'application/pdf'),
        ])
        ->assertStatus(422);

    expect(Attachment::query()->where('type', AttachmentType::GoodConduct)->exists())->toBeFalse();
});

it('★ 4 — يُقبل فور تفعيل المشرف للمفتاح — بلا نشر كود', function () {
    $profile = EmployeeProfile::factory()->create();

    app(SettingsRepository::class)->set(SettingKeys::FEATURE_GOOD_CONDUCT_UPLOAD, true);

    $this->actingAs($profile->user)
        ->postJson('/api/v1/profile/attachments', [
            'type' => 'good_conduct',
            'file' => UploadedFile::fake()->create('conduct.pdf', 50, 'application/pdf'),
        ])
        ->assertCreated();
});

it('5 — حذف المرفق يمحوه من القرص لا من الجدول وحده', function () {
    $profile = EmployeeProfile::factory()->create();

    $uuid = $this->actingAs($profile->user)
        ->postJson('/api/v1/profile/attachments', [
            'type' => 'cv',
            'file' => UploadedFile::fake()->create('cv.pdf', 50, 'application/pdf'),
        ])
        ->json('attachment.uuid');

    $path = Attachment::query()->firstOrFail()->path;

    $this->actingAs($profile->user)
        ->deleteJson("/api/v1/profile/attachments/{$uuid}")
        ->assertOk();

    expect(Attachment::query()->count())->toBe(0);

    // القانون 13: لا يبقى أثرُ بيانٍ حذفه صاحبه.
    Storage::disk('private')->assertMissing($path);
});

it('★ 6 — شركةٌ دفعت رسم الكشف تقرأ السيرة ولا تحذفها', function () {
    $profile = EmployeeProfile::factory()->create();
    $company = CompanyProfile::factory()->create();

    $uuid = $this->actingAs($profile->user)
        ->postJson('/api/v1/profile/attachments', [
            'type' => 'cv',
            'file' => UploadedFile::fake()->create('cv.pdf', 50, 'application/pdf'),
        ])
        ->json('attachment.uuid');

    // الحذف حقُّ صاحب الملفّ وحده — الكشف يشتري القراءة لا الإتلاف.
    $this->actingAs($company->user)
        ->deleteJson("/api/v1/profile/attachments/{$uuid}")
        ->assertForbidden();

    expect(Attachment::query()->count())->toBe(1);
});

it('7 — السيرة الذاتية تُدخل في حساب الاكتمال، وحذفها يُنقصه', function () {
    $profile = EmployeeProfile::factory()->create();

    $this->actingAs($profile->user)->putJson('/api/v1/profile', ['title' => 'مهندس'])->assertOk();
    $before = $profile->fresh()->completeness;

    $uuid = $this->actingAs($profile->user)
        ->postJson('/api/v1/profile/attachments', [
            'type' => 'cv',
            'file' => UploadedFile::fake()->create('cv.pdf', 50, 'application/pdf'),
        ])
        ->json('attachment.uuid');

    $this->actingAs($profile->user)->putJson('/api/v1/profile', [])->assertOk();
    $withCv = $profile->fresh()->completeness;

    expect($withCv)->toBeGreaterThan($before);

    $this->actingAs($profile->user)->deleteJson("/api/v1/profile/attachments/{$uuid}")->assertOk();

    expect($profile->fresh()->completeness)->toBeLessThan($withCv);
});
