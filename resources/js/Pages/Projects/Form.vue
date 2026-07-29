<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeft, Save } from '@lucide/vue';

const props = defineProps({ project: Object, vehicles: Array });
const isEdit = Boolean(props.project);
const form = useForm({
  name: props.project?.name ?? '',
  description: props.project?.description ?? '',
  status: props.project?.status ?? 'active',
  vehicle_ids: props.project?.vehicles?.map(vehicle => vehicle.id) ?? [],
});
const submit = () => isEdit ? form.patch(`/vehiculos/proyectos/${props.project.id}`) : form.post('/vehiculos/proyectos');
</script>

<template>
  <Head :title="isEdit ? 'Editar proyecto' : 'Nuevo proyecto'" />
  <AppLayout :title="isEdit ? 'Editar proyecto' : 'Nuevo proyecto'">
    <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
      <Link href="/vehiculos/proyectos" class="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-[#123f6e]"><ArrowLeft class="h-4 w-4" /> Volver</Link>
      <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
        <label><span>Nombre</span><input v-model="form.name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3" required /></label>
        <label><span>Estado</span><select v-model="form.status" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3"><option value="active">Activo</option><option value="inactive">Inactivo</option></select></label>
        <label class="md:col-span-2"><span>Descripcion</span><textarea v-model="form.description" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3"></textarea></label>
        <section class="md:col-span-2">
          <div class="mb-2 flex items-center justify-between gap-3">
            <span class="font-semibold text-slate-700">Vehiculos del proyecto</span>
            <span class="text-sm text-slate-500">{{ form.vehicle_ids.length }} seleccionados</span>
          </div>
          <div class="max-h-72 overflow-y-auto rounded-md border border-slate-200">
            <label v-for="vehicle in vehicles" :key="vehicle.id" class="flex cursor-pointer items-center justify-between gap-3 border-b border-slate-100 px-3 py-3 last:border-b-0 hover:bg-slate-50">
              <span class="font-medium text-slate-700">{{ vehicle.plate }}</span>
              <input v-model="form.vehicle_ids" type="checkbox" :value="vehicle.id" class="h-4 w-4 rounded border-slate-300 text-[#123f6e]" />
            </label>
            <p v-if="!vehicles.length" class="p-4 text-sm text-slate-500">No hay vehiculos disponibles para asignar.</p>
          </div>
          <p class="mt-2 text-xs text-slate-500">Aqui solo aparecen vehiculos sin proyecto o que ya pertenecen a este proyecto.</p>
        </section>
        <div v-if="Object.keys(form.errors).length" class="md:col-span-2 rounded-md bg-red-50 p-3 text-sm text-red-700"><p v-for="error in form.errors" :key="error">{{ error }}</p></div>
        <div class="md:col-span-2"><button class="inline-flex cursor-pointer items-center gap-2 rounded-md bg-[#123f6e] px-5 py-3 font-semibold text-white transition-colors hover:bg-[#0e2d52]"><Save class="h-5 w-5" /> Guardar proyecto</button></div>
      </form>
    </section>
  </AppLayout>
</template>
