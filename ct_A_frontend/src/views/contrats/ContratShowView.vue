<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { contratService } from '@/services/api'
import { formatMontant, formatDate } from '@/types'
import {
  ArrowLeftIcon,
  PencilIcon,
  PlayIcon,
  PauseIcon,
  StopIcon,
  XMarkIcon,
  CalendarDaysIcon,
  PlusIcon,
  CheckIcon,
  BanknotesIcon,
  ClockIcon,
  ExclamationTriangleIcon,
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
  reference_paiement?: string
}

interface Contrat {
  id: string
  numero: string
  reference_externe?: string
  objet: string
  description?: string
  type_contrat?: { id: string; libelle: string }
  fournisseur?: { id: string; raison_sociale: string }
  direction?: { id: string; libelle: string }
  zone?: { id: string; libelle: string }
  service?: { id: string; libelle: string }
  responsable?: { id: string; prenom: string; nom: string }
  date_signature: string
  date_debut: string
  date_fin?: string
  reconduction_tacite: boolean
  preavis_jours?: number
  periodicite: string
  montant_periodique: number
  montant_annuel: number
  taux_tva: number
  tva_incluse: boolean
  conditions_paiement?: string
  jour_facturation?: number
  contact_fournisseur?: string
  statut: string
  commentaire?: string
  jours_restants?: number
  echeances: Echeance[]
  created_by?: { prenom: string; nom: string }
  created_at: string
}

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const contrat = ref<Contrat | null>(null)
const showActionModal = ref(false)
const actionType = ref('')
const actionMotif = ref('')
const actionLoading = ref(false)
const showFactureModal = ref(false)
const selectedEcheance = ref<Echeance | null>(null)
const factureForm = ref({
  numero_facture: '',
  montant_facture: 0,
  montant_ht: null as number | null,
  montant_tva: null as number | null,
  date_facture: '',
})
const showPaiementModal = ref(false)
const paiementForm = ref({
  reference_paiement: '',
  date_paiement: '',
})

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

const echeanceStatutColors: Record<string, string> = {
  'A_VENIR': 'bg-gray-100 text-gray-800',
  'A_TRAITER': 'bg-yellow-100 text-yellow-800',
  'EN_COURS': 'bg-blue-100 text-blue-800',
  'PAYE': 'bg-green-100 text-green-800',
  'ANNULE': 'bg-red-100 text-red-800',
}

const echeanceStatutLabels: Record<string, string> = {
  'A_VENIR': 'A venir',
  'A_TRAITER': 'A traiter',
  'EN_COURS': 'En cours',
  'PAYE': 'Paye',
  'ANNULE': 'Annule',
}

const statsEcheances = computed(() => {
  if (!contrat.value?.echeances) return { total: 0, aTraiter: 0, enRetard: 0, payees: 0 }
  const echeances = contrat.value.echeances
  return {
    total: echeances.length,
    aTraiter: echeances.filter(e => e.statut === 'A_TRAITER').length,
    enRetard: echeances.filter(e => e.statut === 'A_TRAITER' && new Date(e.date_echeance) < new Date()).length,
    payees: echeances.filter(e => e.statut === 'PAYE').length,
  }
})

async function loadContrat() {
  loading.value = true
  try {
    const response = await contratService.get(route.params.id as string)
    contrat.value = response.data
  } catch (error) {
    console.error('Erreur chargement contrat:', error)
  } finally {
    loading.value = false
  }
}

function goBack() {
  router.push('/contrats')
}

function editContrat() {
  router.push(`/contrats/${route.params.id}/modifier`)
}

function openActionModal(type: string) {
  actionType.value = type
  actionMotif.value = ''
  showActionModal.value = true
}

