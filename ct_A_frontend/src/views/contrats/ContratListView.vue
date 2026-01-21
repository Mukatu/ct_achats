<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { contratService, referentielService } from '@/services/api'
import { formatMontant, formatDate, type Pagination, type SelectOption } from '@/types'
import {
  PlusIcon,
  MagnifyingGlassIcon,
  FunnelIcon,
  EyeIcon,
  TrashIcon,
  ExclamationTriangleIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  CalendarDaysIcon,
} from '@heroicons/vue/24/outline'

interface Contrat {
  id: string
  numero: string
  reference_externe?: string
  objet: string
  type_contrat?: { id: string; libelle: string }
  fournisseur?: { id: string; raison_sociale: string }
  direction?: { id: string; libelle: string }
  zone?: { id: string; libelle: string }
  date_debut: string
  date_fin?: string
  periodicite: string
  montant_periodique: number
  montant_annuel: number
  statut: string
  jours_restants?: number
}

const router = useRouter()
const loading = ref(true)
const data = ref<Pagination<Contrat> | null>(null)

const filters = ref({
  search: '',
  statut: '',
  type_contrat_id: '',
  fournisseur_id: '',
  direction_id: '',
  a_renouveler: false,
})

const showFilters = ref(false)
const statuts = ref<SelectOption[]>([])
const typesContrat = ref<SelectOption[]>([])
const fournisseurs = ref<SelectOption[]>([])
const directions = ref<SelectOption[]>([])
const currentPage = ref(1)

const statutColors: Record<string, string> = {
  'BROUILLON': 'bg-gray-100 text-gray-800',
  'ACTIF': 'bg-green-100 text-green-800',
  'SUSPENDU': 'bg-yellow-100 text-yellow-800',
  'TERMINE': 'bg-blue-100 text-blue-800',
  'RESILIE': 'bg-red-100 text-red-800',
}

const statutLabels: Record<string, string> = {
  'BROUILLON': 'Brouillon',
  'ACTIF': 'Actif',
  'SUSPENDU': 'Suspendu',
  'TERMINE': 'Termine',
  'RESILIE': 'Resilie',
}

const periodiciteLabels: Record<string, string> = {
  'MENSUEL': 'Mensuel',
  'TRIMESTRIEL': 'Trimestriel',
  'SEMESTRIEL': 'Semestriel',
  'ANNUEL': 'Annuel',
  'PONCTUEL': 'Ponctuel',
}

function isExpireSoon(contrat: Contrat): boolean {
  return contrat.jours_restants !== null && contrat.jours_restants !== undefined && contrat.jours_restants <= 90 && contrat.jours_restants > 0
}

function isExpired(contrat: Contrat): boolean {
  return contrat.jours_restants !== null && contrat.jours_restants !== undefined && contrat.jours_restants <= 0
}

async function loadData() {
  loading.value = true
  try {
    const params: any = { page: currentPage.value }
    if (filters.value.search) params.search = filters.value.search
    if (filters.value.statut) params.statut = filters.value.statut
    if (filters.value.type_contrat_id) params.type_contrat_id = filters.value.type_contrat_id
    if (filters.value.fournisseur_id) params.fournisseur_id = filters.value.fournisseur_id
    if (filters.value.direction_id) params.direction_id = filters.value.direction_id
    if (filters.value.a_renouveler) params.a_renouveler = true

    const response = await contratService.getAll(params)
    data.value = response.data
  } catch (error) {
    console.error('Erreur chargement contrats:', error)
  } finally {
    loading.value = false
  }
}

async function loadReferentiels() {
  try {
    const [statutsRes, typesRes, fournisseursRes, directionsRes] = await Promise.all([
      contratService.getStatuts(),
      contratService.getTypesContrat(),
      referentielService.getFournisseurs(),
      referentielService.getDirections(),
    ])
    statuts.value = statutsRes.data
    typesContrat.value = typesRes.data.map((t: any) => ({ value: t.id, label: t.libelle }))
    fournisseurs.value = fournisseursRes.data.map((f: any) => ({ value: f.id, label: f.raison_sociale || f.sigle }))
    directions.value = directionsRes.data.map((d: any) => ({ value: d.id, label: d.libelle }))
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
    statut: '',
    type_contrat_id: '',
    fournisseur_id: '',
    direction_id: '',
    a_renouveler: false,
  }
  currentPage.value = 1
  loadData()
}

function viewContrat(id: string) {
  router.push(`/contrats/${id}`)
}

function createContrat() {
  router.push('/contrats/nouveau')
}

