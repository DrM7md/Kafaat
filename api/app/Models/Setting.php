<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * ⚠️ لا تقرأ من هذا النموذج مباشرةً في منطق العمل.
 * استعمل settings('key') أو SettingsRepository — فهما يقرآن من الكاش
 * ويُصبّبان النوع. Brain/05-settings-engine.md §2.
 *
 * @property string $key
 * @property mixed $value
 * @property string $type
 * @property string $group
 * @property array<int, mixed>|null $options
 */
#[Fillable(['key', 'value', 'type', 'group', 'options', 'is_public', 'description'])]
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'array',
            'options' => 'array',
            'is_public' => 'boolean',
        ];
    }
}
