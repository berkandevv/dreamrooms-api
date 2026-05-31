<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\RoomType;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ImageStorageService
{
    // Devuelve las reglas de validación de imágenes
    public function validationRules(): array
    {
        return [
            'image' => ['sometimes', 'file', 'image', 'max:5120'],
            'images' => ['sometimes', 'array', 'size:1'],
            'images.*' => ['file', 'image', 'max:5120'],
            'alt_text' => ['nullable', 'string', 'max:150'],
            'image_alt_texts' => ['sometimes', 'array', 'size:1'],
            'image_alt_texts.*' => ['nullable', 'string', 'max:150'],
            'is_cover' => ['nullable', Rule::in(['true', 'false'])],
        ];
    }

    // Guarda una imagen y actualiza la portada cuando corresponde
    public function store(Hotel|RoomType $imageOwner, array $validated, string $directory): void
    {
        $image = $validated['image'] ?? ($validated['images'][0] ?? null);

        if (! $image) {
            return;
        }

        $hasCoverImage = $imageOwner->images()->where('is_cover', true)->exists();
        $isCover = array_key_exists('is_cover', $validated)
            ? filter_var($validated['is_cover'], FILTER_VALIDATE_BOOLEAN)
            : ! $hasCoverImage;

        if ($isCover) {
            $imageOwner->images()->update(['is_cover' => false]);
        }

        $path = $image->store("{$directory}/{$imageOwner->id}", 'public');
        /** @var FilesystemAdapter $publicDisk */
        $publicDisk = Storage::disk('public');

        $imageOwner->images()->create([
            'image_url' => $publicDisk->url($path),
            'alt_text' => $validated['alt_text'] ?? ($validated['image_alt_texts'][0] ?? $imageOwner->name),
            'is_cover' => $isCover,
            'sort_order' => (int) $imageOwner->images()->max('sort_order') + 1,
        ]);
    }
}
