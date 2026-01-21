<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { echeanceService, contratService } from '@/services/api'
import { formatMontant, formatDate, type Pagination } from '@/types'
import {
  MagnifyingGlassIcon,
  FunnelIcon,
  EyeIcon,
  BanknotesIcon,
  CheckIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  ExclamationTriangleIcon,
  ArrowLeftIcon,
} from '@heroicons/vue/24/outline'

interface Echeance {
  id: string
  numero: string
  periode: string
  date_echeance: string
  date_facture?: string
  numero_facture?: string
  montant_prevu: number
  montant_facture?: number
  statut: string
  date_paiement?: string
  contrat: {
    id: string
    numero: string
    objet: string
    fournisseur?: { raison_sociale: string }
    type_contrat?: { libelle: string }
    direction?: { libelle: string }
  }
}

const router = useRouter()
const loading = ref(true)
const data = ref<Pagination<Echeance> | null>(null)
const stats = ref({ a_venir: 0, a_traiter: 0, en_retard: 0, du_mois: 0 })

const filters = ref({
  search: '',
  statut: '',
  annee: new Date().getFullYear(),
  mois: '',
  en_retard: false,
  a_traiter: false,
  du_mois: false,
})

const showFilters = ref(false)
const currentPage = ref(1)

const statutColors: Record<string, string> = {
  'A_VENIR': 'bg-gray-100 text-gray-800',
  'A_TRAITER': 'bg-yellow-100 text-yellow-800',
  'EN_COURS': 'bg-blue-100 text-blue-800',
  'PAYE': 'bg-green-100 text-green-800',
  'ANNULE': 'bg-red-100 text-red-800',
}

const statutLabels: Record<string, string> = {
  'A_VENIR': 'A venir',
  'A_TRAITER': 'A traiter',
  'EN_COURS': 'En cours',
  'PAYE': 'Paye',
  'ANNULE': 'Annule',
}

const mois = [
  { value: '1', label: 'Janvier' },
  { value: '2', label: 'Fevrier' },
  { value: '3', label: 'Mars' },
  { value: '4', label: 'Avril' },
  { value: '5', label: 'Mai' },
  { value: '6', label: 'Juin' },
  { value: '7', label: 'Juillet' },
  { value: '8', label: 'Aout' },
  { value: '9', label: 'Septembre' },
  { value: '10', label: 'Octobre' },
  { value: '11', label: 'Novembre' },
  { value: '12', label: 'Decembre' },
]

function isEnRetard(echeance: Echeance): boolean {
  return echeance.statut === 'A_TRAITER' && new Date(echeance.date_echeance) < new Date()
}

async function loadData() {
  loading.value = true
  try {
    const params: any = { page: currentPage.value }
    if (filters.value.search) params.search = filters.value.search
    if (filters.value.statut) params.statut = filters.value.statut
    if (filters.value.annee) params.annee = filters.value.annee
    if (filters.value.mois) params.mois = filters.value.mois
    if (filters.value.en_retard) params.en_retard = true
    if (filters.value.a_traiter) params.a_traiter = true
    if (filters.value.du_mois) params.du_mois = true

    const response = await echeanceService.getAll(params)
    data.value = response.data
  } catch (error) {
    console.error('Erreur chargement echeances:', error)
  } finally {
    loading.value = false
  }
}

async function loadStats() {
  try {
    const response = await echeanceService.getStats(filters.value.annee)
    stats.value = response.data
  } catch (error) {
    console.error('Erreur chargement stats:', error)
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
    annee: new Date().getFullYear(),
    mois: '',
    en_retard: false,
    a_traiter: false,
    du_mois: false,
  }
  currentPage.value = 1
  loadData()
  loadStats()
}

function filterQuick(type: string) {
  filters.value = {
    search: '',
    statut: '',
    annee: new Date().getFullYear(),
    mois: '',
    en_retard: type === 'retard',
    a_traiter: type === 'traiter',
    du_mois: type === 'mois',
  }
  applyFilters()
}

function viewContrat(contratId: string) {
  router.push(`/contrats/${contratId}`)
}

function goBack() {
  router.push('/contrats')
}

let searchTimeout: number | null = null
watch(() => filters.value.search, () => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => applyFilters(), 300) as unknown as number
})

onMounted(() => {
  loadStats()
  loadData()
})
</script>

