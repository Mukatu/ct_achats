<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { daService, ligneService, referentielService } from '@/services/api'
import { formatMontant, formatDate } from '@/types'
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
  DocumentTextIcon,
  ClipboardDocumentListIcon,
  LinkIcon,
  ChevronDownIcon,
  ChevronUpIcon,
  PlusIcon,
  TrashIcon,
  TrophyIcon,
  ClockIcon,
  ShieldCheckIcon,
  BanknotesIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const data = ref<any>(null)

// Accordéon
const expandedLignes = ref<Set<string>>(new Set())

// Modals
const showRejectModal = ref(false)
const showTransformModal = ref(false)
const showAddLigneModal = ref(false)
const showAddOffreModal = ref(false)
const motifRejet = ref('')
const processing = ref(false)

// Transformation en BC
const fournisseurs = ref<any[]>([])
const selectedFournisseur = ref('')
const selectedTypeBC = ref('BCAL')

// Nouvelle ligne
const newLigne = ref({
  designation: '',
  description: '',
  quantite: 1,
  prix_unitaire_estime: '',
})

// Nouvelle offre
const selectedLigneForOffre = ref<any>(null)
const newOffre = ref({
  fournisseur_id: '',
  prix_unitaire: '',
  delai_livraison_jours: '',
  conditions_paiement_jours: '',
  garantie: '',
  garantie_mois: '',
  offre_technique: 'CONFORME',
  reference_offre: '',
  commentaire: '',
})

const statutColors: Record<string, string> = {
  'EN_SUSPENS': 'bg-gray-100 text-gray-800',
  'EN_COURS_ACH': 'bg-blue-100 text-blue-800',
  'EN_COURS_CDG': 'bg-yellow-100 text-yellow-800',
  'EN_COURS_DFC': 'bg-orange-100 text-orange-800',
  'EN_COURS_DG': 'bg-purple-100 text-purple-800',
  'TRAITE': 'bg-green-100 text-green-800',
  'ANNULE': 'bg-red-100 text-red-800',
}

const statutLabels: Record<string, string> = {
  'EN_SUSPENS': 'En Suspens',
  'EN_COURS_ACH': 'En cours ACH',
  'EN_COURS_CDG': 'En cours CDG',
  'EN_COURS_DFC': 'En cours DFC',
  'EN_COURS_DG': 'En cours DG',
  'TRAITE': 'Traitée',
  'ANNULE': 'Annulée',
}

const typeColors: Record<string, string> = {
  'DA': 'bg-blue-100 text-blue-800',
  'DAC': 'bg-orange-100 text-orange-800',
}

const canValidate = computed(() => ['EN_COURS_ACH', 'EN_COURS_CDG', 'EN_COURS_DFC', 'EN_COURS_DG'].includes(data.value?.statut || ''))
const canReject = computed(() => !['TRAITE', 'ANNULE'].includes(data.value?.statut || ''))
const canTransform = computed(() => data.value?.statut === 'TRAITE' && data.value?.type_demande === 'DA')
const canEdit = computed(() => !['TRAITE', 'ANNULE'].includes(data.value?.statut || ''))
const canDelete = computed(() => ['EN_SUSPENS', 'EN_COURS_ACH'].includes(data.value?.statut || ''))

async function loadData() {
  loading.value = true
  try {
    const [daRes, fournisseursRes] = await Promise.all([
      daService.get(route.params.id as string),
      referentielService.getFournisseurs(),
    ])
    data.value = daRes.data.data || daRes.data
    fournisseurs.value = fournisseursRes.data
  } catch (error) {
    console.error('Erreur chargement DA:', error)
    router.push('/demandes-achat')
  } finally {
    loading.value = false
  }
}

function toggleLigne(ligneId: string) {
  if (expandedLignes.value.has(ligneId)) {
    expandedLignes.value.delete(ligneId)
  } else {
    expandedLignes.value.add(ligneId)
  }
}

function getMeilleureOffre(ligne: any) {
  if (!ligne.offres || ligne.offres.length === 0) return null
  const conformes = ligne.offres.filter((o: any) => o.offre_technique === 'CONFORME')
  if (conformes.length === 0) return null
  return conformes.reduce((best: any, curr: any) =>
    (!best || (curr.score || 0) > (best.score || 0)) ? curr : best, null)
}

