<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import L from 'leaflet';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({ vehicles: Array, technicians: Array });
const page = usePage();
const perms = page.props.auth?.permissions ?? [];
const can = (module, action = 'ver') => perms.includes('*') || perms.includes(`${module}.${action}`);
const mapEl = ref(null);
const vehicles = ref(props.vehicles ?? []);
const technicians = ref(props.technicians ?? []);
const query = ref('');
const status = ref('todos');
const availability = ref('todos');
const selectedTechnicianId = ref('');
const distance = ref('');
const lastRefresh = ref(null);
const refreshing = ref(false);
let map;
let markers = [];
let radiusCircles = [];
let pollTimer;
let fittedOnce = false;

const selectedTechnician = computed(() => technicians.value.find(t => String(t.id) === String(selectedTechnicianId.value)) ?? null);
const radiusMeters = computed(() => Math.max(0, Number(distance.value || 0)));
const radiusTechnicians = computed(() => {
  if (!radiusMeters.value) return [];
  if (selectedTechnician.value) return [selectedTechnician.value];
  return technicians.value.filter(t => t.current_latitude && t.current_longitude);
});
const filtered = computed(() => vehicles.value
  .filter(v => `${v.plate} ${v.driver?.name ?? ''} ${v.driver?.last_name ?? ''}`.toLowerCase().includes(query.value.toLowerCase()))
  .filter(v => status.value === 'todos' || (status.value === 'movimiento' ? Boolean(v.is_moving) : !Boolean(v.is_moving)))
  .filter(v => availability.value === 'todos' || (availability.value === 'disponibles' ? (v.inventory ?? []).some(i => Number(i.quantity_available) > 0) : true))
  .filter(v => {
    if (!radiusMeters.value) return true;
    if (!radiusTechnicians.value.length) return true;
    return radiusTechnicians.value.some(t => distanceBetween(t, v) <= radiusMeters.value);
  })
);
const movingCount = computed(() => filtered.value.filter(v => Boolean(v.is_moving)).length);
const stoppedCount = computed(() => filtered.value.filter(v => !Boolean(v.is_moving)).length);
const technicianCount = computed(() => technicians.value.length);
const lastRefreshLabel = computed(() => lastRefresh.value ? lastRefresh.value.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) : 'Pendiente');
const radiusLabel = computed(() => {
  if (!radiusMeters.value) return 'Sin filtro por radio';
  if (selectedTechnician.value) return `${filtered.value.length} vehiculos a ${radiusMeters.value} m de ${selectedTechnician.value.name}`;
  return `${filtered.value.length} vehiculos a ${radiusMeters.value} m de cualquier tecnico`;
});

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));
}

function latLngOf(entity) {
  return [Number(entity.current_latitude), Number(entity.current_longitude)];
}

function distanceBetween(a, b) {
  if (!a?.current_latitude || !a?.current_longitude || !b?.current_latitude || !b?.current_longitude) return Number.POSITIVE_INFINITY;
  const radius = 6371000;
  const lat1 = Number(a.current_latitude) * Math.PI / 180;
  const lat2 = Number(b.current_latitude) * Math.PI / 180;
  const dLat = (Number(b.current_latitude) - Number(a.current_latitude)) * Math.PI / 180;
  const dLng = (Number(b.current_longitude) - Number(a.current_longitude)) * Math.PI / 180;
  const x = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;
  return radius * 2 * Math.atan2(Math.sqrt(x), Math.sqrt(1 - x));
}

function nearestTechnicianDistance(vehicle) {
  const refs = radiusTechnicians.value.length ? radiusTechnicians.value : technicians.value;
  const distances = refs.map(t => distanceBetween(t, vehicle)).filter(Number.isFinite);
  return distances.length ? Math.min(...distances) : null;
}

