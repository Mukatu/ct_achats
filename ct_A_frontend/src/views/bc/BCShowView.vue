<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { bcService } from '@/services/api'
import { formatMontant, formatDate, type BonCommande } from '@/types'
import {
  ArrowLeftIcon,
  PencilIcon,
  CheckIcon,
  XMarkIcon,
  PaperAirplaneIcon,
  DocumentArrowDownIcon,
  TrashIcon,
  UserIcon,
  MapPinIcon,
  CalendarIcon,
  CurrencyDollarIcon,
  DocumentTextIcon,
  ClipboardDocumentListIcon,
  BuildingStorefrontIcon,
  TruckIcon,
  LinkIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const data = ref<BonCommande | null>(null)

// Modals
const showRejectModal = ref(false)
const motifRejet = ref('')
const processing = ref(false)

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

const canValidate = computed(() => ['EN_COURS_A', 'EN_COURS_CDG', 'EN_COURS_DFC'].includes(data.value?.statut || ''))
const canReject = computed(() => !['TRAITE', 'ANNULE', 'LIVRE', 'EN_COURS_FSSEUR'].includes(data.value?.statut || ''))
const canSendToFournisseur = computed(() => ['EN_COURS_DFC'].includes(data.value?.statut || ''))
const canDownloadPdf = computed(() => !!data.value?.id)
const canDelete = computed(() => ['NC', 'EN_COURS_A'].includes(data.value?.statut || ''))

async function loadData() {
  loading.value = true
  try {
    const response = await bcService.get(route.params.id as string)
    data.value = response.data.data || response.data
  } catch (error) {
    console.error('Erreur chargement BC:', error)
    router.push('/bons-commande')
  } finally {
    loading.value = false
  }
}

async function valider() {
  processing.value = true
  try {
    await bcService.valider(route.params.id as string)
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
    await bcService.rejeter(route.params.id as string, motifRejet.value)
    showRejectModal.value = false
    await loadData()
  } catch (error) {
    console.error('Erreur rejet:', error)
    alert('Erreur lors du rejet')
  } finally {
    processing.value = false
  }
}

async function envoyerFournisseur() {
  processing.value = true
  try {
    await bcService.envoyer(route.params.id as string)
    await loadData()
  } catch (error) {
    console.error('Erreur envoi:', error)
    alert('Erreur lors de l\'envoi au fournisseur')
  } finally {
    processing.value = false
  }
}

async function downloadPdf() {
  try {
    const response = await bcService.getPdf(route.params.id as string)
    const blob = new Blob([response.data], { type: 'application/pdf' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `${data.value?.numero || 'BC'}.pdf`
    link.click()
    window.URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Erreur téléchargement PDF:', error)
    alert('Erreur lors du téléchargement du PDF')
  }
}

function goBack() {
  router.push('/bons-commande')
}

function editBC() {
  router.push(`/bons-commande/${route.params.id}/modifier`)
}

async function deleteBC() {
  if (!data.value) return
  if (!confirm(`Voulez-vous vraiment supprimer le BC "${data.value.numero}" ?\n\nCette action est irréversible.`)) {
    return
  }
  processing.value = true
  try {
    await bcService.delete(route.params.id as string)
    router.push('/bons-commande')
  } catch (error: any) {
    alert(error.response?.data?.message || 'Erreur lors de la suppression')
  } finally {
    processing.value = false
  }
}

function goToDA() {
  if (data.value?.demande_achat_id) {
    router.push({ name: 'da-show', params: { id: data.value.demande_achat_id } })
  }
}

function goToFournisseur() {
  if (data.value?.fournisseur_id) {
    router.push({ name: 'fournisseur-show', params: { id: data.value.fournisseur_id } })
  }
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
              <h1 class="text-2xl font-bold text-gray-900">{{ data.numero }}</h1>
              <span
                class="inline-flex items-center px-2.5 py-0.5 rounded text-sm font-medium"
                :class="typeColors[data.type_bc]"
              >
                {{ data.type_bc }}
              </span>
              <span
                class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                :class="statutColors[data.statut] || 'bg-gray-100 text-gray-800'"
              >
                {{ statutLabels[data.statut] || data.statut }}
              </span>
            </div>
            <p class="text-gray-600 mt-1">Bon de Commande</p>
          </div>
        </div>

        <div class="flex flex-wrap gap-2">
          <button v-if="canValidate" @click="valider" :disabled="processing" class="btn-primary inline-flex items-center">
            <CheckIcon class="w-4 h-4 mr-2" />
            Valider
          </button>
          <button v-if="canReject" @click="showRejectModal = true" class="btn-secondary text-red-600 hover:bg-red-50 inline-flex items-center">
            <XMarkIcon class="w-4 h-4 mr-2" />
            Rejeter
          </button>
          <button v-if="canSendToFournisseur" @click="envoyerFournisseur" :disabled="processing" class="btn-primary inline-flex items-center">
            <PaperAirplaneIcon class="w-4 h-4 mr-2" />
            Envoyer au fournisseur
          </button>
          <button v-if="canDownloadPdf" @click="downloadPdf" class="btn-secondary inline-flex items-center">
            <DocumentArrowDownIcon class="w-4 h-4 mr-2" />
            PDF
          </button>
          <button @click="editBC" class="btn-secondary inline-flex items-center">
            <PencilIcon class="w-4 h-4 mr-2" />
            Modifier
          </button>
          <button v-if="canDelete" @click="deleteBC" :disabled="processing" class="btn-secondary text-red-600 hover:bg-red-50 inline-flex items-center">
            <TrashIcon class="w-4 h-4 mr-2" />
            Supprimer
          </button>
        </div>
      </div>

      <!-- Lien vers DA -->
      <div v-if="data.demande_achat" class="card mb-6 bg-blue-50 border-blue-200">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <LinkIcon class="w-5 h-5 text-blue-600" />
            <div>
              <p class="text-sm text-blue-600">Issue de la Demande d'Achat</p>
              <p class="font-medium text-blue-900">{{ data.demande_achat.numero }}</p>
            </div>
          </div>
          <button @click="goToDA" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
            Voir la DA
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
                <p class="text-sm text-gray-600 mt-1">
                  {{ data.fournisseur.ville }}, {{ data.fournisseur.pays }}
                </p>
                <p v-if="data.fournisseur.telephone" class="text-sm text-gray-500">
                  {{ data.fournisseur.telephone }}
                </p>
              </div>
              <button @click="goToFournisseur" class="text-ct-blue-600 hover:text-ct-blue-800 text-sm font-medium">
                Voir fiche
              </button>
            </div>
          </div>

          <!-- Objet -->
          <div class="card">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <DocumentTextIcon class="w-5 h-5 mr-2 text-gray-400" />
              Description
            </h2>
            <div class="space-y-4">
              <div>
                <label class="text-sm text-gray-500">Objet</label>
                <p class="text-gray-900 font-medium">{{ data.objet }}</p>
              </div>
              <div v-if="data.nature_prestation">
                <label class="text-sm text-gray-500">Nature de la prestation</label>
                <p class="text-gray-700">{{ data.nature_prestation }}</p>
              </div>
            </div>
          </div>

          <!-- Lignes -->
          <div class="card">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <ClipboardDocumentListIcon class="w-5 h-5 mr-2 text-gray-400" />
              Lignes de commande
            </h2>

            <div v-if="data.lignes && data.lignes.length > 0" class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-2 text-left text-gray-600">#</th>
                    <th class="px-4 py-2 text-left text-gray-600">Désignation</th>
                    <th class="px-4 py-2 text-right text-gray-600">Qté</th>
                    <th class="px-4 py-2 text-right text-gray-600">Prix unit.</th>
                    <th class="px-4 py-2 text-right text-gray-600">Montant</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="ligne in data.lignes" :key="ligne.id" class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-gray-500">{{ ligne.numero_ligne }}</td>
                    <td class="px-4 py-3">
                      <p class="font-medium text-gray-900">{{ ligne.designation }}</p>
                      <p v-if="ligne.description" class="text-xs text-gray-500">{{ ligne.description }}</p>
                    </td>
                    <td class="px-4 py-3 text-right">{{ ligne.quantite }}</td>
                    <td class="px-4 py-3 text-right">{{ formatMontant(ligne.prix_unitaire_xaf) }}</td>
                    <td class="px-4 py-3 text-right font-medium">{{ formatMontant(ligne.montant_xaf) }}</td>
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
                <span class="font-medium">{{ formatMontant(data.montant_ht_xaf) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">TVA ({{ data.taux_tva }}%)</span>
                <span class="font-medium">{{ formatMontant(data.montant_tva) }}</span>
              </div>
              <div class="flex justify-between pt-3 border-t border-gray-200">
                <span class="text-lg font-semibold text-gray-900">Total TTC</span>
                <span class="text-2xl font-bold text-ct-blue-600">{{ formatMontant(data.montant_ttc_xaf) }}</span>
              </div>
              <div v-if="data.devise_etrangere && data.montant_devise" class="flex justify-between pt-2 border-t border-gray-100 text-sm">
                <span class="text-gray-500">Montant en {{ data.devise_etrangere }}</span>
                <span class="text-gray-700">{{ data.montant_devise?.toLocaleString() }} {{ data.devise_etrangere }}</span>
              </div>
            </div>
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
            </div>
          </div>

          <!-- Livraison -->
          <div class="card">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <TruckIcon class="w-5 h-5 mr-2 text-gray-400" />
              Livraison
            </h2>
            <div class="space-y-3">
              <div v-if="data.date_livraison_prevue">
                <label class="text-sm text-gray-500">Date prévue</label>
                <p class="text-gray-900">{{ formatDate(data.date_livraison_prevue) }}</p>
              </div>
              <div v-if="data.conditions_paiement">
                <label class="text-sm text-gray-500">Conditions de paiement</label>
                <p class="text-gray-900">{{ data.conditions_paiement }}</p>
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
                <label class="text-sm text-gray-500">Demandeur</label>
                <p class="text-gray-900">{{ data.demandeur?.nom_complet || (data.demandeur?.nom + ' ' + data.demandeur?.prenom) || '-' }}</p>
              </div>
              <div v-if="data.acheteur">
                <label class="text-sm text-gray-500">Acheteur</label>
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
                <label class="text-sm text-gray-500">Date BC</label>
                <p class="text-gray-900">{{ formatDate(data.date_bc) }}</p>
              </div>
              <div v-if="data.date_envoi_fournisseur">
                <label class="text-sm text-gray-500">Envoyé au fournisseur</label>
                <p class="text-gray-900">{{ formatDate(data.date_envoi_fournisseur) }}</p>
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

    <!-- Modal Rejet -->
    <div v-if="showRejectModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black bg-opacity-50" @click="showRejectModal = false"></div>
        <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
          <h3 class="text-lg font-semibold mb-4">Rejeter le bon de commande</h3>
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
  </div>
</template>
