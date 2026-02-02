<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { userService } from '@/services/api'
import { type Pagination } from '@/types'
import {
  PlusIcon,
  MagnifyingGlassIcon,
  FunnelIcon,
  EyeIcon,
  PencilIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  UserIcon,
  ShieldCheckIcon,
  ShoppingCartIcon,
} from '@heroicons/vue/24/outline'

interface Role {
  id: string
  code: string
  libelle: string
}

interface User {
  id: string
  matricule: string
  nom: string
  prenom: string
  nom_complet: string
  email: string
  telephone: string
  actif: boolean
  est_acheteur: boolean
  est_valideur: boolean
  roles?: Role[]
  service?: {
    libelle: string
    direction?: {
      libelle: string
      zone?: {
        libelle: string
      }
    }
  }
}

const router = useRouter()
const loading = ref(true)
const data = ref<Pagination<User> | null>(null)

const filters = ref({
  search: '',
  est_acheteur: '',
  est_valideur: '',
  actif: 'true',
})

const showFilters = ref(false)
const currentPage = ref(1)

async function loadData() {
  loading.value = true
  try {
    const params: any = { page: currentPage.value }
    if (filters.value.search) params.search = filters.value.search
    if (filters.value.est_acheteur) params.est_acheteur = filters.value.est_acheteur
    if (filters.value.est_valideur) params.est_valideur = filters.value.est_valideur
    if (filters.value.actif) params.actif = filters.value.actif

    const response = await userService.getAll(params)
    data.value = response.data
  } catch (error) {
    console.error('Erreur chargement utilisateurs:', error)
  } finally {
    loading.value = false
  }
}

function goToPage(page: number) {
  currentPage.value = page
  loadData()
}

function applyFilters() {
  currentPage.value = 1
  loadData()
}

function resetFilters() {
  filters.value = { search: '', est_acheteur: '', est_valideur: '', actif: 'true' }
  currentPage.value = 1
  loadData()
}

function viewUser(id: string) {
  router.push(`/utilisateurs/${id}`)
}

function editUser(id: string) {
  router.push(`/utilisateurs/${id}/modifier`)
}

function createUser() {
  router.push('/utilisateurs/nouveau')
}

let searchTimeout: number | null = null
watch(() => filters.value.search, () => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => applyFilters(), 300) as unknown as number
})

onMounted(() => {
  loadData()
})
</script>

