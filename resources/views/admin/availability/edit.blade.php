<x-app-layout>
    <div class="py-6 bg-gradient-to-br from-sky-50 via-cyan-50 to-white">
        <div
            class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6"
            x-data="confirmableForm({
                saveMessage: 'This will update availability for this date.',
                cancelMessage: 'You will return to the availability list and unsaved changes will be lost.',
                cancelUrl: @js(route('admin.availability.index')),
            })"
        >
            @if (session('status') === 'availability-updated')
                <div class="bg-white/90 border border-green-200 text-green-700 px-4 py-3 rounded-md shadow-sm">
                    Availability updated.
                </div>
            @endif

            <div class="bg-white/90 border border-sky-100 shadow-sm sm:rounded-2xl">
                <form x-ref="form" method="POST" action="{{ route('admin.availability.update', $availability) }}" class="p-6 space-y-6" @submit.prevent="askSave()">
                    @csrf
                    @method('PUT')

                    <div>
                        <h1 class="text-xl font-semibold text-sky-950">Edit Availability</h1>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ $availability->roomType?->hotel?->name }} / {{ $availability->roomType?->name }} / {{ $availability->date?->toDateString() }}
                        </p>
                    </div>

                    @include('admin.availability.partials.form', ['availability' => $availability, 'statuses' => $statuses])

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