function isMeilleurePour(offre: any, ligne: any, critere: string): boolean {
  const conformes = ligne.offres?.filter((o: any) => o.offre_technique === 'CONFORME') || []
  if (conformes.length === 0) return false

  switch (critere) {
    case 'prix':
      return offre.prix_unitaire === Math.min(...conformes.map((o: any) => o.prix_unitaire))
    case 'delai':
      return offre.delai_livraison_jours === Math.min(...conformes.map((o: any) => o.delai_livraison_jours))
    case 'garantie':
      return offre.garantie_mois === Math.max(...conformes.map((o: any) => o.garantie_mois || 0))
    case 'paiement':
      return offre.conditions_paiement_jours === Math.max(...conformes.map((o: any) => o.conditions_paiement_jours))
    default:
      return false
  }
}

async function addLigne() {
  if (!newLigne.value.designation || !newLigne.value.quantite) return
  processing.value = true
  try {
    await daService.createLigne(route.params.id as string, {
      designation: newLigne.value.designation,
      description: newLigne.value.description || null,
      quantite: newLigne.value.quantite,
      prix_unitaire_estime: newLigne.value.prix_unitaire_estime || null,
    })
    showAddLigneModal.value = false
    newLigne.value = { designation: '', description: '', quantite: 1, prix_unitaire_estime: '' }
    await loadData()
  } catch (error) {
    console.error('Erreur ajout ligne:', error)
    alert('Erreur lors de l\'ajout de la ligne')
  } finally {
    processing.value = false
  }
}

async function deleteLigne(ligneId: string) {
  if (!confirm('Supprimer cette ligne ?')) return
  try {
    await daService.deleteLigne(route.params.id as string, ligneId)
    await loadData()
  } catch (error) {
    console.error('Erreur suppression ligne:', error)
  }
}

function openAddOffreModal(ligne: any) {
  selectedLigneForOffre.value = ligne
  newOffre.value = {
    fournisseur_id: '',
    prix_unitaire: '',
    delai_livraison_jours: '',
    conditions_paiement_jours: '',
    garantie: '',
    garantie_mois: '',
    offre_technique: 'CONFORME',
    reference_offre: '',
    commentaire: '',
  }
  showAddOffreModal.value = true
}

async function addOffre() {
  if (!newOffre.value.fournisseur_id || !newOffre.value.prix_unitaire) return
  processing.value = true
  try {
    await ligneService.createOffre(selectedLigneForOffre.value.id, {
      fournisseur_id: newOffre.value.fournisseur_id,
      prix_unitaire: parseInt(newOffre.value.prix_unitaire),
      delai_livraison_jours: parseInt(newOffre.value.delai_livraison_jours) || 1,
      conditions_paiement_jours: parseInt(newOffre.value.conditions_paiement_jours) || 0,
      garantie: newOffre.value.garantie || null,
      garantie_mois: parseInt(newOffre.value.garantie_mois) || 0,
      offre_technique: newOffre.value.offre_technique,
      reference_offre: newOffre.value.reference_offre || null,
      commentaire: newOffre.value.commentaire || null,
    })
    showAddOffreModal.value = false
    await loadData()
    // Auto-expand la ligne
    expandedLignes.value.add(selectedLigneForOffre.value.id)
  } catch (error: any) {
    console.error('Erreur ajout offre:', error)
    alert(error.response?.data?.message || 'Erreur lors de l\'ajout de l\'offre')
  } finally {
    processing.value = false
  }
}

async function deleteOffre(ligneId: string, offreId: string) {
  if (!confirm('Supprimer cette offre ?')) return
  try {
    await ligneService.deleteOffre(ligneId, offreId)
    await loadData()
  } catch (error) {
    console.error('Erreur suppression offre:', error)
  }
}

async function selectOffre(ligneId: string, offreId: string) {
  try {
    await daService.selectOffre(route.params.id as string, ligneId, offreId)
    await loadData()
  } catch (error) {
    console.error('Erreur sélection offre:', error)
  }
}

