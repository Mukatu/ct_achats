<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { receptionService, referentielService } from '@/services/api'
import { formatDate, type Reception, type Pagination, type SelectOption } from '@/types'
import {
  PlusIcon,
  MagnifyingGlassIcon,
  FunnelIcon,
  EyeIcon,
  CheckBadgeIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  TrashIcon,
} from '@heroicons/vue/24/outline'

const router = useRouter()
const loading = ref(true)
const data = ref<Pagination<Reception> | null>(null)

const filters = ref({
  search: '',
  type_reception: '',
  statut: '',
  date_debut: '',
  date_fin: '',
})

const showFilters = ref(false)
const statuts = ref<SelectOption[]>([])
const currentPage = ref(1)

const statutColors: Record<string, string> = {
  'BROUILLON': 'bg-gray-100 text-gray-800',
  'VALIDEE': 'bg-green-100 text-green-800',
  'EN_LITIGE': 'bg-orange-100 text-orange-800',
  'ANNULEE': 'bg-red-100 text-red-800',
}

const statutLabels: Record<string, string> = {
  'BROUILLON': 'Brouillon',
  'VALIDEE': 'Validee',
  'EN_LITIGE': 'En litige',
  'ANNULEE': 'Annulee',
}

const typeColors: Record<string, string> = {
  'LIVRAISON': 'bg-blue-100 text-blue-800',
  'SERVICE_FAIT': 'bg-purple-100 text-purple-800',
  'PARTIELLE': 'bg-yellow-100 text-yellow-800',
}

const typeLabels: Record<string, string> = {
  'LIVRAISON': 'Livraison',
  'SERVICE_FAIT': 'Service fait',
  'PARTIELLE': 'Partielle',
}

async function loadData() {
  loading.value = true
  try {
    const params: any = { page: currentPage.value }
    if (filters.value.search) params.search = filters.value.search
    if (filters.value.type_reception) params.type_reception = filters.value.type_reception
    if (filters.value.statut) params.statut = filters.value.statut
    if (filters.value.date_debut) params.date_debut = filters.value.date_debut
    if (filters.value.date_fin) params.date_fin = filters.value.date_fin

    const response = await receptionService.getAll(params)
    data.value = response.data
  } catch (error) {
    console.error('Erreur chargement BR:', error)
  } finally {
    loading.value = false
  }
}

async function loadReferentiels() {
  try {
    const statutsRes = await referentielService.getStatutsBR()
    statuts.value = statutsRes.data
  } catch (error) {
    console.error('Erreur chargement referentiels:', error)
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
  filters.value = {
    search: '',
    type_reception: '',
    statut: '',
    date_debut: '',
    date_fin: '',
  }
  currentPage.value = 1
  loadData()
}

function viewBR(id: string) {
  router.push(`/receptions/${id}`)
}

function createBR() {
  router.push('/receptions/nouveau')
}

async function deleteBR(br: Reception) {
  if (!confirm(`Voulez-vous vraiment supprimer le BR "${br.numero}" ?\n\nCette action est irréversible.`)) {
    return
  }
  try {
    await receptionService.delete(br.id)
    loadData()
  } catch (error: any) {
    alert(error.response?.data?.message || 'Erreur lors de la suppression')
  }
}

function canDelete(br: Reception): boolean {
  return br.statut === 'BROUILLON'
}

let searchTimeout: number | null = null
watch(() => filters.value.search, () => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => applyFilters(), 300) as unknown as number
})

onMounted(() => {
  loadReferentiels()
  loadData()
})
</script>

<template>
  <div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Bons de Reception</h1>
        <p class="text-gray-600 mt-1">Gerez les receptions de marchandises et services</p>
      </div>
      <button @click="createBR" class="btn-primary inline-flex items-center">
        <PlusIcon class="w-5 h-5 mr-2" />
        Nouvelle Reception
      </button>
    </div>

    <div class="card mb-6">
      <div class="flex flex-col sm:flex-row gap-4">
        <div class="flex-1 relative">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
          <input
            v-model="filters.search"
            type="text"
            placeholder="Rechercher par numero BR ou BC..."
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
            <label class="label">Type de reception</label>
            <select v-model="filters.type_reception" class="input" @change="applyFilters">
              <option value="">Tous les types</option>
              <option value="LIVRAISON">Livraison</option>
              <option value="SERVICE_FAIT">Service fait</option>
              <option value="PARTIELLE">Partielle</option>
            </select>
          </div>
          <div>
            <label class="label">Statut</label>
            <select v-model="filters.statut" class="input" @change="applyFilters">
              <option value="">Tous les statuts</option>
              <option v-for="s in statuts" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
          </div>
          <div>
            <label class="label">Date debut</label>
            <input v-model="filters.date_debut" type="date" class="input" @change="applyFilters" />
          </div>
          <div>
            <label class="label">Date fin</label>
            <input v-model="filters.date_fin" type="date" class="input" @change="applyFilters" />
          </div>
        </div>
        <div class="mt-4 flex justify-end">
          <button @click="resetFilters" class="text-sm text-gray-600 hover:text-gray-900">
            Reinitialiser les filtres
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
              <th>Numero BR</th>
              <th>Type</th>
              <th>Date</th>
              <th>BC Associe</th>
              <th>Fournisseur</th>
              <th>Receptionnaire</th>
              <th>Statut</th>
              <th class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="br in data?.data" :key="br.id" class="hover:bg-gray-50">
              <td class="font-medium text-ct-blue-600">{{ br.numero }}</td>
              <td>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" :class="typeColors[br.type_reception]">
                  {{ typeLabels[br.type_reception] || br.type_reception }}
                </span>
              </td>
              <td>{{ formatDate(br.date_reception) }}</td>
              <td class="font-medium text-gray-700">{{ br.bon_commande?.numero || '-' }}</td>
              <td class="max-w-[150px] truncate" :title="br.bon_commande?.fournisseur?.raison_sociale">
                {{ br.bon_commande?.fournisseur?.raison_sociale || '-' }}
              </td>
              <td>{{ br.receptionnaire?.nom }} {{ br.receptionnaire?.prenom }}</td>
              <td>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="statutColors[br.statut] || 'bg-gray-100 text-gray-800'">
                  {{ statutLabels[br.statut] || br.statut }}
                </span>
              </td>
              <td class="text-right">
                <div class="flex justify-end space-x-1">
                  <button @click="viewBR(br.id)" class="p-1.5 text-gray-500 hover:text-ct-blue-600 hover:bg-gray-100 rounded" title="Voir details">
                    <EyeIcon class="w-5 h-5" />
                  </button>
                  <button v-if="canDelete(br)" @click="deleteBR(br)" class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded" title="Supprimer">
                    <TrashIcon class="w-5 h-5" />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!data?.data?.length">
              <td colspan="8" class="text-center py-8 text-gray-500">Aucune reception trouvee</td>
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
