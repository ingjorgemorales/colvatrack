<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeft, Pencil, Plus, Search, Trash2, X } from '@lucide/vue';
import { ref } from 'vue';

const props = defineProps({ projects: Object, filters: Object });
const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? '');
const perPage = ref(props.filters?.per_page ?? 10);
const apply = () => router.get('/vehiculos/proyectos', { search: search.value, status: status.value, per_page: perPage.value }, { preserveState: true, replace: true });
const clearFilters = () => { search.value = ''; status.value = ''; perPage.value = 10; router.get('/vehiculos/proyectos', {}, { preserveState: true, replace: true }); };
const deactivate = (project) => { if (confirm(`Inactivar proyecto ${project.name}? Los vehiculos asignados conservaran el proyecto como referencia historica.`)) router.delete(`/vehiculos/proyectos/${project.id}`); };
</script>

<template>
  <Head title="Proyectos" />
  <AppLayout title="Proyectos">
    <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <Link href="/vehiculos" class="inline-flex items-center gap-2 text-sm font-semibold text-[#123f6e]"><ArrowLeft class="h-4 w-4" /> Volver a vehiculos</Link>
        <Link href="/vehiculos/proyectos/create" class="inline-flex items-center gap-2 rounded-md bg-[#123f6e] px-4 py-3 font-semibold text-white transition-colors hover:bg-[#0e2d52]"><Plus class="h-5 w-5" /> Nuevo proyecto</Link>
      </div>

      <div class="mb-5 grid gap-2 sm:grid-cols-[1fr_170px_140px_auto_auto]">
        <div class="relative"><Search class="absolute left-3 top-3.5 h-5 w-5 text-slate-400" /><input v-model="search" @keyup.enter="apply" class="w-full rounded-md border border-slate-300 py-3 pl-10 pr-3" placeholder="Buscar proyecto" /></div>
        <select v-model="status" class="rounded-md border border-slate-300 px-3 py-3"><option value="">Todos</option><option value="active">Activos</option><option value="inactive">Inactivos</option></select>
        <select v-model="perPage" @change="apply" class="rounded-md border border-slate-300 px-3 py-3"><option value="10">10 por pagina</option><option value="25">25 por pagina</option><option value="50">50 por pagina</option></select>
        <button @click="apply" class="cursor-pointer rounded-md bg-[#123f6e] px-4 py-3 font-semibold text-white transition-colors hover:bg-[#0e2d52]">Filtrar</button>
        <button @click="clearFilters" class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-md border border-[#123f6e] px-4 py-3 font-semibold text-[#123f6e] transition-colors hover:bg-[#123f6e] hover:text-white"><X class="h-4 w-4" /> Limpiar</button>
      </div>

      <div class="mb-4 flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600">
        <span>Mostrando {{ projects.from ?? 0 }}-{{ projects.to ?? 0 }} de {{ projects.total ?? 0 }} proyectos</span>
        <span>Pagina {{ projects.current_page }} de {{ projects.last_page }}</span>
      </div>

      <div class="hidden sm:block">
        <table class="w-full text-left text-sm">
          <thead class="bg-slate-50 text-slate-500"><tr><th class="px-3 py-2">Proyecto</th><th>Descripcion</th><th>Vehiculos</th><th>Estado</th><th class="text-right">Acciones</th></tr></thead>
          <tbody>
            <tr v-for="project in projects.data" :key="project.id" class="border-t border-slate-100">
              <td class="px-3 py-3 font-semibold text-slate-950">{{ project.name }}</td>
              <td>{{ project.description ?? '-' }}</td>
              <td>{{ project.vehicles_count }}</td>
              <td><span class="rounded px-2 py-1" :class="project.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">{{ project.status }}</span></td>
              <td class="text-right"><Link :href="`/vehiculos/proyectos/${project.id}/edit`" class="mr-2 inline-flex rounded-md border border-slate-200 p-2 text-[#123f6e]"><Pencil class="h-4 w-4" /></Link><button @click="deactivate(project)" class="inline-flex cursor-pointer rounded-md border border-red-200 p-2 text-red-700 transition-colors hover:bg-red-50"><Trash2 class="h-4 w-4" /></button></td>
            </tr>
            <tr v-if="!projects.data.length"><td colspan="5" class="px-3 py-8 text-center text-slate-500">Sin proyectos.</td></tr>
          </tbody>
        </table>
      </div>

      <div class="space-y-2 sm:hidden">
        <div v-for="project in projects.data" :key="project.id" class="rounded border border-slate-100 bg-slate-50 p-3 text-sm">
          <div class="mb-2 flex items-start justify-between gap-2"><div class="font-semibold text-slate-950">{{ project.name }}</div><span class="shrink-0 rounded px-2 py-1 text-xs" :class="project.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">{{ project.status }}</span></div>
          <div class="text-xs text-slate-600">{{ project.description ?? 'Sin descripcion' }}</div>
          <div class="mt-1 text-xs text-slate-600"><span class="font-medium text-slate-700">Vehiculos:</span> {{ project.vehicles_count }}</div>
          <div class="mt-2 flex gap-2"><Link :href="`/vehiculos/proyectos/${project.id}/edit`" class="inline-flex rounded-md border border-slate-200 p-2 text-[#123f6e]"><Pencil class="h-4 w-4" /></Link><button @click="deactivate(project)" class="inline-flex cursor-pointer rounded-md border border-red-200 p-2 text-red-700 transition-colors hover:bg-red-50"><Trash2 class="h-4 w-4" /></button></div>
        </div>
        <p v-if="!projects.data.length" class="py-4 text-center text-sm text-slate-500">Sin proyectos.</p>
      </div>

      <div v-if="projects.last_page > 1" class="mt-5 flex flex-wrap items-center justify-center gap-1 sm:gap-2">
        <Link v-for="link in projects.links" :key="link.label" :href="link.url || '#'" preserve-scroll class="rounded-md border px-2 py-2 text-xs font-semibold sm:px-3 sm:text-sm" :class="[link.active ? 'border-[#123f6e] bg-[#123f6e] text-white' : 'border-slate-200 bg-white text-slate-700', !link.url ? 'pointer-events-none opacity-40' : 'hover:bg-[#edf3fa]']" v-html="link.label" />
      </div>
    </section>
  </AppLayout>
</template>
