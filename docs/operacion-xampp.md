# Operacion local ColvaTrack en XAMPP

## Arranque basico

1. Abrir XAMPP y activar Apache/MySQL si se van a usar desde el panel.
2. Entrar al proyecto:

```powershell
cd C:\xampp\htdocs\colvatrack
```

3. Levantar Laravel para pruebas locales:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

4. Levantar Reverb para chat/notificaciones en tiempo real:

```powershell
php artisan reverb:start --host=127.0.0.1 --port=8080
```

## Scheduler de Laravel

ColvaTrack ya programa estas tareas:

- `gps:sync-last-positions`: cada minuto.
- `alerts:operational`: cada 10 minutos.

Para probar manualmente:

```powershell
php artisan schedule:run
php artisan alerts:operational
php artisan gps:sync-last-positions
```

Para dejarlo automatico en Windows, crear una tarea en Programador de tareas que ejecute cada minuto:

```powershell
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe C:\xampp\htdocs\colvatrack\artisan schedule:run
```

Si el PHP activo es el de XAMPP, usar la ruta equivalente:

```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\colvatrack\artisan schedule:run
```

## Variables operativas

Las alertas usan estas variables del `.env`:

```env
GPS_STALE_AFTER_MINUTES=15
REQUEST_PENDING_ALERT_MINUTES=30
REQUEST_EN_ROUTE_ALERT_MINUTES=60
INVENTORY_LOW_STOCK_THRESHOLD=1
OPERATIONAL_ALERT_REPEAT_MINUTES=60
OPERATIONAL_ALERTS_EMAIL=false
```

`OPERATIONAL_ALERTS_EMAIL=false` evita correos repetidos en pruebas. Cambiarlo a `true` cuando se quiera que las alertas operativas tambien salgan por correo.

## Validaciones rapidas

```powershell
php artisan route:list --except-vendor
php artisan schedule:list
npm run build
```

## Scripts incluidos

Desde PowerShell:

```powershell
cd C:\xampp\htdocs\colvatrack
powershell -ExecutionPolicy Bypass -File .\tools\start-colvatrack.ps1 -WithScheduler -OpenBrowser
powershell -ExecutionPolicy Bypass -File .\tools\install-windows-scheduler.ps1
powershell -ExecutionPolicy Bypass -File .\tools\smoke-test-colvatrack.ps1
```

- `start-colvatrack.ps1` levanta Laravel y Reverb en segundo plano; opcionalmente levanta `schedule:work`.
- `install-windows-scheduler.ps1` instala o reemplaza la tarea `ColvaTrack Laravel Scheduler` cada minuto.
- `smoke-test-colvatrack.ps1` valida login, recuperacion, rutas protegidas y scheduler.

## Móviles GPS reales

La API ServiceTrack no entrega un catalogo completo si se omite `moviles`; responde `count=0`. Por eso ColvaTrack consulta la lista configurada en:

```env
GPS_SERVICETRACK_MOVILES=ZGA89H,WPQ084
```

Tambien puede editarse en `Configuracion GPS` dentro del JSON adicional:

```json
{"header":"x-api-key","accion":"lastposition","moviles":["ZGA89H","WPQ084"]}
```

El comando puede recibir una lista temporal:

```powershell
php artisan gps:sync-last-positions --moviles=ZGA89H,WPQ084
```

Cuando se agreguen mas moviles reales, incluirlos separados por coma, sin espacios.

