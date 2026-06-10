<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import L from 'leaflet';
import { ArrowLeft, CalendarClock, Clock, Gauge, MapPin, Navigation, Route, Search, X } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
  vehicle: Object,
  locations: Array,
  filters: { type: Object, default: () => ({}) },
  hasDateFilter: Boolean,
  maxPoints: Number,
  usesFullRange: Boolean,
});

const mapEl = ref(null);
const filters = ref({
  from: props.filters?.from ?? '',
  to: props.filters?.to ?? '',
});

const points = computed(() => props.locations
  .filter(location => location.latitude && location.longitude)
  .map(location => ({ ...location, lat: Number(location.latitude), lng: Number(location.longitude) }))
);
const firstPoint = computed(() => points.value[0] ?? null);
const lastPoint = computed(() => points.value[points.value.length - 1] ?? null);
const movingPoints = computed(() => points.value.filter(point => Number(point.speed || 0) > 0).length);
const maxSpeed = computed(() => Math.max(0, ...points.value.map(point => Number(point.speed || 0))));
const routeDistanceKm = computed(() => {
  let meters = 0;
  for (let i = 1; i < points.value.length; i += 1) {
    meters += distanceMeters(points.value[i - 1], points.value[i]);
  }
  return (meters / 1000).toFixed(2);
});
const hasRoute = computed(() => points.value.length >= 2);
const rangeLabel = computed(() => {
  if (!props.hasDateFilter) return `Ultimos ${props.maxPoints ?? 300} puntos registrados`;
  if (props.usesFullRange) return 'Todos los puntos GPS del rango seleccionado';
  const from = filters.value.from || 'inicio';
  const to = filters.value.to || 'ahora';
  return `${from} hasta ${to}`;
});

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));
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

function vehicleIcon(type = 'current', heading = 0) {
  const className = type === 'start' ? 'is-start' : type === 'end' ? 'is-end' : 'is-current';
  const label = type === 'start' ? 'Inicio' : type === 'end' ? 'Final' : escapeHtml(props.vehicle.plate);

  return L.divIcon({
    className: '',
    iconSize: [78, 62],
    iconAnchor: [39, 38],
    popupAnchor: [0, -34],
    html: `
      <div class="route-marker ${className}">
        <div class="route-marker__halo"></div>
        <div class="route-marker__car" style="--heading:${Number(heading || 0)}deg">
          <svg viewBox="0 0 64 64" aria-hidden="true">
            <path class="route-marker__body" d="M17 24h5l5-9h14l6 9h4c4 0 7 3 7 7v11c0 2-2 4-4 4h-3a7 7 0 0 1-14 0H27a7 7 0 0 1-14 0h-3c-2 0-4-2-4-4V31c0-4 3-7 7-7h4z" />
            <path class="route-marker__window" d="M28 19h11l4 6H25l3-6z" />
            <circle class="route-marker__wheel" cx="20" cy="46" r="4" />
            <circle class="route-marker__wheel" cx="44" cy="46" r="4" />
            <path class="route-marker__arrow" d="M32 5l6 8H26l6-8z" />
          </svg>
        </div>
        <div class="route-marker__label">${label}</div>
      </div>
    `,
  });
}

