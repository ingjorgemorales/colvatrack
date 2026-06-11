<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeft, Bell, Mail, Save, ShieldCheck, Timer } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps({ provider: Object, defaults: Object });
const isEdit = !!props.provider;
const config = props.provider?.config_json ?? props.defaults ?? {};
const alerts = config.alerts ?? props.defaults?.alerts ?? {};

const form = useForm({
  name: props.provider?.name ?? '',
  base_url: props.provider?.base_url ?? 'http://apis.gservicetrack.com:1880/triplog/',
  client_code: props.provider?.client_code ?? 'trackingvip',
  api_key_encrypted: '',
  request_interval_seconds: props.provider?.request_interval_seconds ?? 11,
  daily_limit: props.provider?.daily_limit ?? 8000,
  status: props.provider?.status ?? 'active',
  accion: config.accion ?? 'lastposition',
  header: config.header ?? 'x-api-key',
  moviles: Array.isArray(config.moviles) ? config.moviles.join(',') : (config.moviles ?? ''),
  alerts_enabled: alerts.enabled ?? true,
  alerts_email_enabled: alerts.email_enabled ?? false,
  gps_stale_after_minutes: alerts.gps_stale_after_minutes ?? 15,
  request_pending_alert_minutes: alerts.request_pending_alert_minutes ?? 30,
  request_en_route_alert_minutes: alerts.request_en_route_alert_minutes ?? 60,
  inventory_low_stock_threshold: alerts.inventory_low_stock_threshold ?? 1,
  repeat_minutes: alerts.repeat_minutes ?? 60,
  config_json: '',
});

const dailyEstimate = computed(() => Math.ceil(86400 / Math.max(Number(form.request_interval_seconds || 1), 1)));
const recommendedInterval = computed(() => Math.ceil(86400 / Math.max(Number(form.daily_limit || 1), 1)));
const overLimit = computed(() => dailyEstimate.value > Number(form.daily_limit || 0));

function buildConfig() {
  return {
    header: form.header || 'x-api-key',
    accion: form.accion || 'lastposition',
    moviles: form.moviles || '',
    alerts: {
      enabled: Boolean(form.alerts_enabled),
      email_enabled: Boolean(form.alerts_email_enabled),
      gps_stale_after_minutes: Number(form.gps_stale_after_minutes || 15),
      request_pending_alert_minutes: Number(form.request_pending_alert_minutes || 30),
      request_en_route_alert_minutes: Number(form.request_en_route_alert_minutes || 60),
      inventory_low_stock_threshold: Number(form.inventory_low_stock_threshold || 1),
      repeat_minutes: Number(form.repeat_minutes || 60),
    },
  };
}

function submit() {
  form.transform((data) => ({
    ...data,
    config_json: JSON.stringify(buildConfig()),
  }));

  if (isEdit) {
    form.patch(`/configuracion/gps/${props.provider.id}`);
  } else {
    form.post('/configuracion/gps');
  }
}
</script>
<template>
  <Head :title="isEdit ? 'Editar proveedor GPS' : 'Nuevo proveedor GPS'" />
  <AppLayout :title="isEdit ? 'Editar proveedor GPS' : 'Nuevo proveedor GPS'">
    <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
      <Link href="/configuracion/gps" class="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-[#123f6e]"><ArrowLeft class="h-4 w-4" /> Volver</Link>
      <form class="grid gap-5" @submit.prevent="submit">
        <div class="grid gap-4 md:grid-cols-2">
          <label><span>Nombre proveedor</span><input v-model="form.name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3" /></label>
          <label><span>Cliente</span><input v-model="form.client_code" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3" /></label>
          <label class="md:col-span-2"><span>URL base</span><input v-model="form.base_url" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3" /></label>
          <label><span>Header API key</span><input v-model="form.header" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3" /></label>
          <label><span>Accion API</span><input v-model="form.accion" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3" /></label>
          <label class="md:col-span-2"><span>API key</span><input v-model="form.api_key_encrypted" type="password" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3" /><small class="mt-1 block text-xs text-slate-500">En edicion puedes dejarla vacia para conservar la clave actual.</small></label>
          <label class="md:col-span-2"><span>Moviles a consultar</span><textarea v-model="form.moviles" rows="5" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3 font-mono text-sm" placeholder="PLACA1,PLACA2,PLACA3"></textarea></label>
        </div>

        <div class="rounded-md border border-slate-200 bg-slate-50 p-4">
          <h2 class="mb-4 flex items-center gap-2 text-base font-bold text-[#123f6e]"><Timer class="h-5 w-5" /> Frecuencia de sincronizacion</h2>
          <div class="grid gap-4 md:grid-cols-2">
            <label><span>Intervalo consulta segundos</span><input v-model="form.request_interval_seconds" type="number" min="10" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3" /></label>
            <label><span>Limite diario API</span><input v-model="form.daily_limit" type="number" min="1" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3" /></label>
            <label><span>Estado</span><select v-model="form.status" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3"><option value="active">Activo</option><option value="inactive">Inactivo</option></select></label>
            <div class="rounded-md bg-white p-3 text-sm">
              <div class="flex items-center gap-2 font-bold" :class="overLimit ? 'text-red-700' : 'text-emerald-700'"><ShieldCheck class="h-4 w-4" /> {{ dailyEstimate }} peticiones/dia estimadas</div>
              <p class="mt-1 text-slate-600">Con limite {{ form.daily_limit }}, el intervalo minimo recomendado es {{ recommendedInterval }}s.</p>
            </div>
          </div>
        </div>

        <div class="rounded-md border border-slate-200 bg-white p-4">
          <h2 class="mb-4 flex items-center gap-2 text-base font-bold text-[#123f6e]"><Bell class="h-5 w-5" /> Alertas operativas</h2>
          <div class="mb-4 flex flex-wrap gap-4 text-sm font-semibold text-slate-700">
            <label class="inline-flex items-center gap-2"><input v-model="form.alerts_enabled" type="checkbox" class="h-4 w-4 rounded border-slate-300" /> Activar alertas</label>
            <label class="inline-flex items-center gap-2"><input v-model="form.alerts_email_enabled" type="checkbox" class="h-4 w-4 rounded border-slate-300" /> <Mail class="h-4 w-4" /> Enviar tambien por correo</label>
          </div>
          <div class="grid gap-4 md:grid-cols-3">
            <label><span>GPS vencido despues de minutos</span><input v-model="form.gps_stale_after_minutes" type="number" min="1" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3" /></label>
            <label><span>Solicitud pendiente despues de minutos</span><input v-model="form.request_pending_alert_minutes" type="number" min="1" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3" /></label>
            <label><span>En camino despues de minutos</span><input v-model="form.request_en_route_alert_minutes" type="number" min="1" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3" /></label>
            <label><span>Inventario bajo si disponible es menor o igual</span><input v-model="form.inventory_low_stock_threshold" type="number" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3" /></label>
            <label><span>Repetir misma alerta cada minutos</span><input v-model="form.repeat_minutes" type="number" min="1" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3" /></label>
          </div>
        </div>

          <div v-if="Object.keys(form.errors).length" class="rounded-md bg-red-50 p-3 text-sm text-red-700"><p v-for="error in form.errors" :key="error">{{ error }}</p></div>
        <div><button class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-md bg-[#123f6e] px-5 py-3 font-semibold text-white transition-colors hover:bg-[#0e2d52] sm:w-auto"><Save class="h-5 w-5" /> Guardar proveedor</button></div>
      </form>
    </section>
  </AppLayout>
</template>