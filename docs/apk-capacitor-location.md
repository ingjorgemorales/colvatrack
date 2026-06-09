# ColvaTrack APK: permisos de ubicacion

Cuando se inicialice Capacitor/Ionic, el proyecto debe conservar el flujo web actual de ubicacion obligatoria y agregar permisos Android nativos.

Permisos requeridos en `android/app/src/main/AndroidManifest.xml`:

```xml
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />
```

Flujo esperado:

1. Solicitar ubicacion despues del login para roles Tecnico y Conductor.
2. Enviar latitud, longitud y precision a `POST /api/users/location`.
3. Repetir el envio cada `LOCATION_UPDATE_INTERVAL_SECONDS`.
4. Bloquear acciones si la ubicacion esta vencida por mas de `LOCATION_MAX_AGE_MINUTES`.
5. Mantener historico en `user_location_logs`.

Variables actuales:

```env
LOCATION_UPDATE_INTERVAL_SECONDS=60
LOCATION_MAX_AGE_MINUTES=10
```

El archivo `resources/js/mobile/locationPermissions.js` deja centralizados los nombres de permisos Android para reutilizarlos cuando se instalen `@capacitor/core`, `@capacitor/cli` e Ionic/Vue.