<template>
  <div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Utilisateurs</h1>
        <p class="text-gray-600 mt-1">Gerez les utilisateurs et leurs droits d'acces</p>
      </div>
      <button @click="createUser" class="btn-primary inline-flex items-center">
        <PlusIcon class="w-5 h-5 mr-2" />
        Nouvel Utilisateur
      </button>
    </div>

    <div class="card mb-6">
      <div class="flex flex-col sm:flex-row gap-4">
        <div class="flex-1 relative">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
          <input
            v-model="filters.search"
            type="text"
            placeholder="Rechercher par nom, email, matricule..."
            class="input pl-10"
          />
        </div>
        <button @click="showFilters = !showFilters" class="btn-secondary inline-flex items-center">
          <FunnelIcon class="w-5 h-5 mr-2" />
          Filtres
        </button>
      </div>

      <div v-if="showFilters" class="mt-4 pt-4 border-t border-gray-200">
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
          <div>
            <label class="label">Acheteur</label>
            <select v-model="filters.est_acheteur" class="input" @change="applyFilters">
              <option value="">Tous</option>
              <option value="true">Oui</option>
              <option value="false">Non</option>
            </select>
          </div>
          <div>
            <label class="label">Valideur</label>
            <select v-model="filters.est_valideur" class="input" @change="applyFilters">
              <option value="">Tous</option>
              <option value="true">Oui</option>
              <option value="false">Non</option>
            </select>
          </div>
          <div>
            <label class="label">Statut</label>
            <select v-model="filters.actif" class="input" @change="applyFilters">
              <option value="">Tous</option>
              <option value="true">Actif</option>
              <option value="false">Inactif</option>
            </select>
          </div>
          <div class="flex items-end">
            <button @click="resetFilters" class="text-sm text-gray-600 hover:text-gray-900">
              Reinitialiser
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-ct-blue-500"></div>
    </div>

    <div v-else class="card">
      <div class="table-container">
        <table class="table">
          <thead>
            <tr>
              <th>Matricule</th>
              <th>Nom complet</th>
              <th>Email</th>
              <th>Service / Direction</th>
              <th>Roles</th>
              <th>Statut</th>
              <th class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in data?.data" :key="user.id" class="hover:bg-gray-50">
              <td class="font-medium text-ct-blue-600">{{ user.matricule || '-' }}</td>
              <td>
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                    <UserIcon class="w-4 h-4 text-gray-500" />
                  </div>
                  <div>
                    <div class="font-medium">{{ user.nom }} {{ user.prenom }}</div>
                    <div v-if="user.telephone" class="text-xs text-gray-500">{{ user.telephone }}</div>
                  </div>
                </div>
              </td>
              <td class="text-gray-600">{{ user.email }}</td>
              <td>
                <div v-if="user.service">
                  <div class="text-sm">{{ user.service.libelle }}</div>
                  <div class="text-xs text-gray-500">{{ user.service.direction?.libelle }}</div>
                </div>
                <span v-else class="text-gray-400">-</span>
              </td>
              <td>
                <div class="flex flex-wrap gap-1">
                  <!-- Roles assignes -->
                  <span
                    v-for="role in user.roles"
                    :key="role.id"
                    :class="[
                      'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                      role.code === 'ADMIN' ? 'bg-red-100 text-red-800' : 'bg-ct-blue-100 text-ct-blue-800'
                    ]"
                    :title="role.libelle"
                  >
                    <ShieldCheckIcon class="w-3 h-3 mr-1" />
                    {{ role.code }}
                  </span>
                  <!-- Badges acheteur/valideur -->
                  <span v-if="user.est_acheteur" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800" title="Acheteur">
                    <ShoppingCartIcon class="w-3 h-3 mr-1" />
                    ACH
                  </span>
                  <span v-if="user.est_valideur" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800" title="Valideur">
                    <ShieldCheckIcon class="w-3 h-3 mr-1" />
                    VAL
                  </span>
                  <span v-if="!user.roles?.length && !user.est_acheteur && !user.est_valideur" class="text-gray-400 text-xs">-</span>
                </div>
              </td>
              <td>
                <span
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                  :class="user.actif ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
                >
                  {{ user.actif ? 'Actif' : 'Inactif' }}
                </span>
              </td>
              <td class="text-right">
                <div class="flex justify-end space-x-1">
                  <button @click="viewUser(user.id)" class="p-1.5 text-gray-500 hover:text-ct-blue-600 hover:bg-gray-100 rounded" title="Voir details">
                    <EyeIcon class="w-5 h-5" />
                  </button>
                  <button @click="editUser(user.id)" class="p-1.5 text-gray-500 hover:text-ct-orange-600 hover:bg-gray-100 rounded" title="Modifier">
                    <PencilIcon class="w-5 h-5" />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!data?.data?.length">
              <td colspan="7" class="text-center py-8 text-gray-500">Aucun utilisateur trouve</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="data && data.last_page > 1" class="flex items-center justify-between mt-4 pt-4 border-t border-gray-200">
        <p class="text-sm text-gray-600">Page {{ data.current_page }} sur {{ data.last_page }} ({{ data.total }} resultats)</p>
        <div class="flex space-x-2">
          <button @click="goToPage(data.current_page - 1)" :disabled="data.current_page === 1" class="btn-secondary p-2 disabled:opacity-50">
            <ChevronLeftIcon class="w-5 h-5" />
          </button>
          <button @click="goToPage(data.current_page + 1)" :disabled="data.current_page === data.last_page" class="btn-secondary p-2 disabled:opacity-50">
            <ChevronRightIcon class="w-5 h-5" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
