<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Eye, EyeOff } from '@lucide/vue';
const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const showPassword = ref(false);
const acceptPolicy = ref(false);
const form = useForm({ email: '', password: '', remember: false });
const submit = () => form.post('/login');
</script>
<template>
  <Head title="Login" />
  <main class="grid min-h-screen place-items-center bg-[#eef2f7] px-4">
    <section class="w-full max-w-md rounded-lg bg-white p-8 shadow-sm ring-1 ring-slate-200">
      <img :src="'/images/logo-login.png'" alt="Colvatel" class="mx-auto mb-8 max-h-24 object-contain" />
      <h1 class="mb-1 text-center text-3xl font-semibold text-[#123f6e]">ColvaTrack</h1>
      <p class="mb-6 text-center text-sm text-slate-500">Gestion GPS, inventario y solicitudes tecnicas</p>
      <div v-if="flash.success" class="mb-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ flash.success }}</div>
      <form class="space-y-4" @submit.prevent="submit">
        <label class="block"><span class="text-sm font-medium text-slate-700">Correo</span><input v-model="form.email" type="email" placeholder="usuario@colvatel.com" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3 outline-none focus:border-[#123f6e]" required /></label>
        <label class="block"><span class="text-sm font-medium text-slate-700">Contraseña</span><div class="relative mt-1"><input v-model="form.password" :type="showPassword ? 'text' : 'password'" placeholder="Ingrese su contraseña" class="w-full rounded-md border border-slate-300 px-3 py-3 pr-10 outline-none focus:border-[#123f6e]" required /><button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700"><component :is="showPassword ? EyeOff : Eye" class="h-5 w-5" /></button></div></label>
        <p v-if="form.errors.email" class="text-sm text-red-600">{{ form.errors.email }}</p>
        <label class="flex items-start gap-2 text-xs text-slate-600"><input v-model="acceptPolicy" type="checkbox" class="mt-0.5 shrink-0 rounded border-slate-300 text-[#123f6e] focus:ring-[#123f6e]" /><span>Acepto la <a href="https://colvatel.com.co/wp-content/uploads/2025/07/E01.D05.-Politica-de-Datos-Personales.pdf" target="_blank" class="font-medium text-[#123f6e] underline hover:text-[#0d3158]">politica de tratamiento de datos personales</a> y los <a href="/terminos" class="font-medium text-[#123f6e] underline hover:text-[#0d3158]">terminos y condiciones</a>.</span></label>
        <button class="w-full rounded-md bg-[#123f6e] px-4 py-3 font-semibold text-white hover:bg-[#0d3158]" :disabled="form.processing || !acceptPolicy">Ingresar</button>
        <Link href="/forgot-password" class="block text-center text-sm font-medium text-[#123f6e]">Recuperar contraseña</Link>
      </form>
      <p class="mt-6 text-center text-xs text-slate-500">&copy; 2026 Colvatel S.A. &mdash; Todos los derechos reservados</p>
      <p class="mt-1 text-center text-xs text-slate-400">Desarrollado por el equipo de TI</p>
    </section>
  </main>
</template>
