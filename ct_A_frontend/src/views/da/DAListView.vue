<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { daService, referentielService } from '@/services/api'
import { formatMontant, formatDate, type DemandeAchat, type Pagination, type SelectOption } from '@/types'
import ImportModal from '@/components/ImportModal.vue'
import {
  PlusIcon,
  MagnifyingGlassIcon,
  FunnelIcon,
  EyeIcon,
  TrashIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  ArrowUpTrayIcon,
} from '@heroicons/vue/24/outline'

const router = useRouter()
const loading = ref(true)
const data = ref<Pagination<DemandeAchat> | null>(null)
const showImportModal = ref(false)

const filters = ref({
  search: '',
  type_demande: '',
  statut: '',
  direction_id: '',
  acheteur_id: '',
  date_debut: '',
  date_fin: '',
})

const showFilters = ref(false)
const statuts = ref<SelectOption[]>([])
const directions = ref<SelectOption[]>([])
const acheteurs = ref<SelectOption[]>([])
const currentPage = ref(1)

const statutColors: Record<string, string> = {
  'EN_SUSPENS': 'bg-gray-100 text-gray-800',
  'EN_COURS_ACH': 'bg-blue-100 text-blue-800',
  'EN_COURS_CDG': 'bg-yellow-100 text-yellow-800',
  'EN_COURS_DFC': 'bg-orange-100 text-orange-800',
  'EN_COURS_DG': 'bg-purple-100 text-purple-800',
  'TRAITE': 'bg-green-100 text-green-800',
  'ANNULE': 'bg-red-100 text-red-800',
}

const statutLabels: Record<string, string> = {
  'EN_SUSPENS': 'En Suspens',
  'EN_COURS_ACH': 'En cours ACH',
  'EN_COURS_CDG': 'En cours CDG',
  'EN_COURS_DFC': 'En cours DFC',
  'EN_COURS_DG': 'En cours DG',
  'TRAITE': 'Traitée',
  'ANNULE': 'Annulée',
}

const typeColors: Record<string, string> = {
  'DA': 'bg-blue-100 text-blue-800',
  'DAC': 'bg-orange-100 text-orange-800',
}

async function loadData() {
  loading.value = true
  try {
    const params: any = { page: currentPage.value }
    if (filters.value.search) params.search = filters.value.search
    if (filters.value.type_demande) params.type_demande = filters.value.type_demande
    if (filters.value.statut) params.statut = filters.value.statut
    if (filters.value.direction_id) params.direction_id = filters.value.direction_id
    if (filters.value.acheteur_id) params.acheteur_id = filters.value.acheteur_id
    if (filters.value.date_debut) params.date_debut = filters.value.date_debut
    if (filters.value.date_fin) params.date_fin = filters.value.date_fin

    const response = await daService.getAll(params)
    data.value = response.data
  } catch (error) {
    console.error('Erreur chargement DA:', error)
  } finally {
    loading.value = false
  }
}

async function loadReferentiels() {
  try {
    const [statutsRes, directionsRes, acheteursRes] = await Promise.all([
      referentielService.getStatutsDA(),
      referentielService.getDirections(),
      referentielService.getAcheteurs(),
    ])
    statuts.value = statutsRes.data
    directions.value = directionsRes.data.map((d: any) => ({
      value: d.id,
      label: d.libelle_court || d.libelle,
    }))
    acheteurs.value = acheteursRes.data
  } catch (error) {
    console.error('Erreur chargement référentiels:', error)
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
    type_demande: '',
    statut: '',
    direction_id: '',
    acheteur_id: '',
    date_debut: '',
    date_fin: '',
  }
  currentPage.value = 1
  loadData()
}

function viewDA(id: string) {
  router.push(`/demandes-achat/${id}`)
}

function createDA() {
  router.push('/demandes-achat/nouveau')
}

async function deleteDA(da: DemandeAchat) {
  if (!confirm(`Voulez-vous vraiment supprimer la DA "${da.numero}" ?\n\nCette action est irréversible.`)) {
    return
  }
  try {
    await daService.delete(da.id)
    loadData()
  } catch (error: any) {
    alert(error.response?.data?.message || 'Erreur lors de la suppression')
  }
}

