<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Bell } from '@lucide/vue';
const props = defineProps({ notifications: Object, filters: Object });
import { ref } from 'vue';
const perPage = ref(props.filters?.per_page ?? 10);
const changePerPage = () => router.get('/notificaciones', { per_page: perPage.value }, { preserveState: true, replace: true });
function href(n){ return n.data_json?.tool_request_id ? `/solicitudes/${n.data_json.tool_request_id}` : '#'; }
</script>
<template>
  <Head title="Notificaciones" />
  <AppLayout title="Notificaciones">
    <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-5 flex flex-wrap items-center justify-between gap-3"><h2 class="flex items-center gap-2 text-lg font-semibold text-[#123f6e]"><Bell class="h-5 w-5" /> Centro de notificaciones</h2><select v-model="perPage" @change="changePerPage" class="rounded-md border border-slate-300 px-3 py-2 text-sm"><option value="10">10 por pag.</option><option value="25">25 por pag.</option><option value="50">50 por pag.</option><option value="100">100 por pag.</option></select></div>
      <div class="space-y-3"><component :is="href(n) !== '#' ? Link : 'article'" v-for="n in notifications.data" :key="n.id" :href="href(n) !== '#' ? href(n) : undefined" class="block rounded-md border p-4 transition-colors" :class="[n.read_at ? 'border-slate-200 bg-white' : 'border-[#123f6e]/30 bg-[#e6eef7]', href(n) !== '#' ? 'hover:border-[#123f6e]/50 hover:bg-[#edf3fa]' : '']"><h3 class="font-semibold text-slate-950">{{ n.title }}</h3><p class="text-sm text-slate-600">{{ n.message }}</p><p class="mt-1 text-xs text-slate-400">{{ n.created_at }}</p></component><p v-if="!notifications.data.length" class="py-8 text-center text-sm text-slate-500">Sin notificaciones.</p></div>
      <div v-if="notifications.last_page > 1" class="mt-5 flex flex-wrap items-center justify-center gap-1 sm:gap-2"><Link v-for="link in notifications.links" :key="link.label" :href="link.url || '#'" preserve-scroll class="rounded-md border px-2 py-2 text-xs font-semibold sm:px-3 sm:text-sm" :class="[link.active ? 'border-[#123f6e] bg-[#123f6e] text-white' : 'border-slate-200 bg-white text-slate-700', !link.url ? 'pointer-events-none opacity-40' : 'hover:bg-[#edf3fa]']" v-html="link.label" /></div>
    </section>
  </AppLayout>
</template>
