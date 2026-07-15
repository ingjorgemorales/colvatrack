<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Search, ShieldCheck } from '@lucide/vue';
import { reactive, ref } from 'vue';

const props = defineProps({ logs: Object, filters: Object, users: Array, modules: Array, actions: Array });
const perPage = ref(props.filters?.per_page ?? 10);
const form = reactive({
  module: props.filters?.module || '',
  action: props.filters?.action || '',
  user_id: props.filters?.user_id || '',
  date_from: props.filters?.date_from || '',
  date_to: props.filters?.date_to || '',
});
function search() {
  router.get('/auditoria', { ...form, per_page: perPage.value }, { preserveState: true, replace: true });
}
function clear() {
  Object.keys(form).forEach((key) => { form[key] = ''; });
  perPage.value = 10;
  search();
}
function shortJson(value) {
  if (!value) return '-';
  const text = JSON.stringify(value);
  return text.length > 120 ? `${text.slice(0, 120)}...` : text;
}
function detailJson(log) {
  const details = {
    parametros: log.old_values?.route_parameters ?? undefined,
    datos: log.new_values ?? undefined,
  };
  const entries = Object.entries(details).filter(([, value]) => value);

  return entries.length ? shortJson(Object.fromEntries(entries)) : '-';
}
function actionLabel(action) {
  return {
    post: 'Creacion / envio',
    patch: 'Actualizacion',
    put: 'Actualizacion',
    delete: 'Eliminacion / desactivacion',
  }[action] ?? action;
}
function moduleLabel(module) {
  return {
    seguridad: 'Seguridad',
    solicitudes: 'Solicitudes',
    inventario: 'Inventario',
    vehiculos: 'Vehiculos',
    usuarios: 'Usuarios',
    roles: 'Roles',
    perfil: 'Perfil',
    configuracion: 'Configuracion',
    reportes: 'Reportes',
    chat: 'Chat',
  }[module] ?? module;
}
function actorName(log) {
  const name = `${log.user?.name ?? 'Sistema'} ${log.user?.last_name ?? ''}`.trim();

  return name || 'Sistema';
}
function legacyRoute(log) {
  return String(log.description ?? '').match(/\(([^)]+)\)$/)?.[1] ?? '';
}
function legacyId(log, key) {
  const value = log.old_values?.route_parameters?.[key];
  const match = String(value ?? '').match(/:(\d+)$/);

  return match?.[1] ?? value ?? '-';
}
function movementText(log) {
  const description = String(log.description ?? '');

  if (!/^(POST|PATCH|PUT|DELETE)\s+\//.test(description)) return description || '-';

  const actor = actorName(log);
  const route = legacyRoute(log);

  return {
    'password.update': `${actor} cambio su contrasena.`,
    'perfil.password': `${actor} cambio su contrasena.`,
    'perfil.update': `${actor} actualizo los datos de su perfil.`,
    'inventario.items.store': `${actor} registro la herramienta ${log.new_values?.name ?? 'sin nombre'}.`,
    'inventario.items.update': `${actor} actualizo la herramienta #${legacyId(log, 'item')}.`,
    'inventario.items.status': `${actor} cambio el estado de la herramienta #${legacyId(log, 'item')}.`,
    'inventario.stock.update': `${actor} actualizo inventario del vehiculo #${log.new_values?.vehicle_id ?? '-'}: herramienta #${log.new_values?.inventory_item_id ?? '-'}, total ${log.new_values?.quantity_total ?? '-'}, disponible ${log.new_values?.quantity_available ?? log.new_values?.quantity_total ?? '-'}.`,
    'solicitudes.store': `${actor} creo una solicitud de herramientas para el vehiculo #${log.new_values?.vehicle_id ?? '-'}.`,
    'solicitudes.status': `${actor} cambio la solicitud #${legacyId(log, 'solicitude')} al estado ${log.new_values?.status ?? '-'}.`,
    'solicitudes.chat.store': `${actor} envio un mensaje en la solicitud #${legacyId(log, 'solicitude')}.`,
    'usuarios.store': `${actor} creo el usuario ${log.new_values?.name ?? ''} ${log.new_values?.last_name ?? ''}.`.trim(),
    'usuarios.update': `${actor} actualizo el usuario #${legacyId(log, 'usuario')}.`,
    'usuarios.destroy': `${actor} desactivo el usuario #${legacyId(log, 'usuario')}.`,
    'vehiculos.store': `${actor} creo el vehiculo ${log.new_values?.plate ?? 'sin placa'}.`,
    'vehiculos.update': `${actor} actualizo el vehiculo #${legacyId(log, 'vehiculo')}.`,
    'vehiculos.destroy': `${actor} desactivo el vehiculo #${legacyId(log, 'vehiculo')}.`,
    'roles.store': `${actor} creo el rol ${log.new_values?.name ?? 'sin nombre'}.`,
    'roles.update': `${actor} actualizo el rol #${legacyId(log, 'role')}.`,
    'roles.destroy': `${actor} elimino el rol #${legacyId(log, 'role')}.`,
  }[route] ?? description;
}
</script>

<template>
  <Head title="Auditoria" />
  <AppLayout title="Auditoria">
    <section class="space-y-5">
      <form class="rounded-md border border-slate-200 bg-white p-4 shadow-sm sm:p-5" @submit.prevent="search">
        <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-[#123f6e] sm:text-lg"><Search class="h-5 w-5" /> Filtros</h2>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
          <select v-model="form.module" class="rounded-md border border-slate-300 px-3 py-3"><option value="">Modulo</option><option v-for="module in modules" :key="module" :value="module">{{ moduleLabel(module) }}</option></select>
          <select v-model="form.action" class="rounded-md border border-slate-300 px-3 py-3"><option value="">Accion</option><option v-for="action in actions" :key="action" :value="action">{{ actionLabel(action) }}</option></select>
          <select v-model="form.user_id" class="rounded-md border border-slate-300 px-3 py-3"><option value="">Usuario</option><option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }} {{ user.last_name }}</option></select>
          <input v-model="form.date_from" type="date" class="rounded-md border border-slate-300 px-3 py-3" />
          <input v-model="form.date_to" type="date" class="rounded-md border border-slate-300 px-3 py-3" />
        </div>
        <div class="mt-4 grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center sm:gap-3"><select v-model="perPage" @change="search" class="col-span-2 w-full rounded-md border border-slate-300 px-3 py-3 text-sm sm:w-auto"><option value="10">10 por pag.</option><option value="25">25 por pag.</option><option value="50">50 por pag.</option><option value="100">100 por pag.</option></select><button class="w-full cursor-pointer rounded-md bg-[#123f6e] px-4 py-3 font-semibold text-white transition-colors hover:bg-[#0e2d52] sm:w-auto">Buscar</button><button type="button" class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-md border border-[#123f6e] px-4 py-3 font-semibold text-[#123f6e] transition-colors hover:bg-[#123f6e] hover:text-white sm:w-auto" @click="clear">Limpiar</button></div>
      </form>

      <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3"><h2 class="flex items-center gap-2 text-base font-semibold text-[#123f6e] sm:text-lg"><ShieldCheck class="h-5 w-5" /> Registro de acciones</h2><span class="text-sm text-slate-500">{{ logs.total }} eventos</span></div>
        <div class="hidden sm:block">
          <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-500"><tr><th class="px-3 py-2">Fecha</th><th>Usuario</th><th>Movimiento realizado</th><th>Modulo</th><th>Tipo</th><th>IP</th><th>Detalle tecnico</th></tr></thead>
            <tbody>
              <tr v-for="log in logs.data" :key="log.id" class="border-t border-slate-100 align-top">
                <td class="px-3 py-3 text-slate-600">{{ log.created_at }}</td>
                <td class="py-3"><div class="font-medium text-slate-900">{{ log.user?.name ?? 'Sistema' }} {{ log.user?.last_name ?? '' }}</div><div class="text-xs text-slate-500">{{ log.user?.email }}</div></td>
                <td class="py-3 text-slate-800"><div class="max-w-xl font-medium leading-6">{{ movementText(log) }}</div></td>
                <td class="py-3 text-slate-700">{{ moduleLabel(log.module) }}</td>
                <td class="py-3"><span class="rounded bg-[#e6eef7] px-2 py-1 font-semibold text-[#123f6e]">{{ actionLabel(log.action) }}</span></td>
                <td class="py-3 text-slate-600">{{ log.ip_address }}</td>
                <td class="break-all py-3 text-xs text-slate-500">{{ detailJson(log) }}</td>
              </tr>
              <tr v-if="!logs.data.length"><td colspan="7" class="px-3 py-8 text-slate-500">No hay registros con esos filtros.</td></tr>
            </tbody>
          </table>
        </div>
        <div class="space-y-2 sm:hidden"><div v-for="log in logs.data" :key="log.id" class="rounded border border-slate-200 bg-white p-3 text-sm shadow-sm"><div class="mb-1 flex items-start justify-between gap-2"><div><div class="font-medium text-slate-900">{{ log.user?.name ?? 'Sistema' }} {{ log.user?.last_name ?? '' }}</div><div class="text-xs text-slate-500">{{ log.user?.email }}</div></div><span class="shrink-0 rounded bg-[#e6eef7] px-2 py-1 text-xs font-semibold text-[#123f6e]">{{ actionLabel(log.action) }}</span></div><div class="text-xs text-slate-500 mb-1">{{ log.created_at }}</div><p class="mt-2 text-sm font-medium leading-6 text-slate-800">{{ movementText(log) }}</p><div class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-slate-600"><div><span class="font-medium text-slate-700">Modulo:</span> {{ moduleLabel(log.module) }}</div><div><span class="font-medium text-slate-700">IP:</span> {{ log.ip_address }}</div></div><p v-if="log.new_values || log.old_values" class="mt-1 break-all text-xs text-slate-400">{{ detailJson(log) }}</p></div><p v-if="!logs.data.length" class="py-4 text-center text-sm text-slate-500">No hay registros con esos filtros.</p></div>
        <div v-if="logs.last_page > 1" class="mt-4 flex flex-wrap items-center justify-center gap-1 sm:gap-2"><Link v-for="link in logs.links" :key="link.label" :href="link.url || '#'" preserve-scroll class="rounded-md border px-2 py-2 text-xs font-semibold sm:px-3 sm:text-sm" :class="[link.active ? 'border-[#123f6e] bg-[#123f6e] text-white' : 'border-slate-200 bg-white text-slate-700', !link.url ? 'pointer-events-none opacity-40' : 'hover:bg-[#edf3fa]']" v-html="link.label" /></div>
      </section>
    </section>
  </AppLayout>
</template>