async function executeAction() {
  if (!contrat.value) return
  actionLoading.value = true
  try {
    switch (actionType.value) {
      case 'activer':
        await contratService.activer(contrat.value.id)
        break
      case 'suspendre':
        await contratService.suspendre(contrat.value.id, actionMotif.value)
        break
      case 'reactiver':
        await contratService.reactiver(contrat.value.id)
        break
      case 'terminer':
        await contratService.terminer(contrat.value.id, { motif: actionMotif.value })
        break
      case 'resilier':
        if (!actionMotif.value) {
          alert('Le motif est obligatoire pour resilier un contrat')
          actionLoading.value = false
          return
        }
        await contratService.resilier(contrat.value.id, actionMotif.value)
        break
    }
    showActionModal.value = false
    loadContrat()
  } catch (error: any) {
    alert(error.response?.data?.message || 'Erreur lors de l\'action')
  } finally {
    actionLoading.value = false
  }
}

async function genererEcheances() {
  if (!contrat.value) return
  try {
    await contratService.genererEcheances(contrat.value.id)
    loadContrat()
  } catch (error: any) {
    alert(error.response?.data?.message || 'Erreur lors de la generation')
  }
}

function openFactureModal(echeance: Echeance) {
  selectedEcheance.value = echeance
  factureForm.value = {
    numero_facture: '',
    montant_facture: echeance.montant_prevu,
    montant_ht: null,
    montant_tva: null,
    date_facture: new Date().toISOString().split('T')[0],
  }
  showFactureModal.value = true
}

async function enregistrerFacture() {
  if (!selectedEcheance.value) return
  actionLoading.value = true
  try {
    const { echeanceService } = await import('@/services/api')
    await echeanceService.enregistrerFacture(selectedEcheance.value.id, factureForm.value)
    showFactureModal.value = false
    loadContrat()
  } catch (error: any) {
    alert(error.response?.data?.message || 'Erreur lors de l\'enregistrement')
  } finally {
    actionLoading.value = false
  }
}

function openPaiementModal(echeance: Echeance) {
  selectedEcheance.value = echeance
  paiementForm.value = {
    reference_paiement: '',
    date_paiement: new Date().toISOString().split('T')[0],
  }
  showPaiementModal.value = true
}

async function marquerPayee() {
  if (!selectedEcheance.value) return
  actionLoading.value = true
  try {
    const { echeanceService } = await import('@/services/api')
    await echeanceService.marquerPayee(selectedEcheance.value.id, paiementForm.value)
    showPaiementModal.value = false
    loadContrat()
  } catch (error: any) {
    alert(error.response?.data?.message || 'Erreur lors du paiement')
  } finally {
    actionLoading.value = false
  }
}

function isEnRetard(echeance: Echeance): boolean {
  return echeance.statut === 'A_TRAITER' && new Date(echeance.date_echeance) < new Date()
}

