<x-app-layout>
    <div class="py-6 bg-gradient-to-br from-sky-50 via-cyan-50 to-white">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white/90 border border-sky-100 shadow-sm sm:rounded-2xl">
                <div class="p-6 text-slate-900">
                    <div class="mb-6">
                        <h1 class="text-xl font-semibold text-sky-950">Room Types</h1>
                        <p class="mt-1 text-sm text-slate-500">Manage room type status, capacity, units, and base pricing.</p>
                    </div>

                    <form method="GET" action="{{ route('admin.room-types.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div class="w-full sm:flex-1">
                            <x-input-label for="q" value="Search" />
                            <x-text-input id="q" name="q" type="text" class="mt-1 block w-full" value="{{ $search }}" placeholder="Room type, hotel, or bed type" />
                        </div>

                        <div class="w-full sm:max-w-xs">
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

                        <div class="w-full sm:max-w-xs">
                            <x-input-label for="status" value="Status" />
                            <select id="status" name="status" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="all" @selected($status === 'all')>All</option>
                                @foreach ($statuses as $statusOption)
                                    <option value="{{ $statusOption }}" @selected($status === $statusOption)>
                                        {{ $statusOption }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex gap-2">
                            <x-primary-button type="submit">
                                Search
                            </x-primary-button>

                            @if ($search !== '' || $hotelId !== 'all' || $status !== 'all')
                                <a href="{{ route('admin.room-types.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-200">
                                    Clear
                                </a>
                            @endif
                        </div>
                    </form>

                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <caption class="sr-only">Room types list</caption>
                            <thead class="text-left text-sky-900 bg-sky-50 border-b border-sky-100">
                                <tr>
                                    <th scope="col" class="py-2 pr-4">Room Type</th>
                                    <th scope="col" class="py-2 pr-4">Hotel</th>
                                    <th scope="col" class="py-2 pr-4">Capacity</th>
                                    <th scope="col" class="py-2 pr-4">Units</th>
                                    <th scope="col" class="py-2 pr-4">Base Price</th>
                                    <th scope="col" class="py-2 pr-4">Status</th>
                                    <th scope="col" class="py-2 pr-4"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($roomTypes as $roomType)
                                    <tr class="border-b border-slate-100">
                                        <td class="py-2 pr-4">
                                            <div class="font-medium text-slate-900">{{ $roomType->name }}</div>
                                            <div class="text-xs text-slate-500">{{ $roomType->bed_type ?? '-' }}</div>
                                        </td>
                                        <td class="py-2 pr-4 text-slate-600">
                                            <div>{{ $roomType->hotel?->name }}</div>
                                            <div class="text-xs text-slate-500">{{ $roomType->hotel?->city }}, {{ $roomType->hotel?->country }}</div>
                                        </td>
                                        <td class="py-2 pr-4 text-slate-600">{{ $roomType->capacity_adults }} adults / {{ $roomType->capacity_children }} children</td>
                                        <td class="py-2 pr-4 text-slate-600">{{ $roomType->total_units }}</td>
                                        <td class="py-2 pr-4 text-slate-600">{{ $roomType->base_price }}</td>
                                        <td class="py-2 pr-4">
                                            @if ($roomType->status === 'active')
                                                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold uppercase tracking-widest text-green-700 bg-green-100 rounded-full">
                                                    active
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold uppercase tracking-widest text-red-700 bg-red-100 rounded-full">
                                                    {{ $roomType->status }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-2 pr-4 text-right">
                                            <a href="{{ route('admin.room-types.edit', $roomType) }}" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-white bg-blue-900 rounded-md hover:bg-blue-800">
                                                Edit
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-6 text-center text-gray-500">
                                            No room types found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 text-sm text-gray-500">
                        Showing {{ $roomTypes->firstItem() ?? 0 }} to {{ $roomTypes->lastItem() ?? 0 }} of {{ $roomTypes->total() }} results
                    </div>
                </div>
            </div>

            <div class="mt-2 flex justify-end">
                {{ $roomTypes->links('admin.partials.pagination') }}
            </div>
        </div>
    </div>
</x-app-layout>
