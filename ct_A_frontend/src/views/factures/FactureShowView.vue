<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { factureService } from '@/services/api'
import { formatMontant, formatDate, type Facture } from '@/types'
import {
  ArrowLeftIcon,
  PencilIcon,
  TrashIcon,
  CheckIcon,
  XMarkIcon,
  BanknotesIcon,
  BuildingStorefrontIcon,
  DocumentTextIcon,
  ClipboardDocumentListIcon,
  CalendarIcon,
  CurrencyDollarIcon,
  LinkIcon,
  ExclamationTriangleIcon,
  PaperAirplaneIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const data = ref<Facture | null>(null)
const processing = ref(false)

// Modals
const showRejectModal = ref(false)
const showPaiementModal = ref(false)
const motifRejet = ref('')
const paiementForm = ref({
  montant: 0,
  date_paiement: new Date().toISOString().split('T')[0],
  reference: '',
})

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

const canEdit = computed(() => data.value?.statut === 'BROUILLON' || data.value?.statut === 'A_RAPPROCHER')
const canDelete = computed(() => ['BROUILLON', 'A_RAPPROCHER'].includes(data.value?.statut || ''))
const canSoumettre = computed(() => data.value?.statut === 'BROUILLON')
const canValidate = computed(() => data.value?.statut === 'A_VALIDER')
const canReject = computed(() => ['A_RAPPROCHER', 'EN_RAPPROCHEMENT', 'A_VALIDER'].includes(data.value?.statut || ''))
const canPay = computed(() => data.value?.statut === 'VALIDEE' && data.value?.statut_paiement !== 'PAYEE')

const isEnRetard = computed(() => {
  if (!data.value) return false
  if (data.value.statut_paiement === 'PAYEE') return false
  if (!data.value.date_echeance) return false
  return new Date(data.value.date_echeance) < new Date()
})

const soldeRestant = computed(() => {
  if (!data.value) return 0
  return data.value.net_a_payer - (data.value.montant_paye || 0)
})

async function loadData() {
  loading.value = true
  try {
    const response = await factureService.get(route.params.id as string)
    data.value = response.data.data || response.data
  } catch (error) {
    console.error('Erreur chargement facture:', error)
    router.push('/factures')
  } finally {
    loading.value = false
  }
}

async function soumettre() {
  processing.value = true
  try {
    await factureService.soumettre(route.params.id as string)
    await loadData()
  } catch (error) {
    console.error('Erreur soumission:', error)
    alert('Erreur lors de la soumission')
  } finally {
    processing.value = false
  }
}

async function valider() {
  processing.value = true
  try {
    await factureService.valider(route.params.id as string)
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
    await factureService.rejeter(route.params.id as string, motifRejet.value)
    showRejectModal.value = false
    await loadData()
  } catch (error) {
    console.error('Erreur rejet:', error)
    alert('Erreur lors du rejet')
  } finally {
    processing.value = false
  }
}

async function enregistrerPaiement() {
  if (paiementForm.value.montant <= 0) return
  processing.value = true
  try {
    await factureService.enregistrerPaiement(route.params.id as string, paiementForm.value)
    showPaiementModal.value = false
    paiementForm.value = { montant: 0, date_paiement: new Date().toISOString().split('T')[0], reference: '' }
    await loadData()
  } catch (error) {
    console.error('Erreur paiement:', error)
    alert('Erreur lors de l\'enregistrement du paiement')
  } finally {
    processing.value = false
  }
}

function goBack() {
  router.push('/factures')
}

function editFacture() {
  router.push(`/factures/${route.params.id}/modifier`)
}

async function deleteFacture() {
  if (!confirm(`Voulez-vous vraiment supprimer la facture "${data.value?.numero_interne}" ?\n\nCette action est irréversible.`)) {
    return
  }
  try {
    await factureService.delete(route.params.id as string)
    router.push('/factures')
  } catch (error: any) {
    alert(error.response?.data?.message || 'Erreur lors de la suppression')
  }
}

function goToBC() {
  if (data.value?.bon_commande_id) {
    router.push({ name: 'bc-show', params: { id: data.value.bon_commande_id } })
  }
}

function goToFournisseur() {
  if (data.value?.fournisseur_id) {
    router.push({ name: 'fournisseur-show', params: { id: data.value.fournisseur_id } })
  }
}

function openPaiementModal() {
  paiementForm.value.montant = soldeRestant.value
  showPaiementModal.value = true
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
            <div class="flex items-center gap-3 flex-wrap">
              <h1 class="text-2xl font-bold text-gray-900">{{ data.numero_interne }}</h1>
              <span class="inline-flex items-center px-2.5 py-0.5 rounded text-sm font-medium" :class="typeColors[data.type_facture]">
                {{ data.type_facture }}
              </span>
              <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium" :class="statutColors[data.statut] || 'bg-gray-100 text-gray-800'">
                {{ statutLabels[data.statut] || data.statut }}
              </span>
              <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium" :class="paiementColors[data.statut_paiement] || 'bg-gray-100 text-gray-800'">
                {{ paiementLabels[data.statut_paiement] || data.statut_paiement }}
              </span>
            </div>
            <p class="text-gray-600 mt-1">N Fournisseur: {{ data.numero_fournisseur }}</p>
          </div>
        </div>

        <div class="flex flex-wrap gap-2">
          <button v-if="canSoumettre" @click="soumettre" :disabled="processing" class="btn-primary inline-flex items-center">
            <PaperAirplaneIcon class="w-4 h-4 mr-2" />
            Soumettre
          </button>
          <button v-if="canValidate" @click="valider" :disabled="processing" class="btn-primary inline-flex items-center">
            <CheckIcon class="w-4 h-4 mr-2" />
            Valider
          </button>
          <button v-if="canReject" @click="showRejectModal = true" class="btn-secondary text-red-600 hover:bg-red-50 inline-flex items-center">
            <XMarkIcon class="w-4 h-4 mr-2" />
            Rejeter
          </button>
          <button v-if="canPay" @click="openPaiementModal" class="btn-primary bg-green-600 hover:bg-green-700 inline-flex items-center">
            <BanknotesIcon class="w-4 h-4 mr-2" />
            Paiement
          </button>
          <button v-if="canEdit" @click="editFacture" class="btn-secondary inline-flex items-center">
            <PencilIcon class="w-4 h-4 mr-2" />
            Modifier
          </button>
          <button v-if="canDelete" @click="deleteFacture" class="btn-secondary text-red-600 hover:bg-red-50 inline-flex items-center">
            <TrashIcon class="w-4 h-4 mr-2" />
            Supprimer
          </button>
        </div>
      </div>

      <!-- Alerte retard -->
      <div v-if="isEnRetard" class="card mb-6 bg-red-50 border-red-200">
        <div class="flex items-center gap-3">
          <ExclamationTriangleIcon class="w-6 h-6 text-red-600" />
          <div>
            <p class="font-medium text-red-800">Facture en retard de paiement</p>
            <p class="text-sm text-red-600">Echeance depassee depuis le {{ formatDate(data.date_echeance) }}</p>
          </div>
        </div>
      </div>

      <!-- Lien vers BC -->
      <div v-if="data.bon_commande" class="card mb-6 bg-blue-50 border-blue-200">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <LinkIcon class="w-5 h-5 text-blue-600" />
            <div>
              <p class="text-sm text-blue-600">Liee au Bon de Commande</p>
              <p class="font-medium text-blue-900">{{ data.bon_commande.numero }}</p>
            </div>
          </div>
          <button @click="goToBC" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
            Voir le BC
          </button>
        </div>
      </div>

      <!-- Content -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main info -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Fournisseur -->
          <div class="card">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <BuildingStorefrontIcon class="w-5 h-5 mr-2 text-gray-400" />
              Fournisseur
            </h2>
            <div v-if="data.fournisseur" class="flex items-start justify-between">
              <div>
                <p class="text-lg font-medium text-gray-900">{{ data.fournisseur.raison_sociale }}</p>
                <p v-if="data.fournisseur.sigle" class="text-sm text-gray-500">{{ data.fournisseur.sigle }}</p>
                <p class="text-sm text-gray-600 mt-1">{{ data.fournisseur.ville }}, {{ data.fournisseur.pays }}</p>
              </div>
              <button @click="goToFournisseur" class="text-ct-blue-600 hover:text-ct-blue-800 text-sm font-medium">
                Voir fiche
              </button>
            </div>
          </div>

          <!-- Lignes -->
          <div class="card">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <ClipboardDocumentListIcon class="w-5 h-5 mr-2 text-gray-400" />
              Lignes de facture
            </h2>

            <div v-if="data.lignes && data.lignes.length > 0" class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-2 text-left text-gray-600">#</th>
                    <th class="px-4 py-2 text-left text-gray-600">Designation</th>
                    <th class="px-4 py-2 text-right text-gray-600">Qte</th>
                    <th class="px-4 py-2 text-right text-gray-600">Prix unit.</th>
                    <th class="px-4 py-2 text-right text-gray-600">Remise</th>
                    <th class="px-4 py-2 text-right text-gray-600">Montant HT</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="ligne in data.lignes" :key="ligne.id" class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-gray-500">{{ ligne.numero_ligne }}</td>
                    <td class="px-4 py-3">
                      <p class="font-medium text-gray-900">{{ ligne.designation }}</p>
                      <p v-if="ligne.reference_fournisseur" class="text-xs text-gray-500">Ref: {{ ligne.reference_fournisseur }}</p>
                    </td>
                    <td class="px-4 py-3 text-right">{{ ligne.quantite }}</td>
                    <td class="px-4 py-3 text-right">{{ formatMontant(ligne.prix_unitaire_ht) }}</td>
                    <td class="px-4 py-3 text-right">
                      <span v-if="ligne.remise_percent" class="text-red-600">-{{ ligne.remise_percent }}%</span>
                      <span v-else class="text-gray-300">-</span>
                    </td>
                    <td class="px-4 py-3 text-right font-medium">{{ formatMontant(ligne.montant_ht) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p v-else class="text-gray-500 text-center py-4">Aucune ligne</p>
          </div>

          <!-- Montants -->
          <div class="card">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <CurrencyDollarIcon class="w-5 h-5 mr-2 text-gray-400" />
              Montants
            </h2>
            <div class="space-y-3">
              <div class="flex justify-between">
                <span class="text-gray-600">Total HT</span>
                <span class="font-medium">{{ formatMontant(data.montant_ht) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">TVA ({{ data.taux_tva }}%)</span>
                <span class="font-medium">{{ formatMontant(data.montant_tva) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Total TTC</span>
                <span class="font-medium">{{ formatMontant(data.montant_ttc) }}</span>
              </div>
              <div v-if="data.retenue_source" class="flex justify-between text-red-600">
                <span>Retenue a la source</span>
                <span class="font-medium">-{{ formatMontant(data.retenue_source) }}</span>
              </div>
              <div class="flex justify-between pt-3 border-t border-gray-200">
                <span class="text-lg font-semibold text-gray-900">Net a payer</span>
                <span class="text-2xl font-bold text-ct-blue-600">{{ formatMontant(data.net_a_payer) }}</span>
              </div>
              <div v-if="data.montant_paye" class="flex justify-between pt-2 border-t border-gray-100">
                <span class="text-gray-600">Deja paye</span>
                <span class="font-medium text-green-600">{{ formatMontant(data.montant_paye) }}</span>
              </div>
              <div v-if="soldeRestant > 0 && data.montant_paye" class="flex justify-between">
                <span class="text-gray-600">Reste a payer</span>
                <span class="font-medium text-orange-600">{{ formatMontant(soldeRestant) }}</span>
              </div>
            </div>
          </div>

          <!-- Commentaire -->
          <div v-if="data.commentaire" class="card">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <DocumentTextIcon class="w-5 h-5 mr-2 text-gray-400" />
              Commentaire
            </h2>
            <p class="text-gray-700 whitespace-pre-wrap">{{ data.commentaire }}</p>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <!-- Dates -->
          <div class="card">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <CalendarIcon class="w-5 h-5 mr-2 text-gray-400" />
              Dates
            </h2>
            <div class="space-y-3">
              <div>
                <label class="text-sm text-gray-500">Date facture</label>
                <p class="text-gray-900">{{ formatDate(data.date_facture) }}</p>
              </div>
              <div>
                <label class="text-sm text-gray-500">Date reception</label>
                <p class="text-gray-900">{{ formatDate(data.date_reception) }}</p>
              </div>
              <div>
                <label class="text-sm text-gray-500">Date echeance</label>
                <p :class="isEnRetard ? 'text-red-600 font-medium' : 'text-gray-900'">
                  {{ formatDate(data.date_echeance) }}
                </p>
              </div>
              <div v-if="data.date_validation">
                <label class="text-sm text-gray-500">Date validation</label>
                <p class="text-gray-900">{{ formatDate(data.date_validation) }}</p>
              </div>
              <div v-if="data.date_paiement">
                <label class="text-sm text-gray-500">Date paiement</label>
                <p class="text-gray-900">{{ formatDate(data.date_paiement) }}</p>
              </div>
            </div>
          </div>

          <!-- Paiement -->
          <div v-if="data.reference_paiement" class="card">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <BanknotesIcon class="w-5 h-5 mr-2 text-gray-400" />
              Paiement
            </h2>
            <div class="space-y-3">
              <div>
                <label class="text-sm text-gray-500">Reference</label>
                <p class="text-gray-900">{{ data.reference_paiement }}</p>
              </div>
            </div>
          </div>

          <!-- Historique -->
          <div class="card">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Historique</h2>
            <div class="space-y-3">
              <div>
                <label class="text-sm text-gray-500">Creee le</label>
                <p class="text-gray-900">{{ formatDate(data.created_at) }}</p>
              </div>
              <div v-if="data.valideur">
                <label class="text-sm text-gray-500">Validee par</label>
                <p class="text-gray-900">{{ data.valideur.nom }} {{ data.valideur.prenom }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Rejet -->
    <div v-if="showRejectModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black bg-opacity-50" @click="showRejectModal = false"></div>
        <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
          <h3 class="text-lg font-semibold mb-4">Rejeter la facture</h3>
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

    <!-- Modal Paiement -->
    <div v-if="showPaiementModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black bg-opacity-50" @click="showPaiementModal = false"></div>
        <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
          <h3 class="text-lg font-semibold mb-4">Enregistrer un paiement</h3>
          <div class="space-y-4">
            <div>
              <label class="label">Montant</label>
              <input v-model.number="paiementForm.montant" type="number" class="input" min="0" :max="soldeRestant" />
              <p class="text-sm text-gray-500 mt-1">Solde restant: {{ formatMontant(soldeRestant) }}</p>
            </div>
            <div>
              <label class="label">Date du paiement</label>
              <input v-model="paiementForm.date_paiement" type="date" class="input" />
            </div>
            <div>
              <label class="label">Reference</label>
              <input v-model="paiementForm.reference" type="text" class="input" placeholder="N virement, cheque..." />
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button @click="showPaiementModal = false" class="btn-secondary">Annuler</button>
            <button @click="enregistrerPaiement" :disabled="paiementForm.montant <= 0 || processing" class="btn-primary bg-green-600 hover:bg-green-700">
              {{ processing ? 'Enregistrement...' : 'Enregistrer' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
