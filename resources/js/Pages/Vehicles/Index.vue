<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { FolderKanban, LockKeyhole, MapPinned, Pencil, Power, PowerOff, Search, UnlockKeyhole, X } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps({ vehicles: Object, filters: Object });
const page = usePage();
const perms = page.props.auth?.permissions ?? [];
const can = (module, action = 'ver') => perms.includes('*') || perms.includes(`${module}.${action}`);
const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? '');
const movement = ref(props.filters?.movement ?? '');
const reserved = ref(props.filters?.reserved ?? '');
const perPage = ref(props.filters?.per_page ?? 10);
const apply = () => router.get('/vehiculos', { search: search.value, status: status.value, movement: movement.value, reserved: reserved.value, per_page: perPage.value }, { preserveState: true, replace: true });
const clearFilters = () => { search.value = ''; status.value = ''; movement.value = ''; reserved.value = ''; perPage.value = 10; router.get('/vehiculos', {}, { preserveState: true, replace: true }); };
const toggleStatus = (vehicle) => {
  if (vehicle.status === 'active') {
    const driverText = vehicle.driver?.name ? `\n\nSe liberara el conductor ${vehicle.driver.name} ${vehicle.driver.last_name ?? ''}.` : '';
    const reservationText = vehicle.active_reservation ? '\n\nTambien se liberara la reserva activa.' : '';
    if (confirm(`Desactivar vehiculo ${vehicle.plate}?${driverText}${reservationText}`)) {
      router.patch(`/vehiculos/${vehicle.id}/estado`, {}, { preserveScroll: true });
    }
    return;
  }

  if (confirm(`Activar vehiculo ${vehicle.plate}?`)) {
    router.patch(`/vehiculos/${vehicle.id}/estado`, {}, { preserveScroll: true });
  }
};
const reserve = (vehicle) => {
  if (!confirm(`Reservar vehiculo ${vehicle.plate}? Mientras este reservado, los tecnicos no podran solicitar herramientas de este vehiculo.`)) return;
  const reason = prompt(`Motivo de la reserva para ${vehicle.plate}`);
  if (!reason) return;
  router.post(`/vehiculos/${vehicle.id}/reservas`, { reason }, { preserveScroll: true });
};
const releaseReservation = (vehicle) => {
  const comment = prompt(`Comentario para liberar la reserva de ${vehicle.plate}`, 'Reserva liberada');
  if (comment === null) return;
  router.patch(`/vehiculos/${vehicle.id}/reservas/liberar`, { release_comment: comment }, { preserveScroll: true });
};
const canManageVehicles = computed(() => can('vehiculos', 'editar'));
</script>

