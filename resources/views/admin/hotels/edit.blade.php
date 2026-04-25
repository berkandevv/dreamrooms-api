<x-app-layout>
    <div class="py-6 bg-gradient-to-br from-sky-50 via-cyan-50 to-white">
        <div
            class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6"
            x-data="confirmableForm({
                saveMessage: 'This will update the hotel information.',
                cancelMessage: 'You will return to the hotels list and unsaved changes will be lost.',
                cancelUrl: @js(route('admin.hotels.index')),
            })"
        >
            @if (session('status') === 'hotel-updated')
                <div class="bg-white/90 border border-green-200 text-green-700 px-4 py-3 rounded-md shadow-sm">
                    Hotel updated.
                </div>
            @endif

            <div class="bg-white/90 border border-sky-100 shadow-sm sm:rounded-2xl">
                <form x-ref="form" method="POST" action="{{ route('admin.hotels.update', $hotel) }}" class="p-6 space-y-6" @submit.prevent="askSave()">
                    @csrf
                    @method('PUT')

                    <div>
                        <h1 class="text-xl font-semibold text-sky-950">Edit Hotel</h1>
                        <p class="mt-1 text-sm text-slate-500">{{ $hotel->name }}</p>
                    </div>

                    @include('admin.hotels.partials.form', ['hotel' => $hotel, 'owners' => $owners, 'statuses' => $statuses])

                    <div class="flex items-center justify-end gap-3">
                        <button type="button" class="text-sm text-gray-600 hover:text-gray-900" @click="askCancel()">
                            Cancel
                        </button>
                        <x-primary-button>Save</x-primary-button>
                    </div>
                </form>
            </div>

            @include('admin.partials.confirm-dialog')
        </div>
    </div>
</x-app-layout>
