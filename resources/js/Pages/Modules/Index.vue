<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
const props = defineProps({ module: String, records: [Object, Array] });
const titles = { solicitudes:'Solicitudes', notificaciones:'Notificaciones', inventario:'Inventario', vehiculos:'Vehiculos', reportes:'Reportes', usuarios:'Usuarios', roles:'Roles y permisos', auditoria:'Auditoria', perfil:'Perfil', 'configuracion-gps':'Configuracion de proveedores GPS' };
const rows = Array.isArray(props.records?.data) ? props.records.data : (Array.isArray(props.records) ? props.records : props.records?.data ?? []);
const columns = rows.length ? Object.keys(rows[0]).filter(k => !['permissions','role','driver','technician','vehicle','assigned_vehicle','inventory'].includes(k)).slice(0,8) : [];
</script>
<template>
  <Head :title="titles[module] ?? module" />
  <AppLayout :title="titles[module] ?? module">
    <section v-if="module === 'reportes'" class="rounded-md border border-slate-200 bg-white p-6 shadow-sm"><h2 class="mb-2 text-lg font-semibold text-[#123f6e]">Exportaciones</h2><a href="/api/reports/export" class="inline-flex rounded-md bg-[#123f6e] px-4 py-3 font-semibold text-white">Exportar vehiculos XLSX</a></section>
    <section v-else class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-4 flex items-center justify-between"><h2 class="text-lg font-semibold text-[#123f6e]">Registros</h2><span class="text-sm text-slate-500">{{ rows.length }} visibles</span></div>
      <div class="hidden sm:block"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-slate-500"><tr><th v-for="col in columns" :key="col" class="px-3 py-2 capitalize">{{ col.replaceAll('_',' ') }}</th></tr></thead><tbody><tr v-for="row in rows" :key="row.id ?? row.email" class="border-t border-slate-100"><td v-for="col in columns" :key="col" class="px-3 py-3">{{ row[col] ?? '-' }}</td></tr><tr v-if="!rows.length"><td class="px-3 py-8 text-slate-500">Sin registros todavia.</td></tr></tbody></table></div>
      <div class="space-y-2 sm:hidden"><div v-for="row in rows" :key="row.id ?? row.email" class="rounded border border-slate-100 bg-slate-50 p-3 text-sm"><div v-for="col in columns" :key="col" class="flex justify-between gap-2 border-b border-slate-100 py-1 last:border-0"><span class="text-xs font-medium text-slate-700 capitalize">{{ col.replaceAll('_',' ') }}:</span><span class="text-xs text-right text-slate-900">{{ row[col] ?? '-' }}</span></div></div><p v-if="!rows.length" class="py-4 text-center text-sm text-slate-500">Sin registros todavia.</p></div>
    </section>
  </AppLayout>
</template>