function dotIcon(point, index) {
  const moving = Number(point.speed || 0) > 0;
  return L.divIcon({
    className: '',
    iconSize: [24, 24],
    iconAnchor: [12, 12],
    html: `<span class="route-dot ${moving ? 'is-moving' : 'is-stopped'}">${index + 1}</span>`,
  });
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

function applyFilters() {
  router.get(`/vehiculos/${props.vehicle.id}/recorrido`, {
    from: filters.value.from || undefined,
    to: filters.value.to || undefined,
  }, {
    preserveScroll: true,
    preserveState: false,
    replace: true,
  });
}

function clearFilters() {
  filters.value = { from: '', to: '' };
  router.get(`/vehiculos/${props.vehicle.id}/recorrido`, {}, {
    preserveScroll: true,
    preserveState: false,
    replace: true,
  });
}

onMounted(() => {
  const center = lastPoint.value ? [lastPoint.value.lat, lastPoint.value.lng] : [props.vehicle.current_latitude ?? 4.65, props.vehicle.current_longitude ?? -74.09];
  const map = L.map(mapEl.value, { zoomControl: true }).setView(center, 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: 'Leaflet | OpenStreetMap' }).addTo(map);

  if (points.value.length) {
    const latLngs = points.value.map(point => [point.lat, point.lng]);

    if (hasRoute.value) {
      L.polyline(latLngs, { color: '#ffffff', weight: 14, opacity: 0.95, lineCap: 'round', lineJoin: 'round', smoothFactor: 0, noClip: true }).addTo(map);
      L.polyline(latLngs, { color: '#082f5f', weight: 9, opacity: 1, lineCap: 'round', lineJoin: 'round', smoothFactor: 0, noClip: true }).addTo(map);
      L.polyline(latLngs, { color: '#b6c82f', weight: 4, opacity: 1, dashArray: '10 12', lineCap: 'round', smoothFactor: 0, noClip: true }).addTo(map);

      points.value.forEach(point => {
        L.circleMarker([point.lat, point.lng], {
          radius: 3,
          color: '#ffffff',
          weight: 1,
          fillColor: Number(point.speed || 0) > 0 ? '#15803d' : '#475569',
          fillOpacity: 0.95,
          interactive: false,
        }).addTo(map);
      });
    }

    const step = Math.max(1, Math.ceil(points.value.length / 80));
    points.value.slice(1, -1).forEach((point, index) => {
      if (index % step === 0) {
        L.marker([point.lat, point.lng], { icon: dotIcon(point, index + 1) })
          .addTo(map)
          .bindPopup(pointPopup(point, `Punto ${index + 2}`));
      }
    });

    L.marker([firstPoint.value.lat, firstPoint.value.lng], { icon: vehicleIcon('start', firstPoint.value.heading) })
      .addTo(map)
      .bindPopup(pointPopup(firstPoint.value, 'Inicio del recorrido'));
    L.marker([lastPoint.value.lat, lastPoint.value.lng], { icon: vehicleIcon('end', lastPoint.value.heading) })
      .addTo(map)
      .bindPopup(pointPopup(lastPoint.value, 'Final del recorrido'));
    map.fitBounds(latLngs, { padding: [52, 52], maxZoom: 17 });
  }
});
</script>

