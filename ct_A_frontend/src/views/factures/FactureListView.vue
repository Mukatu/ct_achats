<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue'
import { useRouter } from 'vue-router'
import { factureService, referentielService } from '@/services/api'
import { formatMontant, formatDate, type Facture, type Pagination, type SelectOption } from '@/types'
import {
  PlusIcon,
  MagnifyingGlassIcon,
  FunnelIcon,
  EyeIcon,
  TrashIcon,
  ExclamationTriangleIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
} from '@heroicons/vue/24/outline'

const router = useRouter()
const loading = ref(true)
const data = ref<Pagination<Facture> | null>(null)

const filters = ref({
  search: '',
  type_facture: '',
  statut: '',
  statut_paiement: '',
  fournisseur_id: '',
  date_debut: '',
  date_fin: '',
  en_retard: false,
})

const showFilters = ref(false)
const statuts = ref<SelectOption[]>([])
const statutsPaiement = ref<SelectOption[]>([])
const typesFacture = ref<SelectOption[]>([])
const fournisseurs = ref<SelectOption[]>([])
const currentPage = ref(1)

const statutColors: Record<string, string> = {
  'BROUILLON': 'bg-gray-100 text-gray-800',
  'A_RAPPROCHER': 'bg-blue-100 text-blue-800',
  'EN_RAPPROCHEMENT': 'bg-indigo-100 text-indigo-800',
  'RAPPROCHEE': 'bg-cyan-100 text-cyan-800',
  'A_VALIDER': 'bg-yellow-100 text-yellow-800',
  'VALIDEE': 'bg-green-100 text-green-800',
  'EN_LITIGE': 'bg-orange-100 text-orange-800',
  'REJETEE': 'bg-red-100 text-red-800',
  'ANNULEE': 'bg-red-100 text-red-800',
}

const statutLabels: Record<string, string> = {
  'BROUILLON': 'Brouillon',
  'A_RAPPROCHER': 'A rapprocher',
  'EN_RAPPROCHEMENT': 'En rapprochement',
  'RAPPROCHEE': 'Rapprochee',
  'A_VALIDER': 'A valider',
  'VALIDEE': 'Validee',
  'EN_LITIGE': 'En litige',
  'REJETEE': 'Rejetee',
  'ANNULEE': 'Annulee',
}

const paiementColors: Record<string, string> = {
  'NON_PAYEE': 'bg-gray-100 text-gray-800',
  'PARTIEL': 'bg-yellow-100 text-yellow-800',
  'EN_PAIEMENT': 'bg-blue-100 text-blue-800',
  'PAYEE': 'bg-green-100 text-green-800',
  'SUSPENDUE': 'bg-red-100 text-red-800',
}

const paiementLabels: Record<string, string> = {
  'NON_PAYEE': 'Non payee',
  'PARTIEL': 'Partiel',
  'EN_PAIEMENT': 'En paiement',
  'PAYEE': 'Payee',
  'SUSPENDUE': 'Suspendue',
}

const typeColors: Record<string, string> = {
  'FACTURE': 'bg-blue-100 text-blue-800',
  'AVOIR': 'bg-red-100 text-red-800',
  'ACOMPTE': 'bg-yellow-100 text-yellow-800',
  'SITUATION': 'bg-purple-100 text-purple-800',
}

// Verifier si une facture est en retard
function isEnRetard(facture: Facture): boolean {
  if (facture.statut_paiement === 'PAYEE') return false
  if (!facture.date_echeance) return false
  return new Date(facture.date_echeance) < new Date()
}

async function loadData() {
  loading.value = true
  try {
    const params: any = { page: currentPage.value }
    if (filters.value.search) params.search = filters.value.search
    if (filters.value.type_facture) params.type_facture = filters.value.type_facture
    if (filters.value.statut) params.statut = filters.value.statut
    if (filters.value.statut_paiement) params.statut_paiement = filters.value.statut_paiement
    if (filters.value.fournisseur_id) params.fournisseur_id = filters.value.fournisseur_id
    if (filters.value.date_debut) params.date_debut = filters.value.date_debut
    if (filters.value.date_fin) params.date_fin = filters.value.date_fin
    if (filters.value.en_retard) params.en_retard = true

    const response = await factureService.getAll(params)
    data.value = response.data
  } catch (error) {
    console.error('Erreur chargement factures:', error)
  } finally {
    loading.value = false
  }
}

async function loadReferentiels() {
  try {
    const [statutsRes, paiementRes, typesRes, fournisseursRes] = await Promise.all([
      referentielService.getStatutsFacture(),
      referentielService.getStatutsPaiement(),
      referentielService.getTypesFacture(),
      referentielService.getFournisseurs(),
    ])
    statuts.value = statutsRes.data
    statutsPaiement.value = paiementRes.data
    typesFacture.value = typesRes.data
    fournisseurs.value = fournisseursRes.data.map((f: any) => ({
      value: f.id,
      label: f.raison_sociale || f.sigle,
    }))
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
    type_facture: '',
    statut: '',
    statut_paiement: '',
    fournisseur_id: '',
    date_debut: '',
    date_fin: '',
    en_retard: false,
  }
  currentPage.value = 1
  loadData()
}

