<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeft, CheckCircle, Clock3, MapPin, MessageCircle, PackageCheck, Phone, Send, Truck, X } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import L from 'leaflet';

const props = defineProps({
  request: Object,
  role: String,
  allowedTransitions: Array,
  routeLocations: { type: Array, default: () => [] },
});
const commentForm = useForm({ status: '', comment: '' });
const messageForm = useForm({ message: '' });
const messages = ref([...(props.request.chat?.messages ?? [])]);
const sendingMessage = ref(false);
const chatBox = ref(null);
const allowed = computed(() => props.allowedTransitions ?? []);
let channelName = null;
let requestChannelName = null;
let refreshTimer = null;
let chatPollTimer = null;
let routeMap = null;
const showRouteMap = ref(false);
const mapEl = ref(null);
const routeInfo = ref(null);
const locating = ref(false);

const labels = {
  pendiente: 'Pendiente',
  aceptada: 'Aceptada',
  rechazada: 'Rechazada',
  en_camino: 'En camino',
  entregada: 'Entregada',
  en_uso: 'En uso',
  para_recoger: 'Para recoger',
  recogida: 'Recogida',
  finalizada: 'Finalizada',
  cancelada: 'Cancelada',
};
const actionLabels = {
  aceptada: 'Aceptar solicitud',
  rechazada: 'Rechazar solicitud',
  en_camino: 'Voy en camino',
  entregada: 'Marcar entregada',
  en_uso: 'Recibido / en uso',
  para_recoger: 'Listo para recoger',
  recogida: 'Herramientas recogidas',
  finalizada: 'Finalizar solicitud',
  cancelada: 'Cancelar solicitud',
};
const statusClasses = {
  pendiente: 'bg-amber-50 text-amber-800 border-amber-200',
  aceptada: 'bg-blue-50 text-blue-800 border-blue-200',
  en_camino: 'bg-sky-50 text-sky-800 border-sky-200',
  entregada: 'bg-emerald-50 text-emerald-800 border-emerald-200',
  en_uso: 'bg-indigo-50 text-indigo-800 border-indigo-200',
  para_recoger: 'bg-orange-50 text-orange-800 border-orange-300',
  recogida: 'bg-slate-100 text-slate-800 border-slate-200',
  finalizada: 'bg-emerald-100 text-emerald-900 border-emerald-200',
  rechazada: 'bg-red-50 text-red-800 border-red-200',
  cancelada: 'bg-red-50 text-red-800 border-red-200',
};
const timeline = computed(() => [
  ['requested_at', 'Solicitada'],
  ['accepted_at', 'Aceptada'],
  ['en_route_at', 'En camino'],
  ['delivered_at', 'Entregada'],
  ['ready_for_pickup_at', 'Para recoger'],
  ['picked_up_at', 'Recogida'],
  ['finalized_at', 'Finalizada'],
].filter(([key]) => props.request[key]));
const currentStatusClass = computed(() => statusClasses[props.request.status] ?? 'bg-slate-100 text-slate-700 border-slate-200');
const hasVehicleRouteLocation = computed(() => Boolean(props.request.vehicle?.current_latitude && props.request.vehicle?.current_longitude));
const vehicleRouteName = computed(() => props.request.vehicle?.plate ? `Vehiculo ${props.request.vehicle.plate}` : 'Vehiculo asignado');
const isFinalized = computed(() => props.request.status === 'finalizada');
const routePoints = computed(() => props.routeLocations
  .filter(location => location.latitude && location.longitude)
  .map(location => ({ ...location, lat: Number(location.latitude), lng: Number(location.longitude) }))
);
const hasHistoricalRoute = computed(() => routePoints.value.length >= 2);
const canOpenRouteMap = computed(() => isFinalized.value ? hasHistoricalRoute.value : Boolean(hasVehicleRouteLocation.value && props.request.technician_latitude && props.request.technician_longitude));
const routeButtonText = computed(() => isFinalized.value ? 'Ver historial del trayecto' : 'Ver ruta en mapa');
const routeModalTitle = computed(() => isFinalized.value ? 'Historial del trayecto' : 'Ruta hacia el tecnico');
const routeUnavailableText = computed(() => {
  if (isFinalized.value && !hasHistoricalRoute.value) return 'No hay suficientes puntos GPS guardados para mostrar el historial del trayecto.';
  if (!isFinalized.value && !hasVehicleRouteLocation.value) return 'El vehiculo asignado no tiene ubicacion GPS disponible.';
  return '';
});
const contactActions = computed(() => {
  const technician = {
    key: 'technician',
    label: 'Llamar tecnico',
    name: props.request.technician?.name ?? 'Tecnico',
    phone: props.request.technician?.phone ?? null,
    href: phoneHref(props.request.technician?.phone),
  };
  const driver = {
    key: 'driver',
    label: 'Llamar conductor',
    name: props.request.driver?.name ?? 'Conductor',
    phone: props.request.driver?.phone ?? null,
    href: phoneHref(props.request.driver?.phone),
  };

  if (props.role === 'Conductor') return [technician];
  if (props.role === 'Tecnico') return [driver];

  return [technician, driver];
});
const bogotaDateTimeFormatter = new Intl.DateTimeFormat('es-CO', {
  timeZone: 'America/Bogota',
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
  hour: '2-digit',
  minute: '2-digit',
  hour12: true,
});

