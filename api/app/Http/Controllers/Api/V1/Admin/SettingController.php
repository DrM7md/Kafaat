<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Settings\SettingsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SettingController extends Controller
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function index(): JsonResponse
    {
        $rows = Setting::query()->orderBy('group')->orderBy('key')->get();

        return response()->json([
            'settings' => $rows->map(fn (Setting $s): array => [
                'key' => $s->key,
                'value' => $this->settings->get($s->key),
                'type' => $s->type,
                'group' => $s->group,
                'options' => $s->options,
                'is_public' => $s->is_public,
                'description' => $s->description,
            ])->groupBy('group'),
        ]);
    }

    /**
     * كل حفظ يمرّ بحرّاس SettingsRepository ويكتب أثراً في audit_logs
     * بالقيمة قبل وبعد.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'exists:settings,key'],
            'value' => ['present'],

            // ★ رفع الحجب عن الاسم/الهاتف/البريد لا يقع صدفةً.
            'confirmed' => ['sometimes', 'boolean'],
        ]);

        $this->settings->set(
            (string) $data['key'],
            $data['value'],
            $request->boolean('confirmed'),
        );

        return response()->json([
            'message' => 'حُدِّث الإعداد.',
            'value' => $this->settings->get((string) $data['key']),
        ]);
    }

    /** الإعدادات العامّة — لغير المصادَقين أيضاً. */
    public function publicSettings(): JsonResponse
    {
        return response()->json(['settings' => $this->settings->public()]);
    }
}