async function deleteContrat(contrat: Contrat) {
  if (!confirm(`Voulez-vous vraiment supprimer le contrat "${contrat.numero}" ?\n\nCette action est irreversible.`)) {
    return
  }
  try {
    await contratService.delete(contrat.id)
    loadData()
  } catch (error: any) {
    alert(error.response?.data?.message || 'Erreur lors de la suppression')
  }
}

function canDelete(contrat: Contrat): boolean {
  return contrat.statut === 'BROUILLON'
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
        <h1 class="text-2xl font-bold text-gray-900">Contrats</h1>
        <p class="text-gray-600 mt-1">Gerez les contrats recurrents et leurs echeances</p>
      </div>
      <div class="flex gap-2">
        <router-link to="/echeances" class="btn-secondary inline-flex items-center">
          <CalendarDaysIcon class="w-5 h-5 mr-2" />
          Echeances
        </router-link>
        <button @click="createContrat" class="btn-primary inline-flex items-center">
          <PlusIcon class="w-5 h-5 mr-2" />
          Nouveau Contrat
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
            placeholder="Rechercher par numero, objet, fournisseur..."
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
              <option v-for="s in statuts" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
          </div>
          <div>
            <label class="label">Type de contrat</label>
            <select v-model="filters.type_contrat_id" class="input" @change="applyFilters">
              <option value="">Tous</option>
              <option v-for="t in typesContrat" :key="t.value" :value="t.value">{{ t.label }}</option>
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
            <label class="label">Direction</label>
            <select v-model="filters.direction_id" class="input" @change="applyFilters">
              <option value="">Toutes</option>
              <option v-for="d in directions" :key="d.value" :value="d.value">{{ d.label }}</option>
            </select>
          </div>
          <div class="flex items-end">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="filters.a_renouveler" @change="applyFilters" class="w-4 h-4 text-ct-blue-600 rounded" />
              <span class="text-sm text-gray-700">A renouveler (90 jours)</span>
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
              <th>Numero</th>
              <th>Type</th>
              <th>Fournisseur</th>
              <th>Objet</th>
              <th>Direction</th>
              <th>Periodicite</th>
              <th class="text-right">Montant annuel</th>
              <th>Echeance</th>
              <th>Statut</th>
              <th class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="contrat in data?.data" :key="contrat.id" class="hover:bg-gray-50">
              <td class="font-medium text-ct-blue-600">{{ contrat.numero }}</td>
              <td>{{ contrat.type_contrat?.libelle || '-' }}</td>
              <td class="max-w-[150px] truncate" :title="contrat.fournisseur?.raison_sociale">
                {{ contrat.fournisseur?.raison_sociale || '-' }}
              </td>
              <td class="max-w-[200px] truncate" :title="contrat.objet">{{ contrat.objet }}</td>
              <td>{{ contrat.direction?.libelle || '-' }}</td>
              <td>{{ periodiciteLabels[contrat.periodicite] || contrat.periodicite }}</td>
              <td class="text-right font-medium">{{ formatMontant(contrat.montant_annuel) }}</td>
              <td>
                <div class="flex items-center gap-1">
                  <span :class="[
                    isExpired(contrat) ? 'text-red-600 font-medium' : '',
                    isExpireSoon(contrat) ? 'text-yellow-600 font-medium' : ''
                  ]">
                    {{ contrat.date_fin ? formatDate(contrat.date_fin) : 'Indeterminee' }}
                  </span>
                  <ExclamationTriangleIcon v-if="isExpired(contrat)" class="w-4 h-4 text-red-500" title="Expire" />
                  <ExclamationTriangleIcon v-else-if="isExpireSoon(contrat)" class="w-4 h-4 text-yellow-500" title="Expire bientot" />
                </div>
              </td>
              <td>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="statutColors[contrat.statut] || 'bg-gray-100 text-gray-800'">
                  {{ statutLabels[contrat.statut] || contrat.statut }}
                </span>
              </td>
              <td class="text-right">
                <button @click="viewContrat(contrat.id)" class="p-1.5 text-gray-500 hover:text-ct-blue-600 hover:bg-gray-100 rounded" title="Voir details">
                  <EyeIcon class="w-5 h-5" />
                </button>
                <button v-if="canDelete(contrat)" @click="deleteContrat(contrat)" class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded" title="Supprimer">
                  <TrashIcon class="w-5 h-5" />
                </button>
              </td>
            </tr>
            <tr v-if="!data?.data?.length">
              <td colspan="10" class="text-center py-8 text-gray-500">Aucun contrat trouve</td>
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
