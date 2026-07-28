<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeft, Boxes, Filter, History, Search, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const props = defineProps({ movements: Object, filters: Object, vehicles: Array, categories: Array, items: Array, movementTypes: Array });
const user = ref(props.filters?.user ?? '');
const dateFrom = ref(props.filters?.date_from ?? '');
const dateTo = ref(props.filters?.date_to ?? '');
const vehicleId = ref(props.filters?.vehicle_id ?? '');
const categoryId = ref(props.filters?.category_id ?? '');
const itemId = ref(props.filters?.item_id ?? '');
const movementType = ref(props.filters?.movement_type ?? '');
const perPage = ref(props.filters?.per_page ?? 15);
const movementLabels = {
  stock_update: 'Actualizacion de stock',
  reserved: 'Reservada',
  released: 'Liberada',
  delivered: 'Entregada',
  returned: 'Devuelta',
};

const filteredItems = computed(() => (props.items ?? []).filter((item) => !categoryId.value || Number(item.inventory_category_id) === Number(categoryId.value)));

const filterPayload = () => ({
  user: user.value || undefined,
  date_from: dateFrom.value || undefined,
  date_to: dateTo.value || undefined,
  vehicle_id: vehicleId.value || undefined,
  category_id: categoryId.value || undefined,
  item_id: itemId.value || undefined,
  movement_type: movementType.value || undefined,
  per_page: perPage.value || undefined,
});

function applyFilters() {
  router.get('/inventario/movimientos', filterPayload(), { preserveState: true, replace: true });
}

function clearFilters() {
  user.value = '';
  dateFrom.value = '';
  dateTo.value = '';
  vehicleId.value = '';
  categoryId.value = '';
  itemId.value = '';
  movementType.value = '';
  perPage.value = 15;
  router.get('/inventario/movimientos', {}, { preserveState: true, replace: true });
}

function changePerPage() {
  applyFilters();
}

watch(categoryId, () => {
  if (itemId.value && !filteredItems.value.some((item) => Number(item.id) === Number(itemId.value))) {
    itemId.value = '';
  }
});

function movementLabel(type) {
  return movementLabels[type] ?? type;
}

function formatBogota(value) {
  if (!value) return '-';

  return new Intl.DateTimeFormat('es-CO', {
    timeZone: 'America/Bogota',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  }).format(new Date(value));
}
</script>

