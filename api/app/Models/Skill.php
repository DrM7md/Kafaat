<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 */
#[Fillable(['name', 'slug'])]
class Skill extends Model
{
    /** @return BelongsToMany<EmployeeProfile, $this> */
    public function employeeProfiles(): BelongsToMany
    {
        return $this->belongsToMany(EmployeeProfile::class);
    }
}
