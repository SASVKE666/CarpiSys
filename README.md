# CarpiSys

Sistema web de inventario perpetuo, órdenes de trabajo y costeo para la Carpintería Real Carranza (Tepic, Nayarit).
Proyecto Profesionalizante Avanzado · Sistemas Computacionales · UAN.

**Stack:** Laravel 13 · Inertia 2 · React 18 · Tailwind 3 · Vite · MySQL (o SQLite para pruebas rápidas).
Requiere **PHP 8.4 o superior**, Composer 2 y Node 20+.

## Instalación

```bash
git clone https://github.com/SASVKE666/CarpiSys.git
cd CarpiSys
composer install
npm ci
cp .env.example .env          # en Windows: copy .env.example .env
php artisan key:generate
```

Edita `.env` según la base de datos:

- **SQLite** (lo más simple): deja `DB_CONNECTION=sqlite` y crea el archivo vacío `database/database.sqlite`.
- **MySQL** (Laragon): `DB_CONNECTION=mysql`, `DB_DATABASE=carpisys`, `DB_USERNAME=root`, `DB_PASSWORD=` (vacío en Laragon). Crea antes la base `carpisys`.

Luego:

```bash
php artisan migrate --seed
php artisan carpisys:crear-usuario --rol=admin     # crea al dueño (pide nombre, correo y contraseña)
npm run build
```

Para desarrollar (recarga automática): `npm run dev` en una terminal y `php artisan serve` en otra.

> **No hay registro público.** Las cuentas las crea el administrador desde la pantalla **Usuarios**
> o con `php artisan carpisys:crear-usuario`. Si la base ya tenía usuarios, la migración deja al primero como administrador.
> Si existe un usuario de prueba (`test@example.com`), cámbiale la contraseña o desactívalo desde **Usuarios**.

## Roles

| | Operario | Administrador |
|---|:-:|:-:|
| Ver dashboard, artículos, inventario (existencias y movimientos) y órdenes (sin importes) | ✔ | ✔ |
| Ver costos, precios, márgenes, Costeo y Reportes (incluye el conteo semanal de movimientos) | | ✔ |
| Registrar movimientos de inventario (entrada, salida, merma, devolución) | ✔ | ✔ |
| Usar material y registrar horas en una orden; cambiar su estado; tiempos muertos | ✔ | ✔ |
| Quitar material que registró uno mismo (vuelve al inventario) | ✔ | ✔ |
| Quitar material de otros o borrar horas | | ✔ |
| Indicar el costo de una compra (actualiza el costo promedio del artículo) | | ✔ |
| Ver órdenes de compra (sin precios) y **recibir mercancía** | ✔ | ✔ |
| Crear, editar, enviar, cancelar órdenes de compra y administrar proveedores; corregir el costo al recibir | | ✔ |
| Crear, editar o desactivar artículos | | ✔ |
| Crear y editar órdenes (cliente, precios, horas de máquina) | | ✔ |
| Ajustar existencias directamente | | ✔ |
| Definir tasas de CIF (menú **Tasas CIF**) | | ✔ |
| Crear usuarios, cambiar roles, definir tarifa por hora, desactivar, restablecer contraseñas | | ✔ |

Los operarios **no reciben** los importes: el servidor no los envía a su navegador (no solo se ocultan en pantalla).

Los permisos se definen en `routes/web.php` (por ruta) y `WorkOrderController@update` (qué puede cambiar un operario).

## Costeo automático por orden (MD + MOD + CIF)

Cada orden de trabajo acumula su costo a partir de lo que realmente se registra en ella (botón **Materiales y horas**):

- **Materiales directos (MD):** al usar material se descuenta del inventario (queda un movimiento de *salida* ligado a la orden) y se suma
  `cantidad × costo unitario del artículo en ese momento`. El costo se congela: cambiarlo después no altera lo ya registrado.
  No se permite usar más de lo que hay en la ubicación. Si se quita un registro, el material regresa al inventario.
- **Costo del artículo:** costo promedio ponderado. Cuando el administrador registra una *entrada* con el costo de la compra, el costo
  del artículo pasa a `(existencia × costo actual + cantidad × costo de la compra) ÷ nueva existencia`.
- **Mano de obra directa (MOD):** `horas × tarifa por hora`. La tarifa de cada persona la define el administrador en **Usuarios**; el operario
  registra sus horas y no ve ni escribe tarifas. Para personal sin cuenta, el administrador indica nombre y tarifa al registrar las horas.
- **Costos indirectos (CIF):** suma de las tasas activas definidas en **Tasas CIF**: % de la mano de obra, % de los materiales, o tarifa por
  hora de máquina (× las *horas de máquina* de la orden). El tipo "monto fijo mensual" aún no se reparte automáticamente.
  **Mientras no haya tasas activas, el CIF de las órdenes es $0**; define tus tasas antes de usar el costeo en serio.
- Una orden **completada o cancelada** ya no admite cambios de materiales ni horas (hay que reabrirla). Cambiar una tasa recalcula las órdenes abiertas.
- Si una orden tiene costos escritos a mano (estimados) y aún no tiene registros, se respetan; al registrar el primer material u hora,
  el costo pasa a calcularse solo desde los registros.

## Compras (órdenes de compra)

