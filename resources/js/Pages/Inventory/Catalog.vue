<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeft, Edit3, Plus, Search, ToggleLeft, ToggleRight, Wrench, X } from '@lucide/vue';
import { reactive, ref } from 'vue';

const props = defineProps({ items: Object, categories: Array, filters: Object });
const perPage = ref(props.filters?.per_page ?? 15);
const form = reactive({
  search: props.filters?.search ?? '',
  category_id: props.filters?.category_id ?? '',
  status: props.filters?.status ?? '',
});
const itemForm = useForm({ category_name: '', name: '', description: '', unit: 'unidad' });
const editForm = useForm({ name: '', category_name: '', unit: '', description: '' });
const editingItem = ref(null);
const unitOptions = [
  { value: 'unidad', label: 'Unidad', hint: 'Escaleras, medidores, conectores' },
  { value: 'pieza', label: 'Pieza', hint: 'Repuestos o elementos sueltos' },
  { value: 'metro', label: 'Metro', hint: 'Cable, fibra optica, coaxial' },
  { value: 'rollo', label: 'Rollo', hint: 'Cinta, cable o material enrollado' },
  { value: 'paquete', label: 'Paquete', hint: 'Tornillos, conectores o kits' },
];

function searchCatalog() {
  router.get('/inventario/catalogo', { ...form, per_page: perPage.value }, { preserveState: true, replace: true });
}

function clearFilters() {
  form.search = '';
  form.category_id = '';
  form.status = '';
  perPage.value = 15;
  router.get('/inventario/catalogo', {}, { preserveState: true, replace: true });
}

function startEdit(item) {
  editForm.name = item.name;
  editForm.category_name = item.category?.name ?? '';
  editForm.unit = item.unit;
  editForm.description = item.description ?? '';
  editingItem.value = item.id;
}

function cancelEdit() {
  editingItem.value = null;
  editForm.reset();
  editForm.clearErrors();
}

function saveEdit(item) {
  editForm.patch(`/inventario/items/${item.id}`, {
    preserveScroll: true,
    onSuccess: cancelEdit,
  });
}

function saveNewItem() {
  itemForm.post('/inventario/items', {
    preserveScroll: true,
    onSuccess: () => itemForm.reset('category_name', 'name', 'description'),
  });
}

function toggleStatus(item) {
  if (item.status === 'active') {
    const confirmed = window.confirm(
      `Vas a desactivar "${item.name}".\n\nEsto hara que la herramienta deje de aparecer en inventario operativo, mapa y creacion de solicitudes. Tampoco se podra asignar ni reservar hasta que la vuelvas a activar.\n\nLos movimientos y solicitudes historicas conservaran su registro.\n\nQuieres continuar?`
    );

    if (!confirmed) return;
  }

  useForm({}).patch(`/inventario/items/${item.id}/status`, { preserveScroll: true });
}
</script>