function vehicleIcon(v) {
  const moving = Boolean(v.is_moving);
  const heading = Number(v.current_heading || 0);
  const plate = escapeHtml(v.plate);
  const stateClass = moving ? 'is-moving' : 'is-stopped';
  const title = moving ? 'Cambio posicion GPS' : 'Sin cambio de posicion';

  return L.divIcon({
    className: '',
    iconSize: [74, 58],
    iconAnchor: [37, 36],
    popupAnchor: [0, -34],
    html: `
      <div class="vehicle-marker ${stateClass}" title="${title}: ${plate}">
        <div class="vehicle-marker__halo"></div>
        <div class="vehicle-marker__car" style="--heading:${heading}deg">
          <svg viewBox="0 0 64 64" aria-hidden="true">
            <path class="vehicle-marker__body" d="M17 24h5l5-9h14l6 9h4c4 0 7 3 7 7v11c0 2-2 4-4 4h-3a7 7 0 0 1-14 0H27a7 7 0 0 1-14 0h-3c-2 0-4-2-4-4V31c0-4 3-7 7-7h4z" />
            <path class="vehicle-marker__window" d="M28 19h11l4 6H25l3-6z" />
            <circle class="vehicle-marker__wheel" cx="20" cy="46" r="4" />
            <circle class="vehicle-marker__wheel" cx="44" cy="46" r="4" />
            <path class="vehicle-marker__arrow" d="M32 5l6 8H26l6-8z" />
          </svg>
        </div>
        <div class="vehicle-marker__plate">${plate}</div>
      </div>
    `,
  });
}

function technicianIcon(t) {
  const fresh = Boolean(t.location_is_fresh);
  const initials = `${t.name?.[0] ?? ''}${t.last_name?.[0] ?? ''}`.toUpperCase() || 'T';
  return L.divIcon({
    className: '',
    iconSize: [62, 62],
    iconAnchor: [31, 44],
    popupAnchor: [0, -38],
    html: `
      <div class="technician-marker ${fresh ? 'is-fresh' : 'is-stale'}">
        <div class="technician-marker__pin"><span>${escapeHtml(initials)}</span></div>
        <div class="technician-marker__label">${escapeHtml(t.name ?? 'Tecnico')}</div>
      </div>
    `,
  });
}

function vehiclePopup(v) {
  const inv = (v.inventory ?? []).slice(0, 5).map(i => `<li>${escapeHtml(i.item?.name ?? 'Item')}: ${escapeHtml(i.quantity_available)} disp.</li>`).join('');
  const moving = Boolean(v.is_moving);
  const state = moving ? 'Cambio de posicion GPS' : 'Sin cambio de posicion';
  const nearestDistance = nearestTechnicianDistance(v);
  const techDistance = nearestDistance === null ? '-' : `${Math.round(nearestDistance)} m`;
  return `
    <div class="vehicle-popup">
      <div class="vehicle-popup__title">${escapeHtml(v.plate)}</div>
      <div class="vehicle-popup__status ${moving ? 'is-moving' : 'is-stopped'}">${state}</div>
      <dl>
        <div><dt>Conductor</dt><dd>${escapeHtml(v.driver?.name ?? 'Sin asignar')}</dd></div>
        <div><dt>Telefono</dt><dd>${escapeHtml(v.driver?.phone ?? '-')}</dd></div>
        <div><dt>Dist. tecnico</dt><dd>${escapeHtml(techDistance)}</dd></div>
        <div><dt>Velocidad GPS</dt><dd>${escapeHtml(v.current_speed ?? 0)} km/h</dd></div>
        <div><dt>Movimiento GPS</dt><dd>${escapeHtml(v.movement_distance_meters ?? 0)} m desde el punto anterior</dd></div>
        <div><dt>Ultima transmision</dt><dd>${escapeHtml(v.last_gps_datetime ?? '-')}</dd></div>
        <div><dt>Direccion</dt><dd>${escapeHtml(v.current_address ?? '-')}</dd></div>
      </dl>
      <strong>Inventario disponible</strong>
      <ul>${inv || '<li>Sin inventario</li>'}</ul>
      <div class="vehicle-popup__actions">
        <a href="/solicitudes/create?vehicle_id=${v.id}">Solicitar herramientas</a>
        ${can('vehiculos', 'recorrido') ? `<a href="/vehiculos/${v.id}/recorrido">Recorrido</a>` : ''}
      </div>
    </div>
  `;
}

