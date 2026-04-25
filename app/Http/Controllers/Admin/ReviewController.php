<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->toString();
        $hotelId = $request->string('hotel_id', 'all')->toString();
        $status = $request->string('status', 'all')->toString();
        $rating = $request->string('rating', 'all')->toString();

        $reviews = Review::query()
            ->with(['hotel:id,name', 'user:id,name,email', 'booking:id,booking_reference'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('comment', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('hotel', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('booking', fn ($query) => $query->where('booking_reference', 'like', "%{$search}%"));
                });
            })
            ->when($hotelId !== 'all', fn ($query) => $query->where('hotel_id', $hotelId))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($rating !== 'all', fn ($query) => $query->where('rating', $rating))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.reviews.index', [
            'reviews' => $reviews,
            'hotels' => $this->hotels(),
            'search' => $search,
            'hotelId' => $hotelId,
            'status' => $status,
            'rating' => $rating,
            'statuses' => $this->statuses(),
        ]);
    }

    public function edit(Review $review): View
    {
        return view('admin.reviews.edit', [
            'review' => $review->load(['hotel:id,name', 'user:id,name,email', 'booking:id,booking_reference']),
            'statuses' => $this->statuses(),
        ]);
    }

    public function update(Request $request, Review $review): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in($this->statuses())],
        ]);

        $review->update($validated);

        return redirect()
            ->route('admin.reviews.edit', $review)
            ->with('status', 'review-updated');
    }

    private function hotels()
    {
        return Hotel::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function statuses(): array
    {
        return ['pending', 'published', 'hidden'];
    }
}
