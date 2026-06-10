<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
const props = defineProps({ users: Object, roles: Array });
const search = ref('');
const rows = computed(() => (props.users.data ?? []).filter(u => `${u.name} ${u.last_name ?? ''} ${u.email}`.toLowerCase().includes(search.value.toLowerCase())));
const deactivate = (user) => { if(confirm(`Desactivar usuario ${user.email}?`)) router.delete(`/usuarios/${user.id}`); };
</script>
<template>
  <Head title="Usuarios" />
  <AppLayout title="Usuarios">
    <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="relative max-w-md flex-1"><Search class="absolute left-3 top-3.5 h-5 w-5 text-slate-400" /><input v-model="search" class="w-full rounded-md border border-slate-300 py-3 pl-10 pr-3 outline-none focus:border-[#123f6e]" placeholder="Buscar por nombre o correo" /></div>
        <Link href="/usuarios/create" class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-[#123f6e] px-4 py-3 font-semibold text-white sm:w-auto"><Plus class="h-5 w-5" /> Nuevo usuario</Link>
      </div>
      <div class="hidden sm:block"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-slate-500"><tr><th class="px-3 py-2">Usuario</th><th>Correo</th><th>Telefono</th><th>Cargo</th><th>Rol</th><th>Vehiculo</th><th>Estado</th><th class="text-right">Acciones</th></tr></thead><tbody><tr v-for="user in rows" :key="user.id" class="border-t border-slate-100"><td class="px-3 py-3 font-medium text-slate-900">{{ user.name }} {{ user.last_name }}</td><td>{{ user.email }}</td><td>{{ user.phone ?? '-' }}</td><td>{{ user.cargo ?? '-' }}</td><td>{{ user.role?.name ?? '-' }}</td><td>{{ user.assigned_vehicle?.plate ?? '-' }}</td><td><span class="rounded px-2 py-1" :class="user.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">{{ user.status }}</span></td><td class="text-right"><Link :href="`/usuarios/${user.id}/edit`" class="mr-2 inline-flex rounded-md border border-slate-200 p-2 text-[#123f6e]"><Pencil class="h-4 w-4" /></Link><button @click="deactivate(user)" class="inline-flex cursor-pointer rounded-md border border-red-200 p-2 text-red-700 transition-colors hover:bg-red-50"><Trash2 class="h-4 w-4" /></button></td></tr></tbody></table></div>
      <div class="space-y-2 sm:hidden"><div v-for="user in rows" :key="user.id" class="rounded border border-slate-100 bg-slate-50 p-3 text-sm"><div class="mb-2 flex items-start justify-between gap-2"><div><div class="font-medium text-slate-900">{{ user.name }} {{ user.last_name }}</div><div class="text-xs text-slate-500">{{ user.email }}</div></div><span class="shrink-0 rounded px-2 py-1 text-xs" :class="user.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">{{ user.status }}</span></div><div class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-slate-600"><div><span class="font-medium text-slate-700">Telefono:</span> {{ user.phone ?? '-' }}</div><div><span class="font-medium text-slate-700">Cargo:</span> {{ user.cargo ?? '-' }}</div><div><span class="font-medium text-slate-700">Rol:</span> {{ user.role?.name ?? '-' }}</div><div><span class="font-medium text-slate-700">Vehiculo:</span> {{ user.assigned_vehicle?.plate ?? '-' }}</div></div><div class="mt-2 flex gap-2"><Link :href="`/usuarios/${user.id}/edit`" class="inline-flex rounded-md border border-slate-200 p-2 text-[#123f6e]"><Pencil class="h-4 w-4" /></Link><button @click="deactivate(user)" class="inline-flex cursor-pointer rounded-md border border-red-200 p-2 text-red-700 transition-colors hover:bg-red-50"><Trash2 class="h-4 w-4" /></button></div></div><p v-if="!rows.length" class="py-4 text-center text-sm text-slate-500">Sin usuarios.</p></div>
    </section>
  </AppLayout>
</template>
