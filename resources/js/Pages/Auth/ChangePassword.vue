<script setup>
import { Head, useForm } from '@inertiajs/vue3';
const form = useForm({ password: '', password_confirmation: '' });
const submit = () => form.post('/password/change');
</script>
<template>
  <Head title="Cambiar contraseña" />
  <main class="grid min-h-screen place-items-center bg-[#eef2f7] px-4">
    <section class="w-full max-w-md rounded-lg bg-white p-8 shadow-sm ring-1 ring-slate-200">
      <img :src="'/images/logo-login.png'" alt="Colvatel" class="mx-auto mb-8 max-h-20 object-contain" />
      <h1 class="mb-2 text-2xl font-semibold text-[#123f6e]">Cambio obligatorio de contraseña</h1>
      <form class="space-y-4" @submit.prevent="submit">
        <input v-model="form.password" type="password" placeholder="Nueva contraseña" class="w-full rounded-md border border-slate-300 px-3 py-3" />
        <input v-model="form.password_confirmation" type="password" placeholder="Confirmar contraseña" class="w-full rounded-md border border-slate-300 px-3 py-3" />
        <p v-for="error in form.errors" class="text-sm text-red-600">{{ error }}</p>
        <button class="w-full rounded-md bg-[#123f6e] px-4 py-3 font-semibold text-white">Actualizar</button>
      </form>
    </section>
  </main>
</template>

