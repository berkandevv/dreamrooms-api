<x-app-layout>
    <div class="py-6 bg-gradient-to-br from-sky-50 via-cyan-50 to-white">
        <div
            class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6"
            x-data="{
                confirm: { open: false, title: '', message: '', onConfirm: () => {} },
                askStatus() {
                    this.confirm = {
                        open: true,
                        title: 'Update booking status?',
                        message: 'This will apply the selected status transition.',
                        onConfirm: () => this.$refs.statusForm.submit(),
                    };
                },
                askPayment() {
                    this.confirm = {
                        open: true,
                        title: 'Register payment?',
                        message: 'This will add a manual payment to the booking.',
                        onConfirm: () => this.$refs.paymentForm.submit(),
                    };
                },
            }"
        >
            @if (session('status') === 'booking-updated')
                <div class="bg-white/90 border border-green-200 text-green-700 px-4 py-3 rounded-md shadow-sm">Booking updated.</div>
            @endif

            @if (session('status') === 'payment-created')
                <div class="bg-white/90 border border-green-200 text-green-700 px-4 py-3 rounded-md shadow-sm">Payment registered.</div>
            @endif

            <div class="bg-white/90 border border-sky-100 shadow-sm sm:rounded-2xl">
                <div class="p-6 space-y-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h1 class="text-xl font-semibold text-sky-950">{{ $booking->booking_reference }}</h1>
                            <p class="mt-1 text-sm text-slate-500">{{ $booking->hotel_name }} / {{ $booking->room_type_name }}</p>
                        </div>

                        <a href="{{ route('admin.bookings.index') }}" class="inline-flex items-center px-5 py-2.5 bg-blue-900 border border-blue-900 rounded-md font-semibold text-xs text-white uppercase tracking-widest shadow-md ring-1 ring-blue-950/10 hover:bg-blue-800">
                            Back to bookings
                        </a>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="rounded-lg border border-sky-100 bg-sky-50 p-4">
                            <div class="text-xs font-semibold uppercase tracking-widest text-sky-900">Customer</div>
                            <div class="mt-2 text-sm text-slate-900">{{ $booking->customer_name }}</div>
                            <div class="text-sm text-slate-600">{{ $booking->customer_email }}</div>
                            <div class="text-sm text-slate-600">{{ $booking->customer_phone ?? '-' }}</div>
                        </div>

                        <div class="rounded-lg border border-sky-100 bg-sky-50 p-4">
                            <div class="text-xs font-semibold uppercase tracking-widest text-sky-900">Stay</div>
                            <div class="mt-2 text-sm text-slate-900">{{ $booking->check_in?->toDateString() }} - {{ $booking->check_out?->toDateString() }}</div>
                            <div class="text-sm text-slate-600">{{ $booking->nights }} nights / {{ $booking->units_booked }} units</div>
                            <div class="text-sm text-slate-600">{{ $booking->adults_count }} adults / {{ $booking->children_count }} children</div>
                        </div>

                        <div class="rounded-lg border border-sky-100 bg-sky-50 p-4">
                            <div class="text-xs font-semibold uppercase tracking-widest text-sky-900">Amounts</div>
                            <div class="mt-2 text-sm text-slate-600">Subtotal: {{ $booking->subtotal_amount }} {{ $booking->currency }}</div>
                            <div class="text-sm text-slate-600">Taxes: {{ $booking->taxes_amount }} {{ $booking->currency }}</div>
                            <div class="text-sm text-slate-600">Discount: {{ $booking->discount_amount }} {{ $booking->currency }}</div>
                            <div class="text-sm font-semibold text-slate-900">Total: {{ $booking->total_amount }} {{ $booking->currency }}</div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-sky-100 bg-sky-50 p-4">
                        <div class="text-xs font-semibold uppercase tracking-widest text-sky-900">Booking Dates</div>
                        <div class="mt-2 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <div class="font-medium text-slate-900">Booked at</div>
                                <div class="text-slate-600">{{ $booking->booked_at?->toDateTimeString() ?? '-' }}</div>
                            </div>

                            <div>
                                <div class="font-medium text-slate-900">Expires at</div>
                                <div class="text-slate-600">{{ $booking->expires_at?->toDateTimeString() ?? '-' }}</div>
                            </div>

                            <div>
                                <div class="font-medium text-slate-900">Confirmed at</div>
                                <div class="text-slate-600">{{ $booking->confirmed_at?->toDateTimeString() ?? '-' }}</div>
                            </div>

                            <div>
                                <div class="font-medium text-slate-900">Cancelled at</div>
                                <div class="text-slate-600">{{ $booking->cancelled_at?->toDateTimeString() ?? '-' }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <form x-ref="statusForm" method="POST" action="{{ route('admin.bookings.status', $booking) }}" class="rounded-lg border border-slate-200 p-4 space-y-4" @submit.prevent="askStatus()">
                            @csrf
                            @method('PATCH')

                            <h2 class="font-semibold text-slate-900">Booking Status</h2>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <x-input-label for="status" value="Status" />
                                    <select id="status" name="status" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                        @foreach ($statuses as $status)
                                            <option value="{{ $status }}" @selected(old('status', $booking->status) === $status)>{{ $status }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error class="mt-2" :messages="$errors->get('status')" />
                                </div>

                                <div>
                                    <x-input-label value="Current Payment" />
                                    <div class="mt-2 text-sm text-slate-700">{{ $booking->payment_status }}</div>
                                </div>
                            </div>
                            <div class="pt-2">
                                <x-primary-button>Update Status</x-primary-button>
                            </div>
                        </form>

                        <form x-ref="paymentForm" method="POST" action="{{ route('admin.bookings.payments.store', $booking) }}" class="rounded-lg border border-slate-200 p-4 space-y-4" @submit.prevent="askPayment()">
                            @csrf

                            <h2 class="font-semibold text-slate-900">Manual Payment</h2>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <x-input-label for="amount" value="Amount" />
                                    <x-text-input id="amount" name="amount" type="number" min="0.01" step="0.01" class="mt-1 block w-full" :value="old('amount')" required />
                                    <x-input-error class="mt-2" :messages="$errors->get('amount')" />
                                </div>

                                <div>
                                    <x-input-label for="payment_status" value="Status" />
                                    <select id="payment_status" name="status" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                        @foreach ($paymentStatuses as $paymentStatus)
                                            <option value="{{ $paymentStatus }}" @selected(old('status', 'paid') === $paymentStatus)>{{ $paymentStatus }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div>
                                <x-input-label for="transaction_reference" value="Reference" />
                                <x-text-input id="transaction_reference" name="transaction_reference" type="text" class="mt-1 block w-full" :value="old('transaction_reference')" />
                            </div>

                            <div class="pt-2">
                                <x-primary-button>Register Payment</x-primary-button>
                            </div>
                        </form>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-4">
                        <h2 class="font-semibold text-slate-900">Payments</h2>
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-sky-900 bg-sky-50 border-b border-sky-100">
                                    <tr>
                                        <th class="py-2 pr-4">Provider</th>
                                        <th class="py-2 pr-4">Amount</th>
                                        <th class="py-2 pr-4">Status</th>
                                        <th class="py-2 pr-4">Reference</th>
                                        <th class="py-2 pr-4">Paid At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($booking->payments as $payment)
                                        <tr class="border-b border-slate-100">
                                            <td class="py-2 pr-4">{{ $payment->provider }}</td>
                                            <td class="py-2 pr-4">{{ $payment->amount }} {{ $payment->currency }}</td>
                                            <td class="py-2 pr-4">{{ $payment->status }}</td>
                                            <td class="py-2 pr-4">{{ $payment->transaction_reference ?? '-' }}</td>
                                            <td class="py-2 pr-4">{{ $payment->paid_at?->toDateTimeString() ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="py-6 text-center text-gray-500">No payments registered.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            @include('admin.partials.confirm-dialog')
        </div>
    </div>
</x-app-layout>
