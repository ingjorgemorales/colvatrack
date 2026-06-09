<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { AlertTriangle, Bell, CheckCircle2, Clock3, Pencil, Plus, PlugZap, RadioTower, Trash2, XCircle } from '@lucide/vue';

const props = defineProps({ providers: Object });
const testForms = Object.fromEntries((props.providers.data ?? []).map(p => [p.id, useForm({ moviles: firstMovil(p) })]));

function firstMovil(provider) {
  const moviles = provider.config_json?.moviles ?? '';
  if (Array.isArray(moviles)) return moviles[0] ?? '';
  return String(moviles || '').split(',').map(value => value.trim()).filter(Boolean)[0] ?? 'WPQ084';
}

const test = (provider) => testForms[provider.id].post(`/configuracion/gps/${provider.id}/test`, { preserveScroll: true });
const deactivate = (provider) => { if (confirm(`Desactivar proveedor ${provider.name}?`)) router.delete(`/configuracion/gps/${provider.id}`); };
const safeLimitClass = (provider) => Number(provider.daily_estimate || 0) <= Number(provider.daily_limit || 0) ? 'text-emerald-700' : 'text-red-700';
const statusIcon = (status) => status === 'success' ? CheckCircle2 : status === 'error' ? XCircle : Clock3;
const statusClass = (status) => status === 'success' ? 'text-emerald-700' : status === 'error' ? 'text-red-700' : 'text-slate-500';
</script>

<template>
  <Head title="Proveedores GPS" />
  <AppLayout title="Configuracion GPS y alertas">
    <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-lg font-bold text-slate-950">Proveedor activo, consumo API y reglas operativas</h2>
          <p class="mt-1 text-sm text-slate-600">El scheduler puede revisar cada segundo, pero el proveedor define cada cuantos segundos se consulta realmente la API.</p>
        </div>
        <Link href="/configuracion/gps/create" class="inline-flex items-center justify-center gap-2 rounded-md bg-[#123f6e] px-4 py-3 font-semibold text-white"><Plus class="h-5 w-5" /> Nuevo proveedor</Link>
      </div>

      <div class="grid gap-4 xl:grid-cols-2">
        <article v-for="p in providers.data" :key="p.id" class="rounded-md border border-slate-200 p-4">
          <div class="mb-4 flex items-start justify-between gap-3">
            <div class="min-w-0">
              <h2 class="flex items-center gap-2 font-semibold text-slate-950"><RadioTower class="h-5 w-5 text-[#123f6e]" /> {{ p.name }}</h2>
              <p class="break-all text-sm text-slate-500">{{ p.base_url }}</p>
            </div>
            <span class="rounded px-2 py-1 text-xs font-bold" :class="p.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">{{ p.status }}</span>
          </div>

          <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div><dt class="text-slate-500">Cliente</dt><dd class="font-medium">{{ p.client_code ?? '-' }}</dd></div>
            <div><dt class="text-slate-500">Vehiculos asociados</dt><dd class="font-medium">{{ p.vehicles_count }}</dd></div>
            <div><dt class="text-slate-500">Intervalo real</dt><dd class="font-semibold text-[#123f6e]">{{ p.request_interval_seconds }}s</dd></div>
            <div><dt class="text-slate-500">Peticiones/dia estimadas</dt><dd class="font-semibold" :class="safeLimitClass(p)">{{ p.daily_estimate }} / {{ p.daily_limit }}</dd></div>
            <div><dt class="text-slate-500">Peticiones hoy</dt><dd class="font-medium">{{ p.today_requests }} total, {{ p.today_success_requests }} ok, {{ p.today_error_requests }} error</dd></div>
            <div><dt class="text-slate-500">Intervalo minimo recomendado</dt><dd class="font-medium">{{ p.recommended_interval_seconds }}s</dd></div>
            <div><dt class="text-slate-500">Ultimo exito</dt><dd>{{ p.last_success_at ?? '-' }}</dd></div>
            <div><dt class="text-slate-500">Ultimo error</dt><dd>{{ p.last_error_at ?? '-' }}</dd></div>
          </dl>

          <div class="mt-4 rounded-md bg-slate-50 p-3 text-sm">
            <div class="mb-2 flex items-center gap-2 font-bold" :class="statusClass(p.last_log?.status)">
              <component :is="statusIcon(p.last_log?.status)" class="h-4 w-4" /> Ultima peticion API: {{ p.last_log?.status ?? 'sin registros' }}
            </div>
            <div class="grid gap-2 sm:grid-cols-3">
              <span>Codigo: <b>{{ p.last_log?.response_code ?? '-' }}</b></span>
              <span>Respuesta: <b>{{ p.last_log?.response_count ?? '-' }}</b></span>
              <span>Fecha: <b>{{ p.last_log?.requested_at ?? '-' }}</b></span>
            </div>
          </div>

          <div class="mt-4 rounded-md border border-slate-200 p-3 text-sm">
            <div class="mb-2 flex items-center gap-2 font-bold text-[#123f6e]"><Bell class="h-4 w-4" /> Alertas operativas</div>
            <div class="grid gap-2 sm:grid-cols-2">
              <span>Estado: <b :class="p.alert_config?.enabled ? 'text-emerald-700' : 'text-slate-500'">{{ p.alert_config?.enabled ? 'activas' : 'desactivadas' }}</b></span>
              <span>Correo: <b>{{ p.alert_config?.email_enabled ? 'si' : 'no' }}</b></span>
              <span>GPS vencido: <b>{{ p.alert_config?.gps_stale_after_minutes }} min</b></span>
              <span>Repeticion: <b>{{ p.alert_config?.repeat_minutes }} min</b></span>
              <span>Pendientes: <b>{{ p.alert_config?.request_pending_alert_minutes }} min</b></span>
              <span>En camino: <b>{{ p.alert_config?.request_en_route_alert_minutes }} min</b></span>
            </div>
          </div>

          <div v-if="Number(p.daily_estimate || 0) > Number(p.daily_limit || 0)" class="mt-4 flex items-start gap-2 rounded-md bg-red-50 p-3 text-sm text-red-700">
            <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
            <span>Este intervalo puede superar el limite diario. Sube el intervalo al menos a {{ p.recommended_interval_seconds }}s.</span>
          </div>

          <form class="mt-4 flex gap-2" @submit.prevent="test(p)">
            <input v-model="testForms[p.id].moviles" class="min-w-0 flex-1 rounded-md border border-slate-300 px-3 py-2" placeholder="WPQ084,ZGA89H" />
            <button class="inline-flex items-center gap-2 rounded-md border border-[#123f6e] px-3 py-2 font-semibold text-[#123f6e]"><PlugZap class="h-4 w-4" /> Probar</button>
          </form>

          <div class="mt-4 flex gap-2">
            <Link :href="`/configuracion/gps/${p.id}/edit`" class="inline-flex rounded-md border border-slate-200 p-2 text-[#123f6e]"><Pencil class="h-4 w-4" /></Link>
            <button @click="deactivate(p)" class="inline-flex rounded-md border border-red-200 p-2 text-red-700"><Trash2 class="h-4 w-4" /></button>
          </div>
        </article>
      </div>
    </section>
  </AppLayout>
</template>