function technicianPopup(t) {
  const freshness = t.location_is_fresh ? 'Ubicacion vigente' : 'Ubicacion vencida';
  return `
    <div class="vehicle-popup">
      <div class="vehicle-popup__title">${escapeHtml(t.name)} ${escapeHtml(t.last_name ?? '')}</div>
      <div class="vehicle-popup__status ${t.location_is_fresh ? 'is-moving' : 'is-stopped'}">${freshness}</div>
      <dl>
        <div><dt>Rol</dt><dd>${escapeHtml(t.role ?? 'Tecnico')}</dd></div>
        <div><dt>Telefono</dt><dd>${escapeHtml(t.phone ?? '-')}</dd></div>
        <div><dt>Email</dt><dd>${escapeHtml(t.email ?? '-')}</dd></div>
        <div><dt>Actualizada</dt><dd>${escapeHtml(t.location_updated_at ?? '-')}</dd></div>
      </dl>
    </div>
  `;
}

function clearLayers() {
  markers.forEach(marker => marker.remove());
  markers = [];
  radiusCircles.forEach(circle => circle.remove());
  radiusCircles = [];
}

function renderMarkers({ fit = false } = {}) {
  if (!map) return;
  clearLayers();
  const bounds = [];

  radiusTechnicians.value.forEach(t => {
    const center = latLngOf(t);
    const circle = L.circle(center, {
      radius: radiusMeters.value,
      color: '#16a34a',
      weight: 3,
      fillColor: '#22c55e',
      fillOpacity: 0.12,
    }).addTo(map);
    circle.bringToBack();
    radiusCircles.push(circle);
    bounds.push(center);
    const lat = center[0];
    const lng = center[1];
    const latDelta = radiusMeters.value / 111320;
    const lngDelta = radiusMeters.value / (111320 * Math.max(Math.cos(lat * Math.PI / 180), 0.2));
    bounds.push([lat + latDelta, lng + lngDelta], [lat - latDelta, lng - lngDelta]);
  });

  technicians.value.forEach(t => {
    if (t.current_latitude && t.current_longitude) {
      const point = latLngOf(t);
      bounds.push(point);
      markers.push(L.marker(point, { icon: technicianIcon(t), zIndexOffset: 1000 }).addTo(map).bindPopup(technicianPopup(t), { maxWidth: 340 }));
    }
  });

  filtered.value.forEach(v => {
    if (v.current_latitude && v.current_longitude) {
      const point = latLngOf(v);
      bounds.push(point);
      markers.push(L.marker(point, { icon: vehicleIcon(v), riseOnHover: true }).addTo(map).bindPopup(vehiclePopup(v), { maxWidth: 380 }));
    }
  });

  if (bounds.length && (fit || !fittedOnce)) {
    map.fitBounds(bounds, { padding: [44, 44], maxZoom: selectedTechnician.value ? 15 : 13 });
    fittedOnce = true;
  }
}

async function refreshVehicles() {
  if (refreshing.value) return;
  refreshing.value = true;
  try {
    const response = await fetch('/api/vehicles/locations', {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    });
    if (!response.ok) return;
    const payload = await response.json();
    vehicles.value = Array.isArray(payload) ? payload : (payload.vehicles ?? []);
    technicians.value = Array.isArray(payload) ? technicians.value : (payload.technicians ?? []);
    lastRefresh.value = new Date();
    renderMarkers();
  } finally {
    refreshing.value = false;
  }
}

