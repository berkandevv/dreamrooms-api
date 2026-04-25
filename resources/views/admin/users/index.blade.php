<x-app-layout>
    <div class="py-6 bg-gradient-to-br from-sky-50 via-cyan-50 to-white">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white/90 border border-sky-100 shadow-sm sm:rounded-2xl">
                <div class="p-6 text-slate-900">
                    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 class="text-xl font-semibold text-sky-950">Users</h1>
                            <p class="mt-1 text-sm text-slate-500">Manage customers and owners.</p>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div class="w-full sm:flex-1">
                            <x-input-label for="q" value="Search" />
                            <x-text-input id="q" name="q" type="text" class="mt-1 block w-full" value="{{ $search }}" placeholder="Name, email, or phone" />
                        </div>

                        <div class="w-full sm:max-w-xs">
                            <x-input-label for="role" value="Role" />
                            <select id="role" name="role" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="all" @selected($role === 'all')>All</option>
                                @foreach ($roles as $roleOption)
                                    <option value="{{ $roleOption->name }}" @selected($role === $roleOption->name)>
                                        {{ $roleOption->name }}
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

                            @if ($search !== '' || $role !== 'all' || $status !== 'all')
                                <a href="{{ route('admin.users.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-200">
                                    Clear
                                </a>
                            @endif
                        </div>
                    </form>

                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <caption class="sr-only">Users list</caption>
                            <thead class="text-left text-sky-900 bg-sky-50 border-b border-sky-100">
                                <tr>
                                    <th scope="col" class="py-2 pr-4">Name</th>
                                    <th scope="col" class="py-2 pr-4">Email</th>
                                    <th scope="col" class="py-2 pr-4">Phone</th>
                                    <th scope="col" class="py-2 pr-4">Role</th>
                                    <th scope="col" class="py-2 pr-4">Status</th>
                                    <th scope="col" class="py-2 pr-4"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($users as $user)
                                    <tr class="border-b border-slate-100">
                                        <td class="py-2 pr-4 font-medium text-slate-900">{{ $user->name }}</td>
                                        <td class="py-2 pr-4 text-slate-600">{{ $user->email }}</td>
                                        <td class="py-2 pr-4 text-slate-600">{{ $user->phone ?? '-' }}</td>
                                        <td class="py-2 pr-4">
                                            @if ($user->role?->name === 'admin')
                                                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold uppercase tracking-widest text-purple-800 bg-purple-100 rounded-full">
                                                    admin
                                                </span>
                                            @elseif ($user->role?->name === 'owner')
                                                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold uppercase tracking-widest text-amber-800 bg-amber-100 rounded-full">
                                                    owner
                                                </span>
                                            @elseif ($user->role?->name === 'customer')
                                                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold uppercase tracking-widest text-sky-800 bg-sky-100 rounded-full">
                                                    customer
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold uppercase tracking-widest text-slate-700 bg-slate-100 rounded-full">
                                                    {{ $user->role?->name ?? '-' }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-2 pr-4">
                                            @if ($user->status === 'active')
                                                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold uppercase tracking-widest text-green-700 bg-green-100 rounded-full">
                                                    active
                                                </span>
                                            @elseif ($user->status === 'suspended')
                                                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold uppercase tracking-widest text-red-700 bg-red-100 rounded-full">
                                                    suspended
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold uppercase tracking-widest text-slate-700 bg-slate-100 rounded-full">
                                                    {{ $user->status }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-2 pr-4 text-right">
                                            @if ($user->role?->name === 'admin')
                                                <span class="inline-flex items-center px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-slate-400 bg-slate-100 rounded-md cursor-not-allowed">
                                                    Disabled
                                                </span>
                                            @else
                                                <a href="{{ route('admin.users.edit', $user) }}" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-white bg-blue-900 rounded-md hover:bg-blue-800">
                                                    Edit
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-6 text-center text-gray-500">
                                            No users found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 text-sm text-gray-500">
                        Showing {{ $users->firstItem() ?? 0 }} to {{ $users->lastItem() ?? 0 }} of {{ $users->total() }} results
                    </div>
                </div>
            </div>

            <div class="mt-2 flex justify-end">
                {{ $users->links('admin.partials.pagination') }}
            </div>
        </div>
    </div>
</x-app-layout>
