<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeft, MapPin, Navigation, Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const props = defineProps({ vehicles: Array, selectedVehicleId: Number, userLocation: Object });
const form = useForm({ vehicle_id: props.selectedVehicleId ?? '', priority: 'normal', technician_address: '', observation: '', items: [] });
const nearbyRadius = ref(500);

const vehicle = computed(() => props.vehicles.find(v => Number(v.id) === Number(form.vehicle_id)) ?? null);
const hasUserLocation = computed(() => Boolean(props.userLocation?.latitude && props.userLocation?.longitude));
const availableItems = computed(() => vehicle.value?.inventory?.filter(i => Number(i.quantity_available) > 0) ?? []);
const nearbyVehicles = computed(() => props.vehicles
  .filter(v => v.distance_meters !== null && v.distance_meters !== undefined)
  .filter(v => !nearbyRadius.value || Number(v.distance_meters) <= Number(nearbyRadius.value))
  .slice(0, 8)
);
const nearestVehicle = computed(() => nearbyVehicles.value.find(v => v.has_available_inventory) ?? nearbyVehicles.value[0] ?? null);

watch(() => form.vehicle_id, () => { form.items = []; });

function addItem(){ const first = availableItems.value[0]; if(first) form.items.push({ inventory_item_id: first.inventory_item_id, quantity: 1 }); }
function maxFor(itemId){ const row = availableItems.value.find(i => Number(i.inventory_item_id) === Number(itemId)); return row?.quantity_available ?? 1; }
function formatDistance(meters) {
  if (meters === null || meters === undefined) return 'Sin GPS';
  return Number(meters) >= 1000 ? `${(Number(meters) / 1000).toFixed(2)} km` : `${Number(meters)} m`;
}
function selectVehicle(vehicleId) {
  form.vehicle_id = vehicleId;
}
</script>
<template>
  <Head title="Nueva solicitud" />
  <AppLayout title="Nueva solicitud">
    <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
      <Link href="/solicitudes" class="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-[#123f6e]"><ArrowLeft class="h-4 w-4" /> Volver</Link>

      <section class="mb-5 rounded-md border border-emerald-200 bg-emerald-50 p-4">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <h2 class="flex items-center gap-2 text-lg font-bold text-emerald-800"><Navigation class="h-5 w-5" /> Vehiculos cercanos al tecnico</h2>
            <p class="mt-1 text-sm text-emerald-900/80">La lista se ordena por distancia desde tu ultima ubicacion sincronizada.</p>
          </div>
          <label class="grid gap-1 text-sm font-semibold text-emerald-900">
            <span>Radio metros</span>
            <input v-model="nearbyRadius" type="number" min="1" class="w-44 rounded-md border border-emerald-300 bg-white px-3 py-2 outline-none focus:border-emerald-700" />
          </label>
        </div>

        <div v-if="!hasUserLocation" class="mt-4 rounded-md bg-white p-3 text-sm text-amber-700">No hay ubicacion vigente del tecnico. Activa la ubicacion para calcular vehiculos cercanos.</div>

        <div v-else-if="nearbyVehicles.length" class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
          <button v-for="v in nearbyVehicles" :key="v.id" type="button" @click="selectVehicle(v.id)" class="rounded-md border bg-white p-3 text-left shadow-sm transition hover:border-emerald-500" :class="Number(form.vehicle_id) === Number(v.id) ? 'border-emerald-600 ring-2 ring-emerald-200' : 'border-slate-200'">
            <div class="flex items-start justify-between gap-2">
              <div>
                <div class="font-bold text-slate-950">{{ v.plate }}</div>
                <div class="text-sm text-slate-500">{{ v.driver?.name ?? 'Sin conductor' }}</div>
              </div>
              <span class="rounded bg-emerald-100 px-2 py-1 text-xs font-bold text-emerald-800">{{ formatDistance(v.distance_meters) }}</span>
            </div>
            <div class="mt-3 flex items-center gap-2 text-xs text-slate-600"><MapPin class="h-4 w-4 text-emerald-700" /> {{ v.available_items_count }} herramientas disponibles</div>
          </button>
        </div>

        <div v-else class="mt-4 rounded-md bg-white p-3 text-sm text-slate-600">No hay vehiculos dentro de {{ nearbyRadius }} m. Aumenta el radio para ver mas opciones.</div>

        <button v-if="nearestVehicle" type="button" @click="selectVehicle(nearestVehicle.id)" class="mt-4 rounded-md bg-emerald-700 px-4 py-2 text-sm font-bold text-white">Usar mas cercano: {{ nearestVehicle.plate }} ({{ formatDistance(nearestVehicle.distance_meters) }})</button>
      </section>

      <form class="space-y-5" @submit.prevent="form.post('/solicitudes')">
        <div class="grid gap-4 md:grid-cols-2">
          <label><span>Vehiculo</span><select v-model="form.vehicle_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3"><option value="">Seleccionar</option><option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.plate }} - {{ formatDistance(v.distance_meters) }} - {{ v.driver?.name ?? 'sin conductor' }}</option></select></label>
          <label><span>Prioridad</span><select v-model="form.priority" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3"><option value="baja">Baja</option><option value="normal">Normal</option><option value="alta">Alta</option><option value="critica">Critica</option></select></label>
        </div>
        <div v-if="vehicle" class="rounded-md bg-[#e6eef7] p-4 text-sm text-[#123f6e]">Conductor: {{ vehicle.driver?.name ?? 'Sin asignar' }} | Distancia: {{ formatDistance(vehicle.distance_meters) }} | Herramientas disponibles: {{ availableItems.length }}</div>
        <label class="block"><span>Direccion/Referencia del tecnico</span><textarea v-model="form.technician_address" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3"></textarea></label>
        <label class="block"><span>Observaciones</span><textarea v-model="form.observation" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3"></textarea></label>
        <section><div class="mb-3 flex items-center justify-between"><h2 class="font-semibold text-[#123f6e]">Herramientas solicitadas</h2><button type="button" @click="addItem" class="inline-flex items-center gap-2 rounded-md border border-[#123f6e] px-3 py-2 font-semibold text-[#123f6e]"><Plus class="h-4 w-4" /> Agregar</button></div><div class="space-y-3"><div v-for="(item, index) in form.items" :key="index" class="grid gap-3 rounded-md border border-slate-200 p-3 md:grid-cols-[1fr_140px_auto]"><select v-model="item.inventory_item_id" class="rounded-md border border-slate-300 px-3 py-3"><option v-for="row in availableItems" :key="row.id" :value="row.inventory_item_id">{{ row.item?.name }} (disp. {{ row.quantity_available }})</option></select><input v-model="item.quantity" type="number" min="1" :max="maxFor(item.inventory_item_id)" class="rounded-md border border-slate-300 px-3 py-3" /><button type="button" @click="form.items.splice(index, 1)" class="rounded-md border border-red-200 p-3 text-red-700"><Trash2 class="h-5 w-5" /></button></div><p v-if="!form.items.length" class="rounded-md bg-slate-50 p-4 text-sm text-slate-500">Agrega al menos una herramienta.</p></div></section>
        <div v-if="Object.keys(form.errors).length" class="rounded-md bg-red-50 p-3 text-sm text-red-700"><p v-for="error in form.errors" :key="error">{{ error }}</p></div>
        <button class="rounded-md bg-[#123f6e] px-5 py-3 font-semibold text-white">Crear solicitud</button>
      </form>
    </section>
  </AppLayout>
</template>