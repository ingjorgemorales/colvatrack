<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Bell, Car, CheckCircle, ClipboardList, MapPin, MessageCircle, PackageCheck, Users } from '@lucide/vue';
const props = defineProps({ stats: Array, recentRequests: Array, role: String, notifications: Array });
const icons = { Bell, Car, CheckCircle, ClipboardList, MapPin, MessageCircle, PackageCheck, Users };
const statusLabels = { pendiente:'Pendiente', aceptada:'Aceptada', rechazada:'Rechazada', en_camino:'En camino', entregada:'Entregada', en_uso:'En uso', recogida:'Recogida', finalizada:'Finalizada', cancelada:'Cancelada' };
const statusClasses = { pendiente:'bg-amber-50 text-amber-800', aceptada:'bg-blue-50 text-blue-800', en_camino:'bg-sky-50 text-sky-800', entregada:'bg-emerald-50 text-emerald-800', en_uso:'bg-indigo-50 text-indigo-800', recogida:'bg-slate-100 text-slate-800', finalizada:'bg-emerald-100 text-emerald-900', rechazada:'bg-red-50 text-red-800', cancelada:'bg-red-50 text-red-800' };
const iconColors = {
  ClipboardList: 'bg-indigo-100 text-indigo-700',
  CheckCircle: 'bg-emerald-100 text-emerald-700',
  MapPin: 'bg-sky-100 text-sky-700',
  PackageCheck: 'bg-teal-100 text-teal-700',
  MessageCircle: 'bg-violet-100 text-violet-700',
  Car: 'bg-amber-100 text-amber-700',
  Bell: 'bg-rose-100 text-rose-700',
  Users: 'bg-slate-100 text-slate-700',
};
</script>
<template>
  <Head title="Dashboard" />
  <AppLayout title="Dashboard">
    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      <Link v-for="card in stats" :key="card.label" :href="card.route || '#'" class="rounded-md border border-slate-200 bg-white p-5 shadow-sm transition-colors hover:bg-[#edf3fa] cursor-pointer">
        <div class="flex items-center justify-between"><div><p class="text-sm font-medium text-slate-500">{{ card.label }}</p><p class="mt-2 text-3xl font-semibold text-slate-950">{{ card.value }}</p></div><span class="grid h-11 w-11 place-items-center rounded-md" :class="iconColors[card.icon] ?? 'bg-[#e6eef7] text-[#123f6e]'"><component :is="icons[card.icon] ?? ClipboardList" class="h-6 w-6" /></span></div>
      </Link>
    </section>
    <section class="mt-6 grid gap-6 xl:grid-cols-[1.2fr_.8fr]">
      <div class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex items-center justify-between"><h2 class="text-lg font-semibold text-[#123f6e]">Ultimas solicitudes</h2><ClipboardList class="h-5 w-5 text-slate-400" /></div>
        <div class="hidden sm:block"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-slate-500"><tr><th class="px-3 py-2">ID</th><th>Vehiculo</th><th>Tecnico</th><th>Conductor</th><th>Estado</th></tr></thead><tbody><tr v-for="item in recentRequests" :key="item.id" class="border-t border-slate-100"><td class="px-3 py-3">#{{ item.id }}</td><td>{{ item.vehicle?.plate ?? '-' }}</td><td>{{ item.technician?.name ?? '-' }}</td><td>{{ item.driver?.name ?? '-' }}</td><td><span class="rounded px-2 py-1" :class="statusClasses[item.status] ?? 'bg-slate-100 text-slate-600'">{{ statusLabels[item.status] ?? item.status }}</span></td></tr><tr v-if="!recentRequests.length"><td colspan="5" class="px-3 py-8 text-center text-slate-500">Sin solicitudes registradas</td></tr></tbody></table></div>
        <div class="space-y-2 sm:hidden"><div v-for="item in recentRequests" :key="item.id" class="rounded border border-slate-100 bg-slate-50 p-3 text-sm"><div class="mb-1 flex items-start justify-between gap-2"><div class="font-semibold">#{{ item.id }} - <span class="text-slate-950">{{ item.vehicle?.plate ?? '-' }}</span></div><span class="shrink-0 rounded px-2 py-1 text-xs" :class="statusClasses[item.status] ?? 'bg-slate-100 text-slate-600'">{{ statusLabels[item.status] ?? item.status }}</span></div><div class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-slate-600"><div><span class="font-medium text-slate-700">Tecnico:</span> {{ item.technician?.name ?? '-' }}</div><div><span class="font-medium text-slate-700">Conductor:</span> {{ item.driver?.name ?? '-' }}</div></div></div><p v-if="!recentRequests.length" class="py-4 text-center text-sm text-slate-500">Sin solicitudes registradas</p></div>
      </div>
      <div class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex items-center justify-between"><h2 class="text-lg font-semibold text-[#123f6e]">Panel {{ role }}</h2><Bell class="h-5 w-5 text-slate-400" /></div>
        <div class="mt-5 border-t border-slate-100 pt-4"><h3 class="mb-2 font-semibold text-slate-800">Notificaciones recientes</h3><p v-if="!notifications.length" class="text-sm text-slate-500">Sin notificaciones nuevas.</p><div v-for="n in notifications" :key="n.id" class="rounded bg-slate-50 px-3 py-2 text-sm"><strong>{{ n.title }}</strong><p class="text-slate-500">{{ n.message }}</p></div></div>
      </div>
    </section>
  </AppLayout>
</template>