<template>
  <Head title="Movimientos recientes" />
  <AppLayout title="Movimientos recientes">
    <section class="space-y-5">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <Link href="/inventario" class="inline-flex items-center gap-2 text-sm font-semibold text-[#123f6e]">
          <ArrowLeft class="h-4 w-4" /> Volver al inventario
        </Link>
        <select v-model="perPage" @change="changePerPage" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
          <option value="15">15 por pag.</option>
          <option value="25">25 por pag.</option>
          <option value="50">50 por pag.</option>
          <option value="100">100 por pag.</option>
        </select>
      </div>

      <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-[#123f6e] sm:text-lg">
          <Filter class="h-5 w-5" /> Filtros
        </h2>
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[1fr_150px_150px_170px_190px_190px_170px_auto_auto]">
          <label class="relative">
            <Search class="pointer-events-none absolute left-3 top-3.5 h-4 w-4 text-slate-400" />
            <input v-model="user" @keyup.enter="applyFilters" class="w-full rounded-md border border-slate-300 py-3 pl-10 pr-3 text-sm outline-none focus:border-[#123f6e]" placeholder="Usuario" />
          </label>
          <input v-model="dateFrom" type="date" class="w-full rounded-md border border-slate-300 px-3 py-3 text-sm" />
          <input v-model="dateTo" type="date" class="w-full rounded-md border border-slate-300 px-3 py-3 text-sm" />
          <select v-model="vehicleId" class="w-full rounded-md border border-slate-300 px-3 py-3 text-sm">
            <option value="">Todos los vehiculos</option>
            <option v-for="vehicle in vehicles" :key="vehicle.id" :value="vehicle.id">{{ vehicle.plate }}</option>
          </select>
          <select v-model="categoryId" class="w-full rounded-md border border-slate-300 px-3 py-3 text-sm">
            <option value="">Todos los tipos</option>
            <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
          </select>
          <select v-model="itemId" class="w-full rounded-md border border-slate-300 px-3 py-3 text-sm">
            <option value="">Todas las herramientas</option>
            <option v-for="item in filteredItems" :key="item.id" :value="item.id">{{ item.name }}</option>
          </select>
          <select v-model="movementType" class="w-full rounded-md border border-slate-300 px-3 py-3 text-sm">
            <option value="">Todos los movimientos</option>
            <option v-for="type in movementTypes" :key="type" :value="type">{{ movementLabel(type) }}</option>
          </select>
          <button @click="applyFilters" class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-md bg-[#123f6e] px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#0e2d52]">
            <Filter class="h-4 w-4" /> Filtrar
          </button>
          <button @click="clearFilters" class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-md border border-[#123f6e] px-4 py-3 text-sm font-semibold text-[#123f6e] transition-colors hover:bg-[#123f6e] hover:text-white">
            <X class="h-4 w-4" /> Limpiar
          </button>
        </div>
      </section>

      <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
          <h2 class="flex items-center gap-2 text-base font-semibold text-[#123f6e] sm:text-lg">
            <History class="h-5 w-5" /> Registro de movimientos
          </h2>
          <span class="text-sm text-slate-500">{{ movements.total ?? 0 }} movimientos</span>
        </div>

        <div class="hidden sm:block">
          <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-500">
              <tr>
                <th class="px-3 py-2">Fecha</th>
                <th>Herramienta</th>
                <th>Vehiculo</th>
                <th>Movimiento</th>
                <th>Cantidad</th>
                <th>Disponible</th>
                <th>Usuario</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="movement in movements.data" :key="movement.id" class="border-t border-slate-100 align-top">
                <td class="px-3 py-3 text-slate-600">{{ formatBogota(movement.created_at) }}</td>
                <td class="py-3">
                  <div class="font-medium text-slate-900">{{ movement.item?.name ?? '-' }}</div>
                  <div class="text-xs text-slate-500">{{ movement.item?.unit ?? '' }}</div>
                </td>
                <td class="py-3 font-medium text-slate-700">{{ movement.vehicle?.plate ?? '-' }}</td>
                <td class="py-3">
                  <span class="rounded bg-[#e6eef7] px-2 py-1 text-xs font-semibold text-[#123f6e]">{{ movementLabel(movement.movement_type) }}</span>
                </td>
                <td class="py-3 text-slate-700">{{ movement.quantity }}</td>
                <td class="py-3 text-slate-700">{{ movement.previous_available }} -> {{ movement.new_available }}</td>
                <td class="py-3 text-slate-600">{{ movement.creator?.name ?? 'Sistema' }} {{ movement.creator?.last_name ?? '' }}</td>
              </tr>
              <tr v-if="!movements.data.length">
                <td colspan="7" class="px-3 py-8 text-center text-slate-500">Sin movimientos registrados.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="space-y-2 sm:hidden">
          <div v-for="movement in movements.data" :key="movement.id" class="rounded border border-slate-200 bg-white p-3 text-sm shadow-sm">
            <div class="mb-2 flex items-start justify-between gap-2">
              <div>
                <div class="font-semibold text-slate-900">{{ movement.item?.name ?? '-' }}</div>
                <div class="text-xs text-slate-500">{{ movement.vehicle?.plate ?? '-' }} | {{ formatBogota(movement.created_at) }}</div>
              </div>
              <Boxes class="h-5 w-5 shrink-0 text-[#123f6e]" />
            </div>
            <div class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-slate-600">
              <div><span class="font-medium text-slate-700">Tipo:</span> {{ movementLabel(movement.movement_type) }}</div>
              <div><span class="font-medium text-slate-700">Cantidad:</span> {{ movement.quantity }}</div>
              <div><span class="font-medium text-slate-700">Disponible:</span> {{ movement.previous_available }} -> {{ movement.new_available }}</div>
              <div><span class="font-medium text-slate-700">Usuario:</span> {{ movement.creator?.name ?? 'Sistema' }}</div>
            </div>
          </div>
          <p v-if="!movements.data.length" class="py-4 text-center text-sm text-slate-500">Sin movimientos registrados.</p>
        </div>

        <div v-if="movements.last_page > 1" class="mt-5 flex flex-wrap items-center justify-center gap-1 sm:gap-2">
          <Link v-for="link in movements.links" :key="link.label" :href="link.url || '#'" preserve-scroll class="rounded-md border px-2 py-2 text-xs font-semibold sm:px-3 sm:text-sm" :class="[link.active ? 'border-[#123f6e] bg-[#123f6e] text-white' : 'border-slate-200 bg-white text-slate-700', !link.url ? 'pointer-events-none opacity-40' : 'hover:bg-[#edf3fa]']" v-html="link.label" />
        </div>
      </section>
    </section>
  </AppLayout>
</template>
