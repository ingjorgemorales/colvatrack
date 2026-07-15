<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeft, Boxes, History } from '@lucide/vue';
import { ref } from 'vue';

const props = defineProps({ movements: Object, filters: Object });
const perPage = ref(props.filters?.per_page ?? 15);
const movementLabels = {
  stock_update: 'Actualizacion de stock',
  reserved: 'Reservada',
  released: 'Liberada',
  delivered: 'Entregada',
  returned: 'Devuelta',
};

function changePerPage() {
  router.get('/inventario/movimientos', { per_page: perPage.value }, { preserveState: true, replace: true });
}

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
