<?php

namespace App\Http\Controllers;

use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OwnerServiceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'scope' => ['nullable', 'string', 'in:hotel,room_type'],
        ]);

        $services = Service::query()
            ->where('is_active', true)
            ->when($validated['scope'] ?? null, fn ($query, string $scope) => $query->whereIn('scope', [$scope, 'both']))
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return ServiceResource::collection($services);
    }
}
