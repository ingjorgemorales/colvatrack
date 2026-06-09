<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Eye, Plus, Search, X } from '@lucide/vue';
import { ref, watch } from 'vue';

const props = defineProps({ requests: Object, role: String, filters: Object });
const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? '');
const priority = ref(props.filters?.priority ?? '');
let timer;

const labels = { pendiente:'Pendiente', aceptada:'Aceptada', rechazada:'Rechazada', en_camino:'En camino', entregada:'Entregada', en_uso:'En uso', recogida:'Recogida', finalizada:'Finalizada', cancelada:'Cancelada' };
const statusClasses = { pendiente:'bg-amber-50 text-amber-800', aceptada:'bg-blue-50 text-blue-800', en_camino:'bg-sky-50 text-sky-800', entregada:'bg-emerald-50 text-emerald-800', en_uso:'bg-indigo-50 text-indigo-800', recogida:'bg-slate-100 text-slate-800', finalizada:'bg-emerald-100 text-emerald-900', rechazada:'bg-red-50 text-red-800', cancelada:'bg-red-50 text-red-800' };
function statusLabel(value) { return labels[value] ?? value; }
function applyFilters() {
  router.get('/solicitudes', { search: search.value || undefined, status: status.value || undefined, priority: priority.value || undefined }, { preserveState: true, preserveScroll: true, replace: true });
}
function clearFilters() {
  search.value = '';
  status.value = '';
  priority.value = '';
  router.get('/solicitudes', {}, { preserveState: true, preserveScroll: true, replace: true });
}
watch([search, status, priority], () => {
  window.clearTimeout(timer);
  timer = window.setTimeout(applyFilters, 350);
});
</script>
<template>
  <Head title="Solicitudes" />
  <AppLayout title="Solicitudes">
    <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-5 flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
        <div class="grid gap-3 md:grid-cols-[1fr_180px_180px_auto] xl:min-w-[780px]">
          <label class="relative"><Search class="pointer-events-none absolute left-3 top-3.5 h-4 w-4 text-slate-400" /><input v-model="search" class="w-full rounded-md border border-slate-300 py-3 pl-10 pr-3" placeholder="Buscar ID, placa o usuario" /></label>
          <select v-model="status" class="rounded-md border border-slate-300 px-3 py-3"><option value="">Todos los estados</option><option value="pendiente">Pendiente</option><option value="aceptada">Aceptada</option><option value="en_camino">En camino</option><option value="entregada">Entregada</option><option value="en_uso">En uso</option><option value="recogida">Recogida</option><option value="finalizada">Finalizada</option><option value="rechazada">Rechazada</option><option value="cancelada">Cancelada</option></select>
          <select v-model="priority" class="rounded-md border border-slate-300 px-3 py-3"><option value="">Todas las prioridades</option><option value="baja">Baja</option><option value="normal">Normal</option><option value="alta">Alta</option><option value="critica">Critica</option></select>
          <button @click="clearFilters" class="inline-flex items-center justify-center gap-2 rounded-md border border-slate-300 px-3 py-3 font-semibold text-slate-700"><X class="h-4 w-4" /> Limpiar</button>
        </div>
        <Link v-if="role !== 'Conductor'" href="/solicitudes/create" class="inline-flex items-center justify-center gap-2 rounded-md bg-[#123f6e] px-4 py-3 font-semibold text-white"><Plus class="h-5 w-5" /> Nueva solicitud</Link>
      </div>
      <div class="overflow-x-auto"><table class="w-full min-w-[980px] text-left text-sm"><thead class="bg-slate-50 text-slate-500"><tr><th class="px-3 py-2">ID</th><th>Vehiculo</th><th>Tecnico</th><th>Conductor</th><th>Prioridad</th><th>Estado</th><th>Solicitada</th><th>Herramientas</th><th class="text-right">Acciones</th></tr></thead><tbody><tr v-for="r in requests.data" :key="r.id" class="border-t border-slate-100"><td class="px-3 py-3 font-semibold">#{{ r.id }}</td><td class="font-semibold text-slate-950">{{ r.vehicle?.plate }}</td><td>{{ r.technician?.name }}</td><td>{{ r.driver?.name ?? '-' }}</td><td class="capitalize">{{ r.priority }}</td><td><span class="rounded px-2 py-1 font-semibold" :class="statusClasses[r.status] ?? 'bg-slate-100 text-slate-700'">{{ statusLabel(r.status) }}</span></td><td>{{ r.requested_at ?? '-' }}</td><td>{{ r.items?.length ?? 0 }}</td><td class="text-right"><Link :href="`/solicitudes/${r.id}`" class="inline-flex rounded-md border border-slate-200 p-2 text-[#123f6e]"><Eye class="h-4 w-4" /></Link></td></tr><tr v-if="!requests.data.length"><td colspan="9" class="px-3 py-8 text-center text-slate-500">Sin solicitudes registradas.</td></tr></tbody></table></div>
    </section>
  </AppLayout>
</template>