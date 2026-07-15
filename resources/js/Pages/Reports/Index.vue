<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Download, FileSpreadsheet, Filter } from '@lucide/vue';
import { computed, reactive, watch } from 'vue';

const props = defineProps({ reports: Array, filters: Object, vehicles: Array, users: Array, categories: Array });
const form = reactive({
  type: props.filters?.type || 'vehicles',
  date_from: props.filters?.date_from || '',
  date_to: props.filters?.date_to || '',
  vehicle_id: props.filters?.vehicle_id || '',
  user_id: props.filters?.user_id || '',
  category_id: props.filters?.category_id || '',
  status: props.filters?.status || '',
});

const currentReport = computed(() => props.reports.find((report) => report.key === form.type) || props.reports[0]);
const statusFilters = {
  vehicles: { label: 'Estado', options: ['active', 'inactive', 'maintenance'] },
  users: { label: 'Estado', options: ['active', 'inactive'] },
  technicians: { label: 'Estado', options: ['active', 'inactive'] },
  drivers: { label: 'Estado', options: ['active', 'inactive'] },
  requests: { label: 'Estado', options: ['pendiente', 'aceptada', 'rechazada', 'vencida', 'en_camino', 'entregada', 'en_uso', 'para_recoger', 'recogida', 'finalizada', 'cancelada'] },
  inventory: { label: 'Estado herramienta', options: ['active', 'inactive'] },
  movements: { label: 'Tipo de movimiento', options: ['stock_update', 'reserved', 'released', 'delivered', 'returned'] },
  audit: { label: 'Modulo', options: ['dashboard', 'mapa', 'solicitudes', 'chat', 'notificaciones', 'inventario', 'vehiculos', 'reportes', 'usuarios', 'roles', 'auditoria', 'perfil', 'configuracion_gps'] },
  activity: { label: 'Estado nuevo', options: ['pendiente', 'aceptada', 'rechazada', 'vencida', 'en_camino', 'entregada', 'en_uso', 'para_recoger', 'recogida', 'finalizada', 'cancelada'] },
};
const statusFilter = computed(() => statusFilters[form.type] ?? null);
const exportUrl = computed(() => {
  const params = new URLSearchParams();
  Object.entries(form).forEach(([key, value]) => { if (value) params.set(key, value); });
  return `/reportes/export?${params.toString()}`;
});
watch(() => form.type, () => {
  if (!statusFilter.value?.options.includes(form.status)) form.status = '';
}, { immediate: true });
</script>

<template>
  <Head title="Reportes" />
  <AppLayout title="Reportes">
    <section class="grid gap-5 xl:grid-cols-[360px_1fr]">
      <aside class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-4 flex items-center gap-2 text-lg font-semibold text-[#123f6e]"><Filter class="h-5 w-5" /> Filtros</h2>
        <div class="space-y-3">
          <label class="block text-sm font-medium text-slate-700">Reporte</label>
          <select v-model="form.type" class="w-full rounded-md border border-slate-300 px-3 py-3">
            <option v-for="report in reports" :key="report.key" :value="report.key">{{ report.name }}</option>
          </select>
          <div class="grid grid-cols-2 gap-3">
            <div><label class="block text-sm font-medium text-slate-700">Desde</label><input v-model="form.date_from" type="date" class="w-full rounded-md border border-slate-300 px-3 py-3" /></div>
            <div><label class="block text-sm font-medium text-slate-700">Hasta</label><input v-model="form.date_to" type="date" class="w-full rounded-md border border-slate-300 px-3 py-3" /></div>
          </div>
          <div><label class="block text-sm font-medium text-slate-700">Vehiculo</label><select v-model="form.vehicle_id" class="w-full rounded-md border border-slate-300 px-3 py-3"><option value="">Todos</option><option v-for="vehicle in vehicles" :key="vehicle.id" :value="vehicle.id">{{ vehicle.plate }}</option></select></div>
          <div><label class="block text-sm font-medium text-slate-700">Usuario</label><select v-model="form.user_id" class="w-full rounded-md border border-slate-300 px-3 py-3"><option value="">Todos</option><option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }} {{ user.last_name }} - {{ user.role?.name }}</option></select></div>
          <div><label class="block text-sm font-medium text-slate-700">Categoria</label><select v-model="form.category_id" class="w-full rounded-md border border-slate-300 px-3 py-3"><option value="">Todas</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select></div>
          <div v-if="statusFilter"><label class="block text-sm font-medium text-slate-700">{{ statusFilter.label }}</label><select v-model="form.status" class="w-full rounded-md border border-slate-300 px-3 py-3"><option value="">Todos</option><option v-for="status in statusFilter.options" :key="status" :value="status">{{ status }}</option></select></div>
          <a :href="exportUrl" class="flex w-full items-center justify-center gap-2 rounded-md bg-[#123f6e] px-4 py-3 font-semibold text-white"><Download class="h-5 w-5" /> Exportar XLSX</a>
        </div>
      </aside>

      <div class="space-y-5">
        <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
          <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
              <h2 class="flex items-center gap-2 text-xl font-semibold text-[#123f6e]"><FileSpreadsheet class="h-6 w-6" /> {{ currentReport?.name }}</h2>
              <p class="mt-1 text-sm text-slate-600">{{ currentReport?.description }}</p>
            </div>
            <span class="rounded bg-[#e6eef7] px-3 py-2 text-sm font-semibold text-[#123f6e]">Formato Excel</span>
          </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <article v-for="report in reports" :key="report.key" class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-2 flex items-center justify-between gap-3"><h3 class="font-semibold text-slate-950">{{ report.name }}</h3><FileSpreadsheet class="h-5 w-5 text-[#8aa12b]" /></div>
            <p class="text-sm leading-6 text-slate-600">{{ report.description }}</p>
            <button @click="form.type = report.key" class="mt-4 rounded-md border border-[#123f6e] px-3 py-2 text-sm font-semibold text-[#123f6e] hover:bg-[#edf3fa]">Seleccionar</button>
          </article>
        </section>
      </div>
    </section>
  </AppLayout>
</template>