onMounted(() => {
  loadContrat()
})
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-4">
        <button @click="goBack" class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg">
          <ArrowLeftIcon class="w-5 h-5" />
        </button>
        <div>
          <h1 class="text-2xl font-bold text-gray-900">{{ contrat?.numero || '...' }}</h1>
          <p class="text-gray-600 mt-1">{{ contrat?.objet }}</p>
        </div>
      </div>
      <div v-if="contrat" class="flex items-center gap-2">
        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium" :class="statutColors[contrat.statut]">
          {{ statutLabels[contrat.statut] }}
        </span>
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-ct-blue-500"></div>
    </div>

    <div v-else-if="contrat" class="space-y-6">
      <!-- Actions -->
      <div class="card">
        <div class="flex flex-wrap gap-2">
          <button v-if="contrat.statut === 'BROUILLON'" @click="editContrat" class="btn-secondary inline-flex items-center">
            <PencilIcon class="w-4 h-4 mr-2" />
            Modifier
          </button>
          <button v-if="contrat.statut === 'BROUILLON'" @click="openActionModal('activer')" class="btn-primary inline-flex items-center">
            <PlayIcon class="w-4 h-4 mr-2" />
            Activer
          </button>
          <button v-if="contrat.statut === 'ACTIF'" @click="openActionModal('suspendre')" class="btn-warning inline-flex items-center">
            <PauseIcon class="w-4 h-4 mr-2" />
            Suspendre
          </button>
          <button v-if="contrat.statut === 'SUSPENDU'" @click="openActionModal('reactiver')" class="btn-primary inline-flex items-center">
            <PlayIcon class="w-4 h-4 mr-2" />
            Reactiver
          </button>
          <button v-if="['ACTIF', 'SUSPENDU'].includes(contrat.statut)" @click="openActionModal('terminer')" class="btn-secondary inline-flex items-center">
            <StopIcon class="w-4 h-4 mr-2" />
            Terminer
          </button>
          <button v-if="['ACTIF', 'SUSPENDU'].includes(contrat.statut)" @click="openActionModal('resilier')" class="btn-danger inline-flex items-center">
            <XMarkIcon class="w-4 h-4 mr-2" />
            Resilier
          </button>
          <button v-if="contrat.statut === 'ACTIF'" @click="genererEcheances" class="btn-secondary inline-flex items-center">
            <CalendarDaysIcon class="w-4 h-4 mr-2" />
            Generer echeances
          </button>
        </div>
      </div>

      <!-- Informations principales -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
          <!-- Details du contrat -->
          <div class="card">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Informations du contrat</h2>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <dt class="text-sm text-gray-500">Type</dt>
                <dd class="font-medium">{{ contrat.type_contrat?.libelle || '-' }}</dd>
              </div>
              <div>
                <dt class="text-sm text-gray-500">Reference externe</dt>
                <dd class="font-medium">{{ contrat.reference_externe || '-' }}</dd>
              </div>
              <div>
                <dt class="text-sm text-gray-500">Fournisseur</dt>
                <dd class="font-medium">{{ contrat.fournisseur?.raison_sociale || '-' }}</dd>
              </div>
              <div>
                <dt class="text-sm text-gray-500">Contact fournisseur</dt>
                <dd class="font-medium">{{ contrat.contact_fournisseur || '-' }}</dd>
              </div>
              <div>
                <dt class="text-sm text-gray-500">Direction</dt>
                <dd class="font-medium">{{ contrat.direction?.libelle || '-' }}</dd>
              </div>
              <div>
                <dt class="text-sm text-gray-500">Service</dt>
                <dd class="font-medium">{{ contrat.service?.libelle || '-' }}</dd>
              </div>
              <div>
                <dt class="text-sm text-gray-500">Responsable</dt>
                <dd class="font-medium">{{ contrat.responsable ? `${contrat.responsable.prenom} ${contrat.responsable.nom}` : '-' }}</dd>
              </div>
              <div class="md:col-span-2">
                <dt class="text-sm text-gray-500">Description</dt>
                <dd class="font-medium">{{ contrat.description || '-' }}</dd>
              </div>
            </dl>
          </div>

          <!-- Duree -->
          <div class="card">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Duree du contrat</h2>
            <dl class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <dt class="text-sm text-gray-500">Date de signature</dt>
                <dd class="font-medium">{{ formatDate(contrat.date_signature) }}</dd>
              </div>
              <div>
                <dt class="text-sm text-gray-500">Date de debut</dt>
                <dd class="font-medium">{{ formatDate(contrat.date_debut) }}</dd>
              </div>
              <div>
                <dt class="text-sm text-gray-500">Date de fin</dt>
                <dd class="font-medium" :class="{ 'text-red-600': contrat.jours_restants !== null && contrat.jours_restants <= 0 }">
                  {{ contrat.date_fin ? formatDate(contrat.date_fin) : 'Duree indeterminee' }}
                  <span v-if="contrat.jours_restants !== null && contrat.jours_restants <= 90" class="text-sm">
                    ({{ contrat.jours_restants > 0 ? `${contrat.jours_restants} jours` : 'Expire' }})
                  </span>
                </dd>
              </div>
              <div>
                <dt class="text-sm text-gray-500">Reconduction tacite</dt>
                <dd class="font-medium">{{ contrat.reconduction_tacite ? 'Oui' : 'Non' }}</dd>
              </div>
              <div v-if="contrat.preavis_jours">
                <dt class="text-sm text-gray-500">Preavis</dt>
                <dd class="font-medium">{{ contrat.preavis_jours }} jours</dd>
              </div>
            </dl>
          </div>
        </div>

        <!-- Sidebar financier -->
        <div class="space-y-6">
          <div class="card">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Montants</h2>
            <dl class="space-y-4">
              <div>
                <dt class="text-sm text-gray-500">Periodicite</dt>
                <dd class="text-xl font-bold text-gray-900">{{ periodiciteLabels[contrat.periodicite] }}</dd>
              </div>
              <div>
                <dt class="text-sm text-gray-500">Montant periodique</dt>
                <dd class="text-xl font-bold text-ct-blue-600">{{ formatMontant(contrat.montant_periodique) }}</dd>
              </div>
              <div>
                <dt class="text-sm text-gray-500">Montant annuel</dt>
                <dd class="text-2xl font-bold text-gray-900">{{ formatMontant(contrat.montant_annuel) }}</dd>
              </div>
              <div>
                <dt class="text-sm text-gray-500">TVA</dt>
                <dd class="font-medium">{{ contrat.taux_tva }}% {{ contrat.tva_incluse ? '(incluse)' : '' }}</dd>
              </div>
              <div>
                <dt class="text-sm text-gray-500">Jour de facturation</dt>
                <dd class="font-medium">Le {{ contrat.jour_facturation || 1 }} du mois</dd>
              </div>
              <div v-if="contrat.conditions_paiement">
                <dt class="text-sm text-gray-500">Conditions de paiement</dt>
                <dd class="font-medium">{{ contrat.conditions_paiement }}</dd>
              </div>
            </dl>
          </div>

          <!-- Stats echeances -->
          <div class="card">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Echeances</h2>
            <div class="grid grid-cols-2 gap-4">
              <div class="text-center p-3 bg-gray-50 rounded-lg">
                <div class="text-2xl font-bold text-gray-900">{{ statsEcheances.total }}</div>
                <div class="text-sm text-gray-500">Total</div>
              </div>
              <div class="text-center p-3 bg-yellow-50 rounded-lg">
                <div class="text-2xl font-bold text-yellow-600">{{ statsEcheances.aTraiter }}</div>
                <div class="text-sm text-gray-500">A traiter</div>
              </div>
              <div class="text-center p-3 bg-red-50 rounded-lg">
                <div class="text-2xl font-bold text-red-600">{{ statsEcheances.enRetard }}</div>
                <div class="text-sm text-gray-500">En retard</div>
              </div>
              <div class="text-center p-3 bg-green-50 rounded-lg">
                <div class="text-2xl font-bold text-green-600">{{ statsEcheances.payees }}</div>
                <div class="text-sm text-gray-500">Payees</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Liste des echeances -->
      <div class="card">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-gray-900">Echeances</h2>
        </div>
        <div class="table-container">
          <table class="table">
            <thead>
              <tr>
                <th>Numero</th>
                <th>Periode</th>
                <th>Echeance</th>
                <th class="text-right">Montant prevu</th>
                <th>Facture</th>
                <th class="text-right">Montant facture</th>
                <th>Statut</th>
                <th>Paiement</th>
                <th class="text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="echeance in contrat.echeances" :key="echeance.id" class="hover:bg-gray-50">
                <td class="font-medium">{{ echeance.numero }}</td>
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
                <td>
                  <span v-if="echeance.numero_facture">{{ echeance.numero_facture }}</span>
                  <span v-else class="text-gray-400">-</span>
                </td>
                <td class="text-right">
                  <span v-if="echeance.montant_facture">{{ formatMontant(echeance.montant_facture) }}</span>
                  <span v-else class="text-gray-400">-</span>
                </td>
                <td>
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" :class="echeanceStatutColors[echeance.statut]">
                    {{ echeanceStatutLabels[echeance.statut] }}
                  </span>
                </td>
                <td>
                  <span v-if="echeance.date_paiement">{{ formatDate(echeance.date_paiement) }}</span>
                  <span v-else class="text-gray-400">-</span>
                </td>
                <td class="text-right">
                  <button
                    v-if="['A_VENIR', 'A_TRAITER'].includes(echeance.statut)"
                    @click="openFactureModal(echeance)"
                    class="p-1.5 text-gray-500 hover:text-ct-blue-600 hover:bg-gray-100 rounded"
                    title="Enregistrer facture"
                  >
                    <BanknotesIcon class="w-5 h-5" />
                  </button>
                  <button
                    v-if="echeance.statut === 'EN_COURS'"
                    @click="openPaiementModal(echeance)"
                    class="p-1.5 text-gray-500 hover:text-green-600 hover:bg-green-50 rounded"
                    title="Marquer comme paye"
                  >
                    <CheckIcon class="w-5 h-5" />
                  </button>
                </td>
              </tr>
              <tr v-if="!contrat.echeances?.length">
                <td colspan="9" class="text-center py-8 text-gray-500">Aucune echeance generee</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Commentaire -->
      <div v-if="contrat.commentaire" class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-2">Commentaire</h2>
        <p class="text-gray-700">{{ contrat.commentaire }}</p>
      </div>

      <!-- Audit -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Informations d'audit</h2>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div>
            <dt class="text-gray-500">Cree par</dt>
            <dd class="font-medium">{{ contrat.created_by ? `${contrat.created_by.prenom} ${contrat.created_by.nom}` : '-' }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Date de creation</dt>
            <dd class="font-medium">{{ formatDate(contrat.created_at) }}</dd>
          </div>
        </dl>
      </div>
    </div>

    <!-- Modal Action -->
    <div v-if="showActionModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
          {{ actionType === 'activer' ? 'Activer le contrat' : '' }}
          {{ actionType === 'suspendre' ? 'Suspendre le contrat' : '' }}
          {{ actionType === 'reactiver' ? 'Reactiver le contrat' : '' }}
          {{ actionType === 'terminer' ? 'Terminer le contrat' : '' }}
          {{ actionType === 'resilier' ? 'Resilier le contrat' : '' }}
        </h3>
        <div v-if="['suspendre', 'terminer', 'resilier'].includes(actionType)" class="mb-4">
          <label class="label">Motif {{ actionType === 'resilier' ? '*' : '' }}</label>
          <textarea v-model="actionMotif" class="input" rows="3" :placeholder="actionType === 'resilier' ? 'Motif obligatoire' : 'Motif (optionnel)'"></textarea>
        </div>
        <div class="flex justify-end gap-2">
          <button @click="showActionModal = false" class="btn-secondary">Annuler</button>
          <button @click="executeAction" :disabled="actionLoading" class="btn-primary">
            {{ actionLoading ? '...' : 'Confirmer' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Facture -->
    <div v-if="showFactureModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Enregistrer la facture</h3>
        <div class="space-y-4">
          <div>
            <label class="label">Numero de facture *</label>
            <input v-model="factureForm.numero_facture" type="text" class="input" placeholder="FAC-2026-001" />
          </div>
          <div>
            <label class="label">Montant facture (FCFA) *</label>
            <input v-model.number="factureForm.montant_facture" type="number" min="0" class="input" />
          </div>
          <div>
            <label class="label">Date de facture</label>
            <input v-model="factureForm.date_facture" type="date" class="input" />
          </div>
        </div>
        <div class="flex justify-end gap-2 mt-6">
          <button @click="showFactureModal = false" class="btn-secondary">Annuler</button>
          <button @click="enregistrerFacture" :disabled="actionLoading" class="btn-primary">
            {{ actionLoading ? '...' : 'Enregistrer' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Paiement -->
    <div v-if="showPaiementModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Marquer comme paye</h3>
        <div class="space-y-4">
          <div>
            <label class="label">Reference de paiement *</label>
            <input v-model="paiementForm.reference_paiement" type="text" class="input" placeholder="VIR-2026-001" />
          </div>
          <div>
            <label class="label">Date de paiement</label>
            <input v-model="paiementForm.date_paiement" type="date" class="input" />
          </div>
        </div>
        <div class="flex justify-end gap-2 mt-6">
          <button @click="showPaiementModal = false" class="btn-secondary">Annuler</button>
          <button @click="marquerPayee" :disabled="actionLoading" class="btn-primary">
            {{ actionLoading ? '...' : 'Confirmer' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.btn-warning {
  @apply bg-yellow-500 text-white hover:bg-yellow-600 px-4 py-2 rounded-lg font-medium transition-colors;
}
.btn-danger {
  @apply bg-red-500 text-white hover:bg-red-600 px-4 py-2 rounded-lg font-medium transition-colors;
}
</style>