<template>
  <Head title="Herramientas del catalogo" />
  <AppLayout title="Herramientas del catalogo">
    <section class="space-y-5">
      <Link href="/inventario" class="inline-flex items-center gap-2 text-sm font-semibold text-[#123f6e]">
        <ArrowLeft class="h-4 w-4" /> Volver al inventario
      </Link>

      <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-[#123f6e] sm:text-lg">
          <Plus class="h-5 w-5" /> Nueva herramienta
        </h2>
        <form class="grid gap-3 xl:grid-cols-[1fr_1fr_160px_1.5fr_auto]" @submit.prevent="saveNewItem">
          <input v-model="itemForm.category_name" class="rounded-md border border-slate-300 px-3 py-3 text-sm" placeholder="Categoria" />
          <input v-model="itemForm.name" class="rounded-md border border-slate-300 px-3 py-3 text-sm" placeholder="Herramienta" />
          <select v-model="itemForm.unit" class="rounded-md border border-slate-300 px-3 py-3 text-sm">
            <option v-for="option in unitOptions" :key="option.value" :value="option.value">{{ option.label }} - {{ option.hint }}</option>
          </select>
          <input v-model="itemForm.description" class="rounded-md border border-slate-300 px-3 py-3 text-sm" placeholder="Descripcion" />
          <button class="cursor-pointer rounded-md bg-[#123f6e] px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#0e2d52]" :disabled="itemForm.processing">
            Guardar
          </button>
        </form>
        <div v-if="Object.keys(itemForm.errors).length" class="mt-3 grid gap-1 text-sm text-red-600 sm:grid-cols-2">
          <p v-for="error in itemForm.errors" :key="error">{{ error }}</p>
        </div>
      </section>

      <form class="rounded-md border border-slate-200 bg-white p-4 shadow-sm sm:p-5" @submit.prevent="searchCatalog">
        <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-[#123f6e] sm:text-lg">
          <Search class="h-5 w-5" /> Filtros
        </h2>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[1fr_220px_180px_130px_auto_auto]">
          <input v-model="form.search" class="rounded-md border border-slate-300 px-3 py-3 text-sm" placeholder="Buscar herramienta, categoria o descripcion" />
          <select v-model="form.category_id" class="rounded-md border border-slate-300 px-3 py-3 text-sm">
            <option value="">Todas las categorias</option>
            <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
          </select>
          <select v-model="form.status" class="rounded-md border border-slate-300 px-3 py-3 text-sm">
            <option value="">Todos los estados</option>
            <option value="active">Activas</option>
            <option value="inactive">Inactivas</option>
          </select>
          <select v-model="perPage" @change="searchCatalog" class="rounded-md border border-slate-300 px-3 py-3 text-sm">
            <option value="15">15 por pag.</option>
            <option value="25">25 por pag.</option>
            <option value="50">50 por pag.</option>
            <option value="100">100 por pag.</option>
          </select>
          <button class="cursor-pointer rounded-md bg-[#123f6e] px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#0e2d52]">Buscar</button>
          <button type="button" class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-md border border-[#123f6e] px-4 py-3 text-sm font-semibold text-[#123f6e] transition-colors hover:bg-[#123f6e] hover:text-white" @click="clearFilters">
            <X class="h-4 w-4" /> Limpiar
          </button>
        </div>
      </form>

      <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
          <h2 class="flex items-center gap-2 text-base font-semibold text-[#123f6e] sm:text-lg">
            <Wrench class="h-5 w-5" /> Herramientas
          </h2>
          <span class="text-sm text-slate-500">{{ items.total ?? 0 }} herramientas</span>
        </div>

        <div class="space-y-3">
          <article v-for="item in items.data" :key="item.id" class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <form v-if="editingItem === item.id" class="grid gap-3 lg:grid-cols-[1fr_1fr_130px_1.5fr_auto_auto]" @submit.prevent="saveEdit(item)">
              <input v-model="editForm.name" class="rounded-md border border-slate-300 px-3 py-3 text-sm" placeholder="Nombre" />
              <input v-model="editForm.category_name" class="rounded-md border border-slate-300 px-3 py-3 text-sm" placeholder="Categoria" />
              <select v-model="editForm.unit" class="rounded-md border border-slate-300 px-3 py-3 text-sm">
                <option v-for="option in unitOptions" :key="option.value" :value="option.value">{{ option.label }} - {{ option.hint }}</option>
              </select>
              <input v-model="editForm.description" class="rounded-md border border-slate-300 px-3 py-3 text-sm" placeholder="Descripcion" />
              <button class="cursor-pointer rounded-md bg-[#123f6e] px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#0e2d52]" :disabled="editForm.processing">Guardar</button>
              <button type="button" class="cursor-pointer rounded-md border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50" @click="cancelEdit">Cancelar</button>
              <p v-for="error in editForm.errors" :key="error" class="text-sm text-red-600 lg:col-span-6">{{ error }}</p>
            </form>

            <div v-else class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="font-semibold text-slate-950" :class="item.status !== 'active' ? 'text-slate-400 line-through' : ''">{{ item.name }}</h3>
                  <span class="rounded px-2 py-1 text-xs font-semibold" :class="item.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'">{{ item.status === 'active' ? 'Activa' : 'Inactiva' }}</span>
                </div>
                <p class="mt-1 text-sm text-slate-500">{{ item.category?.name ?? '-' }} | {{ item.unit }} | {{ item.vehicle_inventories_sum_quantity_total ?? 0 }} en total</p>
                <p v-if="item.description" class="mt-1 text-sm text-slate-600">{{ item.description }}</p>
              </div>
              <div class="flex shrink-0 items-center gap-2">
                <button @click="startEdit(item)" class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50" title="Editar">
                  <Edit3 class="h-4 w-4" /> Editar
                </button>
                <button @click="toggleStatus(item)" class="inline-flex cursor-pointer items-center gap-2 rounded-md px-3 py-2 text-sm font-semibold transition-colors" :class="item.status === 'active' ? 'bg-red-50 text-red-700 hover:bg-red-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'" :title="item.status === 'active' ? 'Desactivar' : 'Activar'">
                  <component :is="item.status === 'active' ? ToggleRight : ToggleLeft" class="h-5 w-5" />
                  {{ item.status === 'active' ? 'Desactivar' : 'Activar' }}
                </button>
              </div>
            </div>
          </article>

          <p v-if="!items.data.length" class="py-8 text-center text-sm text-slate-500">No hay herramientas con esos filtros.</p>
        </div>

        <div v-if="items.last_page > 1" class="mt-5 flex flex-wrap items-center justify-center gap-1 sm:gap-2">
          <Link v-for="link in items.links" :key="link.label" :href="link.url || '#'" preserve-scroll class="rounded-md border px-2 py-2 text-xs font-semibold sm:px-3 sm:text-sm" :class="[link.active ? 'border-[#123f6e] bg-[#123f6e] text-white' : 'border-slate-200 bg-white text-slate-700', !link.url ? 'pointer-events-none opacity-40' : 'hover:bg-[#edf3fa]']" v-html="link.label" />
        </div>
      </section>
    </section>
  </AppLayout>
</template>
