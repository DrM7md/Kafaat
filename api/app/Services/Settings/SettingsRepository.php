<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\Setting;
use App\Services\Audit\AuditLogger;
use App\Support\SettingKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * محرّك الإعدادات — Brain/05-settings-engine.md.
 *
 * يقرأ من الكاش لا من قاعدة البيانات في كل طلب.
 * كل كتابة تُبطل الكاش وتكتب صفّاً في audit_logs.
 */
final class SettingsRepository
{
    private const CACHE_KEY = 'kafaat.settings';

    /** @var array<string, mixed>|null */
    private ?array $memo = null;

    public function __construct(private readonly AuditLogger $audit) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * كل الإعدادات مُصبَّبة الأنواع.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->memo ??= Cache::rememberForever(self::CACHE_KEY, function (): array {
            return Setting::all()
                ->mapWithKeys(fn (Setting $s): array => [$s->key => $this->cast($s)])
                ->all();
        });
    }

    /**
     * الإعدادات العامّة فقط — ما يجوز إرساله إلى الواجهة.
     *
     * @return array<string, mixed>
     */
    public function public(): array
    {
        $publicKeys = Cache::rememberForever(self::CACHE_KEY.'.public_keys', fn (): array => Setting::query()
            ->where('is_public', true)
            ->pluck('key')
            ->all());

        return array_intersect_key($this->all(), array_flip($publicKeys));
    }

    /**
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        $keys = Setting::query()->where('group', $group)->pluck('key')->all();

        return array_intersect_key($this->all(), array_flip($keys));
    }

    /**
     * @throws ValidationException
     */
    public function set(string $key, mixed $value, bool $confirmed = false): void
    {
        $setting = Setting::query()->findOrFail($key);

        $this->guard($setting, $value, $confirmed);

        $before = $this->cast($setting);

        $setting->value = $value;
        $setting->save();

        $this->flush();

        // كل تعديل إعداد فعلٌ حرج — يُسجَّل بالقيمة قبل وبعد (Brain/04 §6).
        $this->audit->log('settings.updated', $setting, [
            'key' => $key,
            'before' => $before,
            'after' => $this->cast($setting->refresh()),
        ]);
    }

    public function flush(): void
    {
        $this->memo = null;
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_KEY.'.public_keys');
    }

    /**
     * حرّاس الإعدادات الخطرة.
     *
     * @throws ValidationException
     */
    private function guard(Setting $setting, mixed $value, bool $confirmed): void
    {
        $this->guardType($setting, $value);

        // القيم المحصورة
        if ($setting->type === 'enum' && $setting->options !== null && ! in_array($value, $setting->options, true)) {
            throw ValidationException::withMessages([
                'value' => 'القيمة غير مسموحة. الخيارات: '.implode('، ', array_map(strval(...), $setting->options)),
            ]);
        }

        // ★ رفع الحجب عن الاسم أو الهاتف أو البريد يعني منح المنتج مجّاناً.
        // لا يقع صدفةً: يتطلّب تأكيداً صريحاً من اللوحة.
        if ($setting->key === SettingKeys::MASKING_FIELDS && ! $confirmed) {
            $removed = array_diff(SettingKeys::protectedMaskedFields(), (array) $value);

            if ($removed !== []) {
                throw ValidationException::withMessages([
                    'value' => 'إزالة ('.implode('، ', $removed).') من الحقول المحجوبة تجعل بيانات '
                        .'التواصل مرئيةً للجميع بلا دفع. أرسل confirmed=true للمتابعة.',
                ]);
            }
        }
    }

    /**
     * النوع يُتحقَّق منه عند **الكتابة**، لا عند القراءة.
     *
     * `cast()` صبٌّ أعمى: النصّ `"false"` يصير `true`، و`"abc"` تصير `0`.
     * فإعدادٌ منطقيٌّ يُطفَأ بنصٍّ خاطئ كان **يُشعَل**، ورسمُ الكشف يصير
     * صفراً. الرفض عند الباب أرحم من صبٍّ صامت.
     *
     * @throws ValidationException
     */
    private function guardType(Setting $setting, mixed $value): void
    {
        $ok = match ($setting->type) {
            'int' => is_int($value) || (is_string($value) && ctype_digit(ltrim($value, '-'))),
            'bool' => is_bool($value),
            'string', 'enum' => is_string($value),
            'array', 'json' => is_array($value),
            default => true,
        };

        if (! $ok) {
            throw ValidationException::withMessages([
                'value' => "القيمة لا تطابق نوع الإعداد ({$setting->type}).",
            ]);
        }
    }

    private function cast(Setting $setting): mixed
    {
        // العمود مخزَّن json، فقيمته تعود بنوعها الأصلي. نصبّ صراحةً
        // ليكون العقد قاطعاً: settings('reveal.fee') يعيد int لا string.
        $value = $setting->getAttribute('value');

        return match ($setting->type) {
            'int' => (int) $value,
            'bool' => (bool) $value,
            'string', 'enum' => (string) $value,
            'array', 'json' => (array) $value,
            default => $value,
        };
    }
}
