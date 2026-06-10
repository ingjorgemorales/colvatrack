<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
const page = usePage(); const flash = computed(() => page.props.flash ?? {});
const form = useForm({ email: '' });
const submit = () => form.post('/forgot-password');
</script>
<template>
  <Head title="Recuperar contraseña" />
  <main class="grid min-h-screen place-items-center bg-[#eef2f7] px-4">
    <section class="w-full max-w-md rounded-lg bg-white p-8 shadow-sm ring-1 ring-slate-200">
      <img :src="'/images/logo-login.png'" alt="Colvatel" class="mx-auto mb-8 max-h-20 object-contain" />
      <h1 class="mb-2 text-2xl font-semibold text-[#123f6e]">Recuperar contraseña</h1>
      <p class="mb-5 text-sm text-slate-500">Enviaremos un enlace de recuperación al correo registrado.</p>
      <div v-if="flash.success" class="mb-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ flash.success }}</div>
      <form class="space-y-4" @submit.prevent="submit">
        <input v-model="form.email" type="email" placeholder="correo@colvatel.com" class="w-full rounded-md border border-slate-300 px-3 py-3 outline-none focus:border-[#123f6e]" />
        <p v-if="form.errors.email" class="text-sm text-red-600">{{ form.errors.email }}</p>
        <button class="w-full cursor-pointer rounded-md bg-[#123f6e] px-4 py-3 font-semibold text-white transition-colors hover:bg-[#0e2d52]">Enviar enlace</button>
        <Link href="/login" class="block text-center text-sm font-medium text-[#123f6e]">Volver al login</Link>
      </form>
    </section>
  </main>
</template>