async function valider() {
  processing.value = true
  try {
    await daService.valider(route.params.id as string)
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
    await daService.rejeter(route.params.id as string, motifRejet.value)
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
  if (!selectedFournisseur.value) {
    alert('Veuillez sélectionner un fournisseur')
    return
  }
  processing.value = true
  try {
    const response = await daService.transformer(route.params.id as string, {
      fournisseur_id: selectedFournisseur.value,
      type_bc: selectedTypeBC.value,
    })
    showTransformModal.value = false
    router.push({ name: 'bc-show', params: { id: response.data.data.id } })
  } catch (error) {
    console.error('Erreur transformation:', error)
    alert('Erreur lors de la transformation')
  } finally {
    processing.value = false
  }
}

function goBack() {
  router.push('/demandes-achat')
}

function editDA() {
  router.push(`/demandes-achat/${route.params.id}/modifier`)
}

async function deleteDA() {
  if (!data.value) return
  if (!confirm(`Voulez-vous vraiment supprimer la DA "${data.value.numero}" ?\n\nCette action est irréversible.`)) {
    return
  }
  processing.value = true
  try {
    await daService.delete(route.params.id as string)
    router.push('/demandes-achat')
  } catch (error: any) {
    alert(error.response?.data?.message || 'Erreur lors de la suppression')
  } finally {
    processing.value = false
  }
}

function goToEB() {
  if (data.value?.expression_besoin_id) {
    router.push({ name: 'eb-show', params: { id: data.value.expression_besoin_id } })
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
              <span class="inline-flex items-center px-2.5 py-0.5 rounded text-sm font-medium" :class="typeColors[data.type_demande]">
                {{ data.type_demande }}
              </span>
              <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium" :class="statutColors[data.statut] || 'bg-gray-100 text-gray-800'">
                {{ statutLabels[data.statut] || data.statut }}
              </span>
            </div>
            <p class="text-gray-600 mt-1">
              {{ data.type_demande === 'DA' ? 'Demande d\'Achat' : 'Demande Achat Caisse' }}
            </p>
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
          <button v-if="canTransform" @click="showTransformModal = true" class="btn-primary inline-flex items-center">
            <ArrowRightIcon class="w-4 h-4 mr-2" />
            Créer BC
          </button>
          <button @click="editDA" class="btn-secondary inline-flex items-center">
            <PencilIcon class="w-4 h-4 mr-2" />
            Modifier
          </button>
          <button v-if="canDelete" @click="deleteDA" :disabled="processing" class="btn-secondary text-red-600 hover:bg-red-50 inline-flex items-center">
            <TrashIcon class="w-4 h-4 mr-2" />
            Supprimer
          </button>
        </div>
      </div>

      <!-- Lien vers EB -->
      <div v-if="data.expression_besoin" class="card mb-6 bg-blue-50 border-blue-200">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <LinkIcon class="w-5 h-5 text-blue-600" />
            <div>
              <p class="text-sm text-blue-600">Issue de l'Expression de Besoin</p>
              <p class="font-medium text-blue-900">{{ data.expression_besoin.numero }}</p>
            </div>
          </div>
          <button @click="goToEB" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
            Voir l'EB
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
              Description de la demande
            </h2>
            <div class="space-y-4">
              <div>
                <label class="text-sm text-gray-500">Objet</label>
                <p class="text-gray-900 font-medium">{{ data.objet }}</p>
              </div>
              <div v-if="data.description">
                <label class="text-sm text-gray-500">Description</label>
                <p class="text-gray-700 whitespace-pre-wrap">{{ data.description }}</p>
              </div>
            </div>
          </div>

          <!-- Lignes avec Accordéon -->
          <div class="card">
            <div class="flex items-center justify-between mb-4">
              <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                <ClipboardDocumentListIcon class="w-5 h-5 mr-2 text-gray-400" />
                Lignes et Offres Fournisseurs
              </h2>
              <button v-if="canEdit" @click="showAddLigneModal = true" class="btn-primary btn-sm inline-flex items-center">
                <PlusIcon class="w-4 h-4 mr-1" />
                Ajouter ligne
              </button>
            </div>

            <div v-if="data.lignes && data.lignes.length > 0" class="space-y-3">
              <!-- Accordéon pour chaque ligne -->
              <div v-for="ligne in data.lignes" :key="ligne.id" class="border border-gray-200 rounded-lg overflow-hidden">
                <!-- Header de la ligne -->
                <div
                  @click="toggleLigne(ligne.id)"
                  class="flex items-center justify-between p-4 bg-gray-50 cursor-pointer hover:bg-gray-100 transition-colors"
                >
                  <div class="flex items-center gap-4 flex-1">
                    <span class="text-sm font-medium text-gray-500 w-8">{{ ligne.numero_ligne }}</span>
                    <div class="flex-1">
                      <p class="font-medium text-gray-900">{{ ligne.designation }}</p>
                      <p class="text-sm text-gray-500">Qté: {{ ligne.quantite }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                      <!-- Badge offres -->
                      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                        :class="ligne.offres?.length ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600'">
                        {{ ligne.offres?.length || 0 }} offre(s)
                      </span>
                      <!-- Meilleure offre -->
                      <div v-if="getMeilleureOffre(ligne)" class="flex items-center gap-1 text-sm text-green-600">
                        <TrophyIcon class="w-4 h-4" />
                        <span class="hidden sm:inline">{{ getMeilleureOffre(ligne).fournisseur?.raison_sociale }}</span>
                      </div>
                    </div>
                  </div>
                  <ChevronDownIcon v-if="!expandedLignes.has(ligne.id)" class="w-5 h-5 text-gray-400 ml-2" />
                  <ChevronUpIcon v-else class="w-5 h-5 text-gray-400 ml-2" />
                </div>

                <!-- Contenu expandé - Offres fournisseurs -->
                <div v-if="expandedLignes.has(ligne.id)" class="p-4 border-t border-gray-200 bg-white">
                  <div class="flex items-center justify-between mb-4">
                    <h4 class="text-sm font-medium text-gray-700">Comparaison des offres</h4>
                    <div class="flex gap-2">
                      <button v-if="canEdit" @click.stop="openAddOffreModal(ligne)" class="btn-secondary btn-sm inline-flex items-center">
                        <PlusIcon class="w-4 h-4 mr-1" />
                        Ajouter offre
                      </button>
                      <button v-if="canEdit" @click.stop="deleteLigne(ligne.id)" class="btn-secondary btn-sm text-red-600 hover:bg-red-50">
                        <TrashIcon class="w-4 h-4" />
                      </button>
                    </div>
                  </div>

                  <!-- Tableau de comparaison des offres -->
                  <div v-if="ligne.offres && ligne.offres.length > 0" class="overflow-x-auto">
                    <table class="w-full text-sm">
                      <thead class="bg-gray-50">
                        <tr>
                          <th class="px-3 py-2 text-left text-gray-600">Fournisseur</th>
                          <th class="px-3 py-2 text-right text-gray-600">
                            <div class="flex items-center justify-end gap-1">
                              <BanknotesIcon class="w-4 h-4" />
                              Prix unit.
                            </div>
                          </th>
                          <th class="px-3 py-2 text-right text-gray-600">
                            <div class="flex items-center justify-end gap-1">
                              <ClockIcon class="w-4 h-4" />
                              Délai
                            </div>
                          </th>
                          <th class="px-3 py-2 text-right text-gray-600">Paiement</th>
                          <th class="px-3 py-2 text-right text-gray-600">
                            <div class="flex items-center justify-end gap-1">
                              <ShieldCheckIcon class="w-4 h-4" />
                              Garantie
                            </div>
                          </th>
                          <th class="px-3 py-2 text-center text-gray-600">Tech.</th>
                          <th class="px-3 py-2 text-center text-gray-600">Score</th>
                          <th class="px-3 py-2 text-center text-gray-600">Action</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-gray-100">
                        <tr v-for="offre in ligne.offres" :key="offre.id"
                          class="hover:bg-gray-50"
                          :class="{ 'bg-green-50': offre.est_selectionnee, 'opacity-50': offre.offre_technique === 'NON_CONFORME' }">
                          <td class="px-3 py-3">
                            <div class="flex items-center gap-2">
                              <TrophyIcon v-if="getMeilleureOffre(ligne)?.id === offre.id" class="w-4 h-4 text-yellow-500" />
                              <span class="font-medium">{{ offre.fournisseur?.raison_sociale || offre.fournisseur?.nom }}</span>
                            </div>
                          </td>
                          <td class="px-3 py-3 text-right">
                            <span :class="{ 'text-green-600 font-semibold': isMeilleurePour(offre, ligne, 'prix') }">
                              {{ formatMontant(offre.prix_unitaire) }}
                            </span>
                          </td>
                          <td class="px-3 py-3 text-right">
                            <span :class="{ 'text-green-600 font-semibold': isMeilleurePour(offre, ligne, 'delai') }">
                              {{ offre.delai_livraison_jours }} jours
                            </span>
                          </td>
                          <td class="px-3 py-3 text-right">
                            <span :class="{ 'text-green-600 font-semibold': isMeilleurePour(offre, ligne, 'paiement') }">
                              {{ offre.conditions_paiement_jours }} jours
                            </span>
                          </td>
                          <td class="px-3 py-3 text-right">
                            <span :class="{ 'text-green-600 font-semibold': isMeilleurePour(offre, ligne, 'garantie') }">
                              {{ offre.garantie || (offre.garantie_mois ? offre.garantie_mois + ' mois' : '-') }}
                            </span>
                          </td>
                          <td class="px-3 py-3 text-center">
                            <span v-if="offre.offre_technique === 'CONFORME'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                              Conforme
                            </span>
                            <span v-else class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                              Non conforme
                            </span>
                          </td>
                          <td class="px-3 py-3 text-center">
                            <span v-if="offre.score" class="font-semibold" :class="getMeilleureOffre(ligne)?.id === offre.id ? 'text-green-600' : 'text-gray-700'">
                              {{ offre.score }}/100
                            </span>
                            <span v-else class="text-gray-400">-</span>
                          </td>
                          <td class="px-3 py-3 text-center">
                            <div class="flex items-center justify-center gap-1">
                              <button
                                v-if="canEdit && offre.offre_technique === 'CONFORME' && !offre.est_selectionnee"
                                @click.stop="selectOffre(ligne.id, offre.id)"
                                class="p-1 text-blue-600 hover:bg-blue-50 rounded"
                                title="Sélectionner cette offre"
                              >
                                <CheckIcon class="w-4 h-4" />
                              </button>
                              <span v-if="offre.est_selectionnee" class="text-green-600 font-medium text-xs">Sélectionné</span>
                              <button
                                v-if="canEdit"
                                @click.stop="deleteOffre(ligne.id, offre.id)"
                                class="p-1 text-red-600 hover:bg-red-50 rounded"
                                title="Supprimer"
                              >
                                <TrashIcon class="w-4 h-4" />
                              </button>
                            </div>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  <p v-else class="text-gray-500 text-center py-4 text-sm">
                    Aucune offre fournisseur. Cliquez sur "Ajouter offre" pour commencer la comparaison.
                  </p>
                </div>
              </div>
            </div>
            <p v-else class="text-gray-500 text-center py-8">
              Aucune ligne. Cliquez sur "Ajouter ligne" pour commencer.
            </p>
          </div>

          <!-- Montant total -->
          <div class="card">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <CurrencyDollarIcon class="w-5 h-5 mr-2 text-gray-400" />
              Montant
            </h2>
            <div class="text-center py-4">
              <p class="text-sm text-gray-500 mb-1">Montant total de la demande</p>
              <p class="text-3xl font-bold text-ct-blue-600">{{ formatMontant(data.montant) }}</p>
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
                <label class="text-sm text-gray-500">Date de demande</label>
                <p class="text-gray-900">{{ formatDate(data.date_demande) }}</p>
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

    <!-- Modal Ajouter Ligne -->
    <div v-if="showAddLigneModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black bg-opacity-50" @click="showAddLigneModal = false"></div>
        <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
          <h3 class="text-lg font-semibold mb-4">Ajouter une ligne</h3>
          <div class="space-y-4">
            <div>
              <label class="label">Désignation *</label>
              <input v-model="newLigne.designation" type="text" class="input" placeholder="Ex: Ordinateur portable HP" />
            </div>
            <div>
              <label class="label">Description</label>
              <textarea v-model="newLigne.description" class="input" rows="2" placeholder="Détails supplémentaires..."></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="label">Quantité *</label>
                <input v-model.number="newLigne.quantite" type="number" class="input" min="1" />
              </div>
              <div>
                <label class="label">Prix estimé</label>
                <input v-model="newLigne.prix_unitaire_estime" type="number" class="input" placeholder="FCFA" />
              </div>
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button @click="showAddLigneModal = false" class="btn-secondary">Annuler</button>
            <button @click="addLigne" :disabled="!newLigne.designation || processing" class="btn-primary">
              {{ processing ? 'Ajout...' : 'Ajouter' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Ajouter Offre -->
    <div v-if="showAddOffreModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black bg-opacity-50" @click="showAddOffreModal = false"></div>
        <div class="relative bg-white rounded-lg shadow-xl max-w-lg w-full p-6">
          <h3 class="text-lg font-semibold mb-2">Ajouter une offre fournisseur</h3>
          <p class="text-sm text-gray-500 mb-4">Pour: {{ selectedLigneForOffre?.designation }}</p>

          <div class="space-y-4">
            <div>
              <label class="label">Fournisseur *</label>
              <select v-model="newOffre.fournisseur_id" class="input">
                <option value="">Sélectionner un fournisseur</option>
                <option v-for="f in fournisseurs" :key="f.value" :value="f.value">
                  {{ f.label }}
                </option>
              </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="label">Prix unitaire (FCFA) *</label>
                <input v-model="newOffre.prix_unitaire" type="number" class="input" min="0" />
              </div>
              <div>
                <label class="label">Délai livraison (jours) *</label>
                <input v-model="newOffre.delai_livraison_jours" type="number" class="input" min="1" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="label">Conditions paiement (jours)</label>
                <input v-model="newOffre.conditions_paiement_jours" type="number" class="input" min="0" placeholder="Ex: 30" />
              </div>
              <div>
                <label class="label">Garantie (mois)</label>
                <input v-model="newOffre.garantie_mois" type="number" class="input" min="0" placeholder="Ex: 12" />
              </div>
            </div>

            <div>
              <label class="label">Offre technique *</label>
              <select v-model="newOffre.offre_technique" class="input">
                <option value="CONFORME">Conforme</option>
                <option value="NON_CONFORME">Non conforme</option>
              </select>
            </div>

            <div>
              <label class="label">Référence devis</label>
              <input v-model="newOffre.reference_offre" type="text" class="input" placeholder="Ex: DEVIS-2026-001" />
            </div>

            <div>
              <label class="label">Commentaire</label>
              <textarea v-model="newOffre.commentaire" class="input" rows="2" placeholder="Notes sur cette offre..."></textarea>
            </div>
          </div>

          <div class="flex justify-end gap-3 mt-6">
            <button @click="showAddOffreModal = false" class="btn-secondary">Annuler</button>
            <button @click="addOffre" :disabled="!newOffre.fournisseur_id || !newOffre.prix_unitaire || processing" class="btn-primary">
              {{ processing ? 'Ajout...' : 'Ajouter l\'offre' }}
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
          <h3 class="text-lg font-semibold mb-4">Rejeter la demande d'achat</h3>
          <textarea v-model="motifRejet" class="input mb-4" rows="3" placeholder="Motif du rejet..."></textarea>
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
          <h3 class="text-lg font-semibold mb-4">Créer un Bon de Commande</h3>
          <p class="text-gray-600 mb-4">
            Cette action va créer un nouveau Bon de Commande (BC) à partir de cette demande d'achat.
          </p>
          <div class="space-y-4 mb-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Fournisseur *</label>
              <select v-model="selectedFournisseur" class="input">
                <option value="">Sélectionner un fournisseur</option>
                <option v-for="f in fournisseurs" :key="f.value" :value="f.value">
                  {{ f.label }}
                </option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Type de BC</label>
              <select v-model="selectedTypeBC" class="input">
                <option value="BCAL">BCAL - BC Achat Local</option>
                <option value="BCL">BCL - BC Local</option>
                <option value="BCAI">BCAI - BC Achat International</option>
                <option value="BCI">BCI - BC International</option>
                <option value="IPO">IPO - International Purchase Order</option>
              </select>
            </div>
          </div>
          <div class="flex justify-end gap-3">
            <button @click="showTransformModal = false" class="btn-secondary">Annuler</button>
            <button @click="transformer" :disabled="!selectedFournisseur || processing" class="btn-primary">
              {{ processing ? 'Création...' : 'Créer le BC' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
