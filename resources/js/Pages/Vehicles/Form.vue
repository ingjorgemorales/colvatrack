<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeft, Save } from '@lucide/vue';
const props = defineProps({ vehicle: Object, drivers: Array, providers: Array, projects: Array });
const isEdit = !!props.vehicle;
const inputClass = 'mt-1 w-full rounded-md border border-slate-300 px-3 py-3';
const apiInputClass = isEdit ? `${inputClass} cursor-not-allowed bg-slate-100 text-slate-600` : inputClass;
const form = useForm({ plate: props.vehicle?.plate ?? '', brand: props.vehicle?.brand ?? '', model: props.vehicle?.model ?? '', year: props.vehicle?.year ?? '', color: props.vehicle?.color ?? '', status: props.vehicle?.status ?? 'active', gps_provider_id: props.vehicle?.gps_provider_id ?? '', project_id: props.vehicle?.project_id ?? '', external_gps_id: props.vehicle?.external_gps_id ?? '', driver_id: props.vehicle?.driver_id ?? '', current_latitude: props.vehicle?.current_latitude ?? '', current_longitude: props.vehicle?.current_longitude ?? '', current_speed: props.vehicle?.current_speed ?? '', current_heading: props.vehicle?.current_heading ?? '', current_address: props.vehicle?.current_address ?? '', imei: props.vehicle?.imei ?? '', odometer: props.vehicle?.odometer ?? '' });
const submit = () => isEdit ? form.patch(`/vehiculos/${props.vehicle.id}`) : form.post('/vehiculos');
</script>
<template>
  <Head :title="isEdit ? 'Editar vehiculo' : 'Nuevo vehiculo'" />
  <AppLayout :title="isEdit ? 'Editar vehiculo' : 'Nuevo vehiculo'">
    <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
      <Link href="/vehiculos" class="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-[#123f6e]"><ArrowLeft class="h-4 w-4" /> Volver</Link>
      <form class="grid gap-4 md:grid-cols-2 xl:grid-cols-3" @submit.prevent="submit">
        <div v-if="isEdit" class="md:col-span-2 xl:col-span-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">Los campos sincronizados desde la API GPS son de solo lectura y se actualizan automaticamente.</div>
        <label><span>Placa</span><input v-model="form.plate" :readonly="isEdit" :class="apiInputClass" /></label>
        <label><span>Marca</span><input v-model="form.brand" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3" /></label>
        <label><span>Modelo</span><input v-model="form.model" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3" /></label>
        <label><span>Ano</span><input v-model="form.year" type="number" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3" /></label>
        <label><span>Color</span><input v-model="form.color" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3" /></label>
        <label><span>Estado</span><select v-model="form.status" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3"><option value="active">Activo</option><option value="maintenance">Mantenimiento</option><option value="inactive">Inactivo</option></select></label>
        <label><span>Proyecto</span><select v-model="form.project_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3"><option value="">Sin proyecto</option><option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option></select></label>
        <label><span>Proveedor GPS</span><select v-model="form.gps_provider_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3"><option value="">Sin proveedor</option><option v-for="p in providers" :key="p.id" :value="p.id">{{ p.name }}</option></select></label>
        <label><span>External GPS ID</span><input v-model="form.external_gps_id" :readonly="isEdit" :class="apiInputClass" /></label>
        <label><span>Conductor asignado</span><select v-model="form.driver_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3"><option value="">Sin conductor</option><option v-for="d in drivers" :key="d.id" :value="d.id">{{ d.name }} {{ d.last_name }}</option></select><small class="mt-1 block text-xs text-slate-500">Solo aparecen conductores activos sin otro vehiculo asignado.</small></label>
        <label><span>Latitud actual</span><input v-model="form.current_latitude" :readonly="isEdit" :class="apiInputClass" /></label>
        <label><span>Longitud actual</span><input v-model="form.current_longitude" :readonly="isEdit" :class="apiInputClass" /></label>
        <label><span>Velocidad</span><input v-model="form.current_speed" :readonly="isEdit" :class="apiInputClass" /></label>
        <label><span>Rumbo</span><input v-model="form.current_heading" :readonly="isEdit" :class="apiInputClass" /></label>
        <label><span>IMEI</span><input v-model="form.imei" :readonly="isEdit" :class="apiInputClass" /></label>
        <label><span>Odometro</span><input v-model="form.odometer" :readonly="isEdit" :class="apiInputClass" /></label>
        <label class="md:col-span-2 xl:col-span-3"><span>Direccion actual</span><textarea v-model="form.current_address" :readonly="isEdit" :class="apiInputClass"></textarea></label>
        <div v-if="Object.keys(form.errors).length" class="md:col-span-2 xl:col-span-3 rounded-md bg-red-50 p-3 text-sm text-red-700"><p v-for="error in form.errors">{{ error }}</p></div>
        <div class="md:col-span-2 xl:col-span-3"><button class="inline-flex cursor-pointer items-center gap-2 rounded-md bg-[#123f6e] px-5 py-3 font-semibold text-white transition-colors hover:bg-[#0e2d52]"><Save class="h-5 w-5" /> Guardar vehiculo</button></div>
      </form>
    </section>
  </AppLayout>
</template>
