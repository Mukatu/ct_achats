<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { fournisseurService } from '@/services/api'
import { type Fournisseur, type Pagination } from '@/types'
import {
  PlusIcon,
  MagnifyingGlassIcon,
  FunnelIcon,
  EyeIcon,
  PencilIcon,
  TrashIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  BuildingOfficeIcon,
  GlobeAltIcon,
  MapPinIcon,
} from '@heroicons/vue/24/outline'

const router = useRouter()
const loading = ref(true)
const data = ref<Pagination<Fournisseur> | null>(null)

const filters = ref({
  search: '',
  type_fournisseur: '',
  statut: '',
})

const showFilters = ref(false)
const currentPage = ref(1)

const statutColors: Record<string, string> = {
  'PROSPECT': 'bg-gray-100 text-gray-800',
  'EN_VALIDATION': 'bg-yellow-100 text-yellow-800',
  'ACTIF': 'bg-green-100 text-green-800',
  'SUSPENDU': 'bg-orange-100 text-orange-800',
  'BLOQUE': 'bg-red-100 text-red-800',
  'INACTIF': 'bg-gray-100 text-gray-600',
}

const statutLabels: Record<string, string> = {
  'PROSPECT': 'Prospect',
  'EN_VALIDATION': 'En validation',
  'ACTIF': 'Actif',
  'SUSPENDU': 'Suspendu',
  'BLOQUE': 'Bloqué',
  'INACTIF': 'Inactif',
}

const typeLabels: Record<string, string> = {
  'LOCAL': 'Local (Congo)',
  'CEMAC': 'Zone CEMAC',
  'INTERNATIONAL': 'International',
}

const typeIcons: Record<string, any> = {
  'LOCAL': MapPinIcon,
  'CEMAC': BuildingOfficeIcon,
  'INTERNATIONAL': GlobeAltIcon,
}

