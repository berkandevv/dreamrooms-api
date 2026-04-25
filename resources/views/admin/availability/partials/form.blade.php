<div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
    <div>
        <x-input-label for="available_units" value="Available Units" />
        <x-text-input id="available_units" name="available_units" type="number" min="0" class="mt-1 block w-full" :value="old('available_units', $availability->available_units)" required />
        <x-input-error class="mt-2" :messages="$errors->get('available_units')" />
    </div>

    <div>
        <x-input-label for="price" value="Price" />
        <x-text-input id="price" name="price" type="number" min="0" step="0.01" class="mt-1 block w-full" :value="old('price', $availability->price)" required />
        <x-input-error class="mt-2" :messages="$errors->get('price')" />
    </div>

    <div>
        <x-input-label for="status" value="Status" />
        <select id="status" name="status" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(old('status', $availability->status) === $status)>
                    {{ $status }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('status')" />
    </div>

    <div>
        <x-input-label for="min_stay_nights" value="Min Stay Nights" />
        <x-text-input id="min_stay_nights" name="min_stay_nights" type="number" min="1" class="mt-1 block w-full" :value="old('min_stay_nights', $availability->min_stay_nights)" />
        <x-input-error class="mt-2" :messages="$errors->get('min_stay_nights')" />
    </div>
</div>
