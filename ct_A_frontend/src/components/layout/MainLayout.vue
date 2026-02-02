<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useAppLogo } from '@/composables/useAppLogo'
import {
  HomeIcon,
  DocumentTextIcon,
  ClipboardDocumentListIcon,
  ShoppingCartIcon,
  TruckIcon,
  BanknotesIcon,
  UserGroupIcon,
  ChartBarIcon,
  Cog6ToothIcon,
  ArrowLeftOnRectangleIcon,
  Bars3Icon,
  XMarkIcon,
  BellIcon,
  DocumentDuplicateIcon,
} from '@heroicons/vue/24/outline'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const { logoUrl, loadLogo } = useAppLogo()

const sidebarOpen = ref(false)

const navigation = [
  { name: 'Tableau de bord', href: '/', icon: HomeIcon },
  { name: 'Expressions de Besoins', href: '/expressions-besoin', icon: DocumentTextIcon },
  { name: 'Demandes d\'Achat', href: '/demandes-achat', icon: ClipboardDocumentListIcon },
  { name: 'Bons de Commande', href: '/bons-commande', icon: ShoppingCartIcon },
  { name: 'Receptions', href: '/receptions', icon: TruckIcon },
  { name: 'Factures', href: '/factures', icon: BanknotesIcon },
  { name: 'Contrats', href: '/contrats', icon: DocumentDuplicateIcon },
  { name: 'Fournisseurs', href: '/fournisseurs', icon: UserGroupIcon },
  { name: 'Statistiques', href: '/stats', icon: ChartBarIcon },
]

const isActive = (href: string) => {
  if (href === '/') return route.path === '/'
  return route.path.startsWith(href)
}

const logout = async () => {
  await authStore.logout()
  router.push('/login')
}

onMounted(async () => {
  if (authStore.token && !authStore.user) {
    await authStore.fetchUser()
  }
  // Charger le logo depuis les paramètres
  loadLogo()
})
</script>

<template>
  <div class="min-h-screen bg-gray-200">
    <!-- Mobile sidebar backdrop -->
    <div 
      v-if="sidebarOpen" 
      class="fixed inset-0 z-40 bg-black bg-opacity-50 lg:hidden"
      @click="sidebarOpen = false"
    />

    <!-- Sidebar -->
    <aside
      :class="[
        'fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-gray-200 transform transition-transform duration-300 lg:translate-x-0',
        sidebarOpen ? 'translate-x-0' : '-translate-x-full'
      ]"
    >
      <!-- Logo -->
      <div class="flex items-center h-16 px-4 border-b border-gray-200">
        <div class="flex items-center">
          <div class="w-10 h-10 rounded-lg bg-white shadow-sm overflow-hidden flex items-center justify-center">
            <img :src="logoUrl" alt="Congo Telecom" class="w-full h-full object-contain">
          </div>
          <span class="ml-3 text-lg font-bold text-gray-900">CT_Achats</span>
        </div>
        <button
          class="ml-auto lg:hidden text-gray-500 hover:text-gray-700"
          @click="sidebarOpen = false"
        >
          <XMarkIcon class="w-6 h-6" />
        </button>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
        <router-link
          v-for="item in navigation"
          :key="item.name"
          :to="item.href"
          :class="[
            'nav-link',
            isActive(item.href) ? 'active' : ''
          ]"
          @click="sidebarOpen = false"
        >
          <component :is="item.icon" class="nav-link-icon" />
          {{ item.name }}
        </router-link>
      </nav>

      <!-- User info -->
      <div class="border-t border-gray-200 p-4">
        <div class="flex items-center">
          <div class="w-10 h-10 rounded-full bg-ct-blue-100 flex items-center justify-center">
            <span class="text-ct-blue-600 font-semibold">
              {{ authStore.user?.prenom?.charAt(0) }}{{ authStore.user?.nom?.charAt(0) }}
            </span>
          </div>
          <div class="ml-3 flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900 truncate">
              {{ authStore.user?.nom_complet }}
            </p>
            <p class="text-xs text-gray-500 truncate">
              {{ authStore.user?.service?.libelle }}
            </p>
          </div>
        </div>
        <button 
          class="mt-4 w-full flex items-center px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
          @click="logout"
        >
          <ArrowLeftOnRectangleIcon class="w-5 h-5 mr-2" />
          Déconnexion
        </button>
      </div>
    </aside>

    <!-- Main content -->
    <div class="lg:pl-64">
      <!-- Top bar -->
      <header class="sticky top-0 z-30 flex items-center h-16 px-4 bg-white border-b border-gray-200 lg:px-8">
        <button 
          class="lg:hidden p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg"
          @click="sidebarOpen = true"
        >
          <Bars3Icon class="w-6 h-6" />
        </button>

        <div class="flex-1">
          <!-- Breadcrumb ou titre page -->
        </div>

        <div class="flex items-center space-x-4">
          <!-- Notifications -->
          <button class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg relative">
            <BellIcon class="w-6 h-6" />
            <span class="absolute top-1 right-1 w-2 h-2 bg-ct-orange-500 rounded-full"></span>
          </button>

          <!-- Settings (Admin uniquement) -->
          <router-link v-if="authStore.isAdmin" to="/parametres" class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg">
            <Cog6ToothIcon class="w-6 h-6" />
          </router-link>
        </div>
      </header>

      <!-- Page content -->
      <main class="p-4 lg:p-8">
        <router-view />
      </main>
    </div>
  </div>
</template>
