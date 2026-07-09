<?php

declare(strict_types=1);

use App\Services\Settings\SettingsRepository;

if (! function_exists('settings')) {
    /**
     * قراءة إعداد ديناميكي — من الكاش، مُصبَّب النوع.
     *
     * ⚠️ لا تكتب قيمةً ثابتة لقاعدة عملٍ مؤثّرة. Brain/05-settings-engine.md §1.
     */
    function settings(string $key, mixed $default = null): mixed
    {
        return app(SettingsRepository::class)->get($key, $default);
    }
}
