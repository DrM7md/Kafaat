<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            LookupSeeder::class,
            SettingSeeder::class,
        ]);

        $admin = User::firstOrCreate(
            ['email' => 'admin@kafaat.qa'],
            ['name' => 'المشرف العام', 'password' => 'password'],
        );

        // type/status عبر forceFill — خارج التعبئة الجماعية (يملكهما الخادم).
        // القيمة الافتراضية للعمود «موظّف»، فبلا هذا يُبذَر المشرف بنوعٍ خاطئ.
        $admin->forceFill([
            'type' => UserType::Admin,
            'status' => UserStatus::Active,
        ])->save();

        $admin->syncRoles([Roles::SUPER_ADMIN]);

        if (app()->environment('local')) {
            $this->call(DemoSeeder::class);
        }
    }
}
