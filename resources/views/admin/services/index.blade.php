<x-app-layout>
    <div class="py-6 bg-gradient-to-br from-sky-50 via-cyan-50 to-white">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white/90 border border-sky-100 shadow-sm sm:rounded-2xl">
                <div class="p-6 text-slate-900">
                    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h1 class="text-xl font-semibold text-sky-950">Services</h1>
                            <p class="mt-1 text-sm text-slate-500">Manage hotel and room type service catalog settings.</p>
                        </div>

                        <a href="{{ route('admin.services.create') }}" class="inline-flex items-center justify-center px-4 py-2 bg-blue-900 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-800">
                            New Service
                        </a>
                    </div>

                    <form method="GET" action="{{ route('admin.services.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6 lg:items-end">
                        <div class="lg:col-span-2">
                            <x-input-label for="q" value="Search" />
                            <x-text-input id="q" name="q" type="text" class="mt-1 block w-full" value="{{ $search }}" placeholder="Name, slug, icon" />
                        </div>

                        <div>
                            <x-input-label for="scope" value="Scope" />
                            <select id="scope" name="scope" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="all" @selected($scope === 'all')>All</option>
                                @foreach ($scopes as $scopeOption)
                                    <option value="{{ $scopeOption }}" @selected($scope === $scopeOption)>{{ $scopeOption }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-input-label for="category" value="Category" />
                            <select id="category" name="category" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="all" @selected($category === 'all')>All</option>
                                @foreach ($categories as $categoryOption)
                                    <option value="{{ $categoryOption }}" @selected($category === $categoryOption)>{{ $categoryOption }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-input-label for="status" value="Status" />
                            <select id="status" name="status" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="all" @selected($status === 'all')>All</option>
                                <option value="active" @selected($status === 'active')>active</option>
                                <option value="inactive" @selected($status === 'inactive')>inactive</option>
                            </select>
                        </div>

                        <div class="flex gap-2">
                            <x-primary-button type="submit">Search</x-primary-button>

                            @if ($search !== '' || $scope !== 'all' || $category !== 'all' || $status !== 'all')
                                <a href="{{ route('admin.services.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-200">
                                    Clear
                                </a>
                            @endif
                        </div>
                    </form>

                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <caption class="sr-only">Services list</caption>
                            <thead class="text-left text-sky-900 bg-sky-50 border-b border-sky-100">
                                <tr>
                                    <th scope="col" class="py-2 pr-4">Service</th>
                                    <th scope="col" class="py-2 pr-4">Category</th>
                                    <th scope="col" class="py-2 pr-4">Scope</th>
                                    <th scope="col" class="py-2 pr-4">Status</th>
                                    <th scope="col" class="py-2 pr-4">Hotels</th>
                                    <th scope="col" class="py-2 pr-4">Room Types</th>
                                    <th scope="col" class="py-2 pr-4"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($services as $service)
                                    <tr class="border-b border-slate-100">
                                        <td class="py-2 pr-4 text-slate-600">
                                            <div class="font-medium text-slate-900">{{ $service->name }}</div>
                                            <div class="text-xs text-slate-500">{{ $service->slug }}</div>
                                        </td>
                                        <td class="py-2 pr-4 text-slate-600">{{ $service->category }}</td>
                                        <td class="py-2 pr-4">
                                            <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold uppercase tracking-widest text-sky-800 bg-sky-100 rounded-full">
                                                {{ $service->scope }}
                                            </span>
                                        </td>
                                        <td class="py-2 pr-4">
                                            @if ($service->is_active)
                                                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold uppercase tracking-widest text-green-700 bg-green-100 rounded-full">active</span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold uppercase tracking-widest text-slate-700 bg-slate-100 rounded-full">inactive</span>
                                            @endif
                                        </td>
                                        <td class="py-2 pr-4 text-slate-600">{{ $service->hotels_count }}</td>
                                        <td class="py-2 pr-4 text-slate-600">{{ $service->room_types_count }}</td>
                                        <td class="py-2 pr-4 text-right">
                                            <a href="{{ route('admin.services.edit', $service) }}" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-white bg-blue-900 rounded-md hover:bg-blue-800">
                                                Edit
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-6 text-center text-gray-500">No services found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 text-sm text-gray-500">
                        Showing {{ $services->firstItem() ?? 0 }} to {{ $services->lastItem() ?? 0 }} of {{ $services->total() }} results
                    </div>
                </div>
            </div>

            <div class="mt-2 flex justify-end">
                {{ $services->links('admin.partials.pagination') }}
            </div>
        </div>
    </div>
</x-app-layout>
