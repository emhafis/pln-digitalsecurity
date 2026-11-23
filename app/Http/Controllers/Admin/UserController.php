<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    private const GROUPS = ['security', 'admin'];

    /**
     * User list, filtered by group (security | admin).
     */
    public function index(Request $request): Response
    {
        $group = $this->group($request);
        $scope = $this->unitScope($request);

        if ($group === 'admin' && $scope !== null) {
            abort(403);
        }

        $search = trim((string) $request->query('search', ''));

        $sortColumns = [
            'username' => 'users.username',
            'name' => 'users.name',
            'email' => 'users.email',
            'role' => 'users.role',
            'status' => 'users.is_active',
        ];

        $sort = (string) $request->query('sort', 'username');
        if (! in_array($sort, ['username', 'name', 'email', 'unit', 'role', 'status'], true)) {
            $sort = 'username';
        }
        $dir = $request->query('dir', 'asc') === 'desc' ? 'desc' : 'asc';

        $query = User::query()
            ->with('unit')
            ->when($scope !== null, fn ($query) => $query
                ->whereIn('users.unit_id', $scope)
                ->where('users.role', '!=', 'superadmin'))
            ->when($group === 'security', fn ($query) => $query->where('role', 'security'))
            ->when($group === 'admin', fn ($query) => $query->whereIn('role', ['admin', 'superadmin']))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('username', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });

        if ($sort === 'unit') {
            $query->leftJoin('units', 'users.unit_id', '=', 'units.id')
                ->orderBy('units.unit_name', $dir)
                ->select('users.*');
        } else {
            $query->orderBy($sortColumns[$sort], $dir);
        }

        $paginator = $query->paginate(50);

        return Inertia::render('Admin/User/Index', [
            'users' => $paginator->getCollection()->map(fn (User $user) => [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'unit_name' => $user->unit?->unit_name ?? '-',
                'status' => $user->is_active ? 'Aktif' : 'Nonaktif',
            ]),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'group' => $group,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    /**
     * User create form.
     */
    public function create(Request $request): Response
    {
        $scope = $this->unitScope($request);

        if ($this->group($request) === 'admin' && $scope !== null) {
            abort(403);
        }

        if ($scope !== null && $request->user()?->unit_id === null) {
            abort(403);
        }

        return Inertia::render('Admin/User/Create', [
            'group' => $this->group($request),
            'units' => $this->units($scope),
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request): RedirectResponse
    {
        $group = $this->group($request);
        $scope = $this->unitScope($request);

        if ($group === 'admin' && $scope !== null) {
            abort(403);
        }

        if ($scope !== null && $request->user()?->unit_id === null) {
            abort(403);
        }

        $allowedRoles = $group === 'admin'
            ? ['admin', 'superadmin']
            : ['security'];

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'unit_id' => $scope === null
                ? ['nullable', 'integer', 'exists:units,id']
                : ['required', 'integer', Rule::in($scope)],
            'role' => ['required', 'string', Rule::in($allowedRoles)],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            'username' => $validated['username'],
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'password' => Hash::make($validated['password']),
            'unit_id' => $validated['unit_id'] ?? null,
            'role' => $validated['role'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        app(ActivityLogService::class)->log([
            'action' => 'created',
            'module' => 'Akun User',
            'description' => 'Membuat akun user: '.$user->username.' ('.$user->role.')',
            'subject' => $user,
            'unit_id' => $user->unit_id,
            'new_values' => $user->only(['username', 'name', 'email', 'role', 'unit_id', 'is_active']),
        ]);

        return redirect()
            ->route('admin.user.index', ['role' => $group])
            ->with('success', 'User berhasil ditambahkan.');
    }

    /**
     * User edit form.
     */
    public function edit(Request $request, int $id): Response
    {
        $user = $this->scopedUser($request, $id)->load('unit');
        $scope = $this->unitScope($request);

        return Inertia::render('Admin/User/Edit', [
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'unit_id' => $user->unit_id,
                'role' => $user->role,
                'is_active' => $user->is_active,
            ],
            'group' => $user->role === 'security' ? 'security' : 'admin',
            'units' => $this->units($scope),
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $user = $this->scopedUser($request, $id);
        $group = $user->role === 'security' ? 'security' : 'admin';
        $scope = $this->unitScope($request);

        $allowedRoles = $group === 'admin'
            ? ['admin', 'superadmin']
            : ['security'];

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255', 'unique:users,username,'.$user->id],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:8'],
            'unit_id' => $scope === null
                ? ['nullable', 'integer', 'exists:units,id']
                : ['required', 'integer', Rule::in($scope)],
            'role' => ['required', 'string', Rule::in($allowedRoles)],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        } else {
            $validated['password'] = Hash::make($validated['password']);
        }

        $old = $user->only(['username', 'name', 'email', 'role', 'unit_id', 'is_active']);
        $user->update($validated);

        app(ActivityLogService::class)->log([
            'action' => 'updated',
            'module' => 'Akun User',
            'description' => 'Mengubah akun user: '.$user->username,
            'subject' => $user,
            'unit_id' => $user->unit_id,
            'old_values' => $old,
            'new_values' => $user->only(['username', 'name', 'email', 'role', 'unit_id', 'is_active']),
        ]);

        return redirect()
            ->route('admin.user.index', ['role' => $group])
            ->with('success', 'User berhasil diperbarui.');
    }

    /**
     * Remove the specified user.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        if ((int) $request->user()?->id === $id) {
            return back()->with('error', 'Tidak dapat menghapus akun sendiri.');
        }

        $user = $this->scopedUser($request, $id);
        $group = $user->role === 'security' ? 'security' : 'admin';
        $old = $user->only(['id', 'username', 'name', 'email', 'role', 'unit_id', 'is_active']);
        $user->delete();

        app(ActivityLogService::class)->log([
            'action' => 'deleted',
            'module' => 'Akun User',
            'description' => 'Menghapus akun user: '.($old['username'] ?? '-'),
            'subject_type' => User::class,
            'subject_id' => $old['id'] ?? $id,
            'unit_id' => $old['unit_id'] ?? null,
            'old_values' => $old,
        ]);

        return redirect()
            ->route('admin.user.index', ['role' => $group])
            ->with('success', 'User berhasil dihapus.');
    }

    private function group(Request $request): string
    {
        $group = (string) $request->query('role', 'security');

        return in_array($group, self::GROUPS, true) ? $group : 'security';
    }

    /**
     * Unit scope for the current user.
     *
     * - null  => unlimited (superadmin).
     * - array => allowed unit ids (user unit + descendants), [] when none.
     *
     * @return array<int, int>|null
     */
    private function unitScope(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        if ($user->role === 'superadmin') {
            return null;
        }

        if ($user->role !== 'admin' || $user->unit_id === null) {
            return [];
        }

        return array_map('intval', $user->unit?->descendantIds() ?? [(int) $user->unit_id]);
    }

    private function scopedUser(Request $request, int $id): User
    {
        $user = User::findOrFail($id);
        $scope = $this->unitScope($request);

        if ($scope !== null) {
            if ($user->role !== 'security'
                || $user->unit_id === null
                || ! in_array((int) $user->unit_id, $scope, true)) {
                abort(403);
            }
        }

        return $user;
    }

    /**
     * @param  array<int, int>|null  $scope
     * @return array<int, array{value: int, label: string}>
     */
    private function units(?array $scope): array
    {
        return Unit::query()
            ->when($scope !== null, fn ($query) => $query->whereIn('id', $scope))
            ->orderBy('unit_name')
            ->get(['id', 'unit_name'])
            ->map(fn (Unit $unit) => [
                'value' => $unit->id,
                'label' => $unit->unit_name,
            ])
            ->toArray();
    }
}
