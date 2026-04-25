<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit User') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div
            class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6"
            x-data="confirmableForm({
                saveMessage: 'This will update the user information.',
                cancelMessage: 'You will return to the users list and unsaved changes will be lost.',
                cancelUrl: @js(route('admin.users.index')),
            })"
        >
            @if (session('status') === 'user-updated')
                <div class="bg-white border border-green-200 text-green-700 px-4 py-3 rounded-md shadow-sm">
                    {{ __('User updated.') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <form x-ref="form" method="POST" action="{{ route('admin.users.update', $user) }}" class="p-6 space-y-6" @submit.prevent="askSave()">
                    @csrf
                    @method('PUT')

                    @include('admin.users.partials.form', ['user' => $user, 'roles' => $roles, 'statuses' => $statuses])

                    <div class="flex items-center justify-end gap-3">
                        <button type="button" class="text-sm text-gray-600 hover:text-gray-900" @click="askCancel()">
                            {{ __('Cancel') }}
                        </button>
                        <x-primary-button>{{ __('Save') }}</x-primary-button>
                    </div>
                </form>
            </div>

            @include('admin.partials.confirm-dialog')
        </div>
    </div>
</x-app-layout>
