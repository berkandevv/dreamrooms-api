<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RoomTypeController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->toString();
        $hotelId = $request->string('hotel_id', 'all')->toString();
        $status = $request->string('status', 'all')->toString();

        $roomTypes = RoomType::query()
            ->with('hotel:id,name,city,country')
            ->withCount(['bookings', 'availability'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('bed_type', 'like', "%{$search}%")
                        ->orWhereHas('hotel', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($hotelId !== 'all', fn ($query) => $query->where('hotel_id', $hotelId))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.room-types.index', [
            'roomTypes' => $roomTypes,
            'hotels' => $this->hotels(),
            'search' => $search,
            'hotelId' => $hotelId,
            'status' => $status,
            'statuses' => $this->statuses(),
        ]);
    }

    public function edit(RoomType $roomType): View
    {
        return view('admin.room-types.edit', [
            'roomType' => $roomType->load('hotel:id,name'),
            'hotels' => $this->hotels(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function update(Request $request, RoomType $roomType): RedirectResponse
    {
        $validated = $request->validate([
            'hotel_id' => ['required', 'integer', 'exists:hotels,id'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'capacity_adults' => ['required', 'integer', 'min:1', 'max:255'],
            'capacity_children' => ['required', 'integer', 'min:0', 'max:255'],
            'size_m2' => ['nullable', 'numeric', 'min:0'],
            'bed_type' => ['nullable', 'string', 'max:100'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'total_units' => ['required', 'integer', 'min:1', 'max:65535'],
            'status' => ['required', Rule::in($this->statuses())],
        ]);

        $this->validateTotalUnits($roomType, (int) $validated['total_units']);

        $roomType->update($validated);

        return redirect()
            ->route('admin.room-types.edit', $roomType)
            ->with('status', 'room-type-updated');
    }

    private function validateTotalUnits(RoomType $roomType, int $totalUnits): void
    {
        $maxAvailableUnits = (int) $roomType->availability()->max('available_units');

        if ($maxAvailableUnits > $totalUnits) {
            throw ValidationException::withMessages([
                'total_units' => ["Total units cannot be lower than existing availability ({$maxAvailableUnits})."],
            ]);
        }
    }

    private function hotels()
    {
        return Hotel::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function statuses(): array
    {
        return ['active', 'inactive'];
    }
}
