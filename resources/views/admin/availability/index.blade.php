<x-app-layout>
    <div class="py-6 bg-gradient-to-br from-sky-50 via-cyan-50 to-white">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white/90 border border-sky-100 shadow-sm sm:rounded-2xl">
                <div class="p-6 text-slate-900">
                    <div class="mb-6">
                        <h1 class="text-xl font-semibold text-sky-950">Availability</h1>
                        <p class="mt-1 text-sm text-slate-500">Manage daily room availability, prices, and open/closed status.</p>
                    </div>

                    <form method="GET" action="{{ route('admin.availability.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6 lg:items-end">
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
                            <x-input-label for="room_type_id" value="Room Type" />
                            <select id="room_type_id" name="room_type_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="all" @selected($roomTypeId === 'all')>All</option>
                                @foreach ($roomTypes as $roomType)
                                    <option value="{{ $roomType->id }}" @selected((string) $roomTypeId === (string) $roomType->id)>
                                        {{ $roomType->name }} - {{ $roomType->hotel?->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
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

                        <div>
                            <x-input-label for="from" value="From" />
                            <x-text-input id="from" name="from" type="date" class="mt-1 block w-full" value="{{ $from }}" />
                        </div>

                        <div>
                            <x-input-label for="to" value="To" />
                            <x-text-input id="to" name="to" type="date" class="mt-1 block w-full" value="{{ $to }}" />
                        </div>

                        <div class="flex gap-2">
                            <x-primary-button type="submit">
                                Search
                            </x-primary-button>

                            @if ($hotelId !== 'all' || $roomTypeId !== 'all' || $status !== 'all' || $from !== '' || $to !== '')
                                <a href="{{ route('admin.availability.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-200">
                                    Clear
                                </a>
                            @endif
                        </div>
                    </form>

                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <caption class="sr-only">Availability list</caption>
                            <thead class="text-left text-sky-900 bg-sky-50 border-b border-sky-100">
                                <tr>
                                    <th scope="col" class="py-2 pr-4">Date</th>
                                    <th scope="col" class="py-2 pr-4">Room Type</th>
                                    <th scope="col" class="py-2 pr-4">Hotel</th>
                                    <th scope="col" class="py-2 pr-4">Units</th>
                                    <th scope="col" class="py-2 pr-4">Price</th>
                                    <th scope="col" class="py-2 pr-4">Min Stay</th>
                                    <th scope="col" class="py-2 pr-4">Status</th>
                                    <th scope="col" class="py-2 pr-4"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($availability as $day)
                                    <tr class="border-b border-slate-100">
                                        <td class="py-2 pr-4 font-medium text-slate-900">{{ $day->date?->toDateString() }}</td>
                                        <td class="py-2 pr-4 text-slate-600">{{ $day->roomType?->name }}</td>
                                        <td class="py-2 pr-4 text-slate-600">{{ $day->roomType?->hotel?->name }}</td>
                                        <td class="py-2 pr-4 text-slate-600">{{ $day->available_units }}</td>
                                        <td class="py-2 pr-4 text-slate-600">{{ $day->price }}</td>
                                        <td class="py-2 pr-4 text-slate-600">{{ $day->min_stay_nights ?? '-' }}</td>
                                        <td class="py-2 pr-4">
                                            @if ($day->status === 'open')
                                                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold uppercase tracking-widest text-green-700 bg-green-100 rounded-full">
                                                    open
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold uppercase tracking-widest text-red-700 bg-red-100 rounded-full">
                                                    closed
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-2 pr-4 text-right">
                                            <a href="{{ route('admin.availability.edit', $day) }}" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-white bg-blue-900 rounded-md hover:bg-blue-800">
                                                Edit
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="py-6 text-center text-gray-500">
                                            No availability found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 text-sm text-gray-500">
                        Showing {{ $availability->firstItem() ?? 0 }} to {{ $availability->lastItem() ?? 0 }} of {{ $availability->total() }} results
                    </div>
                </div>
            </div>

            <div class="mt-2 flex justify-end">
                {{ $availability->links('admin.partials.pagination') }}
            </div>
        </div>
    </div>
</x-app-layout>
