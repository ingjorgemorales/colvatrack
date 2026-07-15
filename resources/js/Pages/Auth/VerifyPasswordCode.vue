<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({ email: String, expiresIn: Number });
const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const remaining = ref(props.expiresIn ?? 120);
let timer = null;
const form = useForm({ email: props.email ?? '', code: '' });
const minutes = computed(() => String(Math.floor(remaining.value / 60)).padStart(2, '0'));
const seconds = computed(() => String(remaining.value % 60).padStart(2, '0'));
const expired = computed(() => remaining.value <= 0);

function submit() {
  form.post('/password-code/verify');
}

function resend() {
  remaining.value = props.expiresIn ?? 120;
  router.post('/forgot-password', { email: form.email }, { preserveScroll: true });
}

onMounted(() => {
  timer = window.setInterval(() => {
    remaining.value = Math.max(0, remaining.value - 1);
  }, 1000);
});

onBeforeUnmount(() => {
  if (timer) window.clearInterval(timer);
});
</script>

<template>
  <Head title="Verificar codigo" />
  <main class="grid min-h-screen place-items-center bg-[#eef2f7] px-4">
    <section class="w-full max-w-md rounded-lg bg-white p-8 shadow-sm ring-1 ring-slate-200">
      <img :src="'/images/logo-login.png'" alt="Colvatel" class="mx-auto mb-8 max-h-20 object-contain" />
      <h1 class="mb-2 text-2xl font-semibold text-[#123f6e]">Verifica tu correo</h1>
      <p class="mb-5 text-sm text-slate-500">Ingresa el codigo de 6 digitos que enviamos a tu correo.</p>
      <div v-if="flash.success" class="mb-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ flash.success }}</div>
      <div class="mb-5 rounded-md bg-[#eef2f7] p-4 text-center">
        <div class="text-xs font-semibold uppercase text-slate-500">Tiempo restante</div>
        <div class="mt-1 text-3xl font-bold text-[#123f6e]">{{ minutes }}:{{ seconds }}</div>
      </div>
      <form class="space-y-4" @submit.prevent="submit">
        <input v-model="form.email" type="email" placeholder="correo@colvatel.com" class="w-full rounded-md border border-slate-300 px-3 py-3 outline-none focus:border-[#123f6e]" />
        <input v-model="form.code" inputmode="numeric" maxlength="6" placeholder="Codigo" class="w-full rounded-md border border-slate-300 px-3 py-3 text-center text-2xl font-bold tracking-[0.45em] outline-none focus:border-[#123f6e]" />
        <p v-for="error in form.errors" :key="error" class="text-sm text-red-600">{{ error }}</p>
        <button class="w-full cursor-pointer rounded-md bg-[#123f6e] px-4 py-3 font-semibold text-white transition-colors hover:bg-[#0e2d52]" :disabled="form.processing || expired">Verificar codigo</button>
        <button type="button" class="w-full cursor-pointer rounded-md border border-[#123f6e] px-4 py-3 font-semibold text-[#123f6e] transition-colors hover:bg-[#edf3fa]" :disabled="!expired || form.processing" @click="resend">Reenviar codigo</button>
        <Link href="/login" class="block text-center text-sm font-medium text-[#123f6e]">Volver al login</Link>
      </form>
    </section>
  </main>
</template>