async function loadData() {
  loading.value = true
  try {
    const params: any = { page: currentPage.value }
    if (filters.value.search) params.search = filters.value.search
    if (filters.value.type_fournisseur) params.type_fournisseur = filters.value.type_fournisseur
    if (filters.value.statut) params.statut = filters.value.statut

    const response = await fournisseurService.getAll(params)
    data.value = response.data
  } catch (error) {
    console.error('Erreur chargement fournisseurs:', error)
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
  filters.value = { search: '', type_fournisseur: '', statut: '' }
  currentPage.value = 1
  loadData()
}

function viewFournisseur(id: string) {
  router.push(`/fournisseurs/${id}`)
}

function editFournisseur(id: string) {
  router.push(`/fournisseurs/${id}/modifier`)
}

function createFournisseur() {
  router.push('/fournisseurs/nouveau')
}

async function deleteFournisseur(fournisseur: Fournisseur) {
  if (!confirm(`Voulez-vous vraiment supprimer le fournisseur "${fournisseur.raison_sociale}" ?\n\nCette action est irréversible.`)) {
    return
  }
  try {
    await fournisseurService.delete(fournisseur.id)
    loadData()
  } catch (error: any) {
    alert(error.response?.data?.message || 'Erreur lors de la suppression')
  }
}

function canDelete(fournisseur: Fournisseur): boolean {
  // On peut supprimer seulement les fournisseurs PROSPECT ou SUSPENDU
  return ['PROSPECT', 'SUSPENDU', 'INACTIF'].includes(fournisseur.statut)
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
        <h1 class="text-2xl font-bold text-gray-900">Fournisseurs</h1>
        <p class="text-gray-600 mt-1">Gérez le référentiel des fournisseurs</p>
      </div>
      <button @click="createFournisseur" class="btn-primary inline-flex items-center">
        <PlusIcon class="w-5 h-5 mr-2" />
        Nouveau Fournisseur
      </button>
    </div>

    <div class="card mb-6">
      <div class="flex flex-col sm:flex-row gap-4">
        <div class="flex-1 relative">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
          <input
            v-model="filters.search"
            type="text"
            placeholder="Rechercher par raison sociale, NIU, RCCM..."
            class="input pl-10"
          />
        </div>
        <button @click="showFilters = !showFilters" class="btn-secondary inline-flex items-center">
          <FunnelIcon class="w-5 h-5 mr-2" />
          Filtres
        </button>
      </div>

      <div v-if="showFilters" class="mt-4 pt-4 border-t border-gray-200">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div>
            <label class="label">Type</label>
            <select v-model="filters.type_fournisseur" class="input" @change="applyFilters">
              <option value="">Tous les types</option>
              <option value="LOCAL">Local (Congo)</option>
              <option value="CEMAC">Zone CEMAC</option>
              <option value="INTERNATIONAL">International</option>
            </select>
          </div>
          <div>
            <label class="label">Statut</label>
            <select v-model="filters.statut" class="input" @change="applyFilters">
              <option value="">Tous les statuts</option>
              <option value="ACTIF">Actif</option>
              <option value="PROSPECT">Prospect</option>
              <option value="EN_VALIDATION">En validation</option>
              <option value="SUSPENDU">Suspendu</option>
              <option value="BLOQUE">Bloqué</option>
              <option value="INACTIF">Inactif</option>
            </select>
          </div>
        </div>
        <div class="mt-4 flex justify-end">
          <button @click="resetFilters" class="text-sm text-gray-600 hover:text-gray-900">
            Réinitialiser les filtres
          </button>
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
              <th>Code</th>
              <th>Raison Sociale</th>
              <th>Type</th>
              <th>Ville / Pays</th>
              <th>NIU / RCCM</th>
              <th>Téléphone</th>
              <th>Statut</th>
              <th class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="f in data?.data" :key="f.id" class="hover:bg-gray-50">
              <td class="font-medium text-ct-blue-600">{{ f.code }}</td>
              <td>
                <div class="font-medium">{{ f.raison_sociale }}</div>
                <div v-if="f.sigle" class="text-xs text-gray-500">{{ f.sigle }}</div>
              </td>
              <td>
                <div class="flex items-center">
                  <component :is="typeIcons[f.type_fournisseur]" class="w-4 h-4 mr-1 text-gray-400" />
                  <span class="text-sm">{{ typeLabels[f.type_fournisseur] || f.type_fournisseur }}</span>
                </div>
              </td>
              <td>
                <div>{{ f.ville || '-' }}</div>
                <div class="text-xs text-gray-500">{{ f.pays }}</div>
              </td>
              <td>
                <div v-if="f.niu" class="text-xs">NIU: {{ f.niu }}</div>
                <div v-if="f.rccm" class="text-xs text-gray-500">RCCM: {{ f.rccm }}</div>
                <span v-if="!f.niu && !f.rccm" class="text-gray-400">-</span>
              </td>
              <td>{{ f.telephone || '-' }}</td>
              <td>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="statutColors[f.statut] || 'bg-gray-100 text-gray-800'">
                  {{ statutLabels[f.statut] || f.statut }}
                </span>
              </td>
              <td class="text-right">
                <div class="flex justify-end space-x-1">
                  <button @click="viewFournisseur(f.id)" class="p-1.5 text-gray-500 hover:text-ct-blue-600 hover:bg-gray-100 rounded" title="Voir détails">
                    <EyeIcon class="w-5 h-5" />
                  </button>
                  <button @click="editFournisseur(f.id)" class="p-1.5 text-gray-500 hover:text-ct-orange-600 hover:bg-gray-100 rounded" title="Modifier">
                    <PencilIcon class="w-5 h-5" />
                  </button>
                  <button
                    v-if="canDelete(f)"
                    @click="deleteFournisseur(f)"
                    class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-gray-100 rounded"
                    title="Supprimer"
                  >
                    <TrashIcon class="w-5 h-5" />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!data?.data?.length">
              <td colspan="8" class="text-center py-8 text-gray-500">Aucun fournisseur trouvé</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="data && data.last_page > 1" class="flex items-center justify-between mt-4 pt-4 border-t border-gray-200">
        <p class="text-sm text-gray-600">Page {{ data.current_page }} sur {{ data.last_page }} ({{ data.total }} résultats)</p>
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
