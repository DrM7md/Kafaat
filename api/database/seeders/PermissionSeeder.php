<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\Permissions;
use App\Support\Roles;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * قابل لإعادة التشغيل (idempotent): تشغيله مرّتين لا يُكرّر ولا يفقد إسنادات.
 * هذا شرط قبول في Brain/04-rbac.md §8.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (Roles::seed() as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');

            // syncPermissions يجعل البذرة idempotent: الحالة النهائية واحدة
            // مهما تكرّر التشغيل. ولا يمسّ الأدوار التي أنشأها المشرف.
            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
