<?php

namespace App\Http\Controllers\Admin;

use App\Models\Hotel;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HotelController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->toString();
        $status = $request->string('status', 'all')->toString();

        $hotels = Hotel::query()
            ->with('owner:id,name,email')
            ->withCount(['bookings', 'roomTypes'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('country', 'like', "%{$search}%")
                        ->orWhereHas('owner', fn ($query) => $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.hotels.index', [
            'hotels' => $hotels,
            'search' => $search,
            'status' => $status,
            'statuses' => $this->statuses(),
        ]);
    }

    public function edit(Hotel $hotel): View
    {
        return view('admin.hotels.edit', [
            'hotel' => $hotel->load('owner:id,name,email'),
            'owners' => $this->owners(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function update(Request $request, Hotel $hotel): RedirectResponse
    {
        $validated = $request->validate([
            'owner_user_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role_id', $this->ownerRoleIds()))],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'stars' => ['required', 'integer', 'min:1', 'max:5'],
            'country' => ['required', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'cancellation_policy' => ['nullable', 'string'],
            'tax_rate_percent' => ['required', 'numeric', 'between:0,100'],
            'discount_rate_percent' => ['required', 'numeric', 'between:0,100'],
            'pets_allowed' => ['nullable', 'boolean'],
            'smoking_allowed' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in($this->statuses())],
        ]);

        $validated['pets_allowed'] = $request->boolean('pets_allowed');
        $validated['smoking_allowed'] = $request->boolean('smoking_allowed');

        if ($validated['name'] !== $hotel->name) {
            $validated['slug'] = $this->generateUniqueSlug($validated['name'], $hotel->id);
        }

        $hotel->update($validated);

        return redirect()
            ->route('admin.hotels.edit', $hotel)
            ->with('status', 'hotel-updated');
    }

    private function owners()
    {
        return User::query()
            ->whereIn('role_id', $this->ownerRoleIds())
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    private function ownerRoleIds(): array
    {
        return \App\Models\Role::query()
            ->where('name', 'owner')
            ->pluck('id')
            ->all();
    }

    private function statuses(): array
    {
        return ['draft', 'published', 'inactive'];
    }

    private function generateUniqueSlug(string $name, ?int $ignoreHotelId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 2;

        while (Hotel::query()
            ->where('slug', $slug)
            ->when($ignoreHotelId, fn ($query) => $query->whereKeyNot($ignoreHotelId))
            ->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