onMounted(() => {
  map = L.map(mapEl.value, { zoomControl: true }).setView([4.65, -74.09], 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: 'Leaflet | OpenStreetMap' }).addTo(map);
  lastRefresh.value = new Date();
  renderMarkers({ fit: true });
  pollTimer = window.setInterval(refreshVehicles, 11000);
});

onBeforeUnmount(() => {
  if (pollTimer) window.clearInterval(pollTimer);
  clearLayers();
});

watch([query, status, availability, selectedTechnicianId, distance], () => renderMarkers({ fit: true }));
</script>

<template>
  <Head title="Mapa" />
  <AppLayout title="Mapa">
    <section class="mb-6 grid gap-4 xl:grid-cols-5">
      <input v-model="query" class="rounded-md border border-slate-200 bg-[#e9eef8] px-5 py-4 outline-none focus:border-[#123f6e]" placeholder="Buscar placa o conductor" />
      <select v-model="status" class="rounded-md border border-slate-200 bg-[#e9eef8] px-5 py-4 outline-none focus:border-[#123f6e]"><option value="todos">Todos los estados</option><option value="movimiento">Vehiculos que cambiaron posicion</option><option value="detenido">Vehiculos sin cambio de posicion</option></select>
      <select v-model="availability" class="rounded-md border border-slate-200 bg-[#e9eef8] px-5 py-4 outline-none focus:border-[#123f6e]"><option value="todos">Todos</option><option value="disponibles">Con herramientas disponibles</option></select>
      <select v-model="selectedTechnicianId" class="rounded-md border border-slate-200 bg-[#e9eef8] px-5 py-4 outline-none focus:border-[#123f6e]"><option value="">Todos los tecnicos</option><option v-for="t in technicians" :key="t.id" :value="t.id">{{ t.name }} {{ t.last_name ?? '' }}</option></select>
      <input v-model="distance" type="number" min="1" class="rounded-md border border-slate-200 bg-[#e9eef8] px-5 py-4 outline-none focus:border-[#16a34a]" placeholder="Radio metros, ej. 500" />
    </section>

    <section class="mb-4 flex flex-wrap items-center gap-3 text-sm text-slate-600">
      <span class="rounded bg-white px-3 py-2 shadow-sm">{{ filtered.length }} vehiculos visibles</span>
      <span class="rounded bg-white px-3 py-2 shadow-sm"><span class="mr-2 inline-block h-2.5 w-2.5 rounded-full bg-[#123f6e]"></span>{{ technicianCount }} tecnicos ubicados</span>
      <span class="rounded bg-white px-3 py-2 shadow-sm"><span class="mr-2 inline-block h-2.5 w-2.5 rounded-full bg-emerald-500"></span>{{ movingCount }} cambiaron posicion</span>
      <span class="rounded bg-white px-3 py-2 shadow-sm"><span class="mr-2 inline-block h-2.5 w-2.5 rounded-full bg-slate-500"></span>{{ stoppedCount }} sin cambio</span>
      <span class="rounded bg-white px-3 py-2 shadow-sm">{{ radiusLabel }}</span>
      <span class="rounded bg-white px-3 py-2 shadow-sm"><span class="mr-2 inline-block h-2.5 w-2.5 rounded-full" :class="refreshing ? 'bg-amber-500' : 'bg-emerald-500'"></span>Actualizado {{ lastRefreshLabel }}</span>
      <button @click="refreshVehicles" class="cursor-pointer rounded bg-white px-3 py-2 font-semibold text-[#123f6e] shadow-sm transition-colors hover:bg-[#edf3fa]">Actualizar ahora</button>
      <Link v-if="page.props.auth?.user?.role?.name === 'Administrador'" href="/vehiculos" class="rounded bg-white px-3 py-2 font-semibold text-[#123f6e] shadow-sm">Gestionar vehiculos</Link>
    </section>

    <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm"><div ref="mapEl" class="h-[58vh] min-h-[300px] sm:min-h-[520px]"></div></section>
  </AppLayout>
</template>