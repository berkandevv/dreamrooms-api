<x-app-layout>
    <div class="py-6 bg-gradient-to-br from-sky-50 via-cyan-50 to-white">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white/90 border border-sky-100 shadow-sm sm:rounded-2xl">
                <div class="p-6 text-slate-900">
                    <div class="mb-6">
                        <h1 class="text-xl font-semibold text-sky-950">Bookings</h1>
                        <p class="mt-1 text-sm text-slate-500">Review booking status, payments, dates, and customer details.</p>
                    </div>

                    <form method="GET" action="{{ route('admin.bookings.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-7 lg:items-end">
                        <div class="lg:col-span-2">
                            <x-input-label for="q" value="Search" />
                            <x-text-input id="q" name="q" type="text" class="mt-1 block w-full" value="{{ $search }}" placeholder="Reference, customer, hotel" />
                        </div>

                        <div>
                            <x-input-label for="hotel_id" value="Hotel" />
                            <select id="hotel_id" name="hotel_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="all" @selected($hotelId === 'all')>All</option>
                                @foreach ($hotels as $hotel)
                                    <option value="{{ $hotel->id }}" @selected((string) $hotelId === (string) $hotel->id)>
                                        {{ $hotel->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-input-label for="status" value="Status" />
                            <select id="status" name="status" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="all" @selected($status === 'all')>All</option>
                                @foreach ($statuses as $statusOption)
                                    <option value="{{ $statusOption }}" @selected($status === $statusOption)>{{ $statusOption }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-input-label for="payment_status" value="Payment" />
                            <select id="payment_status" name="payment_status" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="all" @selected($paymentStatus === 'all')>All</option>
                                @foreach ($paymentStatuses as $paymentStatusOption)
                                    <option value="{{ $paymentStatusOption }}" @selected($paymentStatus === $paymentStatusOption)>{{ $paymentStatusOption }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-input-label for="from" value="From" />
                            <x-text-input id="from" name="from" type="date" class="mt-1 block w-full" value="{{ $from }}" />
                        </div>

                        <div>
                            <x-input-label for="to" value="To" />
                            <x-text-input id="to" name="to" type="date" class="mt-1 block w-full" value="{{ $to }}" />
                        </div>

                        <div class="flex gap-2 lg:col-span-7">
                            <x-primary-button type="submit">Search</x-primary-button>

                            @if ($search !== '' || $hotelId !== 'all' || $status !== 'all' || $paymentStatus !== 'all' || $from !== '' || $to !== '')
                                <a href="{{ route('admin.bookings.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-200">
                                    Clear
                                </a>
                            @endif
                        </div>
                    </form>

                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <caption class="sr-only">Bookings list</caption>
                            <thead class="text-left text-sky-900 bg-sky-50 border-b border-sky-100">
                                <tr>
                                    <th scope="col" class="py-2 pr-4">Reference</th>
                                    <th scope="col" class="py-2 pr-4">Customer</th>
                                    <th scope="col" class="py-2 pr-4">Hotel</th>
                                    <th scope="col" class="py-2 pr-4">Stay</th>
                                    <th scope="col" class="py-2 pr-4">Status</th>
                                    <th scope="col" class="py-2 pr-4">Payment</th>
                                    <th scope="col" class="py-2 pr-4">Total</th>
                                    <th scope="col" class="py-2 pr-4"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($bookings as $booking)
                                    <tr class="border-b border-slate-100">
                                        <td class="py-2 pr-4 font-medium text-slate-900">{{ $booking->booking_reference }}</td>
                                        <td class="py-2 pr-4 text-slate-600">
                                            <div>{{ $booking->customer_name }}</div>
                                            <div class="text-xs text-slate-500">{{ $booking->customer_email }}</div>
                                        </td>
                                        <td class="py-2 pr-4 text-slate-600">{{ $booking->hotel_name }}</td>
                                        <td class="py-2 pr-4 text-slate-600">
                                            {{ $booking->check_in?->toDateString() }} - {{ $booking->check_out?->toDateString() }}
                                        </td>
                                        <td class="py-2 pr-4">
                                            <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold uppercase tracking-widest rounded-full {{ $booking->status === 'cancelled' ? 'text-red-700 bg-red-100' : ($booking->status === 'completed' ? 'text-green-700 bg-green-100' : 'text-sky-800 bg-sky-100') }}">
                                                {{ $booking->status }}
                                            </span>
                                        </td>
                                        <td class="py-2 pr-4">
                                            <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold uppercase tracking-widest rounded-full {{ $booking->payment_status === 'paid' ? 'text-green-700 bg-green-100' : ($booking->payment_status === 'failed' || $booking->payment_status === 'refunded' ? 'text-red-700 bg-red-100' : 'text-amber-800 bg-amber-100') }}">
                                                {{ $booking->payment_status }}
                                            </span>
                                        </td>
                                        <td class="py-2 pr-4 text-slate-600">{{ $booking->total_amount }} {{ $booking->currency }}</td>
                                        <td class="py-2 pr-4 text-right">
                                            <a href="{{ route('admin.bookings.show', $booking) }}" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-white bg-blue-900 rounded-md hover:bg-blue-800">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="py-6 text-center text-gray-500">No bookings found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 text-sm text-gray-500">
                        Showing {{ $bookings->firstItem() ?? 0 }} to {{ $bookings->lastItem() ?? 0 }} of {{ $bookings->total() }} results
                    </div>
                </div>
            </div>

            <div class="mt-2 flex justify-end">
                {{ $bookings->links('admin.partials.pagination') }}
            </div>
        </div>
    </div>
</x-app-layout>
