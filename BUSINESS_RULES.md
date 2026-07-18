# Reglas de negocio — Mi Billetera

Reglas de dominio implementadas en el proyecto. Para el diseño técnico ver [ARCHITECTURE.md](ARCHITECTURE.md).

---

## 1. Usuarios y roles

- Existen dos roles: **`admin`** y **`user`**. Todo usuario nuevo (registro por email o Google) queda como **`user`** por defecto.
- El rol **no** es asignable por asignación masiva (`role` fuera de `$fillable`): nadie puede auto-otorgarse `admin` desde el registro. Solo se cambia desde el panel de administración.
- Solo un **admin** puede acceder a `/admin/users` y **reasignar roles** (User ↔ Admin).
- Un admin **no puede quitarse a sí mismo** el rol de administrador (evita quedar sin acceso).
- El usuario admin inicial se crea por seeder: `admin@mibilletera.test` / `admin1234` (cambiar en producción).

## 2. Datos iniciales de cada usuario

Al crearse un usuario, automáticamente se generan (`SeedDefaultUserData` vía `UserObserver`):

- **Una cuenta** "Efectivo" de tipo `cash` en **ARS**, saldo inicial 0.
- **27 categorías de gasto** y **6 de ingreso** por defecto, con íconos de Tabler (Compras, Alimentos, Transporte, Sueldo, etc.), marcadas como `is_system`.
- El sembrado **no se duplica**: si el usuario ya tiene categorías, se omite.

## 3. Cuentas

- Tipos: **efectivo, banco, tarjeta, otro**. Cada cuenta tiene su **moneda**.
- El **saldo es calculado**, nunca se edita a mano:
  `saldo = saldo_inicial + ingresos − gastos − transferencias_salientes + transferencias_entrantes`.
- **No se puede eliminar** una cuenta que tiene movimientos → hay que **archivarla**.
- **Debe existir siempre al menos una cuenta activa** (no archivada): no se puede eliminar la última.
- Las cuentas archivadas se ocultan de las listas de selección (calculadora, etc.) pero conservan su historial.

## 4. Categorías

- Cada categoría es de tipo **gasto** o **ingreso** (no ambas).
- Soportan **subcategorías** (una categoría puede tener `parent_id`).
- El ícono puede ser un **preset de Tabler** o una **imagen propia** subida por el usuario (ej. logo de un comercio), guardada en el disco `public`.
- Se pueden crear/editar/eliminar libremente, incluidas las del set por defecto.
- Al crear una categoría desde la calculadora, queda **seleccionada automáticamente** para la transacción en curso.

## 5. Transacciones (gasto / ingreso)

- Una transacción es de tipo **gasto**, **ingreso** o **transferencia**.
- Gasto e ingreso requieren: **monto > 0**, una **cuenta** y una **categoría** (ambas del usuario).
- El **monto** se ingresa con una calculadora que admite operaciones (`+ − × ÷`) evaluadas **de izquierda a derecha**.
- La transacción hereda la **moneda de la cuenta**; se puede adjuntar **nota**, **foto** (imagen ≤ 5 MB) y **fecha** (por defecto hoy).
- Solo el dueño puede editar/eliminar sus transacciones.

## 6. Transferencias

- Mueven dinero de una **cuenta origen** a una **cuenta destino** (deben ser **distintas**).
- No llevan categoría y **no cuentan** como ingreso ni como gasto en los totales ni en los gráficos.
- Efecto en saldos: la cuenta origen baja por `amount`; la destino sube por `transfer_amount`.
- **Entre monedas distintas**: se ingresa por separado el monto que sale (moneda origen) y el que entra (moneda destino).

## 7. Multi-moneda y tipo de cambio

- Cada usuario tiene una **moneda base** (`default_currency`, por defecto **ARS**), configurable en Ajustes.
- Los **totales** de Inicio, Gráficos e Informes se expresan en la moneda base: cada movimiento se convierte con `monto × tipo_de_cambio`.
- Cuando se carga una transacción en una cuenta **cuya moneda no es la base**, la calculadora pide una **cotización manual** (`1 [moneda] = X [base]`) que se guarda como `exchange_rate`.
- Cada movimiento se **muestra en su propia moneda** en las listas; solo los agregados se convierten a base.
- Monedas de referencia disponibles: ARS, USD, EUR, BRL.

## 8. Presupuestos

- Se define un **límite mensual** por **categoría de gasto**, y opcionalmente un **presupuesto total** (global).
- Hay **un presupuesto por categoría** como máximo (upsert): volver a definirlo lo actualiza.
- El **gasto del período** se calcula sobre el **mes actual**, convertido a moneda base.
- La barra de progreso cambia de color: **verde** (< 80 %), **ámbar** (80–100 %), **rojo** (excedido, mostrando cuánto se pasó).

## 9. Transacciones recurrentes

- Una recurrente es una **plantilla** (gasto o ingreso) con **frecuencia** (día/semana/mes/año) e **intervalo** (ej. "cada 2 meses"), fecha de **inicio** y fecha de **fin** opcional.
- El comando `recurring:run` (diario) genera las transacciones **pendientes hasta hoy** con **catch-up**: si el sistema estuvo inactivo, crea todas las ocurrencias que faltaban.
- Tras generar, avanza `next_run_date` según la frecuencia; si supera el `end_date`, la recurrente se **desactiva** sola.
- Se pueden **pausar/reactivar** (`is_active`) y editar/eliminar.
- Las recurrentes generan transacciones en la moneda de la cuenta con tipo de cambio = 1 (no piden cotización).

## 10. Recordatorios y notificaciones

- Un recordatorio tiene **título**, **hora** y **días de la semana** (vacío = todos los días) y puede estar **activado/desactivado**.
- El comando `reminders:dispatch` (cada minuto) crea una **alerta** (campanita) por cada recordatorio activo cuya **hora ya pasó hoy** y cuyo **día corresponde** — **una sola vez por día por recordatorio**.
- La **campanita** en la barra superior muestra un **badge** con la cantidad de alertas **no leídas**.
- Al **abrir** el panel, todas las alertas se marcan como **leídas** (el badge desaparece).
- Cada alerta se puede **borrar con swipe hacia la izquierda**.
- (Pendiente) La entrega como notificación push del navegador aún no está implementada; por ahora las alertas viven dentro de la app.

## 11. Períodos y reportes

- **Inicio** e **Informes** trabajan por **mes calendario**, con navegación mes anterior/siguiente (no se puede avanzar más allá del mes actual en Inicio).
- **Gráficos** admite tres rangos: **Semana**, **Mes** (por día) y **Año** (por mes).
- **Informes** compara ingresos y gastos del mes contra el **mes anterior** (variación ▲/▼ %) y muestra el desglose por categoría de gastos e ingresos.

## 12. Autenticación y permisos

- Acceso por **email/contraseña** o **Google**. Los usuarios de Google no tienen contraseña local (se generan una aleatoria) y quedan con email verificado.
- La verificación de email **no es obligatoria** (el registro entra directo a la app).
- Toda la información está **aislada por usuario**: cada quien solo ve y modifica sus propias cuentas, categorías, transacciones, presupuestos, recurrentes, recordatorios y notificaciones. Las operaciones sobre recursos ajenos responden **403**.
