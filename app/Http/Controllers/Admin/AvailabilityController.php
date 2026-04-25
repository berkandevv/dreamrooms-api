<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\RoomType;
use App\Models\RoomTypeAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AvailabilityController extends Controller
{
    public function index(Request $request): View
    {
        $hotelId = $request->string('hotel_id', 'all')->toString();
        $roomTypeId = $request->string('room_type_id', 'all')->toString();
        $status = $request->string('status', 'all')->toString();
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        $availability = RoomTypeAvailability::query()
            ->with('roomType.hotel:id,name')
            ->when($hotelId !== 'all', fn ($query) => $query->whereHas('roomType', fn ($query) => $query->where('hotel_id', $hotelId)))
            ->when($roomTypeId !== 'all', fn ($query) => $query->where('room_type_id', $roomTypeId))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($from !== '', fn ($query) => $query->whereDate('date', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('date', '<=', $to))
            ->orderBy('date')
            ->orderBy('room_type_id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.availability.index', [
            'availability' => $availability,
            'hotels' => $this->hotels(),
            'roomTypes' => $this->roomTypes(),
            'hotelId' => $hotelId,
            'roomTypeId' => $roomTypeId,
            'status' => $status,
            'from' => $from,
            'to' => $to,
            'statuses' => $this->statuses(),
        ]);
    }

    public function edit(RoomTypeAvailability $availability): View
    {
        return view('admin.availability.edit', [
            'availability' => $availability->load('roomType.hotel:id,name'),
            'statuses' => $this->statuses(),
        ]);
    }

    public function update(Request $request, RoomTypeAvailability $availability): RedirectResponse
    {
        $validated = $request->validate([
            'available_units' => ['required', 'integer', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in($this->statuses())],
            'min_stay_nights' => ['nullable', 'integer', 'min:1'],
        ]);

        $roomType = $availability->roomType;

        if ((int) $validated['available_units'] > $roomType->total_units) {
            throw ValidationException::withMessages([
                'available_units' => ["Available units cannot be greater than total units ({$roomType->total_units})."],
            ]);
        }

        $availability->update($validated);

        return redirect()
            ->route('admin.availability.edit', $availability)
            ->with('status', 'availability-updated');
    }

    private function hotels()
    {
        return Hotel::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function roomTypes()
    {
        return RoomType::query()
            ->with('hotel:id,name')
            ->orderBy('name')
            ->get(['id', 'hotel_id', 'name']);
    }

    private function statuses(): array
    {
        return ['open', 'closed'];
    }
}