function viewFacture(id: string) {
  router.push(`/factures/${id}`)
}

function createFacture() {
  router.push('/factures/nouveau')
}

async function deleteFacture(facture: Facture) {
  if (!confirm(`Voulez-vous vraiment supprimer la facture "${facture.numero_interne}" ?\n\nCette action est irréversible.`)) {
    return
  }
  try {
    await factureService.delete(facture.id)
    loadData()
  } catch (error: any) {
    alert(error.response?.data?.message || 'Erreur lors de la suppression')
  }
}

function canDelete(facture: Facture): boolean {
  return ['BROUILLON', 'A_RAPPROCHER'].includes(facture.statut)
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
        <h1 class="text-2xl font-bold text-gray-900">Factures</h1>
        <p class="text-gray-600 mt-1">Gerez les factures fournisseurs et leur paiement</p>
      </div>
      <button @click="createFacture" class="btn-primary inline-flex items-center">
        <PlusIcon class="w-5 h-5 mr-2" />
        Nouvelle Facture
      </button>
    </div>

    <div class="card mb-6">
      <div class="flex flex-col sm:flex-row gap-4">
        <div class="flex-1 relative">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
          <input
            v-model="filters.search"
            type="text"
            placeholder="Rechercher par numero, fournisseur..."
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
            <select v-model="filters.type_facture" class="input" @change="applyFilters">
              <option value="">Tous</option>
              <option v-for="t in typesFacture" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
          </div>
          <div>
            <label class="label">Statut</label>
            <select v-model="filters.statut" class="input" @change="applyFilters">
              <option value="">Tous</option>
              <option v-for="s in statuts" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
          </div>
          <div>
            <label class="label">Paiement</label>
            <select v-model="filters.statut_paiement" class="input" @change="applyFilters">
              <option value="">Tous</option>
              <option v-for="s in statutsPaiement" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
          </div>
          <div>
            <label class="label">Fournisseur</label>
            <select v-model="filters.fournisseur_id" class="input" @change="applyFilters">
              <option value="">Tous les fournisseurs</option>
              <option v-for="f in fournisseurs" :key="f.value" :value="f.value">{{ f.label }}</option>
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
          <div class="flex items-end">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="filters.en_retard" @change="applyFilters" class="w-4 h-4 text-ct-blue-600 rounded" />
              <span class="text-sm text-gray-700">En retard</span>
            </label>
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
              <th>N Interne</th>
              <th>Type</th>
              <th>N Fournisseur</th>
              <th>Fournisseur</th>
              <th>Date</th>
              <th>Echeance</th>
              <th class="text-right">Net a payer</th>
              <th>Statut</th>
              <th>Paiement</th>
              <th class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="facture in data?.data" :key="facture.id" class="hover:bg-gray-50">
              <td class="font-medium text-ct-blue-600">{{ facture.numero_interne }}</td>
              <td>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" :class="typeColors[facture.type_facture]">
                  {{ facture.type_facture }}
                </span>
              </td>
              <td class="text-gray-600">{{ facture.numero_fournisseur }}</td>
              <td class="max-w-[150px] truncate" :title="facture.fournisseur?.raison_sociale">
                {{ facture.fournisseur?.raison_sociale || '-' }}
              </td>
              <td>{{ formatDate(facture.date_facture) }}</td>
              <td>
                <div class="flex items-center gap-1">
                  <span :class="isEnRetard(facture) ? 'text-red-600 font-medium' : ''">
                    {{ formatDate(facture.date_echeance) }}
                  </span>
                  <ExclamationTriangleIcon v-if="isEnRetard(facture)" class="w-4 h-4 text-red-500" title="En retard" />
                </div>
              </td>
              <td class="text-right font-medium">{{ formatMontant(facture.net_a_payer) }}</td>
              <td>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="statutColors[facture.statut] || 'bg-gray-100 text-gray-800'">
                  {{ statutLabels[facture.statut] || facture.statut }}
                </span>
              </td>
              <td>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="paiementColors[facture.statut_paiement] || 'bg-gray-100 text-gray-800'">
                  {{ paiementLabels[facture.statut_paiement] || facture.statut_paiement }}
                </span>
              </td>
              <td class="text-right">
                <button @click="viewFacture(facture.id)" class="p-1.5 text-gray-500 hover:text-ct-blue-600 hover:bg-gray-100 rounded" title="Voir details">
                  <EyeIcon class="w-5 h-5" />
                </button>
                <button v-if="canDelete(facture)" @click="deleteFacture(facture)" class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded" title="Supprimer">
                  <TrashIcon class="w-5 h-5" />
                </button>
              </td>
            </tr>
            <tr v-if="!data?.data?.length">
              <td colspan="10" class="text-center py-8 text-gray-500">Aucune facture trouvee</td>
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
