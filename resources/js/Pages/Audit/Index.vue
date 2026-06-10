<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Search, ShieldCheck } from '@lucide/vue';
import { reactive, ref } from 'vue';

const props = defineProps({ logs: Object, filters: Object, users: Array, modules: Array, actions: Array });
const perPage = ref(props.filters?.per_page ?? 10);
const form = reactive({
  module: props.filters?.module || '',
  action: props.filters?.action || '',
  user_id: props.filters?.user_id || '',
  date_from: props.filters?.date_from || '',
  date_to: props.filters?.date_to || '',
});
function search() {
  router.get('/auditoria', { ...form, per_page: perPage.value }, { preserveState: true, replace: true });
}
function clear() {
  Object.keys(form).forEach((key) => { form[key] = ''; });
  perPage.value = 10;
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
      <form class="rounded-md border border-slate-200 bg-white p-4 shadow-sm sm:p-5" @submit.prevent="search">
        <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-[#123f6e] sm:text-lg"><Search class="h-5 w-5" /> Filtros</h2>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
          <select v-model="form.module" class="rounded-md border border-slate-300 px-3 py-3"><option value="">Modulo</option><option v-for="module in modules" :key="module" :value="module">{{ module }}</option></select>
          <select v-model="form.action" class="rounded-md border border-slate-300 px-3 py-3"><option value="">Accion</option><option v-for="action in actions" :key="action" :value="action">{{ action }}</option></select>
          <select v-model="form.user_id" class="rounded-md border border-slate-300 px-3 py-3"><option value="">Usuario</option><option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }} {{ user.last_name }}</option></select>
          <input v-model="form.date_from" type="date" class="rounded-md border border-slate-300 px-3 py-3" />
          <input v-model="form.date_to" type="date" class="rounded-md border border-slate-300 px-3 py-3" />
        </div>
        <div class="mt-4 grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center sm:gap-3"><select v-model="perPage" @change="search" class="col-span-2 w-full rounded-md border border-slate-300 px-3 py-3 text-sm sm:w-auto"><option value="10">10 por pag.</option><option value="25">25 por pag.</option><option value="50">50 por pag.</option><option value="100">100 por pag.</option></select><button class="w-full cursor-pointer rounded-md bg-[#123f6e] px-4 py-3 font-semibold text-white transition-colors hover:bg-[#0e2d52] sm:w-auto">Buscar</button><button type="button" class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-md border border-[#123f6e] px-4 py-3 font-semibold text-[#123f6e] transition-colors hover:bg-[#123f6e] hover:text-white sm:w-auto" @click="clear">Limpiar</button></div>
      </form>

      <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3"><h2 class="flex items-center gap-2 text-base font-semibold text-[#123f6e] sm:text-lg"><ShieldCheck class="h-5 w-5" /> Registro de acciones</h2><span class="text-sm text-slate-500">{{ logs.total }} eventos</span></div>
        <div class="hidden sm:block">
          <table class="w-full text-left text-sm">
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
        <div class="space-y-2 sm:hidden"><div v-for="log in logs.data" :key="log.id" class="rounded border border-slate-200 bg-white p-3 text-sm shadow-sm"><div class="mb-1 flex items-start justify-between gap-2"><div><div class="font-medium text-slate-900">{{ log.user?.name ?? 'Sistema' }} {{ log.user?.last_name ?? '' }}</div><div class="text-xs text-slate-500">{{ log.user?.email }}</div></div><span class="shrink-0 rounded bg-[#e6eef7] px-2 py-1 text-xs font-semibold text-[#123f6e]">{{ log.action }}</span></div><div class="text-xs text-slate-500 mb-1">{{ log.created_at }}</div><div class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-slate-600"><div><span class="font-medium text-slate-700">Modulo:</span> {{ log.module }}</div><div><span class="font-medium text-slate-700">IP:</span> {{ log.ip_address }}</div></div><p class="mt-1 text-xs text-slate-600">{{ log.description }}</p><p v-if="log.new_values" class="mt-1 text-xs text-slate-400">{{ shortJson(log.new_values) }}</p></div><p v-if="!logs.data.length" class="py-4 text-center text-sm text-slate-500">No hay registros con esos filtros.</p></div>
        <div v-if="logs.last_page > 1" class="mt-4 flex flex-wrap items-center justify-center gap-1 sm:gap-2"><Link v-for="link in logs.links" :key="link.label" :href="link.url || '#'" preserve-scroll class="rounded-md border px-2 py-2 text-xs font-semibold sm:px-3 sm:text-sm" :class="[link.active ? 'border-[#123f6e] bg-[#123f6e] text-white' : 'border-slate-200 bg-white text-slate-700', !link.url ? 'pointer-events-none opacity-40' : 'hover:bg-[#edf3fa]']" v-html="link.label" /></div>
      </section>
    </section>
  </AppLayout>
</template>