<template>
  <Head title="Vehiculos" />
  <AppLayout title="Vehiculos">
    <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-5 grid gap-2 sm:grid-cols-2 lg:grid-cols-[1fr_160px_170px_170px_140px_auto_auto]">
        <div class="relative"><Search class="absolute left-3 top-3.5 h-5 w-5 text-slate-400" /><input v-model="search" @keyup.enter="apply" class="w-full rounded-md border border-slate-300 py-3 pl-10 pr-3" placeholder="Buscar placa, marca o modelo" /></div>
        <select v-model="status" class="w-full rounded-md border border-slate-300 px-3 py-3 sm:w-auto"><option value="">Todos los estados</option><option value="active">Activo</option><option value="maintenance">Mantenimiento</option><option value="inactive">Inactivo</option></select>
        <select v-model="movement" class="w-full rounded-md border border-slate-300 px-3 py-3 sm:w-auto"><option value="">Todos los movimientos</option><option value="moving">En movimiento</option><option value="stopped">Sin movimiento</option></select>
        <select v-model="reserved" class="w-full rounded-md border border-slate-300 px-3 py-3 sm:w-auto"><option value="">Todas las reservas</option><option value="active">Reservados</option><option value="none">No reservados</option></select>
        <select v-model="perPage" @change="apply" class="w-full rounded-md border border-slate-300 px-3 py-3 sm:w-auto"><option value="10">10 por pagina</option><option value="25">25 por pagina</option><option value="50">50 por pagina</option><option value="100">100 por pagina</option></select>
        <button @click="apply" class="w-full cursor-pointer rounded-md bg-[#123f6e] px-4 py-3 font-semibold text-white transition-colors hover:bg-[#0e2d52] sm:w-auto">Filtrar</button>
        <button @click="clearFilters" class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-md border border-[#123f6e] px-4 py-3 font-semibold text-[#123f6e] transition-colors hover:bg-[#123f6e] hover:text-white sm:w-auto"><X class="h-4 w-4" /> Limpiar</button>
      </div>

      <div class="mb-4 flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600">
        <span>Mostrando {{ vehicles.from ?? 0 }}-{{ vehicles.to ?? 0 }} de {{ vehicles.total ?? 0 }} vehiculos</span>
        <div class="flex flex-wrap items-center gap-2">
          <span>Pagina {{ vehicles.current_page }} de {{ vehicles.last_page }}</span>
          <Link href="/vehiculos/proyectos" class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 font-semibold text-[#123f6e] shadow-sm transition-colors hover:bg-[#edf3fa]"><FolderKanban class="h-4 w-4" /> Proyectos</Link>
        </div>
      </div>

      <div class="hidden sm:block">
        <table class="w-full text-left text-sm">
          <thead class="bg-slate-50 text-slate-500"><tr><th class="px-3 py-2">Placa</th><th>Marca/Modelo</th><th>Proyecto</th><th>Conductor</th><th>GPS</th><th>Velocidad</th><th>Ultima GPS</th><th>Estado</th><th class="text-right">Acciones</th></tr></thead>
          <tbody>
            <tr v-for="v in vehicles.data" :key="v.id" class="border-t border-slate-100">
              <td class="px-3 py-3 font-semibold text-slate-950">{{ v.plate }}</td>
              <td>{{ v.brand ?? '-' }} {{ v.model ?? '' }}</td>
              <td>{{ v.project?.name ?? 'Sin proyecto' }}<div v-if="v.active_reservation" class="mt-1 rounded bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800">Reservado</div></td>
              <td>{{ v.driver?.name ?? 'Sin asignar' }}</td>
              <td>{{ v.provider?.name ?? '-' }}</td>
              <td>{{ v.current_speed ?? 0 }} km/h</td>
              <td>{{ v.last_gps_datetime ?? '-' }}</td>
              <td><span class="rounded px-2 py-1" :class="v.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">{{ v.status }}</span></td>
              <td class="text-right"><Link :href="`/vehiculos/${v.id}/recorrido`" class="mr-2 inline-flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 font-semibold text-[#123f6e]"><MapPinned class="h-4 w-4" /> Recorrido</Link><button v-if="canManageVehicles && v.status === 'active' && !v.active_reservation" @click="reserve(v)" class="mr-2 inline-flex cursor-pointer rounded-md border border-amber-200 p-2 text-amber-700 transition-colors hover:bg-amber-50" title="Reservar vehiculo"><LockKeyhole class="h-4 w-4" /></button><button v-if="canManageVehicles && v.active_reservation" @click="releaseReservation(v)" class="mr-2 inline-flex cursor-pointer rounded-md border border-emerald-200 p-2 text-emerald-700 transition-colors hover:bg-emerald-50" title="Liberar reserva"><UnlockKeyhole class="h-4 w-4" /></button><Link :href="`/vehiculos/${v.id}/edit`" class="mr-2 inline-flex rounded-md border border-slate-200 p-2 text-[#123f6e]"><Pencil class="h-4 w-4" /></Link><button v-if="canManageVehicles" @click="toggleStatus(v)" class="inline-flex cursor-pointer rounded-md border p-2 transition-colors" :class="v.status === 'active' ? 'border-orange-200 text-orange-700 hover:bg-orange-50' : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50'" :title="v.status === 'active' ? 'Desactivar vehiculo' : 'Activar vehiculo'"><PowerOff v-if="v.status === 'active'" class="h-4 w-4" /><Power v-else class="h-4 w-4" /></button></td>
            </tr>
            <tr v-if="!vehicles.data.length"><td colspan="9" class="px-3 py-8 text-center text-slate-500">No hay vehiculos con esos filtros.</td></tr>
          </tbody>
        </table>
      </div>
      <div class="space-y-2 sm:hidden"><div v-for="v in vehicles.data" :key="v.id" class="rounded border border-slate-100 bg-slate-50 p-3 text-sm"><div class="mb-2 flex items-start justify-between gap-2"><div class="font-semibold text-slate-950">{{ v.plate }}</div><div class="flex shrink-0 gap-1"><span v-if="v.active_reservation" class="rounded bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800">Reservado</span><span class="rounded px-2 py-1 text-xs" :class="v.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">{{ v.status }}</span></div></div><div class="text-xs text-slate-600">{{ v.brand ?? '-' }} {{ v.model ?? '' }}</div><div class="mt-1 grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-slate-600"><div><span class="font-medium text-slate-700">Proyecto:</span> {{ v.project?.name ?? 'Sin proyecto' }}</div><div><span class="font-medium text-slate-700">Conductor:</span> {{ v.driver?.name ?? 'Sin asignar' }}</div><div><span class="font-medium text-slate-700">GPS:</span> {{ v.provider?.name ?? '-' }}</div><div><span class="font-medium text-slate-700">Velocidad:</span> {{ v.current_speed ?? 0 }} km/h</div><div class="col-span-2"><span class="font-medium text-slate-700">Ultima GPS:</span> {{ v.last_gps_datetime ?? '-' }}</div></div><div class="mt-2 flex flex-wrap gap-2"><Link :href="`/vehiculos/${v.id}/recorrido`" class="inline-flex items-center gap-1 rounded-md border border-slate-200 px-3 py-2 text-xs font-semibold text-[#123f6e]"><MapPinned class="h-3 w-3" /> Recorrido</Link><button v-if="canManageVehicles && v.status === 'active' && !v.active_reservation" @click="reserve(v)" class="inline-flex cursor-pointer rounded-md border border-amber-200 p-2 text-amber-700 transition-colors hover:bg-amber-50"><LockKeyhole class="h-4 w-4" /></button><button v-if="canManageVehicles && v.active_reservation" @click="releaseReservation(v)" class="inline-flex cursor-pointer rounded-md border border-emerald-200 p-2 text-emerald-700 transition-colors hover:bg-emerald-50"><UnlockKeyhole class="h-4 w-4" /></button><Link :href="`/vehiculos/${v.id}/edit`" class="inline-flex rounded-md border border-slate-200 p-2 text-[#123f6e]"><Pencil class="h-4 w-4" /></Link><button v-if="canManageVehicles" @click="toggleStatus(v)" class="inline-flex cursor-pointer rounded-md border p-2 transition-colors" :class="v.status === 'active' ? 'border-orange-200 text-orange-700 hover:bg-orange-50' : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50'"><PowerOff v-if="v.status === 'active'" class="h-4 w-4" /><Power v-else class="h-4 w-4" /></button></div></div><p v-if="!vehicles.data.length" class="py-4 text-center text-sm text-slate-500">No hay vehiculos con esos filtros.</p></div>

      <div v-if="vehicles.last_page > 1" class="mt-5 flex flex-wrap items-center justify-center gap-1 sm:gap-2">
        <Link v-for="link in vehicles.links" :key="link.label" :href="link.url || '#'" preserve-scroll class="rounded-md border px-2 py-2 text-xs font-semibold sm:px-3 sm:text-sm" :class="[link.active ? 'border-[#123f6e] bg-[#123f6e] text-white' : 'border-slate-200 bg-white text-slate-700', !link.url ? 'pointer-events-none opacity-40' : 'hover:bg-[#edf3fa]']" v-html="link.label" />
      </div>
    </section>
  </AppLayout>
</template>
