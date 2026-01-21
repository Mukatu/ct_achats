<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ebService, referentielService } from '@/services/api'
import { formatMontant, formatDate, type ExpressionBesoin, type Pagination, type SelectOption } from '@/types'
import ImportModal from '@/components/ImportModal.vue'
import {
  PlusIcon,
  MagnifyingGlassIcon,
  FunnelIcon,
  EyeIcon,
  PencilIcon,
  TrashIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  ArrowUpTrayIcon,
} from '@heroicons/vue/24/outline'

const router = useRouter()
const loading = ref(true)
const data = ref<Pagination<ExpressionBesoin> | null>(null)
const showImportModal = ref(false)

// Filtres
const filters = ref({
  search: '',
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
  'NA': 'bg-gray-100 text-gray-600',
}

const statutLabels: Record<string, string> = {
  'EN_SUSPENS': 'En Suspens',
  'EN_COURS_ACH': 'En cours ACH',
  'EN_COURS_CDG': 'En cours CDG',
  'EN_COURS_DFC': 'En cours DFC',
  'EN_COURS_DG': 'En cours DG',
  'TRAITE': 'Traitée',
  'ANNULE': 'Annulée',
  'NA': 'N/A',
}

async function loadData() {
  loading.value = true
  try {
    const params: any = { page: currentPage.value }

    if (filters.value.search) params.search = filters.value.search
    if (filters.value.statut) params.statut = filters.value.statut
    if (filters.value.direction_id) params.direction_id = filters.value.direction_id
    if (filters.value.acheteur_id) params.acheteur_id = filters.value.acheteur_id
    if (filters.value.date_debut) params.date_debut = filters.value.date_debut
    if (filters.value.date_fin) params.date_fin = filters.value.date_fin

    const response = await ebService.getAll(params)
    data.value = response.data
  } catch (error) {
    console.error('Erreur chargement EB:', error)
  } finally {
    loading.value = false
  }
}

async function loadReferentiels() {
  try {
    const [statutsRes, directionsRes, acheteursRes] = await Promise.all([
      referentielService.getStatutsEB(),
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
    statut: '',
    direction_id: '',
    acheteur_id: '',
    date_debut: '',
    date_fin: '',
  }
  currentPage.value = 1
  loadData()
}

function viewEB(id: string) {
  router.push(`/expressions-besoin/${id}`)
}

function editEB(id: string) {
  router.push(`/expressions-besoin/${id}/modifier`)
}

function createEB() {
  router.push('/expressions-besoin/nouveau')
}

async function deleteEB(eb: ExpressionBesoin) {
  if (!confirm(`Voulez-vous vraiment supprimer l'EB "${eb.numero}" ?\n\nCette action est irréversible.`)) {
    return
  }
  try {
    await ebService.delete(eb.id)
    loadData()
  } catch (error: any) {
    alert(error.response?.data?.message || 'Erreur lors de la suppression')
  }
}

function canDelete(eb: ExpressionBesoin): boolean {
  // On peut supprimer seulement si le statut est EN_SUSPENS ou EN_COURS_ACH
  return ['EN_SUSPENS', 'EN_COURS_ACH'].includes(eb.statut)
}

// Debounce search
let searchTimeout: number | null = null
watch(() => filters.value.search, () => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    applyFilters()
  }, 300) as unknown as number
})

