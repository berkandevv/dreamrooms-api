# DreamRooms API

API Laravel para catálogo de hoteles, reservas, favoritos, autenticación y gestión de propietario.

## Autenticación

La API usa tokens Bearer con Laravel Sanctum.

Las rutas públicas no necesitan token. Las rutas de cliente y propietario requieren:

```http
Authorization: Bearer TU_TOKEN
Accept: application/json
```

Roles actuales:

- `customer`: cliente que reserva, consulta sus reservas y gestiona favoritos.
- `owner`: propietario que gestiona sus hoteles, habitaciones, disponibilidad, reservas y pagos.
- `admin`: reservado para uso futuro; el panel admin irá por vistas.

---

# 1. Auth

## 1.1 `POST /api/auth/register`

Registra un usuario cliente activo y devuelve token Sanctum.

Body:

```json
{
  "name": "Cliente Demo",
  "email": "cliente@example.com",
  "phone": "+34600000000",
  "password": "password123",
  "password_confirmation": "password123"
}
```

Respuesta: usuario, rol `customer`, `token` y `token_type`.

## 1.2 `POST /api/auth/login`

Inicia sesión con email y contraseña. Solo devuelve token si el usuario está `active`.

Body:

```json
{
  "email": "owner@example.com",
  "password": "password"
}
```

## 1.3 `GET /api/auth/me`

Requiere token. Devuelve el usuario autenticado.

## 1.4 `POST /api/auth/logout`

Requiere token. Revoca solo el token actual.

---

# 2. Catálogo público

Estos endpoints son públicos y solo exponen hoteles publicados.

## 2.1 `GET /api/hotels`

Devuelve el listado de hoteles publicados, sin paginación.

Incluye datos de listado como ubicación, imagen de portada, precio inicial, valoración media y número de reseñas.

## 2.2 `GET /api/hotels/{slug}`

Devuelve el detalle público de un hotel publicado.

El parámetro `{slug}` es el slug del hotel, no el id.

Incluye los datos públicos de contacto del hotel:

```json
{
  "contact": {
    "email": "hotel@example.com",
    "phone": "+34600000000",
    "address": "Dirección del hotel"
  }
}
```

## 2.3 `GET /api/hotels/{slug}/reviews`

Devuelve las reseñas publicadas de un hotel publicado.

---

# 3. Disponibilidad pública

## 3.1 `GET /api/room-types/{roomTypeId}/availability`

Devuelve disponibilidad pública de un tipo de habitación activo dentro de un hotel publicado.

Query params obligatorios:

```http
from=2026-05-01
to=2026-05-03
```

Ejemplo:

```http
GET /api/room-types/1/availability?from=2026-05-01&to=2026-05-03
```

---

# 4. Área de cliente

Todas estas rutas requieren token Sanctum y rol `customer`.

El usuario siempre se obtiene desde el token. No se acepta `user_id` para suplantar usuarios.

## 4.1 `GET /api/bookings`

Devuelve las reservas del cliente autenticado.

## 4.2 `GET /api/bookings/{bookingId}`

Devuelve el detalle de una reserva del cliente autenticado.

## 4.3 `POST /api/bookings`

Crea una reserva para el cliente autenticado.

Body ejemplo:

```json
{
  "room_type_id": 1,
  "check_in": "2026-05-01",
  "check_out": "2026-05-03",
  "adults_count": 2,
  "children_count": 0,
  "units_booked": 1,
  "customer_name": "Cliente Demo",
  "customer_email": "cliente@example.com",
  "customer_phone": "+34600000000",
  "notes": "Llegada tarde",
  "guests": [
    {
      "full_name": "Cliente Demo",
      "is_primary": true
    }
  ]
}
```

La reserva valida:

- hotel publicado;
- tipo de habitación activo;
- fechas obligatorias;
- ocupación;
- disponibilidad diaria;
- estancia mínima;
- unidades disponibles.

## 4.4 `POST /api/bookings/{bookingId}/cancel`

Cancela una reserva del cliente autenticado.

Si se cancela, restaura unidades en la disponibilidad diaria.

## 4.5 `POST /api/bookings/{bookingId}/review`

Crea una reseña para una reserva completada del cliente autenticado.

Body:

```json
{
  "rating": 5,
  "comment": "Muy buena estancia"
}
```

Solo se permite una reseña por reserva.

## 4.6 `GET /api/favorites`

Devuelve los hoteles favoritos del cliente autenticado.

## 4.7 `POST /api/hotels/{hotelId}/favorite`

Añade un hotel publicado a favoritos del cliente autenticado.

## 4.8 `DELETE /api/hotels/{hotelId}/favorite`

Elimina el favorito del cliente autenticado.

El borrado es real sobre la fila de favorito.

---

# 5. Área de propietario: hoteles

Todas estas rutas requieren token Sanctum y rol `owner`.

El propietario siempre se obtiene desde el token. No se acepta `owner_user_id` desde query o body.

