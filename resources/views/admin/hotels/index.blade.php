<x-app-layout>
    <div class="py-6 bg-gradient-to-br from-sky-50 via-cyan-50 to-white">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white/90 border border-sky-100 shadow-sm sm:rounded-2xl">
                <div class="p-6 text-slate-900">
                    <div class="mb-6">
                        <h1 class="text-xl font-semibold text-sky-950">Hotels</h1>
                        <p class="mt-1 text-sm text-slate-500">Manage hotel status, owners, location, and pricing.</p>
                    </div>

                    <form method="GET" action="{{ route('admin.hotels.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div class="w-full sm:flex-1">
                            <x-input-label for="q" value="Search" />
                            <x-text-input id="q" name="q" type="text" class="mt-1 block w-full" value="{{ $search }}" placeholder="Hotel, owner, city, or country" />
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

                            @if ($search !== '' || $status !== 'all')
                                <a href="{{ route('admin.hotels.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-200">
                                    Clear
                                </a>
                            @endif
                        </div>
                    </form>

                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <caption class="sr-only">Hotels list</caption>
                            <thead class="text-left text-sky-900 bg-sky-50 border-b border-sky-100">
                                <tr>
                                    <th scope="col" class="py-2 pr-4">Hotel</th>
                                    <th scope="col" class="py-2 pr-4">Owner</th>
                                    <th scope="col" class="py-2 pr-4">Location</th>
                                    <th scope="col" class="py-2 pr-4">Status</th>
                                    <th scope="col" class="py-2 pr-4">Rooms</th>
                                    <th scope="col" class="py-2 pr-4">Bookings</th>
                                    <th scope="col" class="py-2 pr-4"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($hotels as $hotel)
                                    <tr class="border-b border-slate-100">
                                        <td class="py-2 pr-4">
                                            <div class="font-medium text-slate-900">{{ $hotel->name }}</div>
                                            <div class="text-xs text-slate-500">{{ $hotel->slug }}</div>
                                        </td>
                                        <td class="py-2 pr-4 text-slate-600">
                                            <div>{{ $hotel->owner?->name ?? '-' }}</div>
                                            <div class="text-xs text-slate-500">{{ $hotel->owner?->email }}</div>
                                        </td>
                                        <td class="py-2 pr-4 text-slate-600">{{ $hotel->city }}, {{ $hotel->country }}</td>
                                        <td class="py-2 pr-4">
                                            @if ($hotel->status === 'published')
                                                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold uppercase tracking-widest text-green-700 bg-green-100 rounded-full">
                                                    published
                                                </span>
                                            @elseif ($hotel->status === 'inactive')
                                                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold uppercase tracking-widest text-red-700 bg-red-100 rounded-full">
                                                    inactive
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold uppercase tracking-widest text-slate-700 bg-slate-100 rounded-full">
                                                    {{ $hotel->status }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-2 pr-4 text-slate-600">{{ $hotel->room_types_count }}</td>
                                        <td class="py-2 pr-4 text-slate-600">{{ $hotel->bookings_count }}</td>
                                        <td class="py-2 pr-4 text-right">
                                            <div class="inline-flex items-center gap-2">
                                                <a href="{{ route('admin.room-types.index', ['hotel_id' => $hotel->id]) }}" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-blue-900 bg-blue-50 rounded-md hover:bg-blue-100">
                                                    Rooms
                                                </a>
                                                <a href="{{ route('admin.hotels.edit', $hotel) }}" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-white bg-blue-900 rounded-md hover:bg-blue-800">
                                                    Edit
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-6 text-center text-gray-500">
                                            No hotels found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 text-sm text-gray-500">
                        Showing {{ $hotels->firstItem() ?? 0 }} to {{ $hotels->lastItem() ?? 0 }} of {{ $hotels->total() }} results
                    </div>
                </div>
            </div>

            <div class="mt-2 flex justify-end">
                {{ $hotels->links('admin.partials.pagination') }}
            </div>
        </div>
    </div>
</x-app-layout>