<template>
  <Head :title="`Recorrido ${vehicle.plate}`" />
  <AppLayout :title="`Recorrido ${vehicle.plate}`">
    <Link href="/vehiculos" class="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-[#123f6e]"><ArrowLeft class="h-4 w-4" /> Volver a vehiculos</Link>

    <section class="mb-5 rounded-md border border-slate-200 bg-white p-4 shadow-sm">
      <form class="grid gap-4 lg:grid-cols-[1fr_1fr_auto_auto]" @submit.prevent="applyFilters">
        <label class="grid gap-2 text-sm font-semibold text-slate-700">
          <span class="flex items-center gap-2"><CalendarClock class="h-4 w-4 text-[#123f6e]" /> Desde</span>
          <input v-model="filters.from" type="datetime-local" class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-[#123f6e] focus:outline-none focus:ring-2 focus:ring-[#123f6e]/20" />
        </label>
        <label class="grid gap-2 text-sm font-semibold text-slate-700">
          <span class="flex items-center gap-2"><CalendarClock class="h-4 w-4 text-[#123f6e]" /> Hasta</span>
          <input v-model="filters.to" type="datetime-local" class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-[#123f6e] focus:outline-none focus:ring-2 focus:ring-[#123f6e]/20" />
        </label>
        <button type="submit" class="inline-flex h-10 items-center justify-center gap-2 self-end rounded-md bg-[#123f6e] px-4 text-sm font-bold text-white shadow-sm hover:bg-[#0d3157]"><Search class="h-4 w-4" /> Consultar</button>
        <button type="button" class="inline-flex h-10 items-center justify-center gap-2 self-end rounded-md border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-50" @click="clearFilters"><X class="h-4 w-4" /> Limpiar</button>
      </form>
      <div class="mt-3 text-sm text-slate-500">Rango consultado: <span class="font-semibold text-slate-800">{{ rangeLabel }}</span></div>
    </section>

    <section class="mb-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      <article class="rounded-md border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><Route class="h-5 w-5 text-[#123f6e]" /><span class="text-sm text-slate-500">Puntos GPS en la traza</span></div><div class="mt-2 text-2xl font-bold text-slate-950">{{ points.length }}</div></article>
      <article class="rounded-md border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><Navigation class="h-5 w-5 text-[#9fb428]" /><span class="text-sm text-slate-500">Distancia aprox.</span></div><div class="mt-2 text-2xl font-bold text-slate-950">{{ routeDistanceKm }} km</div></article>
      <article class="rounded-md border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><Gauge class="h-5 w-5 text-emerald-700" /><span class="text-sm text-slate-500">Velocidad max.</span></div><div class="mt-2 text-2xl font-bold text-slate-950">{{ maxSpeed }} km/h</div></article>
      <article class="rounded-md border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><Clock class="h-5 w-5 text-slate-600" /><span class="text-sm text-slate-500">Puntos con velocidad</span></div><div class="mt-2 text-2xl font-bold text-slate-950">{{ movingPoints }}</div></article>
    </section>

    <section class="grid gap-4 xl:grid-cols-[1fr_360px]">
      <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
        <div ref="mapEl" class="h-[62vh] min-h-[300px] sm:min-h-[540px]"></div>
      </div>
      <aside class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-4 flex items-center gap-2 text-lg font-semibold text-[#123f6e]"><MapPin class="h-5 w-5" /> Detalle</h2>
        <dl class="space-y-4 text-sm">
          <div><dt class="text-slate-500">Placa</dt><dd class="text-lg font-bold text-slate-950">{{ vehicle.plate }}</dd></div>
          <div><dt class="text-slate-500">Conductor</dt><dd class="font-medium">{{ vehicle.driver?.name ?? 'Sin asignar' }} {{ vehicle.driver?.last_name ?? '' }}</dd></div>
          <div><dt class="text-slate-500">Proveedor</dt><dd class="font-medium">{{ vehicle.provider?.name ?? '-' }}</dd></div>
          <div><dt class="text-slate-500">Ultimo evento</dt><dd class="font-medium">{{ vehicle.last_gps_event ?? '-' }}</dd></div>
          <div><dt class="text-slate-500">Ultima transmision</dt><dd class="font-medium">{{ vehicle.last_gps_datetime ?? '-' }}</dd></div>
          <div><dt class="text-slate-500">Direccion actual</dt><dd class="font-medium leading-6">{{ vehicle.current_address ?? '-' }}</dd></div>
        </dl>
        <div class="mt-6 rounded-md bg-[#eef2f7] p-4 text-sm text-slate-600">
          <div class="mb-2 flex items-center gap-2"><span class="inline-block h-3 w-8 rounded-full bg-[#082f5f]"></span> Linea con todos los puntos GPS</div>
          <div class="mb-2 flex items-center gap-2"><span class="inline-block h-3 w-8 rounded-full bg-[#b6c82f]"></span> Trazo punteado encima</div>
          <div class="mb-2 flex items-center gap-2"><span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-600 text-[10px] font-bold text-white">1</span> Posicion historica</div>
          <div class="flex items-center gap-2"><span class="inline-block h-3 w-3 rounded-full bg-slate-500"></span> Inicio y final resaltados con carrito</div>
        </div>
      </aside>
    </section>
  </AppLayout>
</template>