function statusLabel(status) { return labels[status] ?? status; }
function actionLabel(status) { return actionLabels[status] ?? `Marcar ${statusLabel(status)}`; }
function formatBogotaDateTime(value) {
  if (!value) return '';

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) return value;

  return bogotaDateTimeFormatter.format(date);
}
function phoneHref(phone) {
  const cleanPhone = String(phone ?? '').replace(/[^\d+]/g, '');
  return cleanPhone ? `tel:${cleanPhone}` : null;
}
function change(status){ commentForm.status = status; commentForm.patch(`/solicitudes/${props.request.id}/status`, { preserveScroll: true }); }
function refreshRequest() {
  if (commentForm.processing) return;
  router.reload({ only: ['request', 'allowedTransitions', 'routeLocations'], preserveScroll: true, preserveState: true });
}
function isCurrentRequestEvent(event) {
  const id = event?.tool_request?.id ?? event?.toolRequest?.id ?? event?.id;
  return Number(id) === Number(props.request.id);
}
function scrollChat(){ nextTick(() => { if(chatBox.value) chatBox.value.scrollTop = chatBox.value.scrollHeight; }); }
function mergeMessages(incoming = []) {
  const seen = new Set(messages.value.map((message) => Number(message.id)));
  const freshMessages = incoming.filter((message) => message?.id && !seen.has(Number(message.id)));

  if (!freshMessages.length) return false;

  messages.value = [...messages.value, ...freshMessages].sort((a, b) => {
    const first = new Date(a.created_at ?? 0).getTime();
    const second = new Date(b.created_at ?? 0).getTime();
    return first - second;
  });
  scrollChat();

  return true;
}
function markChatRead() {
  return window.axios
    .patch(`/solicitudes/${props.request.id}/chat/read`)
    .then(() => window.dispatchEvent(new CustomEvent('notifications:sync')))
    .catch(() => {});
}
async function syncChat() {
  if (!props.request.chat?.id) return;

  try {
    const { data } = await window.axios.get(`/api/tool-requests/${props.request.id}/chat`);
    if (mergeMessages(data.messages ?? [])) {
      await markChatRead();
    }
  } catch (error) {
    // Si Reverb o la API tienen un corte temporal, la vista conserva los mensajes ya cargados.
  }
}
function receiveChatMessage(event) {
  if (!event?.message) return;

  if (mergeMessages([event.message])) {
    markChatRead();
  }
}
async function sendMessage(){
  const text = messageForm.message.trim();
  if (!text || sendingMessage.value) return;

  messageForm.clearErrors();
  sendingMessage.value = true;

  try {
    const { data } = await window.axios.post(`/api/tool-requests/${props.request.id}/chat/messages`, { message: text });
    mergeMessages([data]);
    messageForm.reset();
  } catch (error) {
    messageForm.setError('message', error.response?.data?.message ?? 'No fue posible enviar el mensaje.');
  } finally {
    sendingMessage.value = false;
    scrollChat();
  }
}

