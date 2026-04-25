<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->toString();
        $role = $request->string('role', 'all')->toString();
        $status = $request->string('status', 'all')->toString();

        $users = User::query()
            ->with('role')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($role !== 'all', function ($query) use ($role): void {
                $query->whereHas('role', fn ($query) => $query->where('name', $role));
            })
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'search' => $search,
            'role' => $role,
            'status' => $status,
            'roles' => Role::query()->orderBy('name')->get(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function edit(User $user): View
    {
        $this->ensureManageable($user);

        return view('admin.users.edit', [
            'user' => $user->load('role'),
            'roles' => $this->assignableRoles(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureManageable($user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role_id' => ['required', 'integer', Rule::in($this->assignableRoleIds())],
            'status' => ['required', Rule::in($this->statuses())],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
        ]);

        if ($user->is($request->user()) && $validated['status'] !== 'active') {
            return back()
                ->withErrors(['status' => 'You cannot deactivate your own user.'])
                ->withInput();
        }

        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }

        $user->update($validated);

        return redirect()
            ->route('admin.users.edit', $user)
            ->with('status', 'user-updated');
    }

    private function statuses(): array
    {
        return ['active', 'inactive', 'suspended'];
    }

    private function assignableRoles()
    {
        return Role::query()
            ->whereIn('name', ['owner', 'customer'])
            ->orderBy('name')
            ->get();
    }

    private function assignableRoleIds(): array
    {
        return $this->assignableRoles()
            ->pluck('id')
            ->all();
    }

    private function ensureManageable(User $user): void
    {
        if ($user->loadMissing('role')->role?->name === 'admin') {
            abort(403, 'Admin users cannot be managed from this panel.');
        }
    }
}
