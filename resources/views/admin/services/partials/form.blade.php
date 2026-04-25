<div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
    <div>
        <x-input-label for="name" value="Name" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $service->name)" required autofocus />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>

    <div>
        <x-input-label for="slug" value="Slug" />
        <x-text-input id="slug" name="slug" type="text" class="mt-1 block w-full" :value="old('slug', $service->slug)" />
        <x-input-error class="mt-2" :messages="$errors->get('slug')" />
    </div>

    <div>
        <x-input-label for="category" value="Category" />
        <input
            id="category"
            name="category"
            type="text"
            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
            value="{{ old('category', $service->category) }}"
            list="service-categories"
            required
        >
        <datalist id="service-categories">
            @foreach ($categories as $category)
                <option value="{{ $category }}">
            @endforeach
        </datalist>
        <x-input-error class="mt-2" :messages="$errors->get('category')" />
    </div>

    <div>
        <x-input-label for="scope" value="Scope" />
        <select id="scope" name="scope" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
            @foreach ($scopes as $scope)
                <option value="{{ $scope }}" @selected(old('scope', $service->scope) === $scope)>
                    {{ $scope }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('scope')" />
    </div>

    <div>
        <x-input-label for="icon" value="Icon" />
        <x-text-input id="icon" name="icon" type="text" class="mt-1 block w-full" :value="old('icon', $service->icon)" />
        <x-input-error class="mt-2" :messages="$errors->get('icon')" />
    </div>

    <div class="flex items-end">
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('is_active', $service->is_active))>
            Active
        </label>
    </div>
</div>
