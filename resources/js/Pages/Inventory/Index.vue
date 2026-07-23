<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { History, Plus, Save, Search, Trash2, Wrench, X } from '@lucide/vue';
import { ref } from 'vue';

const props = defineProps({
  vehicles: Object,
  filters: Object,
  items: Array,
  canManageCatalog: Boolean,
});

const search = ref(props.filters?.search ?? '');
const toolFilter = ref(props.filters?.tool_id ?? '');
const perPage = ref(props.filters?.per_page ?? 10);
const addForm = useForm({ vehicle_id: null, inventory_item_id: '', quantity_total: 0 });
const stockForms = ref({});

function apply() {
  router.get('/inventario', {
    search: search.value,
    tool_id: toolFilter.value,
    per_page: perPage.value,
  }, { preserveState: true, replace: true });
}

function clearFilters() {
  search.value = '';
  toolFilter.value = '';
  perPage.value = 10;
  router.get('/inventario', {}, { preserveState: true, replace: true });
}

function formFor(vehicleId, itemId, current = null) {
  const key = `${vehicleId}-${itemId}`;

  if (!stockForms.value[key]) {
    stockForms.value[key] = useForm({
      vehicle_id: vehicleId,
      inventory_item_id: itemId,
      quantity_total: current?.quantity_total ?? 0,
      quantity_available: current?.quantity_available ?? 0,
    });
  }

  return stockForms.value[key];
}

function saveStock(form) {
  form.patch('/inventario/stock', { preserveScroll: true });
}

function assignedItemIds(vehicle) {
  return new Set(vehicle.inventory.map(row => row.inventory_item_id));
}

function assignToVehicle(vehicle) {
  addForm.vehicle_id = vehicle.id;
  addForm
    .transform(data => ({ ...data, quantity_available: data.quantity_total }))
    .patch('/inventario/stock', { preserveScroll: true, onSuccess: () => addForm.reset() });
}

function canRemove(row) {
  return Number(row.quantity_reserved ?? 0) === 0 && Number(row.quantity_delivered ?? 0) === 0;
}

function removeFromVehicle(vehicle, row) {
  if (!canRemove(row)) return;

  const toolName = row.item?.name ?? 'esta herramienta';
  if (!confirm(`Vas a quitar "${toolName}" del vehiculo ${vehicle.plate}.\n\nEsto elimina la herramienta del inventario de este vehiculo y dejara un movimiento historico.\n\nQuieres continuar?`)) return;

  router.delete('/inventario/stock', {
    data: {
      vehicle_id: vehicle.id,
      inventory_item_id: row.inventory_item_id,
    },
    preserveScroll: true,
  });
}
</script>

