<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
const props = defineProps({ users: Object, roles: Array, filters: Object });
const search = ref(props.filters?.search ?? '');
const perPage = ref(props.filters?.per_page ?? 10);
const apply = () => router.get('/usuarios', { search: search.value || undefined, per_page: perPage.value || undefined }, { preserveState: true, replace: true });
const clearFilters = () => { search.value = ''; perPage.value = 10; router.get('/usuarios', {}, { preserveState: true, replace: true }); };
const deactivate = (user) => { if(confirm(`Desactivar usuario ${user.email}?`)) router.delete(`/usuarios/${user.id}`); };
</script>
<template>
  <Head title="Usuarios" />
  <AppLayout title="Usuarios">
    <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-5 grid gap-2 sm:grid-cols-[1fr_120px_auto_auto_auto]">
        <div class="relative"><Search class="absolute left-3 top-3.5 h-5 w-5 text-slate-400" /><input v-model="search" @keyup.enter="apply" class="w-full rounded-md border border-slate-300 py-3 pl-10 pr-3 outline-none focus:border-[#123f6e]" placeholder="Buscar por nombre o correo" /></div>
        <select v-model="perPage" @change="apply" class="w-full rounded-md border border-slate-300 px-3 py-3 text-sm sm:w-auto"><option value="10">10 por pag.</option><option value="25">25 por pag.</option><option value="50">50 por pag.</option><option value="100">100 por pag.</option></select>
        <button @click="apply" class="w-full cursor-pointer rounded-md bg-[#123f6e] px-4 py-3 font-semibold text-white transition-colors hover:bg-[#0e2d52] sm:w-auto">Filtrar</button>
        <button @click="clearFilters" class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-md border border-[#123f6e] px-4 py-3 font-semibold text-[#123f6e] transition-colors hover:bg-[#123f6e] hover:text-white sm:w-auto"><Search class="h-4 w-4" /> Limpiar</button>
        <Link href="/usuarios/create" class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-[#123f6e] px-4 py-3 font-semibold text-white sm:w-auto"><Plus class="h-5 w-5" /> Nuevo usuario</Link>
      </div>
      <div class="hidden sm:block"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-slate-500"><tr><th class="px-3 py-2">Usuario</th><th>Correo</th><th>Telefono</th><th>Cargo</th><th>Rol</th><th>Vehiculo</th><th>Estado</th><th class="text-right">Acciones</th></tr></thead><tbody><tr v-for="user in users.data" :key="user.id" class="border-t border-slate-100"><td class="px-3 py-3 font-medium text-slate-900">{{ user.name }} {{ user.last_name }}</td><td>{{ user.email }}</td><td>{{ user.phone ?? '-' }}</td><td>{{ user.cargo ?? '-' }}</td><td>{{ user.role?.name ?? '-' }}</td><td>{{ user.assigned_vehicle?.plate ?? '-' }}</td><td><span class="rounded px-2 py-1" :class="user.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">{{ user.status }}</span></td><td class="text-right"><Link :href="`/usuarios/${user.id}/edit`" class="mr-2 inline-flex rounded-md border border-slate-200 p-2 text-[#123f6e]"><Pencil class="h-4 w-4" /></Link><button @click="deactivate(user)" class="inline-flex cursor-pointer rounded-md border border-red-200 p-2 text-red-700 transition-colors hover:bg-red-50"><Trash2 class="h-4 w-4" /></button></td></tr></tbody></table></div>
      <div class="space-y-2 sm:hidden"><div v-for="user in users.data" :key="user.id" class="rounded border border-slate-100 bg-slate-50 p-3 text-sm"><div class="mb-2 flex items-start justify-between gap-2"><div><div class="font-medium text-slate-900">{{ user.name }} {{ user.last_name }}</div><div class="text-xs text-slate-500">{{ user.email }}</div></div><span class="shrink-0 rounded px-2 py-1 text-xs" :class="user.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">{{ user.status }}</span></div><div class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-slate-600"><div><span class="font-medium text-slate-700">Telefono:</span> {{ user.phone ?? '-' }}</div><div><span class="font-medium text-slate-700">Cargo:</span> {{ user.cargo ?? '-' }}</div><div><span class="font-medium text-slate-700">Rol:</span> {{ user.role?.name ?? '-' }}</div><div><span class="font-medium text-slate-700">Vehiculo:</span> {{ user.assigned_vehicle?.plate ?? '-' }}</div></div><div class="mt-2 flex gap-2"><Link :href="`/usuarios/${user.id}/edit`" class="inline-flex rounded-md border border-slate-200 p-2 text-[#123f6e]"><Pencil class="h-4 w-4" /></Link><button @click="deactivate(user)" class="inline-flex cursor-pointer rounded-md border border-red-200 p-2 text-red-700 transition-colors hover:bg-red-50"><Trash2 class="h-4 w-4" /></button></div></div><p v-if="!users.data.length" class="py-4 text-center text-sm text-slate-500">Sin usuarios.</p></div>
      <div v-if="users.last_page > 1" class="mt-5 flex flex-wrap items-center justify-center gap-1 sm:gap-2"><Link v-for="link in users.links" :key="link.label" :href="link.url || '#'" preserve-scroll class="rounded-md border px-2 py-2 text-xs font-semibold sm:px-3 sm:text-sm" :class="[link.active ? 'border-[#123f6e] bg-[#123f6e] text-white' : 'border-slate-200 bg-white text-slate-700', !link.url ? 'pointer-events-none opacity-40' : 'hover:bg-[#edf3fa]']" v-html="link.label" /></div>
    </section>
  </AppLayout>
</template>