Menú **Compras**. Una orden de compra pasa por estos estados:

`Borrador` → `Enviada` → `Recepción parcial` → `Recibida` (o `Cancelada`)

- El **administrador** crea la orden (proveedor, artículos, cantidades, costo unitario, IVA — 16 % por defecto — y fecha esperada). Se guarda como **borrador**,
  que puede editarse o eliminarse. Al pedirla al proveedor se marca como **enviada**. El botón *Agregar los de stock bajo* propone los artículos
  por debajo del mínimo (cantidad sugerida: hasta el doble del mínimo).
- **Crear o enviar una orden no mueve el inventario.** El inventario cambia solo al **recibir mercancía**: cada artículo recibido genera un movimiento de
  *entrada* (ligado al código OC-xxxx) en la ubicación elegida y actualiza el costo promedio ponderado del artículo.
- Cualquier usuario activo puede **recibir mercancía** (también por partes). Se captura lo que realmente llegó; el sistema no permite recibir más de lo pendiente.
  Los operarios no ven precios ni totales, y no pueden cambiar el costo; si el costo real difiere, lo corrige el administrador al recibir.
- Si el proveedor ya no enviará el resto, el administrador usa *Dar por recibida*: los artículos pendientes se quitan de la orden.
  Una orden **enviada** (sin recepciones) puede cancelarse; una con recepciones ya no.
- Los proveedores no se borran: se desactivan para conservar el historial.

## Reporte de movimientos por semana

En **Reportes** (solo administrador) hay una tabla con las últimas 12 semanas, de lunes a domingo en la zona horaria de la aplicación
(`APP_TIMEZONE`, por defecto America/Mazatlan). Por semana cuenta: salidas (y cuántas fueron con orden de trabajo y cuántas manuales),
mermas, devoluciones, entradas, órdenes distintas con salidas y días con salidas. Incluye las semanas sin movimientos.

- Son **solo conteos**: no lleva importes ni el nombre de quien registró, para que no sirva para vigilar a una persona.
- **Descargar CSV** (`/reports/movimientos-semanales.csv?semanas=12`, de 1 a 52) lo abre Excel directamente.
- CarpiSys cuenta lo que se registró; el total real de salidas del taller no lo conoce. Si lo usas como numerador de "% de salidas registradas",
  el denominador tiene que medirse por observación o conteo físico.

## Usarlo desde varios dispositivos en la red local (celulares, tabletas, otras PCs)

El sistema corre en **una PC del taller** (el servidor) y los demás dispositivos lo abren en el navegador
mientras estén en el **mismo WiFi**. No necesitan instalar nada ni internet.

1. **En la PC servidor**, en `.env` pon `APP_ENV=production` y `APP_DEBUG=false`, y ejecuta `npm run build` (una vez, y de nuevo tras cada actualización).
2. **Arranca el servidor** con `scripts\iniciar-red-local.bat` (doble clic). Muestra las direcciones, por ejemplo `http://192.168.1.50:8000`.
   Debe quedar abierta esa ventana mientras se use el sistema.
3. **Firewall de Windows:** la primera vez pregunta si permites `php.exe`; acepta en **redes privadas**. O bien, en una terminal de administrador:
   `netsh advfirewall firewall add rule name="CarpiSys" dir=in action=allow protocol=TCP localport=8000 profile=private`
4. **Dirección fija:** en el router, reserva una IP para la PC servidor (reserva DHCP) para que la dirección no cambie.
5. **En el celular/tableta:** conecta al mismo WiFi, abre la dirección en Chrome e inicia sesión.
   Con el menú del navegador → *Agregar a pantalla de inicio* queda un icono de acceso directo.

Notas y límites:

- La conexión es **HTTP sin cifrar** dentro de tu red. Usa WPA2/WPA3 con contraseña en el WiFi y **no abras puertos del router hacia internet**.
- Por ser HTTP, el icono de pantalla de inicio es un acceso directo; no es una app instalable completa (eso exige HTTPS).
- Si la PC servidor se apaga o se desconecta del WiFi, nadie puede entrar. El sistema no funciona sin conexión en los celulares.
- `php artisan serve` es un servidor sencillo, suficiente para pocas personas a la vez. Con Laragon (Apache) también se puede publicar la carpeta `public`.
- Si la interfaz se ve distinta a lo esperado en una red sin internet, es normal: la tipografía de Google Fonts no se descarga y se usa la del sistema.

## Respaldos

```bash
php artisan carpisys:respaldo                              # guarda en storage/app/respaldos
php artisan carpisys:respaldo --destino="E:\Respaldos"     # disco externo o carpeta de Google Drive
php artisan carpisys:respaldo --conservar=30               # cuántos mantener (por defecto 14)
```

En Windows, `scripts\respaldo.bat "E:\Respaldos"` hace lo mismo y se puede programar en el **Programador de tareas**
(por ejemplo, diario al cerrar el taller). Con MySQL usa `mysqldump`; si no lo encuentra, pasa la ruta con `--mysqldump="C:\laragon\bin\mysql\...\bin\mysqldump.exe"`.
**Prueba restaurar un respaldo al menos una vez**: un respaldo que nunca se probó no es una garantía.

## Pruebas

```bash
php artisan test
```
