# Arquitectura — Mi Billetera

Documento técnico del diseño del sistema. Para las reglas de dominio ver [BUSINESS_RULES.md](BUSINESS_RULES.md).

---

## 1. Visión general

Aplicación **monolítica** con Laravel en el backend y Vue en el frontend, unidos por **Inertia.js** (no hay API REST separada). El servidor resuelve rutas, autenticación y autorización; devuelve "páginas" Inertia (componente Vue + props) que se renderizan como una SPA. Es una **PWA instalable** y responsive.

```
Navegador (Vue 3 + PrimeVue)
      │  Inertia (XHR con JSON de props)
      ▼
Laravel 12 (rutas → controllers → Eloquent)
      │
      ▼
MySQL
```

### Stack

- **Backend:** Laravel 12 (PHP 8.2+), MySQL, Inertia Laravel 2, Ziggy (rutas en JS), Socialite (Google), Breeze (scaffolding de auth email/password).
- **Frontend:** Vue 3 (`<script setup>`), Vite 7, PrimeVue 4 (modo *styled* con preset **Aura** personalizado), Tailwind CSS 3 + `tailwindcss-primeui`, `@tabler/icons-vue`, Chart.js.
- **Tests:** PHPUnit 11 (feature tests con SQLite en memoria).

---

## 2. Estructura de carpetas relevante

```
app/
├── Console/Commands/
│   ├── GenerateRecurringTransactions.php   # recurring:run
│   └── DispatchReminders.php               # reminders:dispatch
├── Actions/SeedDefaultUserData.php         # datos iniciales de cada usuario
├── Observers/UserObserver.php              # dispara el sembrado al crear un User
├── Support/DefaultCategories.php           # catálogo de categorías por defecto
├── Http/
│   ├── Controllers/                        # uno por dominio (ver §5)
│   └── Middleware/
│       ├── HandleInertiaRequests.php       # props compartidas (auth, flash, notifications)
│       └── EnsureUserIsAdmin.php           # alias 'admin'
└── Models/                                 # Eloquent (ver §4)

resources/
├── js/
│   ├── app.js          # bootstrap Inertia + PrimeVue + locale es + registro SW
│   ├── theme.js        # preset Aura personalizado (ámbar + superficies oscuras)
│   ├── tablerIcons.js  # mapa clave→componente de íconos de categoría
│   ├── Layouts/        # AppLayout (app), GuestLayout (auth)
│   ├── Pages/          # páginas Inertia (ver §6)
│   └── Components/      # CategoryIcon, NotificationBell, *FormDialog, etc.
├── css/app.css         # capas Tailwind ↔ PrimeVue
└── views/app.blade.php # HTML raíz + tema anti-flash + manifest PWA

public/
├── manifest.webmanifest, sw.js, icons/    # PWA
routes/
├── web.php     # rutas de la app (auth + verified)
├── auth.php    # rutas de Breeze + Google
└── console.php # scheduler
```

---

## 3. Ciclo de un request (Inertia)

1. El navegador visita una URL o hace `router.visit/post/...`.
2. Laravel matchea la ruta (`routes/web.php`) protegida por `auth` (+ `verified`).
3. El controller consulta Eloquent y devuelve `Inertia::render('Pagina', [...props])`.
4. `HandleInertiaRequests::share()` inyecta props globales en **toda** respuesta:
   - `auth.user` — el usuario autenticado (incluye `role`, `default_currency`, `avatar`).
   - `flash.success` / `flash.created_category_id` — mensajes de una acción.
   - `notifications` — `{ unread, items[] }` para la campanita.
5. El cliente monta/actualiza el componente Vue con las props. Mutaciones (POST/PATCH/DELETE) devuelven `back()`/`redirect()`; Inertia recarga las props frescas.

**Autorización:** cada controller valida pertenencia con `abort_unless($model->user_id === Auth::id(), 403)` o con reglas `Rule::exists(...)->where('user_id', ...)`. Las rutas de administración van bajo el middleware `admin`.

---

## 4. Modelo de datos

Todas las entidades de usuario cuelgan de `users` (relaciones `hasMany`) y se borran en cascada.

| Tabla | Columnas clave | Notas |
|-------|----------------|-------|
| **users** | `role` (default `user`), `google_id`, `avatar`, `password` (nullable), `default_currency` (ARS), `locale`, `timezone`, `month_start_day`, `theme` | `role` **no** es `fillable` (anti escalada). `#[ObservedBy(UserObserver)]`. |
| **currencies** | `code` (PK, ISO 4217), `name`, `symbol`, `decimal_places` | Datos de referencia (ARS, USD, EUR, BRL). |
| **accounts** | `user_id`, `name`, `type`, `currency_code`, `initial_balance`, `color`, `icon`, `is_archived`, `sort_order` | Saldo se **calcula** (no se persiste). |
| **categories** | `user_id`, `parent_id` (subcategoría), `type` (expense/income), `name`, `icon_type` (preset/image), `icon_value`, `color`, `is_system` | Íconos Tabler o imagen en disco `public`. |
| **transactions** | `user_id`, `account_id`, `category_id`, `type` (expense/income/transfer), `amount`, `currency_code`, `exchange_rate`, `to_account_id`, `transfer_amount`, `transfer_currency`, `note`, `photo_path`, `transaction_date` | `exchange_rate` convierte a moneda base. Transferencias usan `to_account_id`/`transfer_amount`. |
| **budgets** | `user_id`, `category_id` (null = global), `amount`, `currency_code`, `period` (monthly) | Único por `(user_id, category_id)`. |
| **recurring_transactions** | campos de transacción + `frequency`, `interval`, `next_run_date`, `end_date`, `last_generated_at`, `is_active` | Plantilla que genera transacciones. |
| **reminders** | `user_id`, `title`, `time`, `days` (JSON), `enabled` | `days` vacío = todos los días. |
| **alerts** | `user_id`, `reminder_id`, `title`, `body`, `read_at` | Notificaciones de la campanita. |

