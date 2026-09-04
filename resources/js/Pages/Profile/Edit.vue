<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, onBeforeUnmount } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Clock, Eye, EyeOff, KeyRound, Save } from '@lucide/vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({ profile: Object, availability: Object });
const profileForm = useForm({ phone: props.profile.phone ?? '', cargo: props.profile.cargo ?? '' });
const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });
const showCurrent = ref(false);
const showNew = ref(false);
const showConfirm = ref(false);
const passwordsMatch = () => passwordForm.password && passwordForm.password === passwordForm.password_confirmation;

const canLunch = computed(() => props.availability?.can_lunch ?? false);
const hasUsedToday = computed(() => props.availability?.has_used_today ?? false);

const finishTimestamp = computed(() => {
  const start = props.availability?.driver_occupied_at;
  const duration = (props.availability?.duration_minutes ?? 60) * 60000;
  return start ? new Date(start).getTime() + duration : null;
});
const now = ref(Date.now());
const isOccupied = computed(() => props.availability?.is_occupied ?? false);
const remainingMs = computed(() => (finishTimestamp.value && now.value < finishTimestamp.value) ? finishTimestamp.value - now.value : 0);
const countdownLabel = computed(() => formatCountdown(remainingMs.value));

function formatCountdown(ms) {
  const totalSeconds = Math.max(0, Math.floor(ms / 1000));
  const m = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
  const s = String(totalSeconds % 60).padStart(2, '0');
  return `${m}:${s}`;
}

let timer;
function startCountdown() {
  timer = setInterval(() => { now.value = Date.now(); }, 1000);
}
startCountdown();

function activateOccupied() {
  router.post('/perfil/ocupado', {}, {
    onSuccess: () => { now.value = Date.now(); },
    preserveScroll: true,
  });
}

onBeforeUnmount(() => { if (timer) clearInterval(timer); });
</script>
<template>
  <Head title="Perfil" />
  <AppLayout title="Perfil">
    <section class="grid gap-6 xl:grid-cols-2">
      <form class="rounded-md border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="profileForm.patch('/perfil')">
        <h2 class="mb-4 text-lg font-semibold text-[#123f6e]">Datos personales</h2>
        <div class="grid gap-4"><label><span class="text-sm font-medium text-slate-600">Nombre</span><input :value="`${profile.name} ${profile.last_name ?? ''}`" disabled class="mt-1 w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-3" /></label><label><span class="text-sm font-medium text-slate-600">Rol</span><input :value="profile.role?.name" disabled class="mt-1 w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-3" /></label><label><span class="text-sm font-medium text-slate-600">Telefono</span><input v-model="profileForm.phone" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3" /></label><label><span class="text-sm font-medium text-slate-600">Cargo</span><input v-model="profileForm.cargo" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3" /></label><label><span class="text-sm font-medium text-slate-600">Vehiculo asignado</span><input :value="profile.assigned_vehicle?.plate ?? 'No aplica'" disabled class="mt-1 w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-3" /></label></div>
        <button class="mt-5 inline-flex cursor-pointer items-center gap-2 rounded-md bg-[#123f6e] px-5 py-3 font-semibold text-white transition-colors hover:bg-[#0e2d52]"><Save class="h-5 w-5" /> Guardar perfil</button>
      </form>
      <form class="rounded-md border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="passwordForm.patch('/perfil/password')">
        <h2 class="mb-4 flex items-center gap-2 text-lg font-semibold text-[#123f6e]"><KeyRound class="h-5 w-5" /> Cambiar contraseña</h2>
        <div class="grid gap-4">
          <div class="relative"><input v-model="passwordForm.current_password" :type="showCurrent ? 'text' : 'password'" placeholder="Contrasena actual" class="w-full rounded-md border border-slate-300 px-3 py-3 pr-10" /><button type="button" @click="showCurrent = !showCurrent" class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer text-slate-500 hover:text-slate-700"><component :is="showCurrent ? EyeOff : Eye" class="h-5 w-5" /></button></div>
          <div class="relative"><input v-model="passwordForm.password" :type="showNew ? 'text' : 'password'" placeholder="Nueva contraseña" class="w-full rounded-md border border-slate-300 px-3 py-3 pr-10" /><button type="button" @click="showNew = !showNew" class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer text-slate-500 hover:text-slate-700"><component :is="showNew ? EyeOff : Eye" class="h-5 w-5" /></button></div>
          <div class="relative"><input v-model="passwordForm.password_confirmation" :type="showConfirm ? 'text' : 'password'" placeholder="Confirmar nueva contraseña" class="w-full rounded-md border border-slate-300 px-3 py-3 pr-10" /><button type="button" @click="showConfirm = !showConfirm" class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer text-slate-500 hover:text-slate-700"><component :is="showConfirm ? EyeOff : Eye" class="h-5 w-5" /></button></div>
        </div>
        <div v-if="Object.keys(passwordForm.errors).length" class="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-700"><p v-for="error in passwordForm.errors">{{ error }}</p></div>
        <p v-if="passwordForm.password_confirmation && !passwordsMatch()" class="mt-2 text-sm text-red-600">Las contrasenas no coinciden</p>
        <button class="mt-5 inline-flex cursor-pointer items-center gap-2 rounded-md bg-[#123f6e] px-5 py-3 font-semibold text-white transition-colors hover:bg-[#0e2d52]" :disabled="passwordForm.processing || !passwordsMatch()"><Save class="h-5 w-5" /> Actualizar contrasena</button>
      </form>
                        <div v-if="canLunch" class="rounded-md border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
        <h2 class="mb-4 flex items-center gap-2 text-lg font-semibold text-[#123f6e]"><Clock class="h-5 w-5" /> Estado de disponibilidad</h2>
        <div class="mb-3 inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold" :class="isOccupied ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'">
          <span class="h-2.5 w-2.5 rounded-full" :class="isOccupied ? 'bg-red-600' : 'bg-emerald-600'"></span>
          {{ isOccupied ? 'Ocupado' : 'Disponible' }}
        </div>
        <p v-if="isOccupied" class="mb-2 text-sm font-semibold text-[#123f6e]">Tiempo restante: {{ countdownLabel }}</p>
        <p class="mb-4 text-sm text-slate-600">Al activar el estado &quot;Ocupado&quot; no podras desactivarlo manualmente. El sistema lo restablecera automaticamente cuando finalice el tiempo definido.</p>
        <p v-if="hasUsedToday && !isOccupied" class="mb-4 text-sm font-semibold text-amber-600">Ya utilizaste tu hora de almuerzo hoy. Se habilitara nuevamente manana despues de las 12:00 AM.</p>
        <button @click="activateOccupied" :disabled="isOccupied || hasUsedToday" class="inline-flex items-center gap-2 rounded-md px-5 py-3 font-semibold text-white transition-all" :class="(isOccupied || hasUsedToday) ? 'cursor-not-allowed bg-slate-400 opacity-60' : 'cursor-pointer bg-[#123f6e] hover:bg-[#0e2d52]'">
          {{ hasUsedToday && !isOccupied ? 'Ya utilizado hoy' : 'Ocupado' }}
        </button>
      </div>
    </section>
  </AppLayout>
</template>