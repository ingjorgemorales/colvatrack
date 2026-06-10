<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Eye, EyeOff } from '@lucide/vue';
const showPassword = ref(false);
const showConfirm = ref(false);
const form = useForm({ password: '', password_confirmation: '' });
const passwordsMatch = () => form.password && form.password === form.password_confirmation;
const submit = () => {
  if (!passwordsMatch()) return;
  form.post('/password/change');
};
</script>
<template>
  <Head title="Cambiar contraseña" />
  <main class="grid min-h-screen place-items-center bg-[#eef2f7] px-4">
    <section class="w-full max-w-md rounded-lg bg-white p-8 shadow-sm ring-1 ring-slate-200">
      <img :src="'/images/logo-login.png'" alt="Colvatel" class="mx-auto mb-8 max-h-20 object-contain" />
      <h1 class="mb-2 text-2xl font-semibold text-[#123f6e]">Cambio obligatorio de contraseña</h1>
      <form class="space-y-4" @submit.prevent="submit">
        <div class="relative"><input v-model="form.password" :type="showPassword ? 'text' : 'password'" placeholder="Nueva contraseña" class="w-full rounded-md border border-slate-300 px-3 py-3 pr-10" /><button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer text-slate-500 hover:text-slate-700"><component :is="showPassword ? EyeOff : Eye" class="h-5 w-5" /></button></div>
        <div class="relative"><input v-model="form.password_confirmation" :type="showConfirm ? 'text' : 'password'" placeholder="Confirmar contraseña" class="w-full rounded-md border border-slate-300 px-3 py-3 pr-10" /><button type="button" @click="showConfirm = !showConfirm" class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer text-slate-500 hover:text-slate-700"><component :is="showConfirm ? EyeOff : Eye" class="h-5 w-5" /></button></div>
        <p v-if="form.password_confirmation && !passwordsMatch()" class="text-sm text-red-600">Las contraseñas no coinciden</p>
        <p v-for="error in form.errors" class="text-sm text-red-600">{{ error }}</p>
        <button class="w-full cursor-pointer rounded-md bg-[#123f6e] px-4 py-3 font-semibold text-white transition-colors hover:bg-[#0e2d52]" :disabled="form.processing || !passwordsMatch()">Actualizar</button>
      </form>
    </section>
  </main>
</template>

