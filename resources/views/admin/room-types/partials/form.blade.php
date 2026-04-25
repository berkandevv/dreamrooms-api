<div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
    <div>
        <x-input-label for="name" value="Name" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $roomType->name)" required autofocus />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>

    <div>
        <x-input-label for="hotel_id" value="Hotel" />
        <select id="hotel_id" name="hotel_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
            @foreach ($hotels as $hotel)
                <option value="{{ $hotel->id }}" @selected((string) old('hotel_id', $roomType->hotel_id) === (string) $hotel->id)>
                    {{ $hotel->name }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('hotel_id')" />
    </div>

    <div>
        <x-input-label for="status" value="Status" />
        <select id="status" name="status" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(old('status', $roomType->status) === $status)>
                    {{ $status }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('status')" />
    </div>

    <div>
        <x-input-label for="base_price" value="Base Price" />
        <x-text-input id="base_price" name="base_price" type="number" min="0" step="0.01" class="mt-1 block w-full" :value="old('base_price', $roomType->base_price)" required />
        <x-input-error class="mt-2" :messages="$errors->get('base_price')" />
    </div>
</div>

<div>
    <x-input-label for="description" value="Description" />
    <textarea id="description" name="description" rows="4" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('description', $roomType->description) }}</textarea>
    <x-input-error class="mt-2" :messages="$errors->get('description')" />
</div>

<div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
    <div>
        <x-input-label for="capacity_adults" value="Adult Capacity" />
        <x-text-input id="capacity_adults" name="capacity_adults" type="number" min="1" max="255" class="mt-1 block w-full" :value="old('capacity_adults', $roomType->capacity_adults)" required />
        <x-input-error class="mt-2" :messages="$errors->get('capacity_adults')" />
    </div>

    <div>
        <x-input-label for="capacity_children" value="Children Capacity" />
        <x-text-input id="capacity_children" name="capacity_children" type="number" min="0" max="255" class="mt-1 block w-full" :value="old('capacity_children', $roomType->capacity_children)" required />
        <x-input-error class="mt-2" :messages="$errors->get('capacity_children')" />
    </div>

    <div>
        <x-input-label for="total_units" value="Total Units" />
        <x-text-input id="total_units" name="total_units" type="number" min="1" max="65535" class="mt-1 block w-full" :value="old('total_units', $roomType->total_units)" required />
        <x-input-error class="mt-2" :messages="$errors->get('total_units')" />
    </div>

    <div>
        <x-input-label for="size_m2" value="Size m2" />
        <x-text-input id="size_m2" name="size_m2" type="number" min="0" step="0.01" class="mt-1 block w-full" :value="old('size_m2', $roomType->size_m2)" />
        <x-input-error class="mt-2" :messages="$errors->get('size_m2')" />
    </div>
</div>

<div>
    <x-input-label for="bed_type" value="Bed Type" />
    <x-text-input id="bed_type" name="bed_type" type="text" class="mt-1 block w-full" :value="old('bed_type', $roomType->bed_type)" />
    <x-input-error class="mt-2" :messages="$errors->get('bed_type')" />
</div>
