<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, Home, RefreshCcw, SearchX, ServerCrash, ShieldAlert } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps({
  status: {
    type: Number,
    required: true,
  },
});

const pages = {
  403: {
    title: 'Acceso no autorizado',
    message: 'Tu usuario no tiene permisos para entrar a esta opcion. Si necesitas acceso, solicita que revisen tu rol o permisos.',
    icon: ShieldAlert,
    tone: 'amber',
  },
  404: {
    title: 'Pagina no encontrada',
    message: 'La ruta que intentas abrir no existe o fue movida dentro de ColvaTrack.',
    icon: SearchX,
    tone: 'blue',
  },
  419: {
    title: 'Sesion vencida',
    message: 'La sesion expiro por seguridad. Recarga la pantalla e intenta nuevamente.',
    icon: RefreshCcw,
    tone: 'blue',
  },
  500: {
    title: 'Error del sistema',
    message: 'Ocurrio un problema inesperado. Ya puedes intentar de nuevo; si continua, revisa los logs del servidor.',
    icon: ServerCrash,
    tone: 'red',
  },
  503: {
    title: 'Servicio temporalmente no disponible',
    message: 'ColvaTrack esta ocupado o en mantenimiento. Espera un momento e intenta nuevamente.',
    icon: AlertTriangle,
    tone: 'amber',
  },
};

const error = computed(() => pages[props.status] ?? {
  title: 'Algo no salio bien',
  message: 'No fue posible completar la accion solicitada.',
  icon: AlertTriangle,
  tone: 'blue',
});

const toneClass = computed(() => ({
  amber: 'border-amber-200 bg-amber-50 text-amber-700',
  blue: 'border-blue-200 bg-blue-50 text-[#123f6e]',
  red: 'border-red-200 bg-red-50 text-red-700',
})[error.value.tone]);

function goBack() {
  const referrer = document.referrer;

  if (referrer && referrer !== window.location.href) {
    try {
      const previous = new URL(referrer);
      if (previous.origin === window.location.origin) {
        router.visit(`${previous.pathname}${previous.search}${previous.hash}`);
        return;
      }
    } catch {}
  }

  router.visit('/dashboard');
}
</script>

<template>
  <Head :title="`${status} - ${error.title}`" />

  <main class="grid min-h-screen place-items-center bg-[#eef2f7] px-4 py-10">
    <section class="w-full max-w-2xl rounded-md border border-slate-200 bg-white p-6 text-center shadow-sm sm:p-8">
      <img :src="'/images/logo-login.png'" alt="ColvaTrack" class="mx-auto mb-6 h-16 max-w-56 object-contain" />

      <div class="mx-auto mb-5 grid h-16 w-16 place-items-center rounded-full border" :class="toneClass">
        <component :is="error.icon" class="h-8 w-8" />
      </div>

      <p class="text-sm font-bold uppercase tracking-wide text-slate-400">Error {{ status }}</p>
      <h1 class="mt-2 text-3xl font-bold text-[#123f6e]">{{ error.title }}</h1>
      <p class="mx-auto mt-3 max-w-xl text-base leading-7 text-slate-600">{{ error.message }}</p>

      <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
        <button
          type="button"
          @click="router.visit('/dashboard')"
          class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-md bg-[#123f6e] px-5 py-3 font-semibold text-white transition-colors hover:bg-[#0e2d52]"
        >
          <Home class="h-5 w-5" /> Ir al dashboard
        </button>
        <button
          type="button"
          @click="goBack"
          class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-5 py-3 font-semibold text-[#123f6e] transition-colors hover:bg-[#edf3fa]"
        >
          <ArrowLeft class="h-5 w-5" /> Volver
        </button>
      </div>

      <Link href="/perfil" class="mt-6 inline-block text-sm font-semibold text-slate-500 hover:text-[#123f6e]">
        Revisar mi perfil y rol
      </Link>
    </section>
  </main>
</template>
