<div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
    <div>
        <x-input-label value="Booking" />
        <div class="mt-1 text-sm text-slate-700">{{ $review->booking?->booking_reference }}</div>
    </div>

    <div>
        <x-input-label value="Rating" />
        <div class="mt-1 text-sm text-slate-700">{{ $review->rating }}</div>
    </div>

    <div>
        <x-input-label value="User" />
        <div class="mt-1 text-sm text-slate-700">{{ $review->user?->name }} - {{ $review->user?->email }}</div>
    </div>

    <div>
        <x-input-label for="status" value="Status" />
        <select id="status" name="status" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(old('status', $review->status) === $status)>
                    {{ $status }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('status')" />
    </div>
</div>

<div>
    <x-input-label value="Comment" />
    <div class="mt-1 min-h-32 rounded-md border border-gray-200 bg-gray-50 p-3 text-sm text-slate-700">
        {{ $review->comment ?? '-' }}
    </div>
</div>
