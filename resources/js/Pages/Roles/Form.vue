<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeft, Save } from '@lucide/vue';
import { computed } from 'vue';
const props = defineProps({ role: Object, permissions: Object });
const isEdit = !!props.role;
const selected = props.role?.permissions?.map(p => p.id) ?? [];
const form = useForm({ name: props.role?.name ?? '', description: props.role?.description ?? '', permissions: selected });
const modules = computed(() => Object.entries(props.permissions ?? {}));
const toggle = (id) => form.permissions.includes(id) ? form.permissions = form.permissions.filter(p => p !== id) : form.permissions.push(id);
const submit = () => isEdit ? form.patch(`/roles/${props.role.id}`) : form.post('/roles');
</script>
<template>
  <Head :title="isEdit ? 'Editar rol' : 'Nuevo rol'" />
  <AppLayout :title="isEdit ? 'Editar rol' : 'Nuevo rol'">
    <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
      <Link href="/roles" class="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-[#123f6e]"><ArrowLeft class="h-4 w-4" /> Volver</Link>
      <form class="space-y-5" @submit.prevent="submit">
        <div class="grid gap-4 md:grid-cols-2"><label><span class="text-sm font-medium text-slate-600">Nombre</span><input v-model="form.name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3" /></label><label><span class="text-sm font-medium text-slate-600">Descripcion</span><input v-model="form.description" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3" /></label></div>
        <div class="grid gap-4 lg:grid-cols-2"><article v-for="[module, perms] in modules" :key="module" class="rounded-md border border-slate-200 p-4"><h3 class="mb-3 font-semibold capitalize text-[#123f6e]">{{ module.replaceAll('_',' ') }}</h3><div class="grid grid-cols-2 gap-2 sm:grid-cols-3"><label v-for="permission in perms" :key="permission.id" class="flex items-center gap-2 rounded bg-slate-50 px-2 py-2 text-sm"><input type="checkbox" :checked="form.permissions.includes(permission.id)" @change="toggle(permission.id)" /> {{ permission.action }}</label></div></article></div>
        <div v-if="Object.keys(form.errors).length" class="rounded-md bg-red-50 p-3 text-sm text-red-700"><p v-for="error in form.errors">{{ error }}</p></div>
        <button class="inline-flex items-center gap-2 rounded-md bg-[#123f6e] px-5 py-3 font-semibold text-white"><Save class="h-5 w-5" /> Guardar rol</button>
      </form>
    </section>
  </AppLayout>
</template>
