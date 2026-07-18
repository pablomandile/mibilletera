# Mi Billetera 💰

Administrador de gastos personales inspirado en la app **Money Manager**. Permite registrar gastos, ingresos y transferencias entre cuentas, con soporte multi-moneda, presupuestos, transacciones recurrentes, recordatorios y reportes visuales. Pensado para verse bien tanto en escritorio como en mobile (Chrome), e instalable como **PWA**.

> Documentación relacionada: [ARCHITECTURE.md](ARCHITECTURE.md) · [BUSINESS_RULES.md](BUSINESS_RULES.md)

---

## ✨ Funcionalidades

- **Autenticación**: email/contraseña + **Google** (OAuth vía Socialite). Recuperación de contraseña.
- **Roles**: `admin` y `user` (por defecto). Panel de administración para reasignar roles.
- **Carga de transacciones** con una pantalla tipo calculadora: **Gasto / Ingreso / Transferencia**, grilla de categorías, teclado con operaciones, nota, foto adjunta y fecha.
- **Categorías** propias y subcategorías, con íconos de [Tabler](https://tabler.io/icons) o **imagen subida** (ej. logo de un comercio).
- **Cuentas** múltiples (efectivo, banco, tarjeta, otro) con **saldo calculado** y **multi-moneda** (ARS/USD/EUR/BRL) con tipo de cambio manual.
- **Transferencias** entre cuentas, incluso de distinta moneda.
- **Inicio**: lista de movimientos por fecha + totales del mes (en moneda base).
- **Gráficos**: torta por categoría, barras ingreso/gasto y línea de tendencia (Semana / Mes / Año).
- **Informes**: resumen mensual con comparación contra el mes anterior y desglose por categoría.
- **Presupuestos** por categoría y global, con barra de progreso gasto vs. límite.
- **Recurrentes**: transacciones automáticas periódicas generadas por un comando programado (con catch-up).
- **Recordatorios** + **campanita de notificaciones** (badge de no leídos, panel, swipe para borrar).
- **Ajustes**: perfil, moneda predeterminada, tema claro/oscuro, gestión de categorías y cuentas.
- **Responsive** (sidebar en PC / barra inferior con botón **+** en mobile) + **PWA instalable**.

---

## 🧰 Stack

| Capa | Tecnología |
|------|------------|
| Backend | Laravel **12** (PHP 8.2+), MySQL |
| Puente SPA | Inertia.js **2** + Ziggy |
| Frontend | Vue **3** + Vite 7 |
| UI | **PrimeVue 4** + Tailwind CSS 3 + `tailwindcss-primeui` |
| Íconos | `@tabler/icons-vue` (categorías) + PrimeIcons |
| Gráficos | Chart.js (vía componente `Chart` de PrimeVue) |
| Auth | Laravel Breeze (email/password) + Laravel Socialite (Google) |
| Tests | PHPUnit 11 |

---

## 🚀 Puesta en marcha

**Requisitos:** PHP 8.2+, Composer, Node 18+, MySQL (o MariaDB). En Windows se probó con **Laragon**.

```bash
# 1. Dependencias
composer install
npm install

# 2. Entorno
cp .env.example .env
php artisan key:generate
# Configurar en .env: DB_DATABASE=mibilletera, DB_USERNAME, DB_PASSWORD

# 3. Base de datos (crear la DB "mibilletera" y luego)
php artisan migrate --seed
php artisan storage:link

# 4. Frontend
npm run build       # o `npm run dev` para desarrollo con hot-reload

# 5. Servir
php artisan serve   # http://127.0.0.1:8000  (o vhost de Laragon)

# 6. Tareas programadas (recurrentes + recordatorios)
php artisan schedule:work
```

### Usuarios sembrados (`--seed`)

| Rol | Email | Contraseña |
|-----|-------|-----------|
| Admin | `admin@mibilletera.test` | `admin1234` |
| Usuario | `test@example.com` | `password` |

> ⚠️ Cambiá estas contraseñas antes de cualquier despliegue real.

---

## ⚙️ Configuración opcional

### Google OAuth
En `.env`:
```
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
```
La URI de redirección debe coincidir con `APP_URL` y estar registrada en Google Cloud Console → Credenciales.

### Correo (reseteo de contraseña, etc.)
Por defecto `MAIL_MAILER=log` (los mails van al log). Para envío real (ej. Hostinger):
```
MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=tu-casilla
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="tu-casilla"
```

---

## 🧪 Tests

```bash
php artisan test
```
Suite de feature tests que cubre: sembrado de datos por usuario, carga de gastos/ingresos, transferencias, conversión multi-moneda, presupuestos, informes, recurrentes (con catch-up), recordatorios, notificaciones, roles y ajustes.

---

## 🗓️ Comandos programados

| Comando | Frecuencia | Descripción |
|---------|-----------|-------------|
| `php artisan recurring:run` | diario 00:05 | Genera las transacciones recurrentes pendientes (con catch-up). |
| `php artisan reminders:dispatch` | cada minuto | Crea alertas (campanita) para recordatorios cuya hora ya pasó hoy. |

En producción, un cron debe ejecutar `php artisan schedule:run` cada minuto.

---

## 📌 Estado y pendientes

**Completo:** auth + roles, carga con calculadora, cuentas multi-moneda, transferencias, gráficos, informes, presupuestos, recurrentes, recordatorios + notificaciones, ajustes, responsive + PWA.

**Pendiente (opcional):**
- Configurar credenciales reales de Google OAuth y SMTP.
- Traducir al español los mensajes de validación de Laravel (`lang/es`).
- Push real de recordatorios (Web Push).
- Limpieza de archivos remanentes de Breeze sin uso (`AuthenticatedLayout.vue`, `Profile/Partials/*`, `Welcome.vue`).
- Optimizar el bundle JS (code-splitting de íconos).
