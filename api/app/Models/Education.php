<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $institution
 * @property string $degree
 * @property string|null $field_of_study
 * @property int|null $graduated_year
 */
#[Fillable(['institution', 'degree', 'field_of_study', 'graduated_year'])]
class Education extends Model
{
    /** «education» غير معدودة عند مُجمِّع Laravel، فيستنتج جدولاً باسم `education`. */
    protected $table = 'educations';

    /** @return BelongsTo<EmployeeProfile, $this> */
    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }
}