<template>
  <div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div class="flex items-center gap-4">
        <button @click="goBack" class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg">
          <ArrowLeftIcon class="w-5 h-5" />
        </button>
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Echeances des contrats</h1>
          <p class="text-gray-600 mt-1">Suivi des facturations periodiques</p>
        </div>
      </div>
    </div>

    <!-- Stats rapides -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <button @click="filterQuick('traiter')" class="card hover:shadow-md transition-shadow text-left">
        <div class="text-3xl font-bold text-yellow-600">{{ stats.a_traiter }}</div>
        <div class="text-sm text-gray-500">A traiter</div>
      </button>
      <button @click="filterQuick('retard')" class="card hover:shadow-md transition-shadow text-left">
        <div class="text-3xl font-bold text-red-600">{{ stats.en_retard }}</div>
        <div class="text-sm text-gray-500">En retard</div>
      </button>
      <button @click="filterQuick('mois')" class="card hover:shadow-md transition-shadow text-left">
        <div class="text-3xl font-bold text-blue-600">{{ stats.du_mois }}</div>
        <div class="text-sm text-gray-500">Ce mois</div>
      </button>
      <div class="card">
        <div class="text-3xl font-bold text-gray-600">{{ stats.a_venir }}</div>
        <div class="text-sm text-gray-500">A venir</div>
      </div>
    </div>

    <div class="card mb-6">
      <div class="flex flex-col sm:flex-row gap-4">
        <div class="flex-1 relative">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
          <input
            v-model="filters.search"
            type="text"
            placeholder="Rechercher par numero, contrat, fournisseur..."
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
            <label class="label">Statut</label>
            <select v-model="filters.statut" class="input" @change="applyFilters">
              <option value="">Tous</option>
              <option value="A_VENIR">A venir</option>
              <option value="A_TRAITER">A traiter</option>
              <option value="EN_COURS">En cours</option>
              <option value="PAYE">Paye</option>
              <option value="ANNULE">Annule</option>
            </select>
          </div>
          <div>
            <label class="label">Annee</label>
            <select v-model.number="filters.annee" class="input" @change="applyFilters">
              <option :value="new Date().getFullYear() - 1">{{ new Date().getFullYear() - 1 }}</option>
              <option :value="new Date().getFullYear()">{{ new Date().getFullYear() }}</option>
              <option :value="new Date().getFullYear() + 1">{{ new Date().getFullYear() + 1 }}</option>
            </select>
          </div>
          <div>
            <label class="label">Mois</label>
            <select v-model="filters.mois" class="input" @change="applyFilters">
              <option value="">Tous</option>
              <option v-for="m in mois" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
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
              <th>Contrat</th>
              <th>Fournisseur</th>
              <th>Periode</th>
              <th>Echeance</th>
              <th class="text-right">Montant prevu</th>
              <th>Facture</th>
              <th class="text-right">Montant facture</th>
              <th>Statut</th>
              <th class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="echeance in data?.data" :key="echeance.id" class="hover:bg-gray-50">
              <td>
                <div class="font-medium text-ct-blue-600">{{ echeance.contrat?.numero }}</div>
                <div class="text-sm text-gray-500 truncate max-w-[200px]">{{ echeance.contrat?.objet }}</div>
              </td>
              <td class="max-w-[150px] truncate">{{ echeance.contrat?.fournisseur?.raison_sociale || '-' }}</td>
              <td>{{ echeance.periode }}</td>
              <td>
                <div class="flex items-center gap-1">
                  <span :class="isEnRetard(echeance) ? 'text-red-600 font-medium' : ''">
                    {{ formatDate(echeance.date_echeance) }}
                  </span>
                  <ExclamationTriangleIcon v-if="isEnRetard(echeance)" class="w-4 h-4 text-red-500" />
                </div>
              </td>
              <td class="text-right">{{ formatMontant(echeance.montant_prevu) }}</td>
              <td>{{ echeance.numero_facture || '-' }}</td>
              <td class="text-right">
                {{ echeance.montant_facture ? formatMontant(echeance.montant_facture) : '-' }}
              </td>
              <td>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="statutColors[echeance.statut]">
                  {{ statutLabels[echeance.statut] }}
                </span>
              </td>
              <td class="text-right">
                <button @click="viewContrat(echeance.contrat?.id)" class="p-1.5 text-gray-500 hover:text-ct-blue-600 hover:bg-gray-100 rounded" title="Voir contrat">
                  <EyeIcon class="w-5 h-5" />
                </button>
              </td>
            </tr>
            <tr v-if="!data?.data?.length">
              <td colspan="9" class="text-center py-8 text-gray-500">Aucune echeance trouvee</td>
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
