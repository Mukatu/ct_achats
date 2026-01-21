<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { bcService, referentielService } from '@/services/api'
import { formatMontant, formatDate, type BonCommande, type Pagination, type SelectOption } from '@/types'
import ImportModal from '@/components/ImportModal.vue'
import {
  PlusIcon,
  MagnifyingGlassIcon,
  FunnelIcon,
  EyeIcon,
  TrashIcon,
  DocumentArrowDownIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  ArrowUpTrayIcon,
} from '@heroicons/vue/24/outline'

const router = useRouter()
const loading = ref(true)
const data = ref<Pagination<BonCommande> | null>(null)
const showImportModal = ref(false)

const filters = ref({
  search: '',
  type_bc: '',
  statut: '',
  fournisseur_id: '',
  direction_id: '',
  acheteur_id: '',
  date_debut: '',
  date_fin: '',
})

const showFilters = ref(false)
const statuts = ref<SelectOption[]>([])
const typesBC = ref<SelectOption[]>([])
const directions = ref<SelectOption[]>([])
const acheteurs = ref<SelectOption[]>([])
const fournisseurs = ref<SelectOption[]>([])
const currentPage = ref(1)

const statutColors: Record<string, string> = {
  'NC': 'bg-gray-100 text-gray-800',
  'EN_COURS_A': 'bg-blue-100 text-blue-800',
  'EN_COURS_CDG': 'bg-yellow-100 text-yellow-800',
  'EN_COURS_DFC': 'bg-orange-100 text-orange-800',
  'EN_COURS_FSSEUR': 'bg-indigo-100 text-indigo-800',
  'LIVRAISON_PARTIELLE': 'bg-cyan-100 text-cyan-800',
  'LIVRE': 'bg-teal-100 text-teal-800',
  'TRAITE': 'bg-green-100 text-green-800',
  'ANNULE': 'bg-red-100 text-red-800',
}

const statutLabels: Record<string, string> = {
  'NC': 'N/C',
  'EN_COURS_A': 'En cours A',
  'EN_COURS_CDG': 'En cours CDG',
  'EN_COURS_DFC': 'En cours DFC',
  'EN_COURS_FSSEUR': 'Chez fournisseur',
  'LIVRAISON_PARTIELLE': 'Livraison partielle',
  'LIVRE': 'Livré',
  'TRAITE': 'Traité',
  'ANNULE': 'Annulé',
}

const typeColors: Record<string, string> = {
  'BCAL': 'bg-blue-100 text-blue-800',
  'BCL': 'bg-blue-100 text-blue-800',
  'BCAI': 'bg-purple-100 text-purple-800',
  'BCI': 'bg-purple-100 text-purple-800',
  'IPO': 'bg-green-100 text-green-800',
}

async function loadData() {
  loading.value = true
  try {
    const params: any = { page: currentPage.value }
    if (filters.value.search) params.search = filters.value.search
    if (filters.value.type_bc) params.type_bc = filters.value.type_bc
    if (filters.value.statut) params.statut = filters.value.statut
    if (filters.value.fournisseur_id) params.fournisseur_id = filters.value.fournisseur_id
    if (filters.value.direction_id) params.direction_id = filters.value.direction_id
    if (filters.value.acheteur_id) params.acheteur_id = filters.value.acheteur_id
    if (filters.value.date_debut) params.date_debut = filters.value.date_debut
    if (filters.value.date_fin) params.date_fin = filters.value.date_fin

    const response = await bcService.getAll(params)
    data.value = response.data
  } catch (error) {
    console.error('Erreur chargement BC:', error)
  } finally {
    loading.value = false
  }
}

async function loadReferentiels() {
  try {
    const [statutsRes, typesRes, directionsRes, acheteursRes, fournisseursRes] = await Promise.all([
      referentielService.getStatutsBC(),
      referentielService.getTypesBC(),
      referentielService.getDirections(),
      referentielService.getAcheteurs(),
      referentielService.getFournisseurs(),
    ])
    statuts.value = statutsRes.data
    typesBC.value = typesRes.data
    directions.value = directionsRes.data.map((d: any) => ({
      value: d.id,
      label: d.libelle_court || d.libelle,
    }))
    acheteurs.value = acheteursRes.data
    fournisseurs.value = fournisseursRes.data.map((f: any) => ({
      value: f.id,
      label: f.raison_sociale || f.sigle,
    }))
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
    type_bc: '',
    statut: '',
    fournisseur_id: '',
    direction_id: '',
    acheteur_id: '',
    date_debut: '',
    date_fin: '',
  }
  currentPage.value = 1
  loadData()
}

