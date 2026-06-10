<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Search, ShieldCheck } from '@lucide/vue';
import { reactive } from 'vue';

const props = defineProps({ logs: Object, filters: Object, users: Array, modules: Array, actions: Array });
const form = reactive({
  module: props.filters?.module || '',
  action: props.filters?.action || '',
  user_id: props.filters?.user_id || '',
  date_from: props.filters?.date_from || '',
  date_to: props.filters?.date_to || '',
});
function search() {
  router.get('/auditoria', form, { preserveState: true, replace: true });
}
function clear() {
  Object.keys(form).forEach((key) => { form[key] = ''; });
  search();
}
function shortJson(value) {
  if (!value) return '-';
  const text = JSON.stringify(value);
  return text.length > 120 ? `${text.slice(0, 120)}...` : text;
}
</script>

<template>
  <Head title="Auditoria" />
  <AppLayout title="Auditoria">
    <section class="space-y-5">
      <form class="rounded-md border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="search">
        <h2 class="mb-4 flex items-center gap-2 text-lg font-semibold text-[#123f6e]"><Search class="h-5 w-5" /> Filtros</h2>
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
          <select v-model="form.module" class="rounded-md border border-slate-300 px-3 py-3"><option value="">Modulo</option><option v-for="module in modules" :key="module" :value="module">{{ module }}</option></select>
          <select v-model="form.action" class="rounded-md border border-slate-300 px-3 py-3"><option value="">Accion</option><option v-for="action in actions" :key="action" :value="action">{{ action }}</option></select>
          <select v-model="form.user_id" class="rounded-md border border-slate-300 px-3 py-3"><option value="">Usuario</option><option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }} {{ user.last_name }}</option></select>
          <input v-model="form.date_from" type="date" class="rounded-md border border-slate-300 px-3 py-3" />
          <input v-model="form.date_to" type="date" class="rounded-md border border-slate-300 px-3 py-3" />
        </div>
        <div class="mt-4 flex gap-3"><button class="cursor-pointer rounded-md bg-[#123f6e] px-4 py-3 font-semibold text-white transition-colors hover:bg-[#0e2d52]">Buscar</button><button type="button" class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-md border border-[#123f6e] px-4 py-3 font-semibold text-[#123f6e] transition-colors hover:bg-[#123f6e] hover:text-white" @click="clear">Limpiar</button></div>
      </form>

      <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex items-center justify-between gap-3"><h2 class="flex items-center gap-2 text-lg font-semibold text-[#123f6e]"><ShieldCheck class="h-5 w-5" /> Registro de acciones</h2><span class="text-sm text-slate-500">{{ logs.total }} eventos</span></div>
        <div class="overflow-x-auto">
          <table class="w-full min-w-[1040px] text-left text-sm">
            <thead class="bg-slate-50 text-slate-500"><tr><th class="px-3 py-2">Fecha</th><th>Usuario</th><th>Accion</th><th>Modulo</th><th>Descripcion</th><th>IP</th><th>Datos</th></tr></thead>
            <tbody>
              <tr v-for="log in logs.data" :key="log.id" class="border-t border-slate-100 align-top">
                <td class="px-3 py-3 text-slate-600">{{ log.created_at }}</td>
                <td class="py-3"><div class="font-medium text-slate-900">{{ log.user?.name ?? 'Sistema' }} {{ log.user?.last_name ?? '' }}</div><div class="text-xs text-slate-500">{{ log.user?.email }}</div></td>
                <td class="py-3"><span class="rounded bg-[#e6eef7] px-2 py-1 font-semibold text-[#123f6e]">{{ log.action }}</span></td>
                <td class="py-3 text-slate-700">{{ log.module }}</td>
                <td class="py-3 text-slate-600">{{ log.description }}</td>
                <td class="py-3 text-slate-600">{{ log.ip_address }}</td>
                <td class="py-3 text-xs text-slate-500">{{ shortJson(log.new_values) }}</td>
              </tr>
              <tr v-if="!logs.data.length"><td colspan="7" class="px-3 py-8 text-slate-500">No hay registros con esos filtros.</td></tr>
            </tbody>
          </table>
        </div>
        <div class="mt-4 flex items-center justify-between text-sm">
          <Link v-if="logs.prev_page_url" :href="logs.prev_page_url" class="rounded-md border border-slate-300 px-3 py-2 font-semibold text-slate-700">Anterior</Link><span v-else></span>
          <span class="text-slate-500">Pagina {{ logs.current_page }} de {{ logs.last_page }}</span>
          <Link v-if="logs.next_page_url" :href="logs.next_page_url" class="rounded-md border border-slate-300 px-3 py-2 font-semibold text-slate-700">Siguiente</Link><span v-else></span>
        </div>
      </section>
    </section>
  </AppLayout>
</template>
