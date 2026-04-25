<x-app-layout>
    <div class="py-6 bg-gradient-to-br from-sky-50 via-cyan-50 to-white">
        <div
            class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6"
            x-data="{
                confirm: {
                    open: false,
                    title: '',
                    message: '',
                    onConfirm: () => {},
                },
                askSave() {
                    this.confirm = {
                        open: true,
                        title: 'Create service?',
                        message: 'This will add a new service to the catalog.',
                        onConfirm: () => this.$refs.form.submit(),
                    };
                },
                askCancel() {
                    this.confirm = {
                        open: true,
                        title: 'Discard service?',
                        message: 'You will return to the services list and unsaved changes will be lost.',
                        onConfirm: () => window.location.href = '{{ route('admin.services.index') }}',
                    };
                },
            }"
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
