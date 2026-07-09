<?php

declare(strict_types=1);

namespace App\Services\Masking;

/**
 * مجموعة الحقول المرئية لمشاهدٍ بعينه من ملفٍّ بعينه.
 *
 * التمثيل: «كل الحقول، عدا هذه المحجوبة». قائمة الحجب تأتي من الإعدادات
 * لا من الكود، فتغييرها لا يحتاج نشراً (Brain/05).
 *
 * ★ نقطة التصميم الجوهرية: `filter()` **يحذف المفاتيح** المحجوبة،
 *   ولا يضع مكانها `null`.
 *
 *   لماذا؟ لأن `['name' => null]` يكشف بنية الحقل، ويسهّل الاستنتاج،
 *   ويُغري المطوّر لاحقاً بملئه. الغياب التامّ هو العقد. ADR-003.
 *
 *   اختبار القبول: assertArrayNotHasKey('name', $response) — لا assertNull.
 */
final readonly class FieldSet
{
    /** @param list<string> $hidden */
    private function __construct(private array $hidden) {}

    /** لا شيء محجوب — للمالك والمشرف وصاحب الكشف الصالح. */
    public static function all(): self
    {
        return new self([]);
    }

    /** @param list<string> $hidden */
    public static function allExcept(array $hidden): self
    {
        return new self(array_values(array_unique($hidden)));
    }

    /** @param list<string> $hidden */
    public function except(array $hidden): self
    {
        return self::allExcept([...$this->hidden, ...$hidden]);
    }

    public function has(string $field): bool
    {
        return ! in_array($field, $this->hidden, true);
    }

    /**
     * يبني الاستجابة: كل مفتاحٍ محجوب **يختفي تماماً**.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function filter(array $payload): array
    {
        if ($this->hidden === []) {
            return $payload;
        }

        return array_filter(
            $payload,
            fn (string $key): bool => $this->has($key),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /** @return list<string> */
    public function hidden(): array
    {
        return $this->hidden;
    }

    public function isFullyVisible(): bool
    {
        return $this->hidden === [];
    }
}
