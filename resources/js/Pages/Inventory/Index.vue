<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Boxes, Plus, Save } from '@lucide/vue';
import { ref } from 'vue';
const props = defineProps({ vehicles: Array, categories: Array, items: Array, movements: Array, canManageCatalog: Boolean });
const itemForm = useForm({ category_name: '', name: '', description: '', unit: 'unidad' });
const stockForms = ref({});
function formFor(vehicleId, itemId, current = null) { const key = `${vehicleId}-${itemId}`; if (!stockForms.value[key]) stockForms.value[key] = useForm({ vehicle_id: vehicleId, inventory_item_id: itemId, quantity_total: current?.quantity_total ?? 0, quantity_available: current?.quantity_available ?? 0 }); return stockForms.value[key]; }
function saveStock(form) { form.patch('/inventario/stock', { preserveScroll: true }); }
</script>
<template>
  <Head title="Inventario" />
  <AppLayout title="Inventario">
    <section class="grid gap-6 xl:grid-cols-[1fr_360px]">
      <div class="space-y-5">
        <article v-for="vehicle in vehicles" :key="vehicle.id" class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
          <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="text-lg font-semibold text-[#123f6e]">{{ vehicle.plate }}</h2><p class="text-sm text-slate-500">Conductor: {{ vehicle.driver?.name ?? 'Sin asignar' }}</p></div><span class="rounded bg-[#e6eef7] px-3 py-2 text-sm font-semibold text-[#123f6e]">{{ vehicle.inventory?.length ?? 0 }} herramientas</span></div>
          <div class="overflow-x-auto"><table class="w-full min-w-[760px] text-left text-sm"><thead class="bg-slate-50 text-slate-500"><tr><th class="px-3 py-2">Herramienta</th><th>Total</th><th>Disponible</th><th>Reservada</th><th>Entregada</th><th>Actualizar</th></tr></thead><tbody><tr v-for="row in vehicle.inventory" :key="row.id" class="border-t border-slate-100"><td class="px-3 py-3"><div class="font-medium text-slate-900">{{ row.item?.name }}</div><div class="text-xs text-slate-500">{{ row.item?.category?.name }}</div></td><td>{{ row.quantity_total }}</td><td>{{ row.quantity_available }}</td><td>{{ row.quantity_reserved }}</td><td>{{ row.quantity_delivered }}</td><td><form class="flex items-center gap-2" @submit.prevent="saveStock(formFor(vehicle.id, row.inventory_item_id, row))"><input v-model="formFor(vehicle.id, row.inventory_item_id, row).quantity_total" type="number" class="w-20 rounded border border-slate-300 px-2 py-2" /><input v-model="formFor(vehicle.id, row.inventory_item_id, row).quantity_available" type="number" class="w-20 rounded border border-slate-300 px-2 py-2" /><button class="rounded bg-[#123f6e] p-2 text-white"><Save class="h-4 w-4" /></button></form></td></tr><tr v-if="!vehicle.inventory?.length"><td colspan="6" class="px-3 py-6 text-slate-500">Sin inventario asignado.</td></tr></tbody></table></div>
        </article>
      </div>
      <aside class="space-y-5">
        <section v-if="canManageCatalog" class="rounded-md border border-slate-200 bg-white p-5 shadow-sm"><h2 class="mb-4 flex items-center gap-2 text-lg font-semibold text-[#123f6e]"><Plus class="h-5 w-5" /> Nueva herramienta</h2><form class="space-y-3" @submit.prevent="itemForm.post('/inventario/items', { preserveScroll: true, onSuccess: () => itemForm.reset() })"><input v-model="itemForm.category_name" class="w-full rounded-md border border-slate-300 px-3 py-3" placeholder="Categoria" /><input v-model="itemForm.name" class="w-full rounded-md border border-slate-300 px-3 py-3" placeholder="Herramienta" /><input v-model="itemForm.unit" class="w-full rounded-md border border-slate-300 px-3 py-3" placeholder="Unidad" /><textarea v-model="itemForm.description" class="w-full rounded-md border border-slate-300 px-3 py-3" placeholder="Descripcion"></textarea><p v-for="error in itemForm.errors" class="text-sm text-red-600">{{ error }}</p><button class="w-full rounded-md bg-[#123f6e] px-4 py-3 font-semibold text-white">Guardar herramienta</button></form></section>
        <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm"><h2 class="mb-4 flex items-center gap-2 text-lg font-semibold text-[#123f6e]"><Boxes class="h-5 w-5" /> Movimientos recientes</h2><div class="space-y-3"><div v-for="m in movements" :key="m.id" class="rounded bg-slate-50 p-3 text-sm"><div class="font-medium text-slate-900">{{ m.item?.name }} - {{ m.vehicle?.plate }}</div><div class="text-slate-500">{{ m.movement_type }}: {{ m.quantity }} | {{ m.previous_available }} -> {{ m.new_available }}</div></div><p v-if="!movements.length" class="text-sm text-slate-500">Sin movimientos.</p></div></section>
      </aside>
    </section>
  </AppLayout>
</template>
