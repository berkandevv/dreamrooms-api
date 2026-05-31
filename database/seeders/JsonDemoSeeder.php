<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Favorite;
use App\Models\Hotel;
use App\Models\HotelImage;
use App\Models\Review;
use App\Models\Role;
use App\Models\RoomType;
use App\Models\RoomTypeAvailability;
use App\Models\RoomTypeImage;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class JsonDemoSeeder extends Seeder
{
    private string $basePath;

    /** @var array<string, Role> */
    private array $roles = [];

    /** @var array<string, User> */
    private array $users = [];

    /** @var array<string, Service> */
    private array $services = [];

    /** @var array<string, Hotel> */
    private array $hotels = [];

    /** @var array<string, RoomType> */
    private array $roomTypes = [];

    /** @var array<string, Booking> */
    private array $bookings = [];

    public function run(): void
    {
        $this->basePath = database_path('demo-data');

        $this->resetDemoTables();

        DB::transaction(function (): void {
            $this->seedRoles();
            $this->seedUsers();
            $this->seedServices();
            $this->seedHotels();
            $this->seedInteractions();
        });
    }

    // Limpia las tablas demo para que los JSON sean la fuente completa de datos
    private function resetDemoTables(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ([
            'personal_access_tokens',
            'favorites',
            'reviews',
            'payments',
            'booking_guests',
            'bookings',
            'room_type_availabilities',
            'room_type_services',
            'room_type_images',
            'room_types',
            'hotel_services',
            'hotel_images',
            'hotels',
            'services',
            'users',
            'roles',
        ] as $table) {
            DB::table($table)->truncate();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    // Carga los roles base usados por usuarios y permisos
    private function seedRoles(): void
    {
        foreach ($this->readJson('roles.json') as $roleData) {
            $role = Role::query()->updateOrCreate(
                ['name' => $roleData['name']],
                ['name' => $roleData['name']]
            );

            $this->roles[$role->name] = $role;
        }
    }

    // Crea usuarios demo con contraseñas conocidas para probar la API
    private function seedUsers(): void
    {
        foreach ($this->readJson('users.json') as $userData) {
            $role = $this->roles[$userData['role']] ?? throw new RuntimeException("Missing role [{$userData['role']}]");

            $user = User::query()->updateOrCreate(
                ['email' => $userData['email']],
                [
                    'role_id' => $role->id,
                    'name' => $userData['name'],
                    'phone' => $userData['phone'] ?? null,
                    'status' => $userData['status'] ?? 'active',
                    'email_verified_at' => now(),
                    'password' => Hash::make($userData['password']),
                ]
            );

            $this->users[$userData['key']] = $user;
        }
    }

    // Carga el catálogo de servicios visible para hoteles y habitaciones
    private function seedServices(): void
    {
        foreach ($this->readJson('services.json') as $serviceData) {
            $service = Service::query()->updateOrCreate(
                ['slug' => $serviceData['slug']],
                [
                    'name' => $serviceData['name'],
                    'icon' => $serviceData['icon'] ?? null,
                    'category' => $serviceData['category'],
                    'scope' => $serviceData['scope'],
                    'is_active' => $serviceData['is_active'] ?? true,
                ]
            );

            $this->services[$serviceData['key']] = $service;
        }
    }

    // Crea hoteles habitaciones imágenes servicios y disponibilidad desde JSON
    private function seedHotels(): void
    {
        foreach ($this->readJson('hotels.json') as $hotelData) {
            $owner = $this->users[$hotelData['owner_key']] ?? throw new RuntimeException("Missing owner [{$hotelData['owner_key']}]");

            $hotel = Hotel::query()->updateOrCreate(
                ['slug' => $hotelData['slug']],
                [
                    'owner_user_id' => $owner->id,
                    'name' => $hotelData['name'],
                    'description' => $hotelData['description'],
                    'stars' => $hotelData['stars'],
                    'country' => $hotelData['country'],
                    'region' => $hotelData['region'] ?? null,
                    'city' => $hotelData['city'],
                    'address' => $hotelData['address'],
                    'postal_code' => $hotelData['postal_code'] ?? null,
                    'latitude' => $hotelData['latitude'] ?? null,
                    'longitude' => $hotelData['longitude'] ?? null,
                    'contact_email' => $hotelData['contact_email'] ?? null,
                    'contact_phone' => $hotelData['contact_phone'] ?? null,
                    'check_in_time' => $hotelData['check_in_time'] ?? '15:00:00',
                    'check_out_time' => $hotelData['check_out_time'] ?? '11:00:00',
                    'cancellation_policy' => $hotelData['cancellation_policy'] ?? null,
                    'tax_rate_percent' => $hotelData['tax_rate_percent'] ?? 0,
                    'discount_rate_percent' => $hotelData['discount_rate_percent'] ?? 0,
                    'pets_allowed' => $hotelData['pets_allowed'] ?? false,
                    'smoking_allowed' => $hotelData['smoking_allowed'] ?? false,
                    'status' => $hotelData['status'] ?? 'published',
                ]
            );

            $this->hotels[$hotelData['key']] = $hotel;
            $this->syncHotelServices($hotel, $hotelData['services'] ?? []);
            $this->replaceHotelImages($hotel, $hotelData['images'] ?? []);
            $this->seedRoomTypes($hotel, $hotelData['room_types'] ?? []);
        }
    }

    // Carga reservas comentarios pagos y favoritos de clientes demo
    private function seedInteractions(): void
    {
        $interactions = $this->readJson('customer_activity.json');

        foreach ($interactions['bookings'] ?? [] as $bookingData) {
            $this->seedBooking($bookingData);
        }

        foreach ($interactions['favorites'] ?? [] as $favoriteData) {
            $user = $this->users[$favoriteData['user_key']] ?? throw new RuntimeException("Missing user [{$favoriteData['user_key']}]");

            foreach ($favoriteData['hotel_keys'] as $hotelKey) {
                $hotel = $this->hotels[$hotelKey] ?? throw new RuntimeException("Missing hotel [{$hotelKey}]");

                Favorite::query()->firstOrCreate([
                    'user_id' => $user->id,
                    'hotel_id' => $hotel->id,
                ]);
            }
        }
    }

    // Crea los tipos de habitación de un hotel con imágenes servicios y calendario
    private function seedRoomTypes(Hotel $hotel, array $roomTypes): void
    {
        foreach ($roomTypes as $roomTypeData) {
            $roomType = RoomType::query()->updateOrCreate(
                [
                    'hotel_id' => $hotel->id,
                    'name' => $roomTypeData['name'],
                ],
                [
                    'description' => $roomTypeData['description'],
                    'capacity_adults' => $roomTypeData['capacity_adults'],
                    'capacity_children' => $roomTypeData['capacity_children'],
                    'size_m2' => $roomTypeData['size_m2'] ?? null,
                    'bed_type' => $roomTypeData['bed_type'] ?? null,
                    'base_price' => $roomTypeData['base_price'],
                    'currency' => $roomTypeData['currency'] ?? 'EUR',
                    'total_units' => $roomTypeData['total_units'],
                    'free_cancellation_hours' => $roomTypeData['free_cancellation_hours'] ?? null,
                    'status' => $roomTypeData['status'] ?? 'active',
                ]
            );

            $this->roomTypes[$roomTypeData['key']] = $roomType;
            $this->syncRoomTypeServices($roomType, $roomTypeData['services'] ?? []);
            $this->replaceRoomTypeImages($roomType, $roomTypeData['images'] ?? []);
            $this->seedAvailability($roomType, $roomTypeData['availability'] ?? []);
        }
    }

    // Genera disponibilidad futura desde reglas compactas declaradas en JSON
    private function seedAvailability(RoomType $roomType, array $availability): void
    {
        if ($availability === []) {
            return;
        }

        $start = CarbonImmutable::parse($availability['start'] ?? 'today');
        $days = $availability['days'] ?? 90;
        $weekendMultiplier = (float) ($availability['weekend_multiplier'] ?? 1.2);
        $closedWeekdays = $availability['closed_weekdays'] ?? [];

        for ($day = 0; $day < $days; $day++) {
            $date = $start->addDays($day);
            $isClosed = in_array($date->dayOfWeekIso, $closedWeekdays, true);
            $price = (float) $roomType->base_price * ($date->isWeekend() ? $weekendMultiplier : 1);

            RoomTypeAvailability::query()->updateOrCreate(
                [
                    'room_type_id' => $roomType->id,
                    'date' => $date->toDateString(),
                ],
                [
                    'available_units' => $isClosed ? 0 : $roomType->total_units,
                    'price' => round($price, 2),
                    'currency' => $roomType->currency,
                    'status' => $isClosed ? 'closed' : 'open',
                    'min_stay_nights' => $date->isWeekend() ? 2 : 1,
                ]
            );
        }
    }

    // Crea una reserva con sus huéspedes pago y reseña si están definidos
    private function seedBooking(array $bookingData): void
    {
        $user = $this->users[$bookingData['user_key']] ?? throw new RuntimeException("Missing user [{$bookingData['user_key']}]");
        $roomType = $this->roomTypes[$bookingData['room_type_key']] ?? throw new RuntimeException("Missing room type [{$bookingData['room_type_key']}]");
        $hotel = $roomType->hotel;
        $checkIn = CarbonImmutable::parse($bookingData['check_in']);
        $checkOut = CarbonImmutable::parse($bookingData['check_out']);
        $nights = (int) $checkIn->diffInDays($checkOut);
        $subtotal = $this->bookingSubtotal($roomType, $checkIn, $checkOut, $bookingData['units_booked'] ?? 1);
        $taxes = round($subtotal * ((float) $hotel->tax_rate_percent / 100), 2);
        $discount = round($subtotal * ((float) $hotel->discount_rate_percent / 100), 2);

        $booking = Booking::query()->updateOrCreate(
            ['booking_reference' => $bookingData['reference']],
            [
                'user_id' => $user->id,
                'hotel_id' => $hotel->id,
                'room_type_id' => $roomType->id,
                'hotel_name' => $hotel->name,
                'room_type_name' => $roomType->name,
                'customer_name' => $bookingData['customer_name'] ?? $user->name,
                'customer_email' => $bookingData['customer_email'] ?? $user->email,
                'customer_phone' => $bookingData['customer_phone'] ?? $user->phone,
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'nights' => $nights,
                'adults_count' => $bookingData['adults_count'],
                'children_count' => $bookingData['children_count'] ?? 0,
                'units_booked' => $bookingData['units_booked'] ?? 1,
                'status' => $bookingData['status'],
                'payment_method' => $bookingData['payment_method'] ?? 'hotel',
                'payment_status' => $bookingData['payment_status'],
                'subtotal_amount' => $subtotal,
                'taxes_amount' => $taxes,
                'discount_amount' => $discount,
                'total_amount' => round($subtotal + $taxes - $discount, 2),
                'currency' => $roomType->currency,
                'booked_at' => $bookingData['booked_at'],
                'expires_at' => $bookingData['expires_at'] ?? null,
                'confirmed_at' => $bookingData['confirmed_at'] ?? null,
                'cancelled_at' => $bookingData['cancelled_at'] ?? null,
                'cancellation_deadline_at' => $bookingData['cancellation_deadline_at'] ?? null,
                'notes' => $bookingData['notes'] ?? null,
            ]
        );

        $this->bookings[$bookingData['key']] = $booking;
        $this->replaceGuests($booking, $bookingData['guests'] ?? []);
        $this->replacePayments($booking, $bookingData['payments'] ?? []);
        $this->replaceReview($booking, $bookingData['review'] ?? null);
    }

    // Calcula el subtotal usando la disponibilidad diaria generada
    private function bookingSubtotal(RoomType $roomType, CarbonImmutable $checkIn, CarbonImmutable $checkOut, int $unitsBooked): float
    {
        $prices = RoomTypeAvailability::query()
            ->where('room_type_id', $roomType->id)
            ->where('date', '>=', $checkIn->toDateString())
            ->where('date', '<', $checkOut->toDateString())
            ->pluck('price');

        if ($prices->isEmpty()) {
            return (float) $roomType->base_price * (int) $checkIn->diffInDays($checkOut) * $unitsBooked;
        }

        return (float) $prices->sum() * $unitsBooked;
    }

    // Reemplaza los huéspedes de una reserva para mantener el JSON como fuente
    private function replaceGuests(Booking $booking, array $guests): void
    {
        $booking->guests()->delete();

        foreach ($guests as $guestData) {
            $booking->guests()->create($guestData);
        }
    }

    // Reemplaza los pagos de una reserva para mantener importes y estados controlados
    private function replacePayments(Booking $booking, array $payments): void
    {
        $booking->payments()->delete();

        foreach ($payments as $paymentData) {
            if (($paymentData['amount'] ?? null) === 'total') {
                $paymentData['amount'] = $booking->total_amount;
            }

            $booking->payments()->create($paymentData);
        }
    }

    // Reemplaza la reseña asociada a una reserva completada
    private function replaceReview(Booking $booking, ?array $reviewData): void
    {
        $booking->review()->delete();

        if ($reviewData === null) {
            return;
        }

        Review::query()->create([
            'hotel_id' => $booking->hotel_id,
            'user_id' => $booking->user_id,
            'booking_id' => $booking->id,
            'rating' => $reviewData['rating'],
            'comment' => $reviewData['comment'] ?? null,
            'status' => $reviewData['status'] ?? 'published',
        ]);
    }

    // Sincroniza los servicios de un hotel usando claves del JSON
    private function syncHotelServices(Hotel $hotel, array $serviceKeys): void
    {
        $hotel->services()->sync($this->serviceIds($serviceKeys));
    }

    // Sincroniza los servicios de una habitación usando claves del JSON
    private function syncRoomTypeServices(RoomType $roomType, array $serviceKeys): void
    {
        $roomType->services()->sync($this->serviceIds($serviceKeys));
    }

    // Devuelve IDs de servicios a partir de claves estables
    private function serviceIds(array $serviceKeys): array
    {
        return collect($serviceKeys)
            ->map(function (string $key): int {
                if (! isset($this->services[$key])) {
                    throw new RuntimeException("Missing service [{$key}]");
                }

                return $this->services[$key]->id;
            })
            ->all();
    }

    // Reemplaza las imágenes de hotel definidas en JSON
    private function replaceHotelImages(Hotel $hotel, array $images): void
    {
        $hotel->images()->delete();

        foreach ($images as $index => $imageData) {
            HotelImage::query()->create([
                'hotel_id' => $hotel->id,
                'image_url' => $imageData['url'],
                'alt_text' => $imageData['alt_text'] ?? $hotel->name,
                'is_cover' => $imageData['is_cover'] ?? $index === 0,
                'sort_order' => $imageData['sort_order'] ?? $index,
            ]);
        }
    }

    // Reemplaza las imágenes de habitación definidas en JSON
    private function replaceRoomTypeImages(RoomType $roomType, array $images): void
    {
        $roomType->images()->delete();

        foreach ($images as $index => $imageData) {
            RoomTypeImage::query()->create([
                'room_type_id' => $roomType->id,
                'image_url' => $imageData['url'],
                'alt_text' => $imageData['alt_text'] ?? $roomType->name,
                'is_cover' => $imageData['is_cover'] ?? $index === 0,
                'sort_order' => $imageData['sort_order'] ?? $index,
            ]);
        }
    }

    // Lee y valida un archivo JSON de datos demo
    private function readJson(string $file): array
    {
        $path = "{$this->basePath}/{$file}";

        if (! is_file($path)) {
            throw new RuntimeException("Missing demo data file [{$file}]");
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data)) {
            throw new RuntimeException("Invalid demo data file [{$file}]");
        }

        return $data;
    }
}