## 5.1 `GET /api/owner/hotels`

Devuelve los hoteles del propietario autenticado.

## 5.2 `GET /api/owner/hotels/{hotelId}`

Devuelve el detalle de un hotel del propietario autenticado.

El `{hotelId}` es el id del hotel.

## 5.3 `POST /api/owner/hotels`

Crea un hotel asociado al propietario autenticado.

Body mínimo:

```json
{
  "name": "Hotel Demo",
  "stars": 4,
  "country": "España",
  "city": "Madrid",
  "address": "Calle Demo 1"
}
```

Campos opcionales habituales:

- `description`
- `region`
- `postal_code`
- `latitude`
- `longitude`
- `contact_email`
- `contact_phone`
- `check_in_time`
- `check_out_time`
- `cancellation_policy`
- `pets_allowed`
- `smoking_allowed`
- `status`: `draft`, `published`, `inactive`

## 5.4 `PUT /api/owner/hotels/{hotelId}`

Actualiza un hotel del propietario autenticado.

---

# 6. Área de propietario: tipos de habitación

## 6.1 `GET /api/owner/hotels/{hotelId}/room-types`

Devuelve los tipos de habitación de un hotel del propietario autenticado.

## 6.2 `POST /api/owner/hotels/{hotelId}/room-types`

Crea un tipo de habitación dentro de un hotel del propietario autenticado.

Body mínimo:

```json
{
  "name": "Habitación doble",
  "capacity_adults": 2,
  "capacity_children": 1,
  "base_price": 120,
  "total_units": 8
}
```

Campos opcionales:

- `description`
- `size_m2`
- `bed_type`
- `status`: `active`, `inactive`

## 6.3 `GET /api/owner/room-types/{roomTypeId}`

Devuelve el detalle de un tipo de habitación si pertenece a un hotel del propietario autenticado.

## 6.4 `PUT /api/owner/room-types/{roomTypeId}`

Actualiza un tipo de habitación si pertenece a un hotel del propietario autenticado.

---

# 7. Área de propietario: disponibilidad

## 7.1 `GET /api/owner/room-types/{roomTypeId}/availability`

Devuelve disponibilidad privada de un tipo de habitación del propietario autenticado.

Query params obligatorios:

```http
from=2026-05-01
to=2026-05-03
```

## 7.2 `POST /api/owner/room-types/{roomTypeId}/availability/bulk`

Crea o actualiza disponibilidad diaria en bloque.

Body:

```json
{
  "items": [
    {
      "date": "2026-05-01",
      "available_units": 6,
      "price": 210.5,
      "status": "open",
      "min_stay_nights": 2
    },
    {
      "date": "2026-05-02",
      "available_units": 0,
      "price": 230,
      "status": "closed",
      "min_stay_nights": null
    }
  ]
}
```

---

# 8. Área de propietario: reservas

## 8.1 `GET /api/owner/bookings`

Devuelve reservas de hoteles del propietario autenticado.

Filtros opcionales:

```http
hotel_id=1
status=pending
payment_status=paid
```

## 8.2 `GET /api/owner/bookings/{bookingId}`

Devuelve el detalle de una reserva si pertenece a un hotel del propietario autenticado.

## 8.3 `PUT /api/owner/bookings/{bookingId}/status`

Actualiza el estado de una reserva del propietario autenticado.

Body:

```json
{
  "status": "confirmed"
}
```

Estados permitidos:

- `pending`
- `confirmed`
- `cancelled`
- `completed`

Transiciones actuales:

```text
pending
  ├─ confirmed
  │    ├─ completed
  │    └─ cancelled
  └─ cancelled
```

No se permite volver de `confirmed` a `pending`, ni reabrir reservas `cancelled` o `completed`.

Si pasa a `cancelled`, se restauran unidades en la disponibilidad diaria.

## 8.4 `POST /api/owner/bookings/{bookingId}/payments`

Registra un pago o intento de pago sobre una reserva de un hotel del propietario autenticado.

Esta ruta está en owner, no en cliente, para evitar que un cliente marque su propia reserva como pagada.

Body:

```json
{
  "provider": "manual",
  "amount": 100,
  "status": "paid",
  "currency": "EUR",
  "transaction_reference": "MANUAL-001",
  "metadata": {
    "source": "owner-panel"
  }
}
```

Valores de `provider`:

- `stripe`
- `paypal`
- `manual`

Valores de `status`:

- `pending`
- `authorized`
- `paid`
- `failed`
- `refunded`
- `partially_refunded`

Si no se envía `status`, se usa `paid` por defecto.

---

# 9. Notas de seguridad actuales

- El catálogo y la disponibilidad pública no requieren login.
- Reservas de cliente, favoritos y reseñas requieren token de cliente.
- Endpoints owner requieren token de propietario.
- La API no acepta `user_id` ni `owner_user_id` para decidir permisos en endpoints privados.
