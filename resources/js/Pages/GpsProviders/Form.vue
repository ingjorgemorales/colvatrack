<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeft, Bell, Mail, Plus, Save, ShieldCheck, Timer, X } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps({ provider: Object, defaults: Object });
const isEdit = !!props.provider;
const config = props.provider?.config_json ?? props.defaults ?? {};
const alerts = config.alerts ?? props.defaults?.alerts ?? {};
const rawMoviles = Array.isArray(config.moviles) ? config.moviles.join(',') : (config.moviles ?? '');

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
  moviles: rawMoviles,
  alerts_enabled: alerts.enabled ?? true,
  alerts_email_enabled: alerts.email_enabled ?? false,
  gps_stale_after_minutes: alerts.gps_stale_after_minutes ?? 15,
  request_pending_alert_minutes: alerts.request_pending_alert_minutes ?? 30,
  request_en_route_alert_minutes: alerts.request_en_route_alert_minutes ?? 60,
  inventory_low_stock_threshold: alerts.inventory_low_stock_threshold ?? 1,
  repeat_minutes: alerts.repeat_minutes ?? 60,
  config_json: '',
});

const plateInput = ref('');
const plates = ref(parseMoviles(rawMoviles));
const dailyEstimate = computed(() => Math.ceil(86400 / Math.max(Number(form.request_interval_seconds || 1), 1)));
const recommendedInterval = computed(() => Math.ceil(86400 / Math.max(Number(form.daily_limit || 1), 1)));
const overLimit = computed(() => dailyEstimate.value > Number(form.daily_limit || 0));
const plateCount = computed(() => plates.value.length);

function normalizePlate(value) {
  return String(value ?? '').trim().toUpperCase().replace(/\s+/g, '');
}

function parseMoviles(value) {
  const source = Array.isArray(value) ? value.join(',') : String(value ?? '');
  return [...new Set(source.split(/[\n,;]+/).map(normalizePlate).filter(Boolean))];
}

function syncMoviles() {
  form.moviles = plates.value.join(',');
}

function addPlatesFromText(value = plateInput.value) {
  const incoming = parseMoviles(value);
  if (!incoming.length) return;
  const existing = new Set(plates.value);
  incoming.forEach((plate) => {
    if (!existing.has(plate)) {
      plates.value.push(plate);
      existing.add(plate);
    }
  });
  plateInput.value = '';
  syncMoviles();
}

function handlePlateKeydown(event) {
  if (event.key === 'Enter' || event.key === ',') {
    event.preventDefault();
    addPlatesFromText();
  }
}

function handlePlatePaste(event) {
  const text = event.clipboardData?.getData('text') ?? '';
  if (text.includes(',') || text.includes('\n') || text.includes(';')) {
    event.preventDefault();
    addPlatesFromText(text);
  }
}

function removePlate(index) {
  plates.value.splice(index, 1);
  syncMoviles();
}

function clearPlates() {
  if (!confirm('Quitar todos los moviles configurados?')) return;
  plates.value = [];
  syncMoviles();
}


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
  if (plateInput.value.trim()) addPlatesFromText();
  syncMoviles();

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
          <div class="md:col-span-2">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
              <span class="font-medium text-slate-700">Moviles a consultar</span>
              <span class="rounded bg-[#edf3fa] px-3 py-1 text-xs font-semibold text-[#123f6e]">{{ plateCount }} placas configuradas</span>
            </div>
            <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
              <div class="flex flex-col gap-2 sm:flex-row">
                <input
                  v-model="plateInput"
                  class="min-w-0 flex-1 rounded-md border border-slate-300 bg-white px-3 py-3 uppercase outline-none focus:border-[#123f6e]"
                  placeholder="Escribe una placa y presiona Enter"
                  @keydown="handlePlateKeydown"
                  @paste="handlePlatePaste"
                />
                <button type="button" @click="addPlatesFromText()" class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-md bg-[#123f6e] px-4 py-3 font-semibold text-white transition-colors hover:bg-[#0e2d52]">
                  <Plus class="h-4 w-4" /> Agregar
                </button>
              </div>
              <p class="mt-2 text-xs text-slate-500">Puedes pegar varias placas separadas por coma, punto y coma o salto de linea.</p>

              <div v-if="plates.length" class="mt-4 max-h-72 overflow-y-auto rounded-md border border-slate-200 bg-white p-3">
                <div class="flex flex-wrap gap-2">
                  <span v-for="(plate, index) in plates" :key="plate" class="inline-flex items-center gap-2 rounded-full border border-[#c7d7ea] bg-[#edf3fa] px-3 py-1.5 text-sm font-semibold text-[#123f6e]">
                    {{ plate }}
                    <button type="button" @click="removePlate(index)" class="cursor-pointer rounded-full p-0.5 text-slate-500 transition-colors hover:bg-white hover:text-red-600" :aria-label="`Quitar ${plate}`">
                      <X class="h-3.5 w-3.5" />
                    </button>
                  </span>
                </div>
              </div>
              <div v-else class="mt-4 rounded-md border border-dashed border-slate-300 bg-white px-4 py-6 text-center text-sm text-slate-500">
                Aun no hay moviles configurados para consultar.
              </div>

              <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                <details class="text-xs text-slate-500">
                  <summary class="cursor-pointer font-semibold text-[#123f6e]">Ver cadena enviada a la API</summary>
                  <textarea :value="form.moviles" readonly rows="3" class="mt-2 w-full min-w-[280px] rounded-md border border-slate-200 bg-white px-3 py-2 font-mono text-xs text-slate-600"></textarea>
                </details>
                <button v-if="plates.length" type="button" @click="clearPlates" class="cursor-pointer rounded-md border border-red-200 px-3 py-2 text-xs font-semibold text-red-700 transition-colors hover:bg-red-50">Quitar todas</button>
              </div>
            </div>
          </div>
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
