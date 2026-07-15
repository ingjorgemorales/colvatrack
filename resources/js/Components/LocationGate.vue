<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { LocateFixed, MapPinOff, RefreshCw } from '@lucide/vue';

const page = usePage();
const location = computed(() => page.props.location ?? {});
const required = computed(() => Boolean(location.value.required));
const active = ref(Boolean(location.value.active));
const status = ref(active.value ? 'active' : 'pending');
const message = ref(location.value.message ?? 'Para usar ColvaTrack debes permitir el acceso a tu ubicacion');
const sending = ref(false);
const showNotice = ref(false);
const lastSentAt = ref(location.value.updated_at ? new Date(location.value.updated_at) : null);
let watchId = null;
let intervalId = null;
let noticeTimer = null;
let latestPosition = null;

const noticeVisible = computed(() => required.value && showNotice.value && status.value !== 'active');
const intervalSeconds = computed(() => Math.max(Number(location.value.interval_seconds ?? 60), 15));

function scheduleNotice(delay = 20000) {
    if (noticeTimer !== null) window.clearTimeout(noticeTimer);
    noticeTimer = window.setTimeout(() => {
        if (required.value && status.value !== 'active') showNotice.value = true;
    }, delay);
}

function hideNotice() {
    showNotice.value = false;
    if (noticeTimer !== null) window.clearTimeout(noticeTimer);
    noticeTimer = null;
}

function setError(error) {
    active.value = false;
    status.value = error?.code === 1 ? 'denied' : 'unavailable';
    message.value = error?.code === 1
        ? 'El permiso de ubicacion esta desactivado. Activalo para actualizar tu posicion en el mapa.'
        : 'No fue posible actualizar tu ubicacion. Revisa GPS, permisos o conexion.';
    showNotice.value = true;
}

async function sendPosition(position) {
    latestPosition = position;
    if (sending.value) return;
    sending.value = true;
    try {
        await window.axios.post('/api/users/location', {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy: position.coords.accuracy,
        });
        active.value = true;
        status.value = 'active';
        lastSentAt.value = new Date();
        message.value = 'Ubicacion activa';
        hideNotice();
    } catch (error) {
        active.value = false;
        status.value = 'unavailable';
        message.value = error.response?.status === 419
            ? 'Tu sesion necesita actualizarse. Presiona Revisar e intenta de nuevo.'
            : error.response?.data?.message ?? 'No fue posible guardar tu ubicacion.';
        showNotice.value = true;
    } finally {
        sending.value = false;
    }
}

function requestLocation({ userAction = false } = {}) {
    if (!required.value) return;
    if (!navigator.geolocation) {
        setError();
        return;
    }

    status.value = 'requesting';
    if (userAction) showNotice.value = true;
    else scheduleNotice();

    navigator.geolocation.getCurrentPosition(sendPosition, setError, {
        enableHighAccuracy: true,
        timeout: 15000,
        maximumAge: 30000,
    });

    if (watchId === null) {
        watchId = navigator.geolocation.watchPosition((position) => {
            latestPosition = position;
        }, setError, {
            enableHighAccuracy: true,
            timeout: 20000,
            maximumAge: 30000,
        });
    }

    if (intervalId === null) {
        intervalId = window.setInterval(() => {
            if (latestPosition) sendPosition(latestPosition);
            else requestLocation();
        }, intervalSeconds.value * 1000);
    }
}

function retry() {
    requestLocation({ userAction: true });
}

function refreshPage() {
    router.reload({ only: ['auth', 'location'] });
}

function handleVisibilityChange() {
    if (!required.value || document.hidden) return;
    if (latestPosition) sendPosition(latestPosition);
    else requestLocation();
}

onMounted(() => {
    requestLocation();
    document.addEventListener('visibilitychange', handleVisibilityChange);
});

onBeforeUnmount(() => {
    if (watchId !== null) navigator.geolocation.clearWatch(watchId);
    if (intervalId !== null) window.clearInterval(intervalId);
    if (noticeTimer !== null) window.clearTimeout(noticeTimer);
    document.removeEventListener('visibilitychange', handleVisibilityChange);
});
</script>

<template>
  <div v-if="noticeVisible" class="fixed bottom-4 right-4 z-40 w-[min(420px,calc(100vw-2rem))] rounded-md border border-amber-200 bg-white p-4 text-sm text-slate-700 shadow-xl">
    <div class="flex items-start gap-3">
      <div class="grid h-10 w-10 shrink-0 place-items-center rounded-md bg-amber-50 text-amber-700">
        <MapPinOff class="h-5 w-5" />
      </div>
      <div class="min-w-0 flex-1">
        <h2 class="font-bold text-[#123f6e]">Ubicacion sin sincronizar</h2>
        <p class="mt-1 leading-5">{{ message }}</p>
        <p class="mt-1 text-xs text-slate-500">Se intenta actualizar cada {{ intervalSeconds }} segundos mientras tu sesion este abierta.</p>
        <div class="mt-3 flex flex-wrap gap-2">
          <button @click="retry" class="inline-flex cursor-pointer items-center gap-2 rounded-md bg-[#123f6e] px-3 py-2 font-semibold text-white transition-colors hover:bg-[#0e2d52]"><LocateFixed class="h-4 w-4" /> Activar</button>
          <button @click="refreshPage" class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-slate-300 px-3 py-2 font-semibold text-slate-700 transition-colors hover:bg-slate-50"><RefreshCw class="h-4 w-4" /> Revisar</button>
        </div>
      </div>
    </div>
  </div>
</template>