function viewBC(id: string) {
  router.push(`/bons-commande/${id}`)
}

function createBC() {
  router.push('/bons-commande/nouveau')
}

async function deleteBC(bc: BonCommande) {
  if (!confirm(`Voulez-vous vraiment supprimer le BC "${bc.numero}" ?\n\nCette action est irréversible.`)) {
    return
  }
  try {
    await bcService.delete(bc.id)
    loadData()
  } catch (error: any) {
    alert(error.response?.data?.message || 'Erreur lors de la suppression')
  }
}

function canDelete(bc: BonCommande): boolean {
  return ['NC', 'EN_COURS_A'].includes(bc.statut)
}

async function downloadPdf(id: string) {
  try {
    const response = await bcService.getPdf(id)
    const blob = new Blob([response.data], { type: 'application/pdf' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `BC-${id}.pdf`
    link.click()
    window.URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Erreur téléchargement PDF:', error)
  }
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
        <h1 class="text-2xl font-bold text-gray-900">Bons de Commande</h1>
        <p class="text-gray-600 mt-1">Gerez les bons de commande (BCAL, BCAI, IPO)</p>
      </div>
      <div class="flex gap-2">
        <button @click="showImportModal = true" class="btn-secondary inline-flex items-center">
          <ArrowUpTrayIcon class="w-5 h-5 mr-2" />
          Importer
        </button>
        <button @click="createBC" class="btn-primary inline-flex items-center">
          <PlusIcon class="w-5 h-5 mr-2" />
          Nouveau BC
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
            <label class="label">Type BC</label>
            <select v-model="filters.type_bc" class="input" @change="applyFilters">
              <option value="">Tous les types</option>
              <option v-for="t in typesBC" :key="t.value" :value="t.value">{{ t.label }}</option>
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
            <label class="label">Fournisseur</label>
            <select v-model="filters.fournisseur_id" class="input" @change="applyFilters">
              <option value="">Tous les fournisseurs</option>
              <option v-for="f in fournisseurs" :key="f.value" :value="f.value">{{ f.label }}</option>
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
              <th>Fournisseur</th>
              <th>Objet</th>
              <th class="text-right">Montant TTC</th>
              <th>Statut</th>
              <th class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="bc in data?.data" :key="bc.id" class="hover:bg-gray-50">
              <td class="font-medium text-ct-blue-600">{{ bc.numero }}</td>
              <td>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" :class="typeColors[bc.type_bc]">
                  {{ bc.type_bc }}
                </span>
              </td>
              <td>{{ formatDate(bc.date_bc) }}</td>
              <td class="max-w-[150px] truncate" :title="bc.fournisseur?.raison_sociale">
                {{ bc.fournisseur?.raison_sociale || bc.fournisseur?.sigle }}
              </td>
              <td class="max-w-xs truncate" :title="bc.objet">{{ bc.objet }}</td>
              <td class="text-right font-medium">{{ formatMontant(bc.montant_ttc_xaf) }}</td>
              <td>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="statutColors[bc.statut] || 'bg-gray-100 text-gray-800'">
                  {{ statutLabels[bc.statut] || bc.statut }}
                </span>
              </td>
              <td class="text-right">
                <div class="flex justify-end space-x-1">
                  <button @click="viewBC(bc.id)" class="p-1.5 text-gray-500 hover:text-ct-blue-600 hover:bg-gray-100 rounded" title="Voir détails">
                    <EyeIcon class="w-5 h-5" />
                  </button>
                  <button @click="downloadPdf(bc.id)" class="p-1.5 text-gray-500 hover:text-green-600 hover:bg-gray-100 rounded" title="Télécharger PDF">
                    <DocumentArrowDownIcon class="w-5 h-5" />
                  </button>
                  <button v-if="canDelete(bc)" @click="deleteBC(bc)" class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded" title="Supprimer">
                    <TrashIcon class="w-5 h-5" />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!data?.data?.length">
              <td colspan="8" class="text-center py-8 text-gray-500">Aucun bon de commande trouvé</td>
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
      type="bc"
      @close="showImportModal = false"
      @success="loadData()"
    />
  </div>
</template>
