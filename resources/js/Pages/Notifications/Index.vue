<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Bell, CheckCheck } from '@lucide/vue';
const props = defineProps({ notifications: Object });
const read = (n) => router.patch(`/notificaciones/${n.id}/read`, {}, { preserveScroll: true });
const readAll = () => router.patch('/notificaciones/read-all', {}, { preserveScroll: true });
function href(n){ return n.data_json?.tool_request_id ? `/solicitudes/${n.data_json.tool_request_id}` : '#'; }
</script>
<template>
  <Head title="Notificaciones" />
  <AppLayout title="Notificaciones">
    <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-5 flex items-center justify-between"><h2 class="flex items-center gap-2 text-lg font-semibold text-[#123f6e]"><Bell class="h-5 w-5" /> Centro de notificaciones</h2><button @click="readAll" class="inline-flex items-center gap-2 rounded-md border border-[#123f6e] px-3 py-2 font-semibold text-[#123f6e]"><CheckCheck class="h-4 w-4" /> Marcar todas</button></div>
      <div class="space-y-3"><article v-for="n in notifications.data" :key="n.id" class="rounded-md border p-4" :class="n.read_at ? 'border-slate-200 bg-white' : 'border-[#123f6e]/30 bg-[#e6eef7]'"><div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><h3 class="font-semibold text-slate-950">{{ n.title }}</h3><p class="text-sm text-slate-600">{{ n.message }}</p><p class="mt-1 text-xs text-slate-400">{{ n.created_at }}</p></div><div class="flex gap-2"><Link v-if="href(n) !== '#'" :href="href(n)" class="rounded-md bg-[#123f6e] px-3 py-2 text-sm font-semibold text-white">Abrir</Link><button v-if="!n.read_at" @click="read(n)" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700">Leida</button></div></div></article><p v-if="!notifications.data.length" class="py-8 text-center text-sm text-slate-500">Sin notificaciones.</p></div>
    </section>
  </AppLayout>
</template>
