<div
    x-show="confirm.open"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4"
>
    <div
        x-show="confirm.open"
        x-transition
        @click.outside="confirm.open = false"
        class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl"
    >
        <h2 class="text-lg font-semibold text-slate-900" x-text="confirm.title"></h2>
        <p class="mt-2 text-sm text-slate-600" x-text="confirm.message"></p>

        <div class="mt-6 flex justify-end gap-3">
            <button
                type="button"
                class="inline-flex items-center px-4 py-2 bg-gray-100 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-200"
                @click="confirm.open = false"
            >
                Cancel
            </button>

            <button
                type="button"
                class="inline-flex items-center px-4 py-2 bg-blue-900 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-800"
                @click="confirm.onConfirm()"
            >
                Confirm
            </button>
        </div>
    </div>
</div>
