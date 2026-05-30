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
- `admin`: administrador para el panel web Laravel. No puede iniciar sesión por la API ni usar el flujo del frontend React.

## Usuarios de prueba

Si has ejecutado los seeders, tendrás estas credenciales fijas:

- `owner01@dreamrooms.test` / `password`
- `cliente01@dreamrooms.test` / `password`
- `admin@dreamrooms.com` / `12345678` para el panel web Laravel

`owner01@dreamrooms.test` y `cliente01@dreamrooms.test` sirven para probar el login de API y los flujos del frontend React. El usuario admin queda fuera de la API pública de autenticación.

## Documentación de la API

La documentación técnica de la API se genera con Scramble, que expone la especificación OpenAPI compatible con Swagger.

Rutas disponibles en local:

```http
GET /docs/api       # documentación visual generada por Scramble
GET /docs/api.json  # especificación OpenAPI en JSON
GET /docs/swagger   # vista Swagger del proyecto
```

`/docs/api` permite consultar la documentación visual generada por Scramble, `/docs/api.json` devuelve la especificación OpenAPI en formato JSON y `/docs/swagger` muestra la vista Swagger definida en el proyecto.

---

# Panel admin web

El panel admin usa autenticación web de Laravel Breeze y requiere un usuario con rol `admin`.

Rutas principales:

```http
GET /dashboard
GET /admin/users
GET /admin/hotels
GET /admin/room-types
GET /admin/availability
GET /admin/bookings
GET /admin/reviews
GET /admin/services
```

Desde `/dashboard`, un usuario admin se redirige a `/admin/users`.

---

# 1. Auth

## 1.1 `POST /api/auth/register`

Registra un usuario activo y devuelve token Sanctum. `account_type` puede ser `customer` u `owner`; si no se envía, se usa `customer`.

Body:

```json
{
  "name": "Laura García Molina",
  "email": "cliente@example.com",
  "phone": "+34600000000",
  "account_type": "customer",
  "password": "password123",
  "password_confirmation": "password123"
}
```

Respuesta: usuario, rol seleccionado, `token` y `token_type`.

## 1.2 `POST /api/auth/login`

Inicia sesión con email y contraseña. Solo devuelve token si el usuario está `active` y su rol es `customer` u `owner`.

Los usuarios `admin` no pueden iniciar sesión por esta ruta; el panel admin usa la autenticación web de Laravel.

Body:

```json
{
  "email": "owner01@dreamrooms.test",
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

Este endpoint devuelve filas de calendario por fecha. El rango `from`/`to` es inclusivo porque sirve para pintar calendario.

Para calcular una estancia, la API de reservas usa otra regla: `check_in` incluido y `check_out` excluido. Una estancia del `2026-05-01` al `2026-05-03` ocupa y cobra solo las noches `2026-05-01` y `2026-05-02`.

Conceptos de unidades:

- `total_units`: inventario total del tipo de habitación. Ejemplo: el hotel tiene 8 habitaciones dobles en total.
- `available_units`: disponibilidad de una fecha concreta del calendario. Cambia por día y baja cuando se reserva esa noche.
- `available_units_for_stay`: disponibilidad real para una estancia completa. Es el mínimo de `available_units` entre las noches seleccionadas, y será `0` si alguna noche falta, está cerrada o no cumple las reglas.

## 3.2 `GET /api/room-types/{roomTypeId}/availability/quote`

Comprueba si una estancia concreta se puede reservar y devuelve el mismo cálculo de noches e importes que usará `POST /api/customer/bookings`.

Query params obligatorios:

```http
check_in=2026-05-01
check_out=2026-05-03
```

Query params opcionales:

```http
units_booked=1
```

Ejemplo:

```http
GET /api/room-types/1/availability/quote?check_in=2026-05-01&check_out=2026-05-03&units_booked=1
```

Respuesta:

```json
{
  "data": {
    "room_type_id": 1,
    "check_in": "2026-05-01",
    "check_out": "2026-05-03",
    "nights": 2,
    "stay_dates": ["2026-05-01", "2026-05-02"],
    "units_booked": 1,
    "is_available": true,
    "total_units": 8,
    "available_units_for_stay": 6,
    "remaining_units_after_booking": 5,
    "daily_available_units": [
      {
        "date": "2026-05-01",
        "available_units": 6,
        "status": "open"
      },
      {
        "date": "2026-05-02",
        "available_units": 7,
        "status": "open"
      }
    ],
    "availability_issues": {
      "is_available": true,
      "missing_dates": [],
      "closed_dates": [],
      "insufficient_dates": [],
      "min_stay_violations": []
    },
    "subtotal_amount": 420,
    "taxes_amount": 42,
    "discount_amount": 0,
    "total_amount": 462,
    "currency": "EUR"
  }
}

