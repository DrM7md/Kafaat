<?php

declare(strict_types=1);

it('يستجيب لمسار الفحص الصحّي', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJson([
            'status' => 'ok',
            'service' => 'Kafaat',
            'version' => 'v1',
            'database' => 'up',
        ]);
});

it('يقدّم المسارات تحت البادئة api/v1 لا api', function () {
    $this->getJson('/api/health')->assertNotFound();
});
