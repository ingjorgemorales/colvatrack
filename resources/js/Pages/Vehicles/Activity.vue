<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeft, Car, Clock3, MapPinned, Route, Search } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps({ activity: Object, filters: Object });

const from = ref(props.activity?.filters?.from ?? '');
const to = ref(props.activity?.filters?.to ?? '');
const status = ref(props.filters?.status || 'todos');
const search = ref(props.filters?.search ?? '');
const perPage = ref(props.filters?.per_page ?? 10);

const rows = computed(() => props.activity?.rows?.data ?? []);

const movingRows = computed(() => rows.value.filter(row => row.status === 'moving').length);
const stoppedRows = computed(() => rows.value.filter(row => row.status === 'stopped').length);
const totalPoints = computed(() => rows.value.reduce((sum, row) => sum + Number(row.points_count || 0), 0));
const totalDistance = computed(() => rows.value.reduce((sum, row) => sum + Number(row.distance_km || 0), 0).toFixed(2));

function applyDateFilters() {
  router.get('/vehiculos/actividad', {
    from: from.value,
    to: to.value,
    status: status.value === 'todos' ? '' : status.value,
    search: search.value,
    per_page: perPage.value,
  }, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
  });
}
</script>

<template>
  <Head title="Actividad GPS de vehiculos" />
  <AppLayout title="Actividad GPS de vehiculos">
    <Link href="/dashboard" class="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-[#123f6e]">
      <ArrowLeft class="h-4 w-4" /> Volver al dashboard
    </Link>

    <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-5 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
          <h2 class="text-lg font-semibold text-[#123f6e]">Resumen de actividad</h2>
          <p class="mt-1 text-sm text-slate-500">
            Movimiento calculado entre {{ activity.filters.from_datetime }} y {{ activity.filters.to_datetime }}.
            Umbral: {{ activity.summary.threshold_meters }} metros.
          </p>
        </div>
        <form class="grid gap-3 md:grid-cols-[1fr_1fr_1fr_1fr_120px_auto]" @submit.prevent="applyDateFilters">
          <label class="text-sm font-medium text-slate-600">
            Desde
            <input v-model="from" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
          </label>
          <label class="text-sm font-medium text-slate-600">
            Hasta
            <input v-model="to" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
          </label>
          <label class="text-sm font-medium text-slate-600">
            Estado
            <select v-model="status" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
              <option value="todos">Todos</option>
              <option value="moving">En movimiento</option>
              <option value="stopped">Sin movimiento</option>
            </select>
          </label>
          <label class="text-sm font-medium text-slate-600">
            Buscar
            <span class="mt-1 flex items-center gap-2 rounded-md border border-slate-300 px-3 py-2">
              <Search class="h-4 w-4 text-slate-400" />
              <input v-model="search" type="search" class="min-w-0 flex-1 border-0 p-0 outline-none focus:ring-0" placeholder="Placa o conductor" />
            </span>
          </label>
          <label class="text-sm font-medium text-slate-600">
            Por pag.
            <select v-model="perPage" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
              <option :value="10">10</option>
              <option :value="20">20</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
            </select>
          </label>
          <button class="cursor-pointer rounded-md bg-[#123f6e] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#0e2d52] md:self-end">Filtrar</button>
        </form>
      </div>

      <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
        <article class="rounded-md border border-slate-200 bg-slate-50 p-4">
          <div class="flex items-center justify-between"><p class="text-sm font-medium text-slate-500">Vehiculos visibles</p><Car class="h-5 w-5 text-slate-400" /></div>
          <p class="mt-2 text-2xl font-bold text-slate-950">{{ activity.rows.total }}</p>
        </article>
        <article class="rounded-md border border-slate-200 bg-slate-50 p-4">
          <div class="flex items-center justify-between"><p class="text-sm font-medium text-slate-500">En movimiento</p><MapPinned class="h-5 w-5 text-emerald-600" /></div>
          <p class="mt-2 text-2xl font-bold text-slate-950">{{ movingRows }}</p>
        </article>
        <article class="rounded-md border border-slate-200 bg-slate-50 p-4">
          <div class="flex items-center justify-between"><p class="text-sm font-medium text-slate-500">Sin movimiento</p><Clock3 class="h-5 w-5 text-orange-600" /></div>
          <p class="mt-2 text-2xl font-bold text-slate-950">{{ stoppedRows }}</p>
        </article>
        <article class="rounded-md border border-slate-200 bg-slate-50 p-4">
          <div class="flex items-center justify-between"><p class="text-sm font-medium text-slate-500">Puntos GPS</p><Route class="h-5 w-5 text-[#123f6e]" /></div>
          <p class="mt-2 text-2xl font-bold text-slate-950">{{ totalPoints }}</p>
        </article>
        <article class="rounded-md border border-slate-200 bg-slate-50 p-4">
          <div class="flex items-center justify-between"><p class="text-sm font-medium text-slate-500">Distancia visible</p><Route class="h-5 w-5 text-slate-500" /></div>
          <p class="mt-2 text-2xl font-bold text-slate-950">{{ totalDistance }} km</p>
        </article>
      </div>
    </section>

    <section class="mt-6 rounded-md border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-lg font-semibold text-[#123f6e]">Detalle por vehiculo</h2>
        <span class="text-sm text-slate-500">Mostrando {{ activity.rows.from ?? 0 }}-{{ activity.rows.to ?? 0 }} de {{ activity.rows.total }} vehiculos</span>
      </div>

      <div class="hidden overflow-x-auto lg:block">
        <table class="w-full text-left text-sm">
          <thead class="bg-slate-50 text-slate-500">
            <tr>
              <th class="px-3 py-2">Placa</th>
              <th>Conductor</th>
              <th>Estado</th>
              <th>Puntos</th>
              <th>Vel. max</th>
              <th>Distancia</th>
              <th>Primer GPS</th>
              <th>Ultimo GPS rango</th>
              <th>Ultima transmision</th>
              <th class="text-right">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id" class="border-t border-slate-100">
              <td class="px-3 py-3 font-semibold text-slate-950">{{ row.plate }}</td>
              <td>{{ row.driver }}</td>
              <td>
                <span class="rounded px-2 py-1 text-xs font-semibold" :class="row.status === 'moving' ? 'bg-emerald-100 text-emerald-800' : 'bg-orange-100 text-orange-800'">
                  {{ row.status_label }}
                </span>
              </td>
              <td>{{ row.points_count }}</td>
              <td>{{ row.max_speed }} km/h</td>
              <td>{{ row.distance_km }} km</td>
              <td>{{ row.first_gps_datetime ?? '-' }}</td>
              <td>{{ row.last_gps_datetime ?? '-' }}</td>
              <td>
                <span :class="row.gps_is_fresh ? 'text-emerald-700' : 'text-slate-500'">{{ row.vehicle_last_gps_datetime ?? '-' }}</span>
              </td>
              <td class="text-right">
                <Link :href="row.route_url" class="inline-flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 font-semibold text-[#123f6e] transition-colors hover:bg-[#edf3fa]">
                  <MapPinned class="h-4 w-4" /> Recorrido
                </Link>
              </td>
            </tr>
            <tr v-if="!rows.length">
              <td colspan="10" class="px-3 py-8 text-center text-slate-500">No hay vehiculos con esos filtros.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="space-y-3 lg:hidden">
        <article v-for="row in rows" :key="row.id" class="rounded-md border border-slate-100 bg-slate-50 p-4 text-sm">
          <div class="mb-2 flex items-start justify-between gap-3">
            <div>
              <h3 class="font-semibold text-slate-950">{{ row.plate }}</h3>
              <p class="text-slate-500">{{ row.driver }}</p>
            </div>
            <span class="shrink-0 rounded px-2 py-1 text-xs font-semibold" :class="row.status === 'moving' ? 'bg-emerald-100 text-emerald-800' : 'bg-orange-100 text-orange-800'">
              {{ row.status_label }}
            </span>
          </div>
          <dl class="grid grid-cols-2 gap-2 text-xs text-slate-600">
            <div><dt class="font-medium text-slate-800">Puntos</dt><dd>{{ row.points_count }}</dd></div>
            <div><dt class="font-medium text-slate-800">Vel. max</dt><dd>{{ row.max_speed }} km/h</dd></div>
            <div><dt class="font-medium text-slate-800">Distancia</dt><dd>{{ row.distance_km }} km</dd></div>
            <div><dt class="font-medium text-slate-800">Ultima GPS</dt><dd>{{ row.vehicle_last_gps_datetime ?? '-' }}</dd></div>
          </dl>
          <Link :href="row.route_url" class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 font-semibold text-[#123f6e]">
            <MapPinned class="h-4 w-4" /> Ver recorrido
          </Link>
        </article>
        <p v-if="!rows.length" class="py-6 text-center text-sm text-slate-500">No hay vehiculos con esos filtros.</p>
      </div>

      <div v-if="activity.rows.last_page > 1" class="mt-5 flex flex-wrap items-center justify-center gap-1 sm:gap-2">
        <Link
          v-for="link in activity.rows.links"
          :key="link.label"
          :href="link.url || '#'"
          preserve-scroll
          class="rounded-md border px-2 py-2 text-xs font-semibold sm:px-3 sm:text-sm"
          :class="[link.active ? 'border-[#123f6e] bg-[#123f6e] text-white' : 'border-slate-200 bg-white text-slate-700', !link.url ? 'pointer-events-none opacity-40' : 'hover:bg-[#edf3fa]']"
          v-html="link.label"
        />
      </div>
    </section>
  </AppLayout>
</template>
