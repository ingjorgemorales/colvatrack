<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
const props = defineProps({ roles: Array });
const remove = (role) => { if(confirm(`Eliminar rol ${role.name}?`)) router.delete(`/roles/${role.id}`); };
</script>
<template>
  <Head title="Roles" />
  <AppLayout title="Roles y permisos">
    <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-5 flex justify-end"><Link href="/roles/create" class="inline-flex items-center gap-2 rounded-md bg-[#123f6e] px-4 py-3 font-semibold text-white"><Plus class="h-5 w-5" /> Nuevo rol</Link></div>
      <div class="grid gap-4 lg:grid-cols-3"><article v-for="role in roles" :key="role.id" class="rounded-md border border-slate-200 p-4"><div class="mb-3 flex items-start justify-between"><div><h2 class="font-semibold text-slate-950">{{ role.name }}</h2><p class="text-sm text-slate-500">{{ role.description }}</p></div><span class="rounded bg-[#e6eef7] px-2 py-1 text-xs text-[#123f6e]">{{ role.users_count }} usuarios</span></div><p class="mb-4 text-sm text-slate-500">{{ role.permissions?.length ?? 0 }} permisos asignados</p><div class="flex gap-2"><Link :href="`/roles/${role.id}/edit`" class="inline-flex rounded-md border border-slate-200 p-2 text-[#123f6e]"><Pencil class="h-4 w-4" /></Link><button v-if="!['Administrador','Tecnico','Conductor'].includes(role.name)" @click="remove(role)" class="inline-flex rounded-md border border-red-200 p-2 text-red-700"><Trash2 class="h-4 w-4" /></button></div></article></div>
    </section>
  </AppLayout>
</template>
