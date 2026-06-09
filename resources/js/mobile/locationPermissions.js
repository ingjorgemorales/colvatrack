export const androidLocationPermissions = [
    'android.permission.ACCESS_FINE_LOCATION',
    'android.permission.ACCESS_COARSE_LOCATION',
];

export function isCapacitorRuntime() {
    return Boolean(window.Capacitor?.isNativePlatform?.());
}
