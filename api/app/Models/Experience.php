<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * ⚠️ `company_name` محجوب إذا `is_current` — يعرّض الموظّف لصاحب عمله.
 * الخبرات السابقة مكشوفة: الماضي لا يعرّضه للخطر. Brain/03 §2.
 *
 * @property string $company_name
 * @property bool $is_current
 * @property string $title
 * @property Carbon $started_on
 * @property Carbon|null $ended_on
 * @property string|null $description
 */
#[Fillable(['company_name', 'is_current', 'title', 'started_on', 'ended_on', 'description'])]
class Experience extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'started_on' => 'date',
            'ended_on' => 'date',
        ];
    }

    /** @return BelongsTo<EmployeeProfile, $this> */
    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }
}
