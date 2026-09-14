<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Scholarship;
use App\Models\Sponsor;
use App\Models\User;
use App\Services\ScholarshipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScholarshipController extends Controller
{
    public function __construct(private readonly ScholarshipService $scholarships) {}

    public function index(): View
    {
        return view('admin.scholarships', [
            'scholarships' => Scholarship::with(['user', 'sponsor'])->latest()->get(),
            'sponsors' => Sponsor::latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'duration' => ['required', 'in:30,90,180,365,unlimited'],
            'sponsor_id' => ['nullable', 'exists:sponsors,id'],
            'reason' => ['nullable', 'string', 'max:300'],
        ]);
        $user = User::where('email', $data['email'])->firstOrFail();
        $sponsor = ! empty($data['sponsor_id']) ? Sponsor::find($data['sponsor_id']) : null;
        $this->scholarships->grant($user, $data['duration'] === 'unlimited' ? null : (int) $data['duration'], $sponsor, $data['reason'] ?? null, $request->user()->id);

        return back()->with('status', "Bolsa concedida a {$user->name}.");
    }

    public function revoke(Request $request, Scholarship $scholarship): RedirectResponse
    {
        $this->scholarships->revoke($scholarship, $request->user()->id);

        return back()->with('status', 'Bolsa revogada.');
    }

    public function storeSponsor(Request $request): RedirectResponse
    {
        Sponsor::create($request->validate(['name' => ['required', 'string', 'max:120'], 'seats' => ['required', 'integer', 'min:1']]));

        return back()->with('status', 'Patrocinador criado.');
    }
}