function canDelete(da: DemandeAchat): boolean {
  return ['EN_SUSPENS', 'EN_COURS_ACH'].includes(da.statut)
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
        <h1 class="text-2xl font-bold text-gray-900">Demandes d'Achat</h1>
        <p class="text-gray-600 mt-1">Gerez les demandes d'achat (DA/DAC)</p>
      </div>
      <div class="flex gap-2">
        <button @click="showImportModal = true" class="btn-secondary inline-flex items-center">
          <ArrowUpTrayIcon class="w-5 h-5 mr-2" />
          Importer
        </button>
        <button @click="createDA" class="btn-primary inline-flex items-center">
          <PlusIcon class="w-5 h-5 mr-2" />
          Nouvelle DA
        </button>
      </div>
    </div>

    <div class="card mb-6">
      <div class="flex flex-col sm:flex-row gap-4">
        <div class="flex-1 relative">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
          <input
            v-model="filters.search"
            type="text"
            placeholder="Rechercher par numéro ou objet..."
            class="input pl-10"
          />
        </div>
        <button @click="showFilters = !showFilters" class="btn-secondary inline-flex items-center">
          <FunnelIcon class="w-5 h-5 mr-2" />
          Filtres
        </button>
      </div>

      <div v-if="showFilters" class="mt-4 pt-4 border-t border-gray-200">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          <div>
            <label class="label">Type</label>
            <select v-model="filters.type_demande" class="input" @change="applyFilters">
              <option value="">Tous les types</option>
              <option value="DA">DA - Demande d'Achat</option>
              <option value="DAC">DAC - Demande Achat Caisse</option>
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
            <label class="label">Direction</label>
            <select v-model="filters.direction_id" class="input" @change="applyFilters">
              <option value="">Toutes les directions</option>
              <option v-for="d in directions" :key="d.value" :value="d.value">{{ d.label }}</option>
            </select>
          </div>
          <div>
            <label class="label">Acheteur</label>
            <select v-model="filters.acheteur_id" class="input" @change="applyFilters">
              <option value="">Tous les acheteurs</option>
              <option v-for="a in acheteurs" :key="a.value" :value="a.value">{{ a.label }}</option>
            </select>
          </div>
          <div>
            <label class="label">Date début</label>
            <input v-model="filters.date_debut" type="date" class="input" @change="applyFilters" />
          </div>
          <div>
            <label class="label">Date fin</label>
            <input v-model="filters.date_fin" type="date" class="input" @change="applyFilters" />
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
              <th>Numéro</th>
              <th>Type</th>
              <th>Date</th>
              <th>Direction</th>
              <th>Objet</th>
              <th class="text-right">Montant</th>
              <th>Statut</th>
              <th class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="da in data?.data" :key="da.id" class="hover:bg-gray-50">
              <td class="font-medium text-ct-blue-600">{{ da.numero }}</td>
              <td>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" :class="typeColors[da.type_demande]">
                  {{ da.type_demande }}
                </span>
              </td>
              <td>{{ formatDate(da.date_demande) }}</td>
              <td>{{ da.direction?.libelle_court || da.direction?.libelle }}</td>
              <td class="max-w-xs truncate" :title="da.objet">{{ da.objet }}</td>
              <td class="text-right font-medium">{{ formatMontant(da.montant) }}</td>
              <td>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="statutColors[da.statut] || 'bg-gray-100 text-gray-800'">
                  {{ statutLabels[da.statut] || da.statut }}
                </span>
              </td>
              <td class="text-right">
                <div class="flex justify-end space-x-2">
                  <button @click="viewDA(da.id)" class="p-1.5 text-gray-500 hover:text-ct-blue-600 hover:bg-gray-100 rounded" title="Voir détails">
                    <EyeIcon class="w-5 h-5" />
                  </button>
                  <button v-if="canDelete(da)" @click="deleteDA(da)" class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded" title="Supprimer">
                    <TrashIcon class="w-5 h-5" />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!data?.data?.length">
              <td colspan="8" class="text-center py-8 text-gray-500">Aucune demande d'achat trouvée</td>
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

    <!-- Modal Import -->
    <ImportModal
      :show="showImportModal"
      type="da"
      @close="showImportModal = false"
      @success="loadData()"
    />
  </div>
</template>