onMounted(() => {
  loadReferentiels()
  loadData()
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Expressions de Besoins</h1>
        <p class="text-gray-600 mt-1">Gerez les demandes d'expression de besoins</p>
      </div>
      <div class="flex gap-2">
        <button @click="showImportModal = true" class="btn-secondary inline-flex items-center">
          <ArrowUpTrayIcon class="w-5 h-5 mr-2" />
          Importer
        </button>
        <button @click="createEB" class="btn-primary inline-flex items-center">
          <PlusIcon class="w-5 h-5 mr-2" />
          Nouvelle EB
        </button>
      </div>
    </div>

    <!-- Search and Filters -->
    <div class="card mb-6">
      <div class="flex flex-col sm:flex-row gap-4">
        <!-- Search -->
        <div class="flex-1 relative">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
          <input
            v-model="filters.search"
            type="text"
            placeholder="Rechercher par numéro ou objet..."
            class="input pl-10"
          />
        </div>

        <!-- Filter toggle -->
        <button
          @click="showFilters = !showFilters"
          class="btn-secondary inline-flex items-center"
        >
          <FunnelIcon class="w-5 h-5 mr-2" />
          Filtres
        </button>
      </div>

      <!-- Expanded filters -->
      <div v-if="showFilters" class="mt-4 pt-4 border-t border-gray-200">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
          <div>
            <label class="label">Statut</label>
            <select v-model="filters.statut" class="input" @change="applyFilters">
              <option value="">Tous les statuts</option>
              <option v-for="s in statuts" :key="s.value" :value="s.value">
                {{ s.label }}
              </option>
            </select>
          </div>

          <div>
            <label class="label">Direction</label>
            <select v-model="filters.direction_id" class="input" @change="applyFilters">
              <option value="">Toutes les directions</option>
              <option v-for="d in directions" :key="d.value" :value="d.value">
                {{ d.label }}
              </option>
            </select>
          </div>

          <div>
            <label class="label">Acheteur</label>
            <select v-model="filters.acheteur_id" class="input" @change="applyFilters">
              <option value="">Tous les acheteurs</option>
              <option v-for="a in acheteurs" :key="a.value" :value="a.value">
                {{ a.label }}
              </option>
            </select>
          </div>

          <div>
            <label class="label">Date début</label>
            <input
              v-model="filters.date_debut"
              type="date"
              class="input"
              @change="applyFilters"
            />
          </div>

          <div>
            <label class="label">Date fin</label>
            <input
              v-model="filters.date_fin"
              type="date"
              class="input"
              @change="applyFilters"
            />
          </div>
        </div>

        <div class="mt-4 flex justify-end">
          <button @click="resetFilters" class="text-sm text-gray-600 hover:text-gray-900">
            Réinitialiser les filtres
          </button>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-ct-blue-500"></div>
    </div>

    <!-- Table -->
    <div v-else class="card">
      <div class="table-container">
        <table class="table">
          <thead>
            <tr>
              <th>Numéro</th>
              <th>Date</th>
              <th>Direction</th>
              <th>Objet</th>
              <th class="text-right">Estimation</th>
              <th>Statut</th>
              <th>Acheteur</th>
              <th class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="eb in data?.data" :key="eb.id" class="hover:bg-gray-50">
              <td class="font-medium text-ct-blue-600">{{ eb.numero }}</td>
              <td>{{ formatDate(eb.date_expression) }}</td>
              <td>{{ eb.direction?.libelle_court || eb.direction?.libelle }}</td>
              <td class="max-w-xs truncate" :title="eb.objet">{{ eb.objet }}</td>
              <td class="text-right font-medium">{{ formatMontant(eb.estimation) }}</td>
              <td>
                <span
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                  :class="statutColors[eb.statut] || 'bg-gray-100 text-gray-800'"
                >
                  {{ statutLabels[eb.statut] || eb.statut }}
                </span>
              </td>
              <td>{{ eb.acheteur?.prenom || '-' }}</td>
              <td class="text-right">
                <div class="flex justify-end space-x-2">
                  <button
                    @click="viewEB(eb.id)"
                    class="p-1.5 text-gray-500 hover:text-ct-blue-600 hover:bg-gray-100 rounded"
                    title="Voir détails"
                  >
                    <EyeIcon class="w-5 h-5" />
                  </button>
                  <button
                    v-if="eb.statut === 'EN_SUSPENS' || eb.statut === 'EN_COURS_ACH'"
                    @click="editEB(eb.id)"
                    class="p-1.5 text-gray-500 hover:text-ct-orange-600 hover:bg-gray-100 rounded"
                    title="Modifier"
                  >
                    <PencilIcon class="w-5 h-5" />
                  </button>
                  <button
                    v-if="canDelete(eb)"
                    @click="deleteEB(eb)"
                    class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded"
                    title="Supprimer"
                  >
                    <TrashIcon class="w-5 h-5" />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!data?.data?.length">
              <td colspan="8" class="text-center py-8 text-gray-500">
                Aucune expression de besoin trouvée
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="data && data.last_page > 1" class="flex items-center justify-between mt-4 pt-4 border-t border-gray-200">
        <p class="text-sm text-gray-600">
          Page {{ data.current_page }} sur {{ data.last_page }}
          ({{ data.total }} résultats)
        </p>
        <div class="flex space-x-2">
          <button
            @click="goToPage(data.current_page - 1)"
            :disabled="data.current_page === 1"
            class="btn-secondary p-2 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <ChevronLeftIcon class="w-5 h-5" />
          </button>
          <button
            @click="goToPage(data.current_page + 1)"
            :disabled="data.current_page === data.last_page"
            class="btn-secondary p-2 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <ChevronRightIcon class="w-5 h-5" />
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Import -->
    <ImportModal
      :show="showImportModal"
      type="eb"
      @close="showImportModal = false"
      @success="loadData()"
    />
  </div>
</template>
