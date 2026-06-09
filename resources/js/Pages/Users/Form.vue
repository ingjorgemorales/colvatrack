<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeft, Save } from '@lucide/vue';
const props = defineProps({ user: Object, roles: Array, vehicles: Array });
const isEdit = !!props.user;
const form = useForm({
  role_id: props.user?.role_id ?? '', name: props.user?.name ?? '', last_name: props.user?.last_name ?? '', email: props.user?.email ?? '', phone: props.user?.phone ?? '', cargo: props.user?.cargo ?? '', status: props.user?.status ?? 'active',
  password: '', password_confirmation: '', must_change_password: props.user?.must_change_password ?? true, vehicle_id: props.user?.assigned_vehicle?.id ?? '',
});
const submit = () => isEdit ? form.patch(`/usuarios/${props.user.id}`) : form.post('/usuarios');
</script>
<template>
  <Head :title="isEdit ? 'Editar usuario' : 'Nuevo usuario'" />
  <AppLayout :title="isEdit ? 'Editar usuario' : 'Nuevo usuario'">
    <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-5 flex items-center justify-between"><Link href="/usuarios" class="inline-flex items-center gap-2 text-sm font-semibold text-[#123f6e]"><ArrowLeft class="h-4 w-4" /> Volver</Link></div>
      <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
        <label><span class="text-sm font-medium text-slate-600">Nombre</span><input v-model="form.name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3" /></label>
        <label><span class="text-sm font-medium text-slate-600">Apellido</span><input v-model="form.last_name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3" /></label>
        <label><span class="text-sm font-medium text-slate-600">Correo</span><input v-model="form.email" type="email" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3" /></label>
        <label><span class="text-sm font-medium text-slate-600">Telefono</span><input v-model="form.phone" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3" /></label>
        <label><span class="text-sm font-medium text-slate-600">Cargo</span><input v-model="form.cargo" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3" /></label>
        <label><span class="text-sm font-medium text-slate-600">Rol</span><select v-model="form.role_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3"><option value="">Seleccionar</option><option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option></select></label>
        <label><span class="text-sm font-medium text-slate-600">Vehiculo si es conductor</span><select v-model="form.vehicle_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3"><option value="">Sin asignar</option><option v-for="vehicle in vehicles" :key="vehicle.id" :value="vehicle.id">{{ vehicle.plate }} - {{ vehicle.brand }}</option></select></label>
        <label><span class="text-sm font-medium text-slate-600">Estado</span><select v-model="form.status" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3"><option value="active">Activo</option><option value="inactive">Inactivo</option></select></label>
        <label><span class="text-sm font-medium text-slate-600">Contrasena temporal</span><input v-model="form.password" type="password" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3" :placeholder="isEdit ? 'Dejar en blanco para conservar' : ''" /></label>
        <label><span class="text-sm font-medium text-slate-600">Confirmar contraseña</span><input v-model="form.password_confirmation" type="password" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3" /></label>
        <label class="flex items-center gap-3 md:col-span-2"><input v-model="form.must_change_password" type="checkbox" class="h-5 w-5" /> <span class="text-sm font-medium text-slate-700">Requiere cambio de contraseña en el primer inicio</span></label>
        <div v-if="Object.keys(form.errors).length" class="md:col-span-2 rounded-md bg-red-50 p-3 text-sm text-red-700"><p v-for="error in form.errors">{{ error }}</p></div>
        <div class="md:col-span-2"><button class="inline-flex items-center gap-2 rounded-md bg-[#123f6e] px-5 py-3 font-semibold text-white"><Save class="h-5 w-5" /> Guardar usuario</button></div>
      </form>
    </section>
  </AppLayout>
</template>
