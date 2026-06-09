<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Bell, Car, CheckCircle, ClipboardList, MapPin, MessageCircle, PackageCheck, Users } from '@lucide/vue';
const props = defineProps({ stats: Array, recentRequests: Array, role: String, notifications: Array });
const icons = { Bell, Car, CheckCircle, ClipboardList, MapPin, MessageCircle, PackageCheck, Users };
</script>
<template>
  <Head title="Dashboard" />
  <AppLayout title="Dashboard">
    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      <article v-for="card in stats" :key="card.label" class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between"><div><p class="text-sm font-medium text-slate-500">{{ card.label }}</p><p class="mt-2 text-3xl font-semibold text-slate-950">{{ card.value }}</p></div><span class="grid h-11 w-11 place-items-center rounded-md bg-[#e6eef7] text-[#123f6e]"><component :is="icons[card.icon] ?? ClipboardList" class="h-6 w-6" /></span></div>
      </article>
    </section>
    <section class="mt-6 grid gap-6 xl:grid-cols-[1.2fr_.8fr]">
      <div class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex items-center justify-between"><h2 class="text-lg font-semibold text-[#123f6e]">Ultimas solicitudes</h2><ClipboardList class="h-5 w-5 text-slate-400" /></div>
        <div class="overflow-x-auto"><table class="w-full min-w-[680px] text-left text-sm"><thead class="bg-slate-50 text-slate-500"><tr><th class="px-3 py-2">ID</th><th>Vehiculo</th><th>Tecnico</th><th>Conductor</th><th>Estado</th></tr></thead><tbody><tr v-for="item in recentRequests" :key="item.id" class="border-t border-slate-100"><td class="px-3 py-3">#{{ item.id }}</td><td>{{ item.vehicle?.plate ?? '-' }}</td><td>{{ item.technician?.name ?? '-' }}</td><td>{{ item.driver?.name ?? '-' }}</td><td><span class="rounded bg-[#e6eef7] px-2 py-1 text-[#123f6e]">{{ item.status }}</span></td></tr><tr v-if="!recentRequests.length"><td colspan="5" class="px-3 py-8 text-center text-slate-500">Sin solicitudes registradas</td></tr></tbody></table></div>
      </div>
      <div class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex items-center justify-between"><h2 class="text-lg font-semibold text-[#123f6e]">Panel {{ role }}</h2><Bell class="h-5 w-5 text-slate-400" /></div>
        <div class="space-y-3 text-sm text-slate-600"><p>Ubicacion obligatoria activa para tecnicos y conductores.</p><p>GPS: <code>php artisan gps:sync-last-positions</code>.</p><p>Correo SMTP configurado para recuperacion de contraseña y notificaciones.</p></div>
        <div class="mt-5 border-t border-slate-100 pt-4"><h3 class="mb-2 font-semibold text-slate-800">Notificaciones recientes</h3><p v-if="!notifications.length" class="text-sm text-slate-500">Sin notificaciones nuevas.</p><div v-for="n in notifications" :key="n.id" class="rounded bg-slate-50 px-3 py-2 text-sm"><strong>{{ n.title }}</strong><p class="text-slate-500">{{ n.message }}</p></div></div>
      </div>
    </section>
  </AppLayout>
</template>
