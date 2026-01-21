<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { receptionService, bcService } from '@/services/api'
import {
  ArrowLeftIcon,
  CheckIcon,
  PlusIcon,
  TrashIcon,
  MagnifyingGlassIcon,
} from '@heroicons/vue/24/outline'

const router = useRouter()
const route = useRoute()
const loading = ref(false)
const submitting = ref(false)
const errors = ref<Record<string, string[]>>({})

// Recherche BC
const searchBC = ref('')
const searchingBC = ref(false)
const bcsDisponibles = ref<any[]>([])
const selectedBC = ref<any>(null)
const lignesBC = ref<any[]>([])

// Formulaire
const form = ref({
  bon_commande_id: '',
  date_reception: new Date().toISOString().split('T')[0],
  type_reception: 'LIVRAISON',
  lieu_reception: '',
  numero_bl_fournisseur: '',
  date_bl_fournisseur: '',
  numero_tracking: '',
  transporteur: '',
  commentaire: '',
})

// Lignes de reception
interface LigneReception {
  ligne_bon_commande_id: string
  designation: string
  quantite_restante: number
  quantite_recue: number
  quantite_conforme: number
  quantite_non_conforme: number
  quantite_refusee: number
  motif_non_conformite: string
  motif_refus: string
  numero_lot: string
  numero_serie: string
  commentaire: string
  unite: string
}

const lignes = ref<LigneReception[]>([])

// Rechercher des BC avec livraison en attente
async function searchBonsCommande() {
  if (searchBC.value.length < 2) {
    bcsDisponibles.value = []
    return
  }

  searchingBC.value = true
  try {
    const response = await bcService.getAll({
      search: searchBC.value,
      statut: 'EN_COURS_FSSEUR',
      per_page: 10
    })
    bcsDisponibles.value = response.data?.data || []
  } catch (error) {
    console.error('Erreur recherche BC:', error)
  } finally {
    searchingBC.value = false
  }
}

// Selectionner un BC
async function selectBC(bc: any) {
  selectedBC.value = bc
  form.value.bon_commande_id = bc.id
  bcsDisponibles.value = []
  searchBC.value = ''

  // Charger les lignes disponibles
  try {
    const response = await receptionService.getLignesBC(bc.id)
    lignesBC.value = response.data.lignes || []

    // Pre-remplir les lignes de reception
    lignes.value = lignesBC.value.map((l: any) => ({
      ligne_bon_commande_id: l.id,
      designation: l.designation,
      quantite_restante: l.quantite_restante,
      quantite_recue: l.quantite_restante, // Par defaut, reception totale
      quantite_conforme: l.quantite_restante,
      quantite_non_conforme: 0,
      quantite_refusee: 0,
      motif_non_conformite: '',
      motif_refus: '',
      numero_lot: '',
      numero_serie: '',
      commentaire: '',
      unite: l.unite || '',
    }))
  } catch (error) {
    console.error('Erreur chargement lignes BC:', error)
  }
}

// Annuler la selection du BC
function clearBC() {
  selectedBC.value = null
  form.value.bon_commande_id = ''
  lignesBC.value = []
  lignes.value = []
}

// Calculer quantite conforme automatiquement
function updateQuantiteConforme(ligne: LigneReception) {
  ligne.quantite_conforme = Math.max(0,
    ligne.quantite_recue - ligne.quantite_non_conforme - ligne.quantite_refusee
  )
}

// Validation et soumission
async function submit() {
  errors.value = {}

  if (!form.value.bon_commande_id) {
    errors.value.bon_commande_id = ['Selectionnez un bon de commande']
    return
  }

  const validLignes = lignes.value.filter(l => l.quantite_recue > 0)
  if (validLignes.length === 0) {
    errors.value.lignes = ['Ajoutez au moins une ligne avec une quantite recue']
    return
  }

  submitting.value = true

  try {
    const payload = {
      ...form.value,
      lignes: validLignes.map(l => ({
        ligne_bon_commande_id: l.ligne_bon_commande_id,
        quantite_recue: l.quantite_recue,
        quantite_conforme: l.quantite_conforme,
        quantite_non_conforme: l.quantite_non_conforme,
        quantite_refusee: l.quantite_refusee,
        motif_non_conformite: l.motif_non_conformite || null,
        motif_refus: l.motif_refus || null,
        numero_lot: l.numero_lot || null,
        numero_serie: l.numero_serie || null,
        commentaire: l.commentaire || null,
      })),
    }

    const response = await receptionService.create(payload)

    router.push({
      name: 'br-show',
      params: { id: response.data.data.id },
    })
  } catch (error: any) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {}
    } else {
      console.error('Erreur creation BR:', error)
      alert('Une erreur est survenue lors de la creation')
    }
  } finally {
    submitting.value = false
  }
}

