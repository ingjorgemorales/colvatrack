<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Eye, EyeOff, KeyRound, Save } from '@lucide/vue';
const props = defineProps({ profile: Object });
const profileForm = useForm({ phone: props.profile.phone ?? '', cargo: props.profile.cargo ?? '' });
const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });
const showCurrent = ref(false);
const showNew = ref(false);
const showConfirm = ref(false);
const passwordsMatch = () => passwordForm.password && passwordForm.password === passwordForm.password_confirmation;
</script>
<template>
  <Head title="Perfil" />
  <AppLayout title="Perfil">
    <section class="grid gap-6 xl:grid-cols-2">
      <form class="rounded-md border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="profileForm.patch('/perfil')">
        <h2 class="mb-4 text-lg font-semibold text-[#123f6e]">Datos personales</h2>
        <div class="grid gap-4"><label><span class="text-sm font-medium text-slate-600">Nombre</span><input :value="`${profile.name} ${profile.last_name ?? ''}`" disabled class="mt-1 w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-3" /></label><label><span class="text-sm font-medium text-slate-600">Rol</span><input :value="profile.role?.name" disabled class="mt-1 w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-3" /></label><label><span class="text-sm font-medium text-slate-600">Telefono</span><input v-model="profileForm.phone" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3" /></label><label><span class="text-sm font-medium text-slate-600">Cargo</span><input v-model="profileForm.cargo" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3" /></label><label><span class="text-sm font-medium text-slate-600">Vehiculo asignado</span><input :value="profile.assigned_vehicle?.plate ?? 'No aplica'" disabled class="mt-1 w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-3" /></label></div>
        <button class="mt-5 inline-flex items-center gap-2 rounded-md bg-[#123f6e] px-5 py-3 font-semibold text-white"><Save class="h-5 w-5" /> Guardar perfil</button>
      </form>
      <form class="rounded-md border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="passwordForm.patch('/perfil/password')">
        <h2 class="mb-4 flex items-center gap-2 text-lg font-semibold text-[#123f6e]"><KeyRound class="h-5 w-5" /> Cambiar contraseña</h2>
        <div class="grid gap-4">
          <div class="relative"><input v-model="passwordForm.current_password" :type="showCurrent ? 'text' : 'password'" placeholder="Contrasena actual" class="w-full rounded-md border border-slate-300 px-3 py-3 pr-10" /><button type="button" @click="showCurrent = !showCurrent" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700"><component :is="showCurrent ? EyeOff : Eye" class="h-5 w-5" /></button></div>
          <div class="relative"><input v-model="passwordForm.password" :type="showNew ? 'text' : 'password'" placeholder="Nueva contraseña" class="w-full rounded-md border border-slate-300 px-3 py-3 pr-10" /><button type="button" @click="showNew = !showNew" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700"><component :is="showNew ? EyeOff : Eye" class="h-5 w-5" /></button></div>
          <div class="relative"><input v-model="passwordForm.password_confirmation" :type="showConfirm ? 'text' : 'password'" placeholder="Confirmar nueva contraseña" class="w-full rounded-md border border-slate-300 px-3 py-3 pr-10" /><button type="button" @click="showConfirm = !showConfirm" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700"><component :is="showConfirm ? EyeOff : Eye" class="h-5 w-5" /></button></div>
        </div>
        <div v-if="Object.keys(passwordForm.errors).length" class="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-700"><p v-for="error in passwordForm.errors">{{ error }}</p></div>
        <p v-if="passwordForm.password_confirmation && !passwordsMatch()" class="mt-2 text-sm text-red-600">Las contraseñas no coinciden</p>
        <button class="mt-5 inline-flex items-center gap-2 rounded-md bg-[#123f6e] px-5 py-3 font-semibold text-white" :disabled="passwordForm.processing || !passwordsMatch()"><Save class="h-5 w-5" /> Actualizar contraseña</button>
      </form>
    </section>
  </AppLayout>
</template>