<template>
  <Head title="Inventario" />
  <AppLayout title="Inventario">
    <section class="grid gap-6 xl:grid-cols-[1fr_360px]">
      <div class="space-y-5">
        <div class="grid gap-2 sm:grid-cols-[1fr_1fr_120px_auto_auto]">
          <div class="relative">
            <Search class="absolute left-3 top-3.5 h-5 w-5 text-slate-400" />
            <input v-model="search" @keyup.enter="apply" class="w-full rounded-md border border-slate-300 py-3 pl-10 pr-3 text-sm" placeholder="Buscar placa o conductor" />
          </div>
          <select v-model="toolFilter" @change="apply" class="w-full rounded-md border border-slate-300 px-3 py-3 text-sm">
            <option value="">Todas las herramientas</option>
            <option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }}</option>
          </select>
          <select v-model="perPage" @change="apply" class="w-full rounded-md border border-slate-300 px-3 py-3 text-sm sm:w-auto">
            <option value="10">10 por pag.</option>
            <option value="25">25 por pag.</option>
            <option value="50">50 por pag.</option>
            <option value="100">100 por pag.</option>
          </select>
          <button @click="apply" class="w-full cursor-pointer rounded-md bg-[#123f6e] px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#0e2d52] sm:w-auto">Filtrar</button>
          <button @click="clearFilters" class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-md border border-[#123f6e] px-4 py-3 text-sm font-semibold text-[#123f6e] transition-colors hover:bg-[#123f6e] hover:text-white sm:w-auto">
            <X class="h-4 w-4" /> Limpiar
          </button>
        </div>

        <div class="text-sm text-slate-600">Mostrando {{ vehicles.from ?? 0 }}-{{ vehicles.to ?? 0 }} de {{ vehicles.total ?? 0 }} vehiculos</div>

        <article v-for="vehicle in vehicles.data" :key="vehicle.id" class="rounded-md border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
          <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 class="text-base font-semibold text-[#123f6e] sm:text-lg">{{ vehicle.plate }}</h2>
              <p class="text-sm text-slate-500">Conductor: {{ vehicle.driver?.name ?? 'Sin asignar' }}</p>
            </div>
            <span class="inline-block self-start rounded bg-[#e6eef7] px-2 py-1 text-xs font-semibold text-[#123f6e] sm:self-auto sm:px-3 sm:py-2 sm:text-sm">{{ vehicle.inventory?.length ?? 0 }} herramientas</span>
          </div>

          <div class="hidden sm:block">
            <table class="w-full text-left text-sm">
              <thead class="bg-slate-50 text-slate-500">
                <tr>
                  <th class="px-3 py-2">Herramienta</th>
                  <th>Total</th>
                  <th>Disponible</th>
                  <th>Reservada</th>
                  <th>Entregada</th>
                  <th v-if="canManageCatalog">Gestionar</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in vehicle.inventory" :key="row.id" class="border-t border-slate-100">
                  <td class="px-3 py-3">
                    <div class="font-medium text-slate-900">{{ row.item?.name }}</div>
                    <div class="text-xs text-slate-500">{{ row.item?.category?.name }}</div>
                  </td>
                  <td>{{ row.quantity_total }}</td>
                  <td>{{ row.quantity_available }}</td>
                  <td>{{ row.quantity_reserved }}</td>
                  <td>{{ row.quantity_delivered }}</td>
                  <td v-if="canManageCatalog">
                    <div class="flex flex-wrap items-center gap-1">
                      <form class="flex items-center gap-1" @submit.prevent="saveStock(formFor(vehicle.id, row.inventory_item_id, row))">
                        <input v-model="formFor(vehicle.id, row.inventory_item_id, row).quantity_total" type="number" class="w-16 rounded border border-slate-300 px-2 py-2 text-xs" title="Total" />
                        <input v-model="formFor(vehicle.id, row.inventory_item_id, row).quantity_available" type="number" class="w-16 rounded border border-slate-300 px-2 py-2 text-xs" title="Disponible" />
                        <button class="cursor-pointer rounded bg-[#123f6e] p-2 text-white transition-colors hover:bg-[#0e2d52]" title="Guardar cantidades">
                          <Save class="h-4 w-4" />
                        </button>
                      </form>
                      <button
                        type="button"
                        @click="removeFromVehicle(vehicle, row)"
                        class="rounded border border-red-200 p-2 text-red-700 transition-colors"
                        :class="canRemove(row) ? 'cursor-pointer hover:bg-red-50' : 'cursor-not-allowed opacity-40'"
                        :title="canRemove(row) ? 'Quitar herramienta del vehiculo' : 'No se puede quitar mientras tenga cantidad reservada o entregada'"
                      >
                        <Trash2 class="h-4 w-4" />
                      </button>
                    </div>
                  </td>
                </tr>
                <tr v-if="!vehicle.inventory?.length">
                  <td :colspan="canManageCatalog ? 6 : 5" class="px-3 py-6 text-slate-500">Sin inventario asignado.</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="space-y-2 sm:hidden">
            <div v-for="row in vehicle.inventory" :key="row.id" class="rounded border border-slate-100 bg-slate-50 p-3 text-sm">
              <div class="mb-2 font-medium text-slate-900">
                {{ row.item?.name }}
                <span class="ml-1 text-xs font-normal text-slate-500">{{ row.item?.category?.name }}</span>
              </div>
              <div class="mb-2 grid grid-cols-2 gap-2">
                <div><span class="text-xs text-slate-500">Total:</span><span class="ml-1 font-semibold">{{ row.quantity_total }}</span></div>
                <div><span class="text-xs text-slate-500">Disponible:</span><span class="ml-1 font-semibold">{{ row.quantity_available }}</span></div>
                <div><span class="text-xs text-slate-500">Reservada:</span><span class="ml-1 font-semibold">{{ row.quantity_reserved }}</span></div>
                <div><span class="text-xs text-slate-500">Entregada:</span><span class="ml-1 font-semibold">{{ row.quantity_delivered }}</span></div>
              </div>
              <div v-if="canManageCatalog" class="flex flex-wrap items-center gap-1">
                <form class="flex items-center gap-1" @submit.prevent="saveStock(formFor(vehicle.id, row.inventory_item_id, row))">
                  <input v-model="formFor(vehicle.id, row.inventory_item_id, row).quantity_total" type="number" class="w-14 rounded border border-slate-300 px-2 py-2 text-xs" title="Total" />
                  <input v-model="formFor(vehicle.id, row.inventory_item_id, row).quantity_available" type="number" class="w-14 rounded border border-slate-300 px-2 py-2 text-xs" title="Disponible" />
                  <button class="cursor-pointer rounded bg-[#123f6e] p-2 text-white transition-colors hover:bg-[#0e2d52]" title="Guardar cantidades">
                    <Save class="h-4 w-4" />
                  </button>
                </form>
                <button
                  type="button"
                  @click="removeFromVehicle(vehicle, row)"
                  class="rounded border border-red-200 p-2 text-red-700 transition-colors"
                  :class="canRemove(row) ? 'cursor-pointer hover:bg-red-50' : 'cursor-not-allowed opacity-40'"
                  :title="canRemove(row) ? 'Quitar herramienta del vehiculo' : 'No se puede quitar mientras tenga cantidad reservada o entregada'"
                >
                  <Trash2 class="h-4 w-4" />
                </button>
              </div>
            </div>
            <p v-if="!vehicle.inventory?.length" class="py-4 text-center text-sm text-slate-500">Sin inventario asignado.</p>
          </div>

          <form v-if="canManageCatalog && items.length" class="mt-3 grid grid-cols-1 gap-2 sm:flex sm:flex-wrap sm:items-center sm:gap-2" @submit.prevent="assignToVehicle(vehicle)">
            <select v-model="addForm.inventory_item_id" class="w-full rounded border border-slate-300 px-3 py-2 text-sm sm:w-auto">
              <option value="">Agregar herramienta...</option>
              <option v-for="item in items.filter(i => !assignedItemIds(vehicle).has(i.id))" :key="item.id" :value="item.id">{{ item.name }} ({{ item.category?.name }})</option>
            </select>
            <input v-model="addForm.quantity_total" type="number" placeholder="Cantidad" title="Cantidad total que colocas en el vehiculo" class="w-full rounded border border-slate-300 px-2 py-2 text-sm sm:w-24" />
            <button class="w-full cursor-pointer rounded bg-green-600 px-3 py-2 text-sm font-semibold text-white transition-colors hover:bg-green-700 sm:w-auto" :disabled="addForm.processing || !addForm.inventory_item_id">
              <Plus class="h-4 w-4 inline" /> Asignar
            </button>
          </form>
        </article>

        <p v-if="!vehicles.data?.length" class="py-8 text-center text-sm text-slate-500">No hay vehiculos con ese filtro.</p>

        <div v-if="vehicles.last_page > 1" class="flex flex-wrap items-center justify-center gap-1 sm:gap-2">
          <Link v-for="link in vehicles.links" :key="link.label" :href="link.url || '#'" preserve-scroll class="rounded-md border px-2 py-2 text-xs font-semibold sm:px-3 sm:text-sm" :class="[link.active ? 'border-[#123f6e] bg-[#123f6e] text-white' : 'border-slate-200 bg-white text-slate-700', !link.url ? 'pointer-events-none opacity-40' : 'hover:bg-[#edf3fa]']" v-html="link.label" />
        </div>
      </div>

      <aside class="space-y-5">
        <section v-if="canManageCatalog" class="rounded-md border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
          <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-[#123f6e] sm:text-lg">
            <Wrench class="h-5 w-5" /> Herramientas del catalogo
          </h2>
          <Link href="/inventario/catalogo" class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-[#123f6e] px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#0e2d52]">
            <Wrench class="h-4 w-4" /> Ver herramientas
          </Link>
        </section>

        <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
          <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-[#123f6e] sm:text-lg">
            <History class="h-5 w-5" /> Movimientos recientes
          </h2>
          <Link href="/inventario/movimientos" class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-[#123f6e] px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#0e2d52]">
            <History class="h-4 w-4" /> Movimientos recientes
          </Link>
        </section>
      </aside>
    </section>
  </AppLayout>
</template>
