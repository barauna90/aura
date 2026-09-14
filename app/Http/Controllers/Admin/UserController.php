<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $users = User::withTrashed()->with(['subscriptions' => fn ($s) => $s->whereIn('status', ['ACTIVE', 'TRIALING'])->with('plan')])
            ->when($q, fn ($query) => $query->where(fn ($w) => $w->where('email', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%")))
            ->latest()->paginate(30)->withQueryString();

        return view('admin.users', compact('users', 'q'));
    }

    public function update(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', Rule::in(User::ROLES)], 'is_active' => ['nullable', 'boolean']]);
        if ($data['role'] === 'SUPER_ADMIN' && ! $request->user()->hasRole('SUPER_ADMIN')) {
            abort(403);
        }
        $user->update(['role' => $data['role'], 'is_active' => $request->boolean('is_active')]);
        $audit->log('user.updated', $request->user()->id, 'User', $user->id, $data);

        return back()->with('status', 'Usuário atualizado.');
    }
}