```

Usa este endpoint en el front cuando el usuario ya ha elegido entrada, salida y unidades. `GET /availability` queda para pintar días del calendario.

Para mostrar “quedan X habitaciones”, usa `available_units_for_stay`, no `total_units` ni la suma de `available_units`. En el ejemplo anterior se muestran 6 disponibles para toda la estancia porque la noche más limitada tiene 6.

---

# 4. Área de cliente

Todas estas rutas requieren token Sanctum y rol `customer`.

El usuario siempre se obtiene desde el token. No se acepta `user_id` para suplantar usuarios.

## 4.1 `GET /api/customer/bookings`

Devuelve las reservas del cliente autenticado.

## 4.2 `GET /api/customer/bookings/{bookingId}`

Devuelve el detalle de una reserva del cliente autenticado.

## 4.3 `POST /api/customer/bookings`

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
  "payment_method": "card",
  "customer_name": "Laura García Molina",
  "customer_email": "cliente@example.com",
  "customer_phone": "+34600000000",
  "notes": "Llegada tarde",
  "guests": [
    {
      "full_name": "Laura García Molina",
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

Valores de `payment_method`:

- `card`: pago simulado con tarjeta, la reserva queda `confirmed` y `paid`
- `hotel`: pago en el hotel, la reserva queda `pending` y el owner registrará el pago completo

## 4.4 `POST /api/customer/bookings/{bookingId}/cancel`

Cancela una reserva del cliente autenticado.

Si se cancela, restaura unidades en la disponibilidad diaria.

## 4.5 `POST /api/customer/bookings/{bookingId}/review`

Crea una reseña para una reserva completada del cliente autenticado.

Body:

```json
{
  "rating": 5,
  "comment": "Muy buena estancia"
}
```

Solo se permite una reseña por reserva.

## 4.6 `GET /api/customer/favorites`

Devuelve los hoteles favoritos del cliente autenticado.

## 4.7 `POST /api/customer/hotels/{hotelId}/favorite`

Añade un hotel publicado a favoritos del cliente autenticado.

## 4.8 `DELETE /api/customer/hotels/{hotelId}/favorite`

Elimina el favorito del cliente autenticado.

El borrado es real sobre la fila de favorito.

---

# 5. Área de propietario: hoteles

Todas estas rutas requieren token Sanctum y rol `owner`.

El propietario siempre se obtiene desde el token. No se acepta `owner_user_id` desde query o body.

## 5.1 `GET /api/owner/services`

Devuelve el catálogo de servicios activos que se pueden asociar a hoteles o tipos de habitación.

Filtro opcional:

```http
scope=hotel
scope=room_type
```

Si se envía `scope`, también se incluyen servicios con scope `both`.

## 5.2 `GET /api/owner/hotels`

Devuelve los hoteles del propietario autenticado.

## 5.3 `GET /api/owner/hotels/{hotelId}`

Devuelve el detalle de un hotel del propietario autenticado.

El `{hotelId}` es el id del hotel.

## 5.4 `POST /api/owner/hotels`

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

## 5.5 `PUT /api/owner/hotels/{hotelId}`

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

Registra un pago manual completo sobre una reserva de pago en hotel.

Esta ruta está en owner, no en cliente, para evitar que un cliente marque su propia reserva como pagada.
No permite pagos parciales ni reservas pagadas por tarjeta.

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

- `manual`

Valores de `status`:

- `paid`
- `failed`
- `refunded`

Si no se envía `status`, se usa `paid` por defecto.

---

# 9. Notas de seguridad actuales

- El catálogo y la disponibilidad pública no requieren login.
- Reservas de cliente, favoritos y reseñas requieren token de cliente.
- Endpoints owner requieren token de propietario.
- El rol admin no puede iniciar sesión por `/api/auth/login` ni acceder a las rutas privadas de cliente/propietario por API.
- La API no acepta `user_id` ni `owner_user_id` para decidir permisos en endpoints privados.