function openRouteMap() {
  if (!canOpenRouteMap.value) return;

  showRouteMap.value = true;
  routeInfo.value = null;
  locating.value = true;
  nextTick(() => {
    locating.value = false;
    if (isFinalized.value) {
      initHistoricalMap();
      return;
    }

    const vehicleLat = Number(props.request.vehicle?.current_latitude);
    const vehicleLng = Number(props.request.vehicle?.current_longitude);
    const techLat = Number(props.request.technician_latitude);
    const techLng = Number(props.request.technician_longitude);
    if (vehicleLat && vehicleLng && techLat && techLng) initLiveMap(vehicleLat, vehicleLng, techLat, techLng);
  });
}
function initBaseMap(center) {
  if (routeMap) routeMap.remove();
  routeMap = L.map(mapEl.value, { zoomControl: true }).setView(center, 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: 'Leaflet | OpenStreetMap' }).addTo(routeMap);
}
function routeDistanceKm(points) {
  let meters = 0;
  for (let i = 1; i < points.length; i += 1) {
    meters += distanceMeters(points[i - 1], points[i]);
  }
  return (meters / 1000).toFixed(1);
}
function distanceMeters(a, b) {
  const radius = 6371000;
  const dLat = (b.lat - a.lat) * Math.PI / 180;
  const dLng = (b.lng - a.lng) * Math.PI / 180;
  const lat1 = a.lat * Math.PI / 180;
  const lat2 = b.lat * Math.PI / 180;
  const x = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;
  return radius * 2 * Math.atan2(Math.sqrt(x), Math.sqrt(1 - x));
}
function pointPopup(point, title) {
  return `
    <div class="vehicle-popup route-popup">
      <div class="vehicle-popup__title">${escapeHtml(title)}</div>
      <dl>
        <div><dt>Fecha GPS</dt><dd>${escapeHtml(point.gps_datetime ?? '-')}</dd></div>
        <div><dt>Velocidad</dt><dd>${escapeHtml(point.speed ?? 0)} km/h</dd></div>
        <div><dt>Evento</dt><dd>${escapeHtml(point.gps_event ?? '-')}</dd></div>
        <div><dt>Direccion</dt><dd>${escapeHtml(point.address ?? '-')}</dd></div>
      </dl>
    </div>
  `;
}
function initLiveMap(vehicleLat, vehicleLng, techLat, techLng) {
  initBaseMap([vehicleLat, vehicleLng]);
  L.marker([vehicleLat, vehicleLng], {
    icon: L.divIcon({
      className: '', iconSize: [32, 32], iconAnchor: [16, 16],
      html: '<div style="width:32px;height:32px;border-radius:50%;background:#123f6e;border:3px solid #fff;display:grid;place-items:center;color:#fff;font-size:14px;font-weight:900;box-shadow:0 4px 12px rgba(0,0,0,.3);">V</div>'
    })
  }).addTo(routeMap).bindPopup(vehicleRouteName.value);
  L.marker([techLat, techLng], {
    icon: L.divIcon({
      className: '', iconSize: [32, 32], iconAnchor: [16, 32],
      html: '<div style="width:32px;height:32px;border-radius:50% 50% 50% 4px;background:#15803d;border:3px solid #fff;display:grid;place-items:center;color:#fff;font-size:14px;font-weight:900;transform:rotate(-45deg);box-shadow:0 4px 12px rgba(0,0,0,.3);"><span style="transform:rotate(45deg)">T</span></div>'
    })
  }).addTo(routeMap).bindPopup(`Tecnico: ${props.request.technician?.name}`);
  fetch(`https://router.project-osrm.org/route/v1/driving/${vehicleLng},${vehicleLat};${techLng},${techLat}?geometries=geojson&overview=full&steps=true`)
    .then(r => r.json())
    .then(data => {
      const route = data.routes?.[0];
      if (!route) return;
      const coords = route.geometry.coordinates.map(c => [c[1], c[0]]);
      L.polyline(coords, { color: '#123f6e', weight: 6, opacity: 0.9 }).addTo(routeMap);
      L.polyline(coords, { color: '#9fb428', weight: 3, opacity: 1, dashArray: '8 10' }).addTo(routeMap);
      routeInfo.value = { distance: (route.distance / 1000).toFixed(1), duration: Math.round(route.duration / 60) };
      routeMap.fitBounds(coords, { padding: [52, 52], maxZoom: 16 });
    })
    .catch(() => {
      const bounds = L.latLngBounds([vehicleLat, vehicleLng], [techLat, techLng]);
      routeMap.fitBounds(bounds, { padding: [52, 52], maxZoom: 14 });
    });
}
function initHistoricalMap() {
  const points = routePoints.value;
  if (!points.length) return;

  initBaseMap([points[0].lat, points[0].lng]);

  const latLngs = points.map(point => [point.lat, point.lng]);
  L.polyline(latLngs, { color: '#ffffff', weight: 12, opacity: 0.95, lineCap: 'round', lineJoin: 'round' }).addTo(routeMap);
  L.polyline(latLngs, { color: '#123f6e', weight: 7, opacity: 1, lineCap: 'round', lineJoin: 'round' }).addTo(routeMap);
  L.polyline(latLngs, { color: '#9fb428', weight: 3, opacity: 1, dashArray: '8 10', lineCap: 'round' }).addTo(routeMap);

  points.forEach(point => {
    L.circleMarker([point.lat, point.lng], {
      radius: 3,
      color: '#ffffff',
      weight: 1,
      fillColor: Number(point.speed || 0) > 0 ? '#15803d' : '#475569',
      fillOpacity: 0.95,
      interactive: false,
    }).addTo(routeMap);
  });

  const first = points[0];
  const last = points[points.length - 1];
  L.marker([first.lat, first.lng]).addTo(routeMap).bindPopup(pointPopup(first, 'Inicio del trayecto'));
  L.marker([last.lat, last.lng]).addTo(routeMap).bindPopup(pointPopup(last, 'Final del trayecto'));
  routeInfo.value = { distance: routeDistanceKm(points), duration: null, points: points.length };
  routeMap.fitBounds(latLngs, { padding: [52, 52], maxZoom: 17 });
}
function closeRouteMap() {
  showRouteMap.value = false;
  if (routeMap) { routeMap.remove(); routeMap = null; }
}

