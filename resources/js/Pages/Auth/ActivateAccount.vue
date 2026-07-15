<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Eye, EyeOff } from '@lucide/vue';

const props = defineProps({ email: String });
const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const showPassword = ref(false);
const showConfirm = ref(false);
const form = useForm({ email: props.email ?? '', code: '', password: '', password_confirmation: '' });
const passwordsMatch = () => form.password && form.password === form.password_confirmation;
const submit = () => {
  if (!passwordsMatch()) return;
  form.post('/activate-account');
};
</script>

<template>
  <Head title="Activar cuenta" />
  <main class="grid min-h-screen place-items-center bg-[#eef2f7] px-4">
    <section class="w-full max-w-md rounded-lg bg-white p-8 shadow-sm ring-1 ring-slate-200">
      <img :src="'/images/logo-login.png'" alt="Colvatel" class="mx-auto mb-8 max-h-20 object-contain" />
      <h1 class="mb-2 text-2xl font-semibold text-[#123f6e]">Activar cuenta</h1>
      <p class="mb-5 text-sm text-slate-500">Usa el codigo que recibiste por correo y crea tu contrasena.</p>
      <div v-if="flash.success" class="mb-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ flash.success }}</div>
      <form class="space-y-4" @submit.prevent="submit">
        <input v-model="form.email" type="email" placeholder="correo@colvatel.com" class="w-full rounded-md border border-slate-300 px-3 py-3 outline-none focus:border-[#123f6e]" />
        <input v-model="form.code" inputmode="numeric" maxlength="6" placeholder="Codigo de activacion" class="w-full rounded-md border border-slate-300 px-3 py-3 text-center text-xl font-bold tracking-[0.35em] outline-none focus:border-[#123f6e]" />
        <div class="relative"><input v-model="form.password" :type="showPassword ? 'text' : 'password'" placeholder="Nueva contrasena" class="w-full rounded-md border border-slate-300 px-3 py-3 pr-10" /><button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer text-slate-500 hover:text-slate-700"><component :is="showPassword ? EyeOff : Eye" class="h-5 w-5" /></button></div>
        <div class="relative"><input v-model="form.password_confirmation" :type="showConfirm ? 'text' : 'password'" placeholder="Confirmar contrasena" class="w-full rounded-md border border-slate-300 px-3 py-3 pr-10" /><button type="button" @click="showConfirm = !showConfirm" class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer text-slate-500 hover:text-slate-700"><component :is="showConfirm ? EyeOff : Eye" class="h-5 w-5" /></button></div>
        <p v-if="form.password_confirmation && !passwordsMatch()" class="text-sm text-red-600">Las contrasenas no coinciden</p>
        <p v-for="error in form.errors" :key="error" class="text-sm text-red-600">{{ error }}</p>
        <button class="w-full cursor-pointer rounded-md bg-[#123f6e] px-4 py-3 font-semibold text-white transition-colors hover:bg-[#0e2d52]" :disabled="form.processing || !passwordsMatch()">Activar cuenta</button>
        <Link href="/login" class="block text-center text-sm font-medium text-[#123f6e]">Volver al login</Link>
      </form>
    </section>
  </main>
</template>
