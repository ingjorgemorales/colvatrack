<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { BarChart3, Bell, Boxes, Car, ClipboardList, LogOut, Map, Menu, Settings, ShieldCheck, UserCog, Users, X } from '@lucide/vue';
import LocationGate from '@/Components/LocationGate.vue';
import axios from 'axios';

const props = defineProps({ title: { type: String, default: 'Dashboard' } });
const page = usePage();
const open = ref(false);
const showNotif = ref(false);
const showUserMenu = ref(false);
const notifList = ref([]);
const loadingNotif = ref(false);
const user = computed(() => page.props.auth?.user ?? null);
const flash = computed(() => page.props.flash ?? {});
const unreadCount = ref(page.props.unread_notifications ?? 0);
let notificationChannel = null;
let notificationPoll = null;
const closeHandler = (e) => {
  if (!e.target.closest('[data-notif]')) showNotif.value = false;
  if (!e.target.closest('[data-user-menu]')) showUserMenu.value = false;
};
const initials = computed(() => `${user.value?.name?.[0] ?? 'U'}${user.value?.last_name?.[0] ?? ''}`.toUpperCase());
const permissions = computed(() => page.props.auth?.permissions ?? []);
const can = (module, action = 'ver') => permissions.value.includes('*') || permissions.value.includes(`${module}.${action}`);
const isAdmin = computed(() => user.value?.role?.name === 'Administrador');
const nav = computed(() => [
    ['Dashboard', '/dashboard', BarChart3, can('dashboard')], ['Mapa', '/mapa', Map, can('mapa')], ['Solicitudes', '/solicitudes', ClipboardList, can('solicitudes')],
  ['Notificaciones', '/notificaciones', Bell, can('notificaciones')], ['Inventario', '/inventario', Boxes, can('inventario')],
  ['Vehiculos', '/vehiculos', Car, can('vehiculos')], ['Reportes', '/reportes', BarChart3, can('reportes')], ['Usuarios', '/usuarios', Users, can('usuarios')],
  ['Roles', '/roles', ShieldCheck, can('roles')], ['Auditoria', '/auditoria', UserCog, can('auditoria')], ['Perfil', '/perfil', UserCog, can('perfil')],
  ['Configuracion GPS', '/configuracion/gps', Settings, can('configuracion_gps')],
].filter(item => item[3]));
const logout = () => router.post('/logout');

const timeAgo = (dt) => {
  const diff = Date.now() - new Date(dt).getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 1) return 'Ahora';
  if (mins < 60) return `Hace ${mins} min`;
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return `Hace ${hrs} h`;
  const days = Math.floor(hrs / 24);
  return `Hace ${days} d`;
};

const fetchNotifications = async () => {
  loadingNotif.value = true;
  try {
    const { data } = await axios.get('/api/notifications');
    notifList.value = data.data ?? [];
    unreadCount.value = data.unread_count ?? unreadCount.value;
  } catch { notifList.value = []; }
  loadingNotif.value = false;
};

const syncNotifications = async () => {
  try {
    const { data } = await axios.get('/api/notifications');
    notifList.value = data.data ?? [];
    unreadCount.value = data.unread_count ?? unreadCount.value;
  } catch {}
};

const pushNotification = (notification) => {
  if (!notification?.id) {
    unreadCount.value += 1;
    if (showNotif.value) syncNotifications();
    return;
  }

  const exists = notifList.value.some(n => Number(n.id) === Number(notification.id));
  if (!exists) notifList.value = [notification, ...notifList.value].slice(0, 30);
  if (!exists && !notification.read_at) unreadCount.value += 1;
};

const toggleNotif = () => {
  showNotif.value = !showNotif.value;
  if (showNotif.value && notifList.value.length === 0) fetchNotifications();
};

const markAsRead = async (n) => {
  if (!n.read_at) {
    n.read_at = new Date().toISOString();
    unreadCount.value = Math.max(0, unreadCount.value - 1);
    await axios.patch(`/api/notifications/${n.id}/read`);
  }
  if (n.url) router.visit(n.url);
};

onMounted(() => {
  if (window.Echo && user.value?.id) {
    notificationChannel = `notifications.${user.value.id}`;
    window.Echo.private(notificationChannel).listen('NotificationCreated', (event) => {
      pushNotification(event.notification);
    });
  }
  notificationPoll = window.setInterval(syncNotifications, 30000);
  window.addEventListener('notifications:sync', syncNotifications);
  document.addEventListener('click', closeHandler);
});
onBeforeUnmount(() => {
  if (window.Echo && notificationChannel) window.Echo.leave(notificationChannel);
  if (notificationPoll) window.clearInterval(notificationPoll);
  window.removeEventListener('notifications:sync', syncNotifications);
  document.removeEventListener('click', closeHandler);
});
watch(() => page.url, () => { showNotif.value = false; });
</script>

