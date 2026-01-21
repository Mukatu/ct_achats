<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ebService, referentielService } from '@/services/api'
import { formatMontant, formatDate, type ExpressionBesoin } from '@/types'
import {
  ArrowLeftIcon,
  PencilIcon,
  CheckIcon,
  XMarkIcon,
  ArrowRightIcon,
  UserIcon,
  MapPinIcon,
  CalendarIcon,
  CurrencyDollarIcon,
  BuildingOfficeIcon,
  DocumentTextIcon,
  TrashIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const data = ref<ExpressionBesoin | null>(null)
const acheteurs = ref<any[]>([])

// Modals
const showAssignModal = ref(false)
const showRejectModal = ref(false)
const showTransformModal = ref(false)
const selectedAcheteur = ref('')
const motifRejet = ref('')
const processing = ref(false)
const montantTransform = ref<number | null>(null)
const typeDemande = ref('DA')

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

const canAssign = computed(() => data.value?.statut === 'EN_SUSPENS')
const canValidate = computed(() => ['EN_COURS_ACH', 'EN_COURS_CDG', 'EN_COURS_DFC', 'EN_COURS_DG'].includes(data.value?.statut || ''))
const canReject = computed(() => !['TRAITE', 'ANNULE'].includes(data.value?.statut || ''))
const canTransform = computed(() => data.value?.statut === 'EN_COURS_ACH')
const canDelete = computed(() => ['EN_SUSPENS', 'EN_COURS_ACH'].includes(data.value?.statut || ''))

async function loadData() {
  loading.value = true
  try {
    const [ebRes, acheteursRes] = await Promise.all([
      ebService.get(route.params.id as string),
      referentielService.getAcheteurs(),
    ])
    data.value = ebRes.data.data || ebRes.data
    acheteurs.value = acheteursRes.data
  } catch (error) {
    console.error('Erreur chargement EB:', error)
    router.push('/expressions-besoin')
  } finally {
    loading.value = false
  }
}

async function assignerAcheteur() {
  if (!selectedAcheteur.value) return
  processing.value = true
  try {
    await ebService.assigner(route.params.id as string, selectedAcheteur.value)
    showAssignModal.value = false
    await loadData()
  } catch (error) {
    console.error('Erreur assignation:', error)
    alert('Erreur lors de l\'assignation')
  } finally {
    processing.value = false
  }
}

async function valider() {
  processing.value = true
  try {
    await ebService.valider(route.params.id as string)
    await loadData()
  } catch (error) {
    console.error('Erreur validation:', error)
    alert('Erreur lors de la validation')
  } finally {
    processing.value = false
  }
}

async function rejeter() {
  if (!motifRejet.value.trim()) return
  processing.value = true
  try {
    await ebService.rejeter(route.params.id as string, motifRejet.value)
    showRejectModal.value = false
    await loadData()
  } catch (error) {
    console.error('Erreur rejet:', error)
    alert('Erreur lors du rejet')
  } finally {
    processing.value = false
  }
}

async function transformer() {
  if (!montantTransform.value || montantTransform.value <= 0) {
    alert('Veuillez saisir un montant valide')
    return
  }
  processing.value = true
  try {
    const response = await ebService.transformer(route.params.id as string, {
      type_demande: typeDemande.value,
      montant: montantTransform.value
    })
    showTransformModal.value = false
    router.push({ name: 'da-show', params: { id: response.data.data.id } })
  } catch (error) {
    console.error('Erreur transformation:', error)
    alert('Erreur lors de la transformation')
  } finally {
    processing.value = false
  }
}

async function deleteEB() {
  if (!data.value) return
  if (!confirm(`Voulez-vous vraiment supprimer l'EB "${data.value.numero}" ?\n\nCette action est irréversible.`)) {
    return
  }
  processing.value = true
  try {
    await ebService.delete(route.params.id as string)
    router.push('/expressions-besoin')
  } catch (error: any) {
    alert(error.response?.data?.message || 'Erreur lors de la suppression')
  } finally {
    processing.value = false
  }
}

function goBack() {
  router.push('/expressions-besoin')
}

function editEB() {
  router.push(`/expressions-besoin/${route.params.id}/modifier`)
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div>
    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-ct-blue-500"></div>
    </div>

    <div v-else-if="data">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
        <div class="flex items-start gap-4">
          <button @click="goBack" class="p-2 hover:bg-gray-100 rounded-lg mt-1">
            <ArrowLeftIcon class="w-5 h-5 text-gray-600" />
          </button>
          <div>
            <div class="flex items-center gap-3">
              <h1 class="text-2xl font-bold text-gray-900">{{ data.numero }}</h1>
              <span
                class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                :class="statutColors[data.statut] || 'bg-gray-100 text-gray-800'"
              >
                {{ statutLabels[data.statut] || data.statut }}
              </span>
            </div>
            <p class="text-gray-600 mt-1">Expression de Besoin</p>
          </div>
        </div>

        <div class="flex flex-wrap gap-2">
          <button v-if="canAssign" @click="showAssignModal = true" class="btn-secondary inline-flex items-center">
            <UserIcon class="w-4 h-4 mr-2" />
            Assigner
          </button>
          <button v-if="canValidate" @click="valider" :disabled="processing" class="btn-primary inline-flex items-center">
            <CheckIcon class="w-4 h-4 mr-2" />
            Valider
          </button>
          <button v-if="canReject" @click="showRejectModal = true" class="btn-secondary text-red-600 hover:bg-red-50 inline-flex items-center">
            <XMarkIcon class="w-4 h-4 mr-2" />
            Rejeter
          </button>
          <button v-if="canTransform" @click="showTransformModal = true" class="btn-primary inline-flex items-center">
            <ArrowRightIcon class="w-4 h-4 mr-2" />
            Transformer en DA
          </button>
          <button @click="editEB" class="btn-secondary inline-flex items-center">
            <PencilIcon class="w-4 h-4 mr-2" />
            Modifier
          </button>
          <button v-if="canDelete" @click="deleteEB" :disabled="processing" class="btn-secondary text-red-600 hover:bg-red-50 inline-flex items-center">
            <TrashIcon class="w-4 h-4 mr-2" />
            Supprimer
          </button>
        </div>
      </div>

      <!-- Content -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main info -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Objet -->
          <div class="card">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <DocumentTextIcon class="w-5 h-5 mr-2 text-gray-400" />
              Description du besoin
            </h2>
            <div class="space-y-4">
              <div>
                <label class="text-sm text-gray-500">Objet</label>
                <p class="text-gray-900 font-medium">{{ data.objet }}</p>
              </div>
              <div v-if="data.description_detaillee">
                <label class="text-sm text-gray-500">Description détaillée</label>
                <p class="text-gray-700 whitespace-pre-wrap">{{ data.description_detaillee }}</p>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div v-if="data.quantite_souhaitee">
                  <label class="text-sm text-gray-500">Quantité souhaitée</label>
                  <p class="text-gray-900">{{ data.quantite_souhaitee }}</p>
                </div>
                <div v-if="data.date_besoin">
                  <label class="text-sm text-gray-500">Date de besoin</label>
                  <p class="text-gray-900">{{ formatDate(data.date_besoin) }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Estimation et fournisseur -->
          <div class="card">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <CurrencyDollarIcon class="w-5 h-5 mr-2 text-gray-400" />
              Budget et fournisseur
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="text-sm text-gray-500">Estimation budgétaire</label>
                <p class="text-2xl font-bold text-ct-blue-600">
                  {{ data.estimation ? formatMontant(data.estimation) : 'Non renseignée' }}
                </p>
              </div>
              <div v-if="data.fournisseur_suggere">
                <label class="text-sm text-gray-500">Fournisseur suggéré</label>
                <p class="text-gray-900 font-medium">{{ data.fournisseur_suggere.raison_sociale }}</p>
                <p class="text-sm text-gray-500">{{ data.fournisseur_suggere.ville }}, {{ data.fournisseur_suggere.pays }}</p>
              </div>
            </div>
            <div v-if="data.commentaire" class="mt-4 pt-4 border-t border-gray-200">
              <label class="text-sm text-gray-500">Commentaire</label>
              <p class="text-gray-700">{{ data.commentaire }}</p>
            </div>
          </div>

          <!-- Motif de rejet -->
          <div v-if="data.motif_rejet" class="card border-red-200 bg-red-50">
            <h2 class="text-lg font-semibold text-red-800 mb-2">Motif de rejet</h2>
            <p class="text-red-700">{{ data.motif_rejet }}</p>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <!-- Localisation -->
          <div class="card">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <MapPinIcon class="w-5 h-5 mr-2 text-gray-400" />
              Localisation
            </h2>
            <div class="space-y-3">
              <div>
                <label class="text-sm text-gray-500">Zone</label>
                <p class="text-gray-900">{{ data.zone?.libelle || '-' }}</p>
              </div>
              <div>
                <label class="text-sm text-gray-500">Direction</label>
                <p class="text-gray-900">{{ data.direction?.libelle_court || data.direction?.libelle || '-' }}</p>
              </div>
              <div v-if="data.service">
                <label class="text-sm text-gray-500">Service</label>
                <p class="text-gray-900">{{ data.service.libelle }}</p>
              </div>
            </div>
          </div>

          <!-- Personnes -->
          <div class="card">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <UserIcon class="w-5 h-5 mr-2 text-gray-400" />
              Intervenants
            </h2>
            <div class="space-y-3">
              <div>
                <label class="text-sm text-gray-500">Demandeur (EB physique)</label>
                <p class="text-gray-900">
                  {{ data.demandeur?.nom_complet || (data.demandeur ? data.demandeur.nom + ' ' + data.demandeur.prenom : null) || data.demandeur_nom || '-' }}
                </p>
                <p v-if="data.demandeur?.matricule" class="text-xs text-gray-500">Matricule: {{ data.demandeur.matricule }}</p>
              </div>
              <div v-if="data.acheteur">
                <label class="text-sm text-gray-500">Acheteur assigné</label>
                <p class="text-gray-900">{{ data.acheteur.nom_complet || data.acheteur.nom + ' ' + data.acheteur.prenom }}</p>
              </div>
            </div>
          </div>

          <!-- Dates -->
          <div class="card">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <CalendarIcon class="w-5 h-5 mr-2 text-gray-400" />
              Historique
            </h2>
            <div class="space-y-3">
              <div>
                <label class="text-sm text-gray-500">Date d'expression</label>
                <p class="text-gray-900">{{ formatDate(data.date_expression) }}</p>
              </div>
              <div v-if="data.date_validation">
                <label class="text-sm text-gray-500">Date de validation</label>
                <p class="text-gray-900">{{ formatDate(data.date_validation) }}</p>
              </div>
              <div>
                <label class="text-sm text-gray-500">Créé le</label>
                <p class="text-gray-900">{{ formatDate(data.created_at) }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Assignation -->
    <div v-if="showAssignModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black bg-opacity-50" @click="showAssignModal = false"></div>
        <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
          <h3 class="text-lg font-semibold mb-4">Assigner un acheteur</h3>
          <select v-model="selectedAcheteur" class="input mb-4">
            <option value="">Sélectionner un acheteur</option>
            <option v-for="a in acheteurs" :key="a.value" :value="a.value">
              {{ a.label }}
            </option>
          </select>
          <div class="flex justify-end gap-3">
            <button @click="showAssignModal = false" class="btn-secondary">Annuler</button>
            <button @click="assignerAcheteur" :disabled="!selectedAcheteur || processing" class="btn-primary">
              {{ processing ? 'Assignation...' : 'Assigner' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Rejet -->
    <div v-if="showRejectModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black bg-opacity-50" @click="showRejectModal = false"></div>
        <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
          <h3 class="text-lg font-semibold mb-4">Rejeter l'expression de besoin</h3>
          <textarea
            v-model="motifRejet"
            class="input mb-4"
            rows="3"
            placeholder="Motif du rejet..."
          ></textarea>
          <div class="flex justify-end gap-3">
            <button @click="showRejectModal = false" class="btn-secondary">Annuler</button>
            <button @click="rejeter" :disabled="!motifRejet.trim() || processing" class="btn-primary bg-red-600 hover:bg-red-700">
              {{ processing ? 'Rejet...' : 'Rejeter' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Transformation -->
    <div v-if="showTransformModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black bg-opacity-50" @click="showTransformModal = false"></div>
        <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
          <h3 class="text-lg font-semibold mb-4">Transformer en Demande d'Achat</h3>
          <p class="text-gray-600 mb-4">
            Cette action va créer une nouvelle Demande d'Achat à partir de cette expression de besoin.
          </p>
          <div class="space-y-4 mb-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Type de demande</label>
              <select v-model="typeDemande" class="input">
                <option value="DA">Demande d'Achat (DA)</option>
                <option value="DAC">Demande d'Achat Comptant (DAC)</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Montant (XAF) *</label>
              <input
                v-model.number="montantTransform"
                type="number"
                min="0"
                step="1"
                class="input"
                placeholder="Saisir le montant..."
              />
              <p v-if="data?.estimation" class="text-sm text-gray-500 mt-1">
                Estimation initiale : {{ formatMontant(data.estimation) }}
              </p>
            </div>
          </div>
          <div class="flex justify-end gap-3">
            <button @click="showTransformModal = false" class="btn-secondary">Annuler</button>
            <button @click="transformer" :disabled="!montantTransform || processing" class="btn-primary">
              {{ processing ? 'Transformation...' : 'Transformer' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
