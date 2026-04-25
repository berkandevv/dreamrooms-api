<x-app-layout>
    <div class="py-6 bg-gradient-to-br from-sky-50 via-cyan-50 to-white">
        <div
            class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6"
            x-data="confirmableForm({
                saveTitle: 'Create service?',
                saveMessage: 'This will add a new service to the catalog.',
                cancelTitle: 'Discard service?',
                cancelMessage: 'You will return to the services list and unsaved changes will be lost.',
                cancelUrl: @js(route('admin.services.index')),
            })"
        >
            <div class="bg-white/90 border border-sky-100 shadow-sm sm:rounded-2xl">
                <form x-ref="form" method="POST" action="{{ route('admin.services.store') }}" class="p-6 space-y-6" @submit.prevent="askSave()">
                    @csrf

                    <div>
                        <h1 class="text-xl font-semibold text-sky-950">New Service</h1>
                        <p class="mt-1 text-sm text-slate-500">Add a new service to the catalog.</p>
                    </div>

                    @include('admin.services.partials.form', ['service' => $service, 'scopes' => $scopes, 'categories' => $categories])

                    <div class="flex items-center justify-end gap-3">
                        <button type="button" class="text-sm text-gray-600 hover:text-gray-900" @click="askCancel()">
                            Cancel
                        </button>
                        <x-primary-button>Create</x-primary-button>
                    </div>
                </form>
            </div>

            @include('admin.partials.confirm-dialog')
        </div>
    </div>
</x-app-layout>