**Relaciones (Eloquent):** `User hasMany` → accounts, categories, transactions, budgets, recurringTransactions, reminders, alerts. `Category` self-ref (`parent`/`children`). `Transaction belongsTo` account, toAccount, category. `Account belongsTo` currency.

**Cálculo de saldo** (`Account::currentBalance()`):
`initial_balance + Σ ingresos − Σ gastos − Σ transferencias salientes (amount) + Σ transferencias entrantes (transfer_amount)`.

**Conversión a moneda base** (Dashboard/Charts/Reports): cada monto se convierte con `amount * exchange_rate`; las transferencias se excluyen de los totales de ingreso/gasto.

---

## 5. Controllers (por dominio)

| Controller | Responsabilidad |
|------------|-----------------|
| `DashboardController` | Inicio: movimientos del mes + totales (base). |
| `TransactionController` | Pantalla "Agregar" + guardar (gasto/ingreso/transferencia) + borrar. |
| `AccountController` | CRUD de cuentas + saldos. |
| `CategoryController` | CRUD de categorías/subcategorías (subida de imagen). |
| `ChartsController` | Datos de torta/barras/línea por rango (semana/mes/año). |
| `ReportsController` | Resumen mensual + desglose + comparación mes anterior. |
| `BudgetController` | Presupuestos (upsert por categoría/global) + gasto del mes. |
| `RecurringTransactionController` | CRUD de recurrentes. |
| `ReminderController` | CRUD de recordatorios. |
| `NotificationController` | Marcar alertas leídas / borrar. |
| `PreferencesController` | Moneda predeterminada / tema. |
| `AdminUserController` | (admin) listar usuarios / cambiar rol. |
| `Auth/GoogleController` | Flujo OAuth de Google. |

---

## 6. Frontend

- **Layouts:** `AppLayout.vue` (shell autenticado: sidebar en `md+`, barra inferior con botón **+** grande y redondo en mobile, barra superior con la **campanita**, `Toast` de PrimeVue). `GuestLayout.vue` para pantallas de auth.
- **Páginas** (`resources/js/Pages`): `Dashboard`, `Charts/Index`, `Reports/Index`, `Transactions/Create`, `Settings/{Index,Accounts,Categories,Budgets,Recurring,Reminders}`, `Admin/Users`, `Profile/Edit`, `Auth/*`.
- **Componentes propios:** `CategoryIcon` (ícono Tabler o imagen sobre círculo de color), `CategoryFormDialog`, `AccountFormDialog`, `NotificationBell` + `AlertItem` (swipe), `GoogleButton`.
- **Íconos de categoría:** `tablerIcons.js` mapea claves kebab-case (ej. `shopping-cart`) a componentes Tabler; `resolveIcon()` cae a un ícono genérico si falta.
- **Formato de moneda:** `Intl.NumberFormat('es-AR')`. La calculadora evalúa expresiones **de izquierda a derecha** (sin precedencia), como Money Manager.

### Tema
`theme.js` define un preset basado en **Aura** con primario **ámbar** y superficies neutras oscuras. Modo oscuro por defecto vía clase `.dark` en `<html>` (script anti-flash en `app.blade.php` que respeta `localStorage.theme`). `app.css` ordena las capas CSS (`tailwind-base, primevue, tailwind-utilities`) para que las utilidades de Tailwind ganen a los estilos de PrimeVue.

### PWA
`manifest.webmanifest` + `sw.js` (service worker manual: cache-first para estáticos, network-first para navegaciones) + íconos en `public/icons`. Registrado en `app.js`.

---

## 7. Tareas programadas y notificaciones

- **`recurring:run`** (`GenerateRecurringTransactions`, diario 00:05): por cada recurrente activa vencida genera las transacciones pendientes con **catch-up** (tope 366 iteraciones), avanza `next_run_date` según frecuencia/intervalo y desactiva las que pasaron su `end_date`.
- **`reminders:dispatch`** (`DispatchReminders`, cada minuto): por cada recordatorio activo cuya hora ya pasó hoy (y el día corresponde) crea un `alert` — **una vez por día por recordatorio**.
- La campanita lee `notifications` (compartido en Inertia): `unread` = alertas sin `read_at`; al abrir el panel se marcan todas leídas; el swipe-izquierda borra una alerta.

El scheduler corre con `php artisan schedule:work` (dev) o un cron con `schedule:run` (producción).

---

## 8. Autenticación

- **Email/contraseña:** scaffolding de Breeze (rutas en `routes/auth.php`).
- **Google:** `GoogleController` hace *find-or-create* por `google_id` o `email`, marca el email como verificado y loguea. Requiere credenciales en `.env`.
- El modelo `User` **no** implementa `MustVerifyEmail`, por lo que el middleware `verified` no bloquea (registro entra directo).
- Al crear cualquier usuario, `UserObserver` invoca `SeedDefaultUserData`: crea una cuenta "Efectivo" (ARS) y el set de categorías por defecto (ver [BUSINESS_RULES.md](BUSINESS_RULES.md)).

---

## 9. Convenciones

- Un controller por dominio; validación con `$request->validate([...])` y reglas de pertenencia.
- Scoping siempre por `user_id`; nunca confiar en IDs del cliente sin verificar pertenencia.
- Montos `decimal(15,2)`; tipos de cambio `decimal(18,8)`.
- Respuestas de mutación devuelven `back()` con `flash('success', ...)` → toast en el frontend.
- Íconos y colores viven en la fila de la categoría/cuenta (no hardcodeados en el front).
