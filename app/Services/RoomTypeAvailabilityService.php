<?php

namespace App\Services;

use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RoomTypeAvailabilityService
{
    public function buildStayData(array $validated): array
    {
        $checkIn = CarbonImmutable::parse($validated['check_in']);
        $checkOut = CarbonImmutable::parse($validated['check_out']);

        return [
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'nights' => (int) $checkIn->diffInDays($checkOut),
            'units_booked' => (int) ($validated['units_booked'] ?? 1),
            'children_count' => (int) ($validated['children_count'] ?? 0),
            'stay_dates' => $this->buildStayDates($checkIn, $checkOut),
        ];
    }

    public function buildStayDates(CarbonImmutable $checkIn, CarbonImmutable $checkOut): array
    {
        $stayDates = [];

        for ($date = $checkIn; $date->lt($checkOut); $date = $date->addDay()) {
            $stayDates[] = $date->toDateString();
        }

        return $stayDates;
    }

    public function availabilityForStay(RoomType $roomType, array $stayDates, bool $lock = false): Collection
    {
        $query = $roomType->availability()
            ->whereIn('date', $stayDates)
            ->orderBy('date');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query
            ->get()
            ->keyBy(fn ($day) => $day->date->toDateString());
    }

    public function validateAvailability(Collection $availability, array $stayDates, int $nights, int $unitsBooked): void
    {
        if ($availability->count() !== $nights) {
            throw ValidationException::withMessages([
                'check_in' => ['There is no availability for every night in the selected stay.'],
            ]);
        }

        foreach ($stayDates as $stayDate) {
            $day = $availability[$stayDate];

            if ($day->status !== 'open' || $day->available_units < $unitsBooked) {
                throw ValidationException::withMessages([
                    'check_in' => ['The selected room type is not available for the requested dates.'],
                ]);
            }

            if ($day->min_stay_nights !== null && $nights < $day->min_stay_nights) {
                throw ValidationException::withMessages([
                    'check_out' => ["The selected dates require at least {$day->min_stay_nights} nights."],
                ]);
            }
        }
    }

    public function quoteStay(RoomType $roomType, array $stayData): array
    {
        $availability = $this->availabilityForStay($roomType, $stayData['stay_dates']);
        $issues = $this->availabilityIssues(
            $availability,
            $stayData['stay_dates'],
            $stayData['nights'],
            $stayData['units_booked'],
        );
        $amounts = $this->calculateAmounts($availability, $roomType, $stayData['units_booked']);
        $inventory = $this->inventorySummary($availability, $roomType, $stayData['stay_dates'], $stayData['units_booked'], $issues);

        return [
            'room_type_id' => $roomType->id,
            'check_in' => $stayData['check_in']->toDateString(),
            'check_out' => $stayData['check_out']->toDateString(),
            'nights' => $stayData['nights'],
            'stay_dates' => $stayData['stay_dates'],
            'units_booked' => $stayData['units_booked'],
            'is_available' => $issues['is_available'],
            'total_units' => $inventory['total_units'],
            'available_units_for_stay' => $inventory['available_units_for_stay'],
            'remaining_units_after_booking' => $inventory['remaining_units_after_booking'],
            'daily_available_units' => $inventory['daily_available_units'],
            'availability_issues' => $issues,
            'subtotal_amount' => $amounts['subtotal'],
            'taxes_amount' => $amounts['taxes'],
            'discount_amount' => $amounts['discount'],
            'total_amount' => $amounts['total'],
            'currency' => $roomType->currency ?? 'EUR',
        ];
    }

    public function calculateAmounts(Collection $availability, RoomType $roomType, int $unitsBooked): array
    {
        $subtotal = $availability->sum(fn ($day) => (float) $day->price) * $unitsBooked;
        $taxes = round($subtotal * ((float) $roomType->hotel->tax_rate_percent / 100), 2);
        $discount = round($subtotal * ((float) $roomType->hotel->discount_rate_percent / 100), 2);

        return [
            'subtotal' => round($subtotal, 2),
            'taxes' => $taxes,
            'discount' => $discount,
            'total' => round($subtotal + $taxes - $discount, 2),
        ];
    }

    private function availabilityIssues(Collection $availability, array $stayDates, int $nights, int $unitsBooked): array
    {
        $missingDates = [];
        $closedDates = [];
        $insufficientDates = [];
        $minStayViolations = [];

        foreach ($stayDates as $stayDate) {
            $day = $availability->get($stayDate);

            if (! $day) {
                $missingDates[] = $stayDate;

                continue;
            }

            if ($day->status !== 'open') {
                $closedDates[] = $stayDate;
            }

            if ($day->available_units < $unitsBooked) {
                $insufficientDates[] = [
                    'date' => $stayDate,
                    'available_units' => $day->available_units,
                    'requested_units' => $unitsBooked,
                ];
            }

            if ($day->min_stay_nights !== null && $nights < $day->min_stay_nights) {
                $minStayViolations[] = [
                    'date' => $stayDate,
                    'min_stay_nights' => $day->min_stay_nights,
                    'requested_nights' => $nights,
                ];
            }
        }

        return [
            'is_available' => $missingDates === []
                && $closedDates === []
                && $insufficientDates === []
                && $minStayViolations === [],
            'missing_dates' => $missingDates,
            'closed_dates' => $closedDates,
            'insufficient_dates' => $insufficientDates,
            'min_stay_violations' => $minStayViolations,
        ];
    }

    private function inventorySummary(
        Collection $availability,
        RoomType $roomType,
        array $stayDates,
        int $unitsBooked,
        array $issues,
    ): array {
        $dailyAvailableUnits = collect($stayDates)
            ->map(function (string $stayDate) use ($availability): array {
                $day = $availability->get($stayDate);

                return [
                    'date' => $stayDate,
                    'available_units' => $day?->available_units ?? 0,
                    'status' => $day?->status ?? 'missing',
                ];
            })
            ->values()
            ->all();

        $availableUnitsForStay = $issues['is_available']
            ? collect($dailyAvailableUnits)->min('available_units')
            : 0;

        return [
            'total_units' => $roomType->total_units,
            'available_units_for_stay' => $availableUnitsForStay,
            'remaining_units_after_booking' => max($availableUnitsForStay - $unitsBooked, 0),
            'daily_available_units' => $dailyAvailableUnits,
        ];
    }
}
