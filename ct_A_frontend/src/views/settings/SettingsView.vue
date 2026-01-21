<script setup lang="ts">
import { ref, computed, markRaw } from 'vue'
import {
  Cog6ToothIcon,
  BuildingOfficeIcon,
  MapPinIcon,
  UserGroupIcon,
  CubeIcon,
  BanknotesIcon,
  ShieldCheckIcon,
  HashtagIcon,
  ScaleIcon,
  UsersIcon,
  ShoppingCartIcon,
} from '@heroicons/vue/24/outline'

import SettingsGeneral from './tabs/SettingsGeneral.vue'
import SettingsSeuils from './tabs/SettingsSeuils.vue'
import SettingsNumerotation from './tabs/SettingsNumerotation.vue'
import SettingsZones from './tabs/SettingsZones.vue'
import SettingsDirections from './tabs/SettingsDirections.vue'
import SettingsServices from './tabs/SettingsServices.vue'
import SettingsUnites from './tabs/SettingsUnites.vue'
import SettingsNatures from './tabs/SettingsNatures.vue'
import SettingsRoles from './tabs/SettingsRoles.vue'
import SettingsUsers from './tabs/SettingsUsers.vue'
import SettingsAcheteurs from './tabs/SettingsAcheteurs.vue'

const tabs = [
  { id: 'general', name: 'General', icon: Cog6ToothIcon, component: markRaw(SettingsGeneral) },
  { id: 'seuils', name: 'Seuils de validation', icon: ScaleIcon, component: markRaw(SettingsSeuils) },
  { id: 'numerotation', name: 'Numerotation', icon: HashtagIcon, component: markRaw(SettingsNumerotation) },
  { id: 'zones', name: 'Zones', icon: MapPinIcon, component: markRaw(SettingsZones) },
  { id: 'directions', name: 'Directions', icon: BuildingOfficeIcon, component: markRaw(SettingsDirections) },
  { id: 'services', name: 'Services', icon: UserGroupIcon, component: markRaw(SettingsServices) },
  { id: 'unites', name: 'Unites de mesure', icon: CubeIcon, component: markRaw(SettingsUnites) },
  { id: 'natures', name: 'Natures de depense', icon: BanknotesIcon, component: markRaw(SettingsNatures) },
  { id: 'roles', name: 'Roles et permissions', icon: ShieldCheckIcon, component: markRaw(SettingsRoles) },
  { id: 'acheteurs', name: 'Acheteurs', icon: ShoppingCartIcon, component: markRaw(SettingsAcheteurs) },
  { id: 'users', name: 'Utilisateurs', icon: UsersIcon, component: markRaw(SettingsUsers) },
]

const activeTab = ref('general')

const currentComponent = computed(() => {
  const tab = tabs.find(t => t.id === activeTab.value)
  return tab?.component
})
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Parametres systeme</h1>
      <p class="text-gray-600 mt-1">Configuration et referentiels de l'application</p>
    </div>

    <div class="flex flex-col lg:flex-row gap-6">
      <!-- Sidebar tabs -->
      <div class="lg:w-64 flex-shrink-0">
        <nav class="card p-2 space-y-1">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            @click="activeTab = tab.id"
            :class="[
              'w-full flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-colors',
              activeTab === tab.id
                ? 'bg-ct-blue-50 text-ct-blue-700'
                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
            ]"
          >
            <component :is="tab.icon" class="w-5 h-5" />
            {{ tab.name }}
          </button>
        </nav>
      </div>

      <!-- Content -->
      <div class="flex-1">
        <component :is="currentComponent" />
      </div>
    </div>
  </div>
</template>
