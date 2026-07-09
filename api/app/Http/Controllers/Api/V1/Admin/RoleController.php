<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Permissions;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

final class RoleController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'roles' => Role::with('permissions')->get()->map(fn (Role $r): array => [
                'id' => $r->id,
                'name' => $r->name,
                'permissions' => $r->permissions->pluck('name')->all(),
                'users_count' => $r->users()->count(),
                'is_system' => in_array($r->name, [Roles::SUPER_ADMIN, Roles::EMPLOYEE, Roles::COMPANY], true),
            ]),

            // كتالوج الصلاحيات مجمّعاً، مع تمييز الحرجة لتُحاط بتحذير أحمر.
            'catalog' => Permissions::grouped(),
            'critical' => Permissions::critical(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', 'unique:roles,name'],
        ]);

        // query()->create() يُعيد نموذج Role لا عقد Spatie، فيقبله AuditLogger.
        $role = Role::query()->create(['name' => $data['name'], 'guard_name' => 'web']);

        $this->audit->log('role.created', $role, ['name' => $role->name]);

        return response()->json(['role' => $role], 201);
    }

    /**
     * حفظ تفاضلي (diff) لا استبدال كامل — ليُسجَّل في التدقيق ما تغيّر بدقّة.
     */
    public function syncPermissions(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'in:'.implode(',', Permissions::all())],
        ]);

        /** @var list<string> $after */
        $after = $data['permissions'];
        $before = $role->permissions->pluck('name')->all();

        $this->guardSelfLockout($request, $role, $after);

        $role->syncPermissions($after);

        $this->audit->log(AuditLogger::ROLE_PERMISSIONS_UPDATED, $role, [
            'role' => $role->name,
            'added' => array_values(array_diff($after, $before)),
            'removed' => array_values(array_diff($before, $after)),
        ]);

        return response()->json(['permissions' => $after]);
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        if (in_array($role->name, [Roles::SUPER_ADMIN, Roles::EMPLOYEE, Roles::COMPANY], true)) {
            throw ValidationException::withMessages([
                'role' => 'هذا دورٌ بنيويّ لا يُحذف. النظام يعتمد عليه عند التسجيل.',
            ]);
        }

        $this->audit->log('role.deleted', $role, ['name' => $role->name]);

        $role->delete();

        return response()->json(['message' => 'حُذف الدور.']);
    }

    /**
     * ★ قفل الباب من الداخل.
     *
     * لا يستطيع المستخدم نزع `rbac.manage` عن نفسه عبر دورٍ يملكه —
     * وإلّا حبس نفسه خارج اللوحة، وبقي النظام بلا من يديره.
     *
     * @param  list<string>  $after
     */
    private function guardSelfLockout(Request $request, Role $role, array $after): void
    {
        /** @var User $actor */
        $actor = $request->user();

        if ($actor->hasRole(Roles::SUPER_ADMIN)) {
            return;   // Gate::before يمنحه كل شيء بصرف النظر عن الأدوار.
        }

        $losesRbac = ! in_array(Permissions::RBAC_MANAGE, $after, true)
            && $role->hasPermissionTo(Permissions::RBAC_MANAGE);

        if ($losesRbac && $actor->hasRole($role->name) && $actor->getDirectPermissions()
            ->pluck('name')->doesntContain(Permissions::RBAC_MANAGE)) {
            throw ValidationException::withMessages([
                'permissions' => 'لا يمكنك نزع صلاحية إدارة الأدوار عن نفسك.',
            ]);
        }
    }
}