<template>
  <div class="min-h-screen bg-[#eef2f7] lg:flex">
    <aside :class="['fixed inset-y-0 left-0 z-[9999] w-64 border-r border-slate-200 bg-white transition-transform lg:sticky lg:top-0 lg:h-screen lg:translate-x-0', open ? 'translate-x-0' : '-translate-x-full']">
      <div class="flex h-24 items-center justify-between border-b border-slate-200 px-4">
        <img :src="'/images/logo-login.png'" alt="Colvatel" class="max-h-16 w-full object-contain" />
        <button class="lg:hidden cursor-pointer" @click="open=false"><X class="h-5 w-5" /></button>
      </div>
      <nav class="space-y-1 overflow-y-auto p-2" style="height: calc(100% - 6rem)">
        <Link v-for="[label, href, Icon] in nav" :key="label" :href="href" @click="open=false" class="flex items-center gap-3 rounded-md px-3 py-3 text-[15px] font-medium text-slate-700 hover:bg-[#edf3fa] hover:text-[#123f6e]" :class="{'bg-[#e3ebf5] text-[#123f6e]': page.url === href || page.url.startsWith(href + '/')}">
          <component :is="Icon" class="h-5 w-5 shrink-0" /> <span class="min-w-0 flex-1">{{ label }}</span><span v-if="label === 'Notificaciones' && unreadCount" class="rounded-full bg-[#123f6e] px-2 py-0.5 text-xs text-white">{{ unreadCount }}</span>
        </Link>
        <button @click="logout(); open=false" class="flex w-full items-center gap-3 rounded-md px-3 py-3 text-left text-[15px] font-medium text-slate-700 hover:bg-red-50 hover:text-red-700"><LogOut class="h-5 w-5" /> Cerrar sesion</button>
      </nav>
    </aside>

    <div class="min-w-0 flex-1">
      <header class="sticky top-0 z-30 border-b border-slate-200 bg-[#e9eef8]">
        <div class="flex h-14 items-center justify-between px-4 sm:px-6">
          <div class="flex items-center gap-4">
            <button class="lg:hidden cursor-pointer" @click="open=true"><Menu class="h-6 w-6" /></button>
            <Link href="/dashboard" class="text-2xl font-semibold text-[#123f6e]">ColvaTrack</Link>
          </div>
          <div class="flex items-center gap-2 sm:gap-3 text-right">
            <div data-notif class="relative">
              <button @click="toggleNotif" class="relative rounded-md p-1.5 transition-colors hover:bg-slate-200">
                <Bell class="h-5 w-5 text-slate-700" />
                <span v-if="unreadCount" class="absolute -right-0.5 -top-0.5 inline-flex min-w-[18px] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white" style="height:18px">{{ unreadCount > 99 ? '99+' : unreadCount }}</span>
              </button>
              <div v-if="showNotif" class="absolute right-0 top-full z-50 mt-2 max-h-96 w-80 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg">
                <div class="flex items-center justify-between border-b border-slate-100 p-3">
                  <span class="text-sm font-semibold text-slate-800">Notificaciones</span>
                </div>
                <div v-if="!loadingNotif && notifList.length === 0" class="p-6 text-center text-sm text-slate-500">No hay notificaciones</div>
                <div v-if="loadingNotif" class="p-6 text-center text-sm text-slate-400">Cargando...</div>
                <div v-for="n in notifList" :key="n.id" @click="markAsRead(n)" class="cursor-pointer border-b border-slate-100 p-3 last:border-0 hover:bg-slate-50" :class="{'bg-blue-50/40': !n.read_at}">
                  <div class="text-sm font-medium text-slate-800">{{ n.title }}</div>
                  <div class="mt-0.5 text-xs text-slate-500">{{ n.message }}</div>
                  <div class="mt-1 text-[10px] text-slate-400">{{ timeAgo(n.created_at) }}</div>
                </div>
              </div>
            </div>
            <div class="hidden sm:block leading-tight"><div class="font-medium text-slate-950">{{ user?.name }} {{ user?.last_name }}</div><div class="text-sm text-slate-600">{{ user?.role?.name }}</div></div>
            <div data-user-menu class="relative"><button @click="showUserMenu=!showUserMenu" class="grid h-9 w-9 place-items-center rounded-full bg-slate-200 text-sm font-semibold text-slate-800 hover:bg-slate-300 cursor-pointer">{{ initials }}</button><div v-if="showUserMenu" class="absolute right-0 top-full z-50 mt-2 w-44 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg"><Link href="/perfil" @click="showUserMenu=false" class="flex items-center gap-2 px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50"><UserCog class="h-4 w-4" /> Perfil</Link><hr class="border-slate-100" /><button @click="logout(); showUserMenu=false" class="flex w-full items-center gap-2 px-4 py-3 text-sm font-medium text-red-700 hover:bg-red-50"><LogOut class="h-4 w-4" /> Cerrar sesion</button></div></div>
          </div>
        </div>
        <div class="border-t border-slate-200 bg-[#e9eef8] px-4 py-4 sm:px-6"><h1 class="text-2xl font-semibold text-[#123f6e]">{{ props.title }}</h1></div>
      </header>
      <main class="p-4 sm:p-6">
        <LocationGate />
        <div v-if="flash.success" class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ flash.success }}</div>
        <div v-if="flash.error" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ flash.error }}</div>
        <slot />
      </main>
    </div>
  </div>
</template>
