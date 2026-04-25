<div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
    <div>
        <x-input-label for="name" value="Name" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $hotel->name)" required autofocus />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>

    <div>
        <x-input-label for="owner_user_id" value="Owner" />
        <select id="owner_user_id" name="owner_user_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
            @foreach ($owners as $owner)
                <option value="{{ $owner->id }}" @selected((string) old('owner_user_id', $hotel->owner_user_id) === (string) $owner->id)>
                    {{ $owner->name }} - {{ $owner->email }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('owner_user_id')" />
    </div>

    <div>
        <x-input-label for="status" value="Status" />
        <select id="status" name="status" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(old('status', $hotel->status) === $status)>
                    {{ $status }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('status')" />
    </div>

    <div>
        <x-input-label for="stars" value="Stars" />
        <x-text-input id="stars" name="stars" type="number" min="1" max="5" class="mt-1 block w-full" :value="old('stars', $hotel->stars)" required />
        <x-input-error class="mt-2" :messages="$errors->get('stars')" />
    </div>
</div>

<div>
    <x-input-label for="description" value="Description" />
    <textarea id="description" name="description" rows="4" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('description', $hotel->description) }}</textarea>
    <x-input-error class="mt-2" :messages="$errors->get('description')" />
</div>

<div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
    <div>
        <x-input-label for="country" value="Country" />
        <x-text-input id="country" name="country" type="text" class="mt-1 block w-full" :value="old('country', $hotel->country)" required />
        <x-input-error class="mt-2" :messages="$errors->get('country')" />
    </div>

    <div>
        <x-input-label for="region" value="Region" />
        <x-text-input id="region" name="region" type="text" class="mt-1 block w-full" :value="old('region', $hotel->region)" />
        <x-input-error class="mt-2" :messages="$errors->get('region')" />
    </div>

    <div>
        <x-input-label for="city" value="City" />
        <x-text-input id="city" name="city" type="text" class="mt-1 block w-full" :value="old('city', $hotel->city)" required />
        <x-input-error class="mt-2" :messages="$errors->get('city')" />
    </div>

    <div>
        <x-input-label for="postal_code" value="Postal Code" />
        <x-text-input id="postal_code" name="postal_code" type="text" class="mt-1 block w-full" :value="old('postal_code', $hotel->postal_code)" />
        <x-input-error class="mt-2" :messages="$errors->get('postal_code')" />
    </div>
</div>

<div>
    <x-input-label for="address" value="Address" />
    <x-text-input id="address" name="address" type="text" class="mt-1 block w-full" :value="old('address', $hotel->address)" required />
    <x-input-error class="mt-2" :messages="$errors->get('address')" />
</div>

<div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
    <div>
        <x-input-label for="contact_email" value="Contact Email" />
        <x-text-input id="contact_email" name="contact_email" type="email" class="mt-1 block w-full" :value="old('contact_email', $hotel->contact_email)" />
        <x-input-error class="mt-2" :messages="$errors->get('contact_email')" />
    </div>

    <div>
        <x-input-label for="contact_phone" value="Contact Phone" />
        <x-text-input id="contact_phone" name="contact_phone" type="text" class="mt-1 block w-full" :value="old('contact_phone', $hotel->contact_phone)" />
        <x-input-error class="mt-2" :messages="$errors->get('contact_phone')" />
    </div>

    <div>
        <x-input-label for="check_in_time" value="Check-in Time" />
        <x-text-input id="check_in_time" name="check_in_time" type="time" class="mt-1 block w-full" :value="old('check_in_time', $hotel->check_in_time?->format('H:i'))" />
        <x-input-error class="mt-2" :messages="$errors->get('check_in_time')" />
    </div>

    <div>
        <x-input-label for="check_out_time" value="Check-out Time" />
        <x-text-input id="check_out_time" name="check_out_time" type="time" class="mt-1 block w-full" :value="old('check_out_time', $hotel->check_out_time?->format('H:i'))" />
        <x-input-error class="mt-2" :messages="$errors->get('check_out_time')" />
    </div>
</div>

<div>
    <x-input-label for="cancellation_policy" value="Cancellation Policy" />
    <textarea id="cancellation_policy" name="cancellation_policy" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('cancellation_policy', $hotel->cancellation_policy) }}</textarea>
    <x-input-error class="mt-2" :messages="$errors->get('cancellation_policy')" />
</div>

<div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
    <div>
        <x-input-label for="tax_rate_percent" value="Tax Rate Percent" />
        <x-text-input id="tax_rate_percent" name="tax_rate_percent" type="number" min="0" max="100" step="0.01" class="mt-1 block w-full" :value="old('tax_rate_percent', $hotel->tax_rate_percent)" required />
        <x-input-error class="mt-2" :messages="$errors->get('tax_rate_percent')" />
    </div>

    <div>
        <x-input-label for="discount_rate_percent" value="Discount Rate Percent" />
        <x-text-input id="discount_rate_percent" name="discount_rate_percent" type="number" min="0" max="100" step="0.01" class="mt-1 block w-full" :value="old('discount_rate_percent', $hotel->discount_rate_percent)" required />
        <x-input-error class="mt-2" :messages="$errors->get('discount_rate_percent')" />
    </div>
</div>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <label class="flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" name="pets_allowed" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('pets_allowed', $hotel->pets_allowed))>
        Pets allowed
    </label>

    <label class="flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" name="smoking_allowed" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('smoking_allowed', $hotel->smoking_allowed))>
        Smoking allowed
    </label>
</div>
