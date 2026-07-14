<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Bell } from '@lucide/vue';
import axios from 'axios';
const props = defineProps({ notifications: Object, filters: Object });
import { ref, watch } from 'vue';
const perPage = ref(props.filters?.per_page ?? 10);
const rows = ref([...(props.notifications?.data ?? [])]);
const changePerPage = () => router.get('/notificaciones', { per_page: perPage.value }, { preserveState: true, replace: true });
watch(() => props.notifications?.data, (data) => { rows.value = [...(data ?? [])]; });
function href(n) {
  if (n.url) return n.url;
  if (['tool_request', 'tool_request_status', 'chat'].includes(n.type) && n.data_json?.tool_request_id) return `/solicitudes/${n.data_json.tool_request_id}`;
  if (n.type === 'gps_stale_summary') return '/mapa';
  if (n.type === 'request_delay_summary') return '/solicitudes';
  if (n.type === 'low_stock_summary') return '/inventario';
  return null;
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
async function openNotification(n) {
  const target = href(n);

  if (!n.read_at) {
    n.read_at = new Date().toISOString();
    await axios.patch(`/api/notifications/${n.id}/read`).catch(() => {});
  }

  if (target) router.visit(target);
}
</script>
<template>
  <Head title="Notificaciones" />
  <AppLayout title="Notificaciones">
    <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-5 flex flex-wrap items-center justify-between gap-3"><h2 class="flex items-center gap-2 text-lg font-semibold text-[#123f6e]"><Bell class="h-5 w-5" /> Centro de notificaciones</h2><select v-model="perPage" @change="changePerPage" class="rounded-md border border-slate-300 px-3 py-2 text-sm"><option value="10">10 por pag.</option><option value="25">25 por pag.</option><option value="50">50 por pag.</option><option value="100">100 por pag.</option></select></div>
      <div class="space-y-3"><button v-for="n in rows" :key="n.id" type="button" @click="openNotification(n)" class="block w-full cursor-pointer rounded-md border p-4 text-left transition-colors" :class="[n.read_at ? 'border-slate-200 bg-white' : 'border-[#123f6e]/30 bg-[#e6eef7]', href(n) ? 'hover:border-[#123f6e]/50 hover:bg-[#edf3fa]' : 'hover:bg-slate-50']"><h3 class="font-semibold text-slate-950">{{ n.title }}</h3><p class="text-sm text-slate-600">{{ n.message }}</p><p class="mt-1 text-xs text-slate-400">{{ formatBogota(n.created_at) }}</p></button><p v-if="!rows.length" class="py-8 text-center text-sm text-slate-500">Sin notificaciones.</p></div>
      <div v-if="notifications.last_page > 1" class="mt-5 flex flex-wrap items-center justify-center gap-1 sm:gap-2"><Link v-for="link in notifications.links" :key="link.label" :href="link.url || '#'" preserve-scroll class="rounded-md border px-2 py-2 text-xs font-semibold sm:px-3 sm:text-sm" :class="[link.active ? 'border-[#123f6e] bg-[#123f6e] text-white' : 'border-slate-200 bg-white text-slate-700', !link.url ? 'pointer-events-none opacity-40' : 'hover:bg-[#edf3fa]']" v-html="link.label" /></div>
    </section>
  </AppLayout>
</template>
