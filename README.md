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
| Ver dashboard, artículos, inventario, órdenes, costeo y reportes | ✔ | ✔ |
| Registrar movimientos de inventario (existe en el servidor; la pantalla para hacerlo aún no) | ✔ | ✔ |
| Cambiar el estado de una orden y registrar tiempos muertos | ✔ | ✔ |
| Crear, editar o desactivar artículos | | ✔ |
| Crear y editar órdenes (cliente, precios, costos) | | ✔ |
| Ajustar existencias directamente | | ✔ |
| Crear usuarios, cambiar roles, desactivar, restablecer contraseñas | | ✔ |

Los permisos se definen en `routes/web.php` (por ruta) y `WorkOrderController@update` (qué puede cambiar un operario).

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