onMounted(() => {
  scrollChat();
  markChatRead();
  if (window.Echo && props.request.chat?.id) {
    channelName = `chat.${props.request.chat.id}`;
    window.Echo.private(channelName).listen('ChatMessageSent', receiveChatMessage);
  }
  if (window.Echo) {
    requestChannelName = 'tool-requests';
    window.Echo.channel(requestChannelName).listen('ToolRequestStatusChanged', (event) => {
      if (isCurrentRequestEvent(event)) refreshRequest();
    });
  }
  refreshTimer = window.setInterval(refreshRequest, 10000);
  chatPollTimer = window.setInterval(syncChat, 5000);
});
watch(() => props.request.chat?.messages, (incoming) => mergeMessages(incoming ?? []));
watch(() => [
  props.request.status,
  props.request.vehicle?.current_latitude,
  props.request.vehicle?.current_longitude,
  props.routeLocations?.length,
], () => {
  if (!showRouteMap.value) return;
  if (!canOpenRouteMap.value) {
    closeRouteMap();
    return;
  }
  openRouteMap();
});
onBeforeUnmount(() => { if(window.Echo && channelName) window.Echo.leave(channelName); if(window.Echo && requestChannelName) window.Echo.leave(requestChannelName); if(refreshTimer) window.clearInterval(refreshTimer); if(chatPollTimer) window.clearInterval(chatPollTimer); closeRouteMap(); });
</script>
<template>
  <Head :title="`Solicitud #${request.id}`" />
  <AppLayout :title="`Solicitud #${request.id}`">
    <Link href="/solicitudes" class="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-[#123f6e]"><ArrowLeft class="h-4 w-4" /> Volver</Link>
    <section class="grid gap-6 xl:grid-cols-[1fr_390px]">
      <div class="space-y-5">
        <article class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
          <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 class="text-xl font-semibold text-[#123f6e]">{{ request.vehicle?.plate }}</h2>
              <p class="text-sm text-slate-500">Tecnico: {{ request.technician?.name }} | Conductor: {{ request.driver?.name ?? '-' }}</p>
            </div>
            <span class="rounded border px-3 py-2 font-semibold" :class="currentStatusClass">{{ statusLabel(request.status) }}</span>
          </div>
          <div class="grid gap-3 text-sm md:grid-cols-3">
            <div class="rounded bg-slate-50 p-3"><div class="text-slate-500">Prioridad</div><div class="font-bold capitalize text-slate-950">{{ request.priority }}</div></div>
            <div class="rounded bg-slate-50 p-3"><div class="text-slate-500">Solicitada</div><div class="font-bold text-slate-950">{{ request.requested_at ?? '-' }}</div></div>
            <div class="rounded bg-slate-50 p-3"><div class="text-slate-500">Herramientas</div><div class="font-bold text-slate-950">{{ request.items?.length ?? 0 }}</div></div>
          </div>
          <p class="mt-4 text-sm text-slate-600">{{ request.observation ?? 'Sin observaciones' }}</p>
        </article>

        <article class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="mb-4 flex items-center gap-2 font-semibold text-[#123f6e]"><PackageCheck class="h-5 w-5" /> Herramientas</h2>
          <table class="w-full text-left text-sm"><thead class="bg-slate-50 text-slate-500"><tr><th class="px-3 py-2">Herramienta</th><th>Cantidad</th><th>Estado</th></tr></thead><tbody><tr v-for="item in request.items" :key="item.id" class="border-t border-slate-100"><td class="px-3 py-3">{{ item.item?.name }}</td><td>{{ item.quantity }}</td><td class="capitalize">{{ item.status }}</td></tr></tbody></table>
        </article>

        <article class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="mb-4 flex items-center gap-2 font-semibold text-[#123f6e]"><Clock3 class="h-5 w-5" /> Tiempos clave</h2>
          <div class="grid gap-3 md:grid-cols-2"><div v-for="([key, label]) in timeline" :key="key" class="rounded bg-slate-50 p-3 text-sm"><strong>{{ label }}</strong><p class="text-slate-500">{{ request[key] }}</p></div><p v-if="!timeline.length" class="text-sm text-slate-500">Aun no hay tiempos registrados.</p></div>
        </article>

        <article class="rounded-md border border-slate-200 bg-white p-5 shadow-sm"><h2 class="mb-4 font-semibold text-[#123f6e]">Historial de estados</h2><div class="space-y-3"><div v-for="h in request.histories" :key="h.id" class="rounded bg-slate-50 p-3 text-sm"><strong>{{ statusLabel(h.old_status ?? 'inicio') }} -> {{ statusLabel(h.new_status) }}</strong><p class="text-slate-500">{{ h.user?.name }} | {{ h.created_at }}</p><p v-if="h.comment" class="text-slate-600">{{ h.comment }}</p></div></div></article>
      </div>
      <aside class="space-y-5">
        <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="mb-4 flex items-center gap-2 font-semibold text-[#123f6e]"><Truck class="h-5 w-5" /> Acciones del flujo</h2>
          <div class="grid gap-2">
            <button v-for="status in allowed" :key="status" @click="change(status)" class="cursor-pointer rounded-md px-4 py-3 font-semibold text-white transition-colors" :class="['rechazada','cancelada'].includes(status) ? 'bg-red-700 hover:bg-red-800' : 'bg-[#123f6e] hover:bg-[#0e2d52]'">{{ actionLabel(status) }}</button>
            <p v-if="!allowed.length" class="rounded-md bg-slate-50 p-3 text-sm text-slate-500">No tienes acciones pendientes para este estado.</p>
          </div>
          <p v-for="error in commentForm.errors" :key="error" class="mt-2 text-sm text-red-600">{{ error }}</p>
        </section>

        <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="mb-4 flex items-center gap-2 font-semibold text-[#123f6e]"><Phone class="h-5 w-5" /> Contacto rapido</h2>
          <div class="grid gap-3">
            <div v-for="action in contactActions" :key="action.key" class="rounded-md bg-slate-50 p-3">
              <div class="mb-2 text-sm">
                <div class="font-semibold text-slate-900">{{ action.name }}</div>
                <div class="text-slate-500">{{ action.phone ?? 'Sin telefono registrado' }}</div>
              </div>
              <a v-if="action.href" :href="action.href" class="flex w-full items-center justify-center gap-2 rounded-md bg-[#123f6e] px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#0e2d52]">
                <Phone class="h-4 w-4" /> {{ action.label }}
              </a>
              <button v-else disabled class="flex w-full cursor-not-allowed items-center justify-center gap-2 rounded-md bg-slate-300 px-4 py-3 text-sm font-semibold text-slate-600">
                <Phone class="h-4 w-4" /> {{ action.label }}
              </button>
            </div>
          </div>
        </section>

        <section v-if="request.technician_latitude && request.technician_longitude" class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="mb-4 flex items-center gap-2 font-semibold text-[#123f6e]"><MapPin class="h-5 w-5" /> {{ isFinalized ? 'Historial del trayecto' : 'Ubicacion del tecnico' }}</h2>
          <p class="mb-3 text-sm text-slate-600">{{ request.technician_address ?? 'Direccion no registrada' }}</p>
          <p v-if="routeUnavailableText" class="mb-3 rounded-md bg-amber-50 p-3 text-sm text-amber-800">{{ routeUnavailableText }}</p>
          <p v-else-if="isFinalized" class="mb-3 rounded-md bg-emerald-50 p-3 text-sm text-emerald-800">Esta solicitud ya finalizo. El mapa mostrara el recorrido guardado durante el trayecto.</p>
          <p v-else class="mb-3 rounded-md bg-blue-50 p-3 text-sm text-blue-800">Esta solicitud sigue activa. El mapa usara la ubicacion actual del vehiculo.</p>
          <button :disabled="!canOpenRouteMap" @click="openRouteMap" class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-md bg-[#123f6e] px-4 py-3 font-semibold text-white transition-colors hover:bg-[#0e2d52] disabled:cursor-not-allowed disabled:opacity-60">
            <MapPin class="h-4 w-4" /> {{ routeButtonText }}
          </button>
        </section>

        <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm"><h2 class="mb-3 flex items-center gap-2 font-semibold text-[#123f6e]"><MessageCircle class="h-5 w-5" /> Chat</h2><div ref="chatBox" class="mb-3 h-80 space-y-3 overflow-y-auto rounded-md bg-slate-50 p-3"><div v-for="m in messages" :key="m.id" class="rounded-md bg-white p-3 text-sm shadow-sm"><div class="mb-1 font-semibold text-[#123f6e]">{{ m.sender?.name ?? 'Usuario' }}</div><p class="text-slate-700">{{ m.message }}</p><p class="mt-1 text-xs text-slate-400">{{ formatBogotaDateTime(m.created_at) }}</p></div><p v-if="!messages.length" class="py-8 text-center text-sm text-slate-500">Sin mensajes todavia.</p></div><form class="flex gap-2" @submit.prevent="sendMessage"><input v-model="messageForm.message" class="min-w-0 flex-1 rounded-md border border-slate-300 px-3 py-3" placeholder="Escribir mensaje" /><button :disabled="sendingMessage" class="cursor-pointer rounded-md bg-[#123f6e] px-4 text-white transition-colors hover:bg-[#0e2d52] disabled:cursor-not-allowed disabled:opacity-60"><Send class="h-5 w-5" /></button></form><p v-for="error in messageForm.errors" :key="error" class="mt-2 text-sm text-red-600">{{ error }}</p></section>
      </aside>
    </section>
    <Teleport to="body">
      <div v-if="showRouteMap" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 p-4" @click.self="closeRouteMap">
        <div class="flex w-full max-w-4xl flex-col overflow-hidden rounded-lg bg-white shadow-2xl" style="max-height:90vh">
          <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <div>
              <h3 class="font-bold text-[#123f6e]">{{ routeModalTitle }}</h3>
              <p v-if="routeInfo && routeInfo.duration" class="text-sm text-slate-500">{{ routeInfo.distance }} km - {{ routeInfo.duration }} min</p>
              <p v-else-if="routeInfo" class="text-sm text-slate-500">{{ routeInfo.distance }} km - {{ routeInfo.points }} puntos GPS</p>
              <p v-else-if="locating" class="text-sm text-slate-500">Cargando ubicacion del vehiculo...</p>
            </div>
            <button @click="closeRouteMap" class="cursor-pointer rounded-md p-2 text-slate-500 transition-colors hover:bg-slate-100"><X class="h-5 w-5" /></button>
          </div>
          <div ref="mapEl" class="min-h-[460px] w-full flex-1"></div>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>
