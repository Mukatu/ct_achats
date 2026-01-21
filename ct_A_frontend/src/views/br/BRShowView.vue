<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { receptionService } from '@/services/api'
import { formatDate, type Reception } from '@/types'
import {
  ArrowLeftIcon,
  PencilIcon,
  CheckIcon,
  UserIcon,
  MapPinIcon,
  CalendarIcon,
  TruckIcon,
  DocumentTextIcon,
  ClipboardDocumentListIcon,
  LinkIcon,
  CheckBadgeIcon,
  TrashIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const data = ref<Reception | null>(null)
const processing = ref(false)

const statutColors: Record<string, string> = {
  'BROUILLON': 'bg-gray-100 text-gray-800',
  'VALIDEE': 'bg-green-100 text-green-800',
  'EN_LITIGE': 'bg-orange-100 text-orange-800',
  'ANNULEE': 'bg-red-100 text-red-800',
}

const statutLabels: Record<string, string> = {
  'BROUILLON': 'Brouillon',
  'VALIDEE': 'Validee',
  'EN_LITIGE': 'En litige',
  'ANNULEE': 'Annulee',
}

const typeColors: Record<string, string> = {
  'LIVRAISON': 'bg-blue-100 text-blue-800',
  'SERVICE_FAIT': 'bg-purple-100 text-purple-800',
  'PARTIELLE': 'bg-yellow-100 text-yellow-800',
}

const typeLabels: Record<string, string> = {
  'LIVRAISON': 'Livraison',
  'SERVICE_FAIT': 'Service fait',
  'PARTIELLE': 'Partielle',
}

const canValidate = computed(() => data.value?.statut === 'BROUILLON')
const canEdit = computed(() => data.value?.statut === 'BROUILLON')
const canDelete = computed(() => data.value?.statut === 'BROUILLON')

async function loadData() {
  loading.value = true
  try {
    const response = await receptionService.get(route.params.id as string)
    data.value = response.data.data || response.data
  } catch (error) {
    console.error('Erreur chargement BR:', error)
    router.push('/receptions')
  } finally {
    loading.value = false
  }
}

async function valider() {
  if (!confirm('Voulez-vous valider cette reception ? Les quantites seront mises a jour sur le bon de commande.')) {
    return
  }

  processing.value = true
  try {
    await receptionService.valider(route.params.id as string)
    await loadData()
  } catch (error) {
    console.error('Erreur validation:', error)
    alert('Erreur lors de la validation')
  } finally {
    processing.value = false
  }
}

function goBack() {
  router.push('/receptions')
}

function editBR() {
  router.push(`/receptions/${route.params.id}/modifier`)
}

function goToBC() {
  if (data.value?.bon_commande_id) {
    router.push({ name: 'bc-show', params: { id: data.value.bon_commande_id } })
  }
}

async function deleteBR() {
  if (!data.value) return
  if (!confirm(`Voulez-vous vraiment supprimer le BR "${data.value.numero}" ?\n\nCette action est irréversible.`)) {
    return
  }
  processing.value = true
  try {
    await receptionService.delete(route.params.id as string)
    router.push('/receptions')
  } catch (error: any) {
    alert(error.response?.data?.message || 'Erreur lors de la suppression')
  } finally {
    processing.value = false
  }
}

// Calcul du taux de conformite global
const tauxConformite = computed(() => {
  if (!data.value?.lignes || data.value.lignes.length === 0) return 0
  const totalRecue = data.value.lignes.reduce((sum, l) => sum + (l.quantite_recue || 0), 0)
  const totalConforme = data.value.lignes.reduce((sum, l) => sum + (l.quantite_conforme || 0), 0)
  if (totalRecue === 0) return 0
  return Math.round((totalConforme / totalRecue) * 100)
})

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
                :class="typeColors[data.type_reception]"
              >
                {{ typeLabels[data.type_reception] || data.type_reception }}
              </span>
              <span
                class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                :class="statutColors[data.statut] || 'bg-gray-100 text-gray-800'"
              >
                {{ statutLabels[data.statut] || data.statut }}
              </span>
            </div>
            <p class="text-gray-600 mt-1">Bon de Reception</p>
          </div>
        </div>

        <div class="flex flex-wrap gap-2">
          <button v-if="canValidate" @click="valider" :disabled="processing" class="btn-primary inline-flex items-center">
            <CheckBadgeIcon class="w-4 h-4 mr-2" />
            Valider la reception
          </button>
          <button v-if="canEdit" @click="editBR" class="btn-secondary inline-flex items-center">
            <PencilIcon class="w-4 h-4 mr-2" />
            Modifier
          </button>
          <button v-if="canDelete" @click="deleteBR" :disabled="processing" class="btn-secondary inline-flex items-center text-red-600 hover:bg-red-50">
            <TrashIcon class="w-4 h-4 mr-2" />
            Supprimer
          </button>
        </div>
      </div>

      <!-- Lien vers BC -->
      <div v-if="data.bon_commande" class="card mb-6 bg-blue-50 border-blue-200">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <LinkIcon class="w-5 h-5 text-blue-600" />
            <div>
              <p class="text-sm text-blue-600">Reception du Bon de Commande</p>
              <p class="font-medium text-blue-900">{{ data.bon_commande.numero }}</p>
              <p class="text-sm text-blue-700">{{ data.bon_commande.fournisseur?.raison_sociale }}</p>
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
          <!-- Lignes de reception -->
          <div class="card">
            <div class="flex items-center justify-between mb-4">
              <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                <ClipboardDocumentListIcon class="w-5 h-5 mr-2 text-gray-400" />
                Articles recus
              </h2>
              <div class="flex items-center gap-2">
                <span class="text-sm text-gray-500">Taux de conformite:</span>
                <span
                  class="px-2 py-1 rounded text-sm font-medium"
                  :class="tauxConformite >= 90 ? 'bg-green-100 text-green-800' : tauxConformite >= 70 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'"
                >
                  {{ tauxConformite }}%
                </span>
              </div>
            </div>

            <div v-if="data.lignes && data.lignes.length > 0" class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-2 text-left text-gray-600">#</th>
                    <th class="px-4 py-2 text-left text-gray-600">Designation</th>
                    <th class="px-4 py-2 text-right text-gray-600">Attendue</th>
                    <th class="px-4 py-2 text-right text-gray-600">Recue</th>
                    <th class="px-4 py-2 text-right text-gray-600">Conforme</th>
                    <th class="px-4 py-2 text-right text-gray-600">Non conf.</th>
                    <th class="px-4 py-2 text-right text-gray-600">Refusee</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="ligne in data.lignes" :key="ligne.id" class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-gray-500">{{ ligne.numero_ligne }}</td>
                    <td class="px-4 py-3">
                      <p class="font-medium text-gray-900">{{ ligne.ligne_bon_commande?.designation }}</p>
                      <p v-if="ligne.numero_lot" class="text-xs text-gray-500">Lot: {{ ligne.numero_lot }}</p>
                      <p v-if="ligne.numero_serie" class="text-xs text-gray-500">Serie: {{ ligne.numero_serie }}</p>
                    </td>
                    <td class="px-4 py-3 text-right">{{ ligne.quantite_attendue }}</td>
                    <td class="px-4 py-3 text-right font-medium">{{ ligne.quantite_recue }}</td>
                    <td class="px-4 py-3 text-right text-green-600">{{ ligne.quantite_conforme }}</td>
                    <td class="px-4 py-3 text-right text-orange-600">
                      <span v-if="ligne.quantite_non_conforme > 0">{{ ligne.quantite_non_conforme }}</span>
                      <span v-else class="text-gray-300">-</span>
                    </td>
                    <td class="px-4 py-3 text-right text-red-600">
                      <span v-if="ligne.quantite_refusee > 0">{{ ligne.quantite_refusee }}</span>
                      <span v-else class="text-gray-300">-</span>
                    </td>
                  </tr>
                </tbody>
              </table>

              <!-- Details non-conformites -->
              <div class="mt-4 space-y-2">
                <template v-for="ligne in data.lignes" :key="'nc-' + ligne.id">
                  <div v-if="ligne.motif_non_conformite" class="p-3 bg-orange-50 border border-orange-200 rounded-lg text-sm">
                    <span class="font-medium text-orange-800">Non-conformite ({{ ligne.ligne_bon_commande?.designation }}):</span>
                    <span class="text-orange-700 ml-1">{{ ligne.motif_non_conformite }}</span>
                  </div>
                  <div v-if="ligne.motif_refus" class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm">
                    <span class="font-medium text-red-800">Refus ({{ ligne.ligne_bon_commande?.designation }}):</span>
                    <span class="text-red-700 ml-1">{{ ligne.motif_refus }}</span>
                  </div>
                </template>
              </div>
            </div>
            <p v-else class="text-gray-500 text-center py-4">Aucune ligne</p>
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
          <!-- Informations livraison -->
          <div class="card">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <TruckIcon class="w-5 h-5 mr-2 text-gray-400" />
              Livraison
            </h2>
            <div class="space-y-3">
              <div v-if="data.lieu_reception">
                <label class="text-sm text-gray-500">Lieu de reception</label>
                <p class="text-gray-900">{{ data.lieu_reception }}</p>
              </div>
              <div v-if="data.numero_bl_fournisseur">
                <label class="text-sm text-gray-500">N BL Fournisseur</label>
                <p class="text-gray-900">{{ data.numero_bl_fournisseur }}</p>
              </div>
              <div v-if="data.date_bl_fournisseur">
                <label class="text-sm text-gray-500">Date BL</label>
                <p class="text-gray-900">{{ formatDate(data.date_bl_fournisseur) }}</p>
              </div>
              <div v-if="data.transporteur">
                <label class="text-sm text-gray-500">Transporteur</label>
                <p class="text-gray-900">{{ data.transporteur }}</p>
              </div>
              <div v-if="data.numero_tracking">
                <label class="text-sm text-gray-500">N Tracking</label>
                <p class="text-gray-900">{{ data.numero_tracking }}</p>
              </div>
            </div>
          </div>

          <!-- Receptionnaire -->
          <div class="card">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <UserIcon class="w-5 h-5 mr-2 text-gray-400" />
              Receptionnaire
            </h2>
            <div class="space-y-3">
              <div>
                <label class="text-sm text-gray-500">Receptionnee par</label>
                <p class="text-gray-900">{{ data.receptionnaire?.nom }} {{ data.receptionnaire?.prenom }}</p>
              </div>
            </div>
          </div>

          <!-- Dates -->
          <div class="card">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <CalendarIcon class="w-5 h-5 mr-2 text-gray-400" />
              Dates
            </h2>
            <div class="space-y-3">
              <div>
                <label class="text-sm text-gray-500">Date de reception</label>
                <p class="text-gray-900">{{ formatDate(data.date_reception) }}</p>
              </div>
              <div>
                <label class="text-sm text-gray-500">Cree le</label>
                <p class="text-gray-900">{{ formatDate(data.created_at) }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
