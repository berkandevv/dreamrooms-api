<x-app-layout>
    <div class="py-6 bg-gradient-to-br from-sky-50 via-cyan-50 to-white">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white/90 border border-sky-100 shadow-sm sm:rounded-2xl">
                <div class="p-6 text-slate-900">
                    <div class="mb-6">
                        <h1 class="text-xl font-semibold text-sky-950">Reviews</h1>
                        <p class="mt-1 text-sm text-slate-500">Moderate review status and customer comments.</p>
                    </div>

                    <form method="GET" action="{{ route('admin.reviews.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6 lg:items-end">
                        <div class="lg:col-span-2">
                            <x-input-label for="q" value="Search" />
                            <x-text-input id="q" name="q" type="text" class="mt-1 block w-full" value="{{ $search }}" placeholder="Comment, user, hotel, booking" />
                        </div>

                        <div>
                            <x-input-label for="hotel_id" value="Hotel" />
                            <select id="hotel_id" name="hotel_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="all" @selected($hotelId === 'all')>All</option>
                                @foreach ($hotels as $hotel)
                                    <option value="{{ $hotel->id }}" @selected((string) $hotelId === (string) $hotel->id)>
                                        {{ $hotel->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-input-label for="status" value="Status" />
                            <select id="status" name="status" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="all" @selected($status === 'all')>All</option>
                                @foreach ($statuses as $statusOption)
                                    <option value="{{ $statusOption }}" @selected($status === $statusOption)>{{ $statusOption }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-input-label for="rating" value="Rating" />
                            <select id="rating" name="rating" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="all" @selected($rating === 'all')>All</option>
                                @foreach ([5, 4, 3, 2, 1] as $ratingOption)
                                    <option value="{{ $ratingOption }}" @selected((string) $rating === (string) $ratingOption)>{{ $ratingOption }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex gap-2">
                            <x-primary-button type="submit">Search</x-primary-button>

                            @if ($search !== '' || $hotelId !== 'all' || $status !== 'all' || $rating !== 'all')
                                <a href="{{ route('admin.reviews.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-200">
                                    Clear
                                </a>
                            @endif
                        </div>
                    </form>

                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <caption class="sr-only">Reviews list</caption>
                            <thead class="text-left text-sky-900 bg-sky-50 border-b border-sky-100">
                                <tr>
                                    <th scope="col" class="py-2 pr-4">User</th>
                                    <th scope="col" class="py-2 pr-4">Hotel</th>
                                    <th scope="col" class="py-2 pr-4">Booking</th>
                                    <th scope="col" class="py-2 pr-4">Rating</th>
                                    <th scope="col" class="py-2 pr-4">Comment</th>
                                    <th scope="col" class="py-2 pr-4">Status</th>
                                    <th scope="col" class="py-2 pr-4"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($reviews as $review)
                                    <tr class="border-b border-slate-100">
                                        <td class="py-2 pr-4 text-slate-600">
                                            <div>{{ $review->user?->name }}</div>
                                            <div class="text-xs text-slate-500">{{ $review->user?->email }}</div>
                                        </td>
                                        <td class="py-2 pr-4 text-slate-600">{{ $review->hotel?->name }}</td>
                                        <td class="py-2 pr-4 text-slate-600">{{ $review->booking?->booking_reference }}</td>
                                        <td class="py-2 pr-4 font-medium text-slate-900">{{ $review->rating }}</td>
                                        <td class="py-2 pr-4 text-slate-600 max-w-md truncate">{{ $review->comment ?? '-' }}</td>
                                        <td class="py-2 pr-4">
                                            @if ($review->status === 'published')
                                                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold uppercase tracking-widest text-green-700 bg-green-100 rounded-full">published</span>
                                            @elseif ($review->status === 'hidden')
                                                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold uppercase tracking-widest text-red-700 bg-red-100 rounded-full">hidden</span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold uppercase tracking-widest text-amber-800 bg-amber-100 rounded-full">pending</span>
                                            @endif
                                        </td>
                                        <td class="py-2 pr-4 text-right">
                                            <a href="{{ route('admin.reviews.edit', $review) }}" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-white bg-blue-900 rounded-md hover:bg-blue-800">
                                                Edit
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-6 text-center text-gray-500">No reviews found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 text-sm text-gray-500">
                        Showing {{ $reviews->firstItem() ?? 0 }} to {{ $reviews->lastItem() ?? 0 }} of {{ $reviews->total() }} results
                    </div>
                </div>
            </div>

            <div class="mt-2 flex justify-end">
                {{ $reviews->links('admin.partials.pagination') }}
            </div>
        </div>
    </div>
</x-app-layout>