function goBack() {
  router.push('/receptions')
}

// Si un BC est passe en parametre
onMounted(async () => {
  const bcId = route.query.bc as string
  if (bcId) {
    try {
      const response = await bcService.get(bcId)
      const bc = response.data.data || response.data
      await selectBC(bc)
    } catch (error) {
      console.error('Erreur chargement BC:', error)
    }
  }
})

let searchTimeout: number | null = null
watch(searchBC, () => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => searchBonsCommande(), 300) as unknown as number
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="flex items-center gap-4 mb-6">
      <button @click="goBack" class="p-2 hover:bg-gray-100 rounded-lg">
        <ArrowLeftIcon class="w-5 h-5 text-gray-600" />
      </button>
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Nouvelle Reception</h1>
        <p class="text-gray-600 mt-1">Enregistrez une reception de marchandises ou services</p>
      </div>
    </div>

    <!-- Form -->
    <form @submit.prevent="submit" class="space-y-6">
      <!-- Selection du BC -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Bon de Commande</h2>

        <div v-if="!selectedBC">
          <div class="relative">
            <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
            <input
              v-model="searchBC"
              type="text"
              placeholder="Rechercher un bon de commande par numero..."
              class="input pl-10"
            />
          </div>

          <p v-if="errors.bon_commande_id" class="text-red-500 text-sm mt-1">{{ errors.bon_commande_id[0] }}</p>

          <!-- Resultats de recherche -->
          <div v-if="bcsDisponibles.length > 0" class="mt-2 border border-gray-200 rounded-lg divide-y divide-gray-100 max-h-60 overflow-y-auto">
            <div
              v-for="bc in bcsDisponibles"
              :key="bc.id"
              @click="selectBC(bc)"
              class="p-3 hover:bg-gray-50 cursor-pointer"
            >
              <div class="flex items-center justify-between">
                <div>
                  <span class="font-medium text-ct-blue-600">{{ bc.numero }}</span>
                  <span class="text-gray-500 ml-2">{{ bc.type_bc }}</span>
                </div>
                <span class="text-sm text-gray-500">{{ bc.fournisseur?.raison_sociale }}</span>
              </div>
              <p class="text-sm text-gray-600 truncate">{{ bc.objet }}</p>
            </div>
          </div>

          <p v-if="searchingBC" class="mt-2 text-sm text-gray-500">Recherche en cours...</p>
        </div>

        <!-- BC Selectionne -->
        <div v-else class="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <div class="flex items-start justify-between">
            <div>
              <p class="font-semibold text-blue-900">{{ selectedBC.numero }}</p>
              <p class="text-sm text-blue-700">{{ selectedBC.fournisseur?.raison_sociale }}</p>
              <p class="text-sm text-blue-600">{{ selectedBC.objet }}</p>
            </div>
            <button type="button" @click="clearBC" class="text-blue-600 hover:text-blue-800 text-sm">
              Changer
            </button>
          </div>
        </div>
      </div>

      <!-- Informations de reception -->
      <div v-if="selectedBC" class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Informations de reception</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="label">Date de reception <span class="text-red-500">*</span></label>
            <input v-model="form.date_reception" type="date" class="input" required />
          </div>

          <div>
            <label class="label">Type de reception <span class="text-red-500">*</span></label>
            <select v-model="form.type_reception" class="input" required>
              <option value="LIVRAISON">Livraison</option>
              <option value="SERVICE_FAIT">Service fait</option>
              <option value="PARTIELLE">Partielle</option>
            </select>
          </div>

          <div>
            <label class="label">Lieu de reception</label>
            <input v-model="form.lieu_reception" type="text" class="input" placeholder="Magasin, entrepot..." />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4">
          <div>
            <label class="label">N BL Fournisseur</label>
            <input v-model="form.numero_bl_fournisseur" type="text" class="input" placeholder="N du bon de livraison" />
          </div>

          <div>
            <label class="label">Date BL</label>
            <input v-model="form.date_bl_fournisseur" type="date" class="input" />
          </div>

          <div>
            <label class="label">N Tracking</label>
            <input v-model="form.numero_tracking" type="text" class="input" placeholder="Numero de suivi" />
          </div>

          <div>
            <label class="label">Transporteur</label>
            <input v-model="form.transporteur" type="text" class="input" placeholder="Nom du transporteur" />
          </div>
        </div>

        <div class="mt-4">
          <label class="label">Commentaire</label>
          <textarea v-model="form.commentaire" class="input" rows="2" placeholder="Observations sur la reception..."></textarea>
        </div>
      </div>

      <!-- Lignes de reception -->
      <div v-if="lignes.length > 0" class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Articles a receptionner</h2>

        <p v-if="errors.lignes" class="text-red-500 text-sm mb-4">{{ errors.lignes[0] }}</p>

        <div class="space-y-4">
          <div
            v-for="(ligne, index) in lignes"
            :key="ligne.ligne_bon_commande_id"
            class="border border-gray-200 rounded-lg p-4 bg-gray-50"
          >
            <div class="flex items-start justify-between mb-3">
              <div>
                <span class="font-medium text-gray-900">{{ ligne.designation }}</span>
                <span class="text-sm text-gray-500 ml-2">(Reste: {{ ligne.quantite_restante }} {{ ligne.unite }})</span>
              </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
              <div>
                <label class="label text-xs">Qte recue</label>
                <input
                  v-model.number="ligne.quantite_recue"
                  type="number"
                  class="input text-sm"
                  min="0"
                  :max="ligne.quantite_restante"
                  step="0.01"
                  @change="updateQuantiteConforme(ligne)"
                />
              </div>

              <div>
                <label class="label text-xs">Qte conforme</label>
                <input
                  v-model.number="ligne.quantite_conforme"
                  type="number"
                  class="input text-sm bg-green-50"
                  min="0"
                  step="0.01"
                  readonly
                />
              </div>

              <div>
                <label class="label text-xs">Qte non conforme</label>
                <input
                  v-model.number="ligne.quantite_non_conforme"
                  type="number"
                  class="input text-sm"
                  min="0"
                  step="0.01"
                  @change="updateQuantiteConforme(ligne)"
                />
              </div>

              <div>
                <label class="label text-xs">Qte refusee</label>
                <input
                  v-model.number="ligne.quantite_refusee"
                  type="number"
                  class="input text-sm"
                  min="0"
                  step="0.01"
                  @change="updateQuantiteConforme(ligne)"
                />
              </div>

              <div>
                <label class="label text-xs">N Lot</label>
                <input
                  v-model="ligne.numero_lot"
                  type="text"
                  class="input text-sm"
                  placeholder="Lot..."
                />
              </div>

              <div>
                <label class="label text-xs">N Serie</label>
                <input
                  v-model="ligne.numero_serie"
                  type="text"
                  class="input text-sm"
                  placeholder="Serie..."
                />
              </div>
            </div>

            <div v-if="ligne.quantite_non_conforme > 0" class="mt-3">
              <label class="label text-xs">Motif non-conformite</label>
              <input
                v-model="ligne.motif_non_conformite"
                type="text"
                class="input text-sm"
                placeholder="Expliquer la non-conformite..."
              />
            </div>

            <div v-if="ligne.quantite_refusee > 0" class="mt-3">
              <label class="label text-xs">Motif refus</label>
              <input
                v-model="ligne.motif_refus"
                type="text"
                class="input text-sm"
                placeholder="Expliquer le refus..."
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div v-if="selectedBC" class="flex justify-end gap-4">
        <button type="button" @click="goBack" class="btn-secondary">
          Annuler
        </button>
        <button type="submit" :disabled="submitting" class="btn-primary inline-flex items-center">
          <span v-if="submitting" class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></span>
          <CheckIcon v-else class="w-5 h-5 mr-2" />
          {{ submitting ? 'Creation...' : 'Creer la reception' }}
        </button>
      </div>
    </form>
  </div>
</template>
