<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { fournisseurService } from '@/services/api'
import { type Fournisseur } from '@/types'
import {
  ArrowLeftIcon,
  PencilIcon,
  TrashIcon,
  BuildingOfficeIcon,
  GlobeAltIcon,
  MapPinIcon,
  PhoneIcon,
  EnvelopeIcon,
  IdentificationIcon,
  BanknotesIcon,
  DocumentTextIcon,
  BuildingLibraryIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const data = ref<Fournisseur | null>(null)

const statutColors: Record<string, string> = {
  'PROSPECT': 'bg-gray-100 text-gray-800',
  'EN_VALIDATION': 'bg-yellow-100 text-yellow-800',
  'ACTIF': 'bg-green-100 text-green-800',
  'SUSPENDU': 'bg-orange-100 text-orange-800',
  'BLOQUE': 'bg-red-100 text-red-800',
  'INACTIF': 'bg-gray-100 text-gray-600',
}

const statutLabels: Record<string, string> = {
  'PROSPECT': 'Prospect',
  'EN_VALIDATION': 'En validation',
  'ACTIF': 'Actif',
  'SUSPENDU': 'Suspendu',
  'BLOQUE': 'Bloqué',
  'INACTIF': 'Inactif',
}

const typeLabels: Record<string, string> = {
  'LOCAL': 'Local (Congo)',
  'CEMAC': 'Zone CEMAC',
  'INTERNATIONAL': 'International',
}

const typeIcons: Record<string, any> = {
  'LOCAL': MapPinIcon,
  'CEMAC': BuildingOfficeIcon,
  'INTERNATIONAL': GlobeAltIcon,
}

async function loadData() {
  loading.value = true
  try {
    const response = await fournisseurService.get(route.params.id as string)
    data.value = response.data.data || response.data
  } catch (error) {
    console.error('Erreur chargement fournisseur:', error)
    router.push('/fournisseurs')
  } finally {
    loading.value = false
  }
}

function goBack() {
  router.push('/fournisseurs')
}

function editFournisseur() {
  router.push(`/fournisseurs/${route.params.id}/modifier`)
}

const canDelete = computed(() => ['PROSPECT', 'SUSPENDU', 'INACTIF'].includes(data.value?.statut || ''))

async function deleteFournisseur() {
  if (!data.value) return
  if (!confirm(`Voulez-vous vraiment supprimer le fournisseur "${data.value.raison_sociale}" ?\n\nCette action est irréversible.`)) {
    return
  }
  try {
    await fournisseurService.delete(data.value.id)
    router.push('/fournisseurs')
  } catch (error: any) {
    alert(error.response?.data?.message || 'Erreur lors de la suppression')
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
              <h1 class="text-2xl font-bold text-gray-900">{{ data.raison_sociale }}</h1>
              <span
                class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                :class="statutColors[data.statut] || 'bg-gray-100 text-gray-800'"
              >
                {{ statutLabels[data.statut] || data.statut }}
              </span>
            </div>
            <div class="flex items-center gap-2 mt-1">
              <span class="text-ct-blue-600 font-medium">{{ data.code }}</span>
              <span v-if="data.sigle" class="text-gray-500">- {{ data.sigle }}</span>
            </div>
          </div>
        </div>

        <div class="flex gap-2">
          <button @click="editFournisseur" class="btn-secondary inline-flex items-center">
            <PencilIcon class="w-4 h-4 mr-2" />
            Modifier
          </button>
          <button
            v-if="canDelete"
            @click="deleteFournisseur"
            class="btn-secondary inline-flex items-center text-red-600 hover:text-red-700 hover:bg-red-50"
          >
            <TrashIcon class="w-4 h-4 mr-2" />
            Supprimer
          </button>
        </div>
      </div>

      <!-- Content -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main info -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Type et infos générales -->
          <div class="card">
            <div class="flex items-center gap-4 mb-6">
              <div class="w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center">
                <component :is="typeIcons[data.type_fournisseur]" class="w-8 h-8 text-gray-500" />
              </div>
              <div>
                <p class="text-lg font-medium text-gray-900">{{ typeLabels[data.type_fournisseur] || data.type_fournisseur }}</p>
                <p class="text-gray-500">{{ data.ville }}, {{ data.pays }}</p>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div v-if="data.adresse">
                <label class="text-sm text-gray-500">Adresse</label>
                <p class="text-gray-900 whitespace-pre-wrap">{{ data.adresse }}</p>
              </div>
            </div>
          </div>

          <!-- Identification fiscale -->
          <div class="card">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <IdentificationIcon class="w-5 h-5 mr-2 text-gray-400" />
              Identification fiscale
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="text-sm text-gray-500">NIU (Numéro d'Identification Unique)</label>
                <p class="text-gray-900 font-mono">{{ data.niu || '-' }}</p>
              </div>
              <div>
                <label class="text-sm text-gray-500">RCCM (Registre du Commerce)</label>
                <p class="text-gray-900 font-mono">{{ data.rccm || '-' }}</p>
              </div>
            </div>
          </div>

          <!-- Informations fiscales -->
          <div class="card">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <BanknotesIcon class="w-5 h-5 mr-2 text-gray-400" />
              Informations financières
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="text-sm text-gray-500">Devise par défaut</label>
                <p class="text-gray-900 font-medium">{{ data.devise_defaut }}</p>
              </div>
              <div>
                <label class="text-sm text-gray-500">Taux TVA</label>
                <p class="text-gray-900 font-medium">{{ data.taux_tva }}%</p>
              </div>
            </div>
          </div>

          <!-- Informations bancaires (si disponible) -->
          <div class="card" v-if="(data as any).banque || (data as any).compte_bancaire">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <BuildingLibraryIcon class="w-5 h-5 mr-2 text-gray-400" />
              Informations bancaires
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div v-if="(data as any).banque">
                <label class="text-sm text-gray-500">Banque</label>
                <p class="text-gray-900">{{ (data as any).banque }}</p>
              </div>
              <div v-if="(data as any).compte_bancaire">
                <label class="text-sm text-gray-500">Numéro de compte</label>
                <p class="text-gray-900 font-mono">{{ (data as any).compte_bancaire }}</p>
              </div>
              <div v-if="(data as any).rib">
                <label class="text-sm text-gray-500">RIB / IBAN</label>
                <p class="text-gray-900 font-mono">{{ (data as any).rib }}</p>
              </div>
            </div>
          </div>

          <!-- Notes -->
          <div class="card" v-if="(data as any).commentaire">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <DocumentTextIcon class="w-5 h-5 mr-2 text-gray-400" />
              Notes
            </h2>
            <p class="text-gray-700 whitespace-pre-wrap">{{ (data as any).commentaire }}</p>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <!-- Contact -->
          <div class="card">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Contact</h2>
            <div class="space-y-4">
              <div v-if="data.telephone" class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                  <PhoneIcon class="w-5 h-5 text-gray-500" />
                </div>
                <div>
                  <label class="text-xs text-gray-500">Téléphone</label>
                  <p class="text-gray-900">{{ data.telephone }}</p>
                </div>
              </div>

              <div v-if="data.email" class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                  <EnvelopeIcon class="w-5 h-5 text-gray-500" />
                </div>
                <div>
                  <label class="text-xs text-gray-500">Email</label>
                  <a :href="'mailto:' + data.email" class="text-ct-blue-600 hover:underline">{{ data.email }}</a>
                </div>
              </div>

              <div v-if="(data as any).site_web" class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                  <GlobeAltIcon class="w-5 h-5 text-gray-500" />
                </div>
                <div>
                  <label class="text-xs text-gray-500">Site web</label>
                  <a :href="(data as any).site_web" target="_blank" class="text-ct-blue-600 hover:underline">
                    {{ (data as any).site_web }}
                  </a>
                </div>
              </div>

              <p v-if="!data.telephone && !data.email && !(data as any).site_web" class="text-gray-500 text-sm">
                Aucune information de contact
              </p>
            </div>
          </div>

          <!-- Localisation -->
          <div class="card">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <MapPinIcon class="w-5 h-5 mr-2 text-gray-400" />
              Localisation
            </h2>
            <div class="space-y-3">
              <div>
                <label class="text-sm text-gray-500">Ville</label>
                <p class="text-gray-900">{{ data.ville || '-' }}</p>
              </div>
              <div>
                <label class="text-sm text-gray-500">Pays</label>
                <p class="text-gray-900">{{ data.pays }}</p>
              </div>
            </div>
          </div>

          <!-- Statistiques (placeholder) -->
          <div class="card bg-gradient-to-br from-ct-blue-50 to-white">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Statistiques</h2>
            <div class="space-y-3">
              <div class="flex justify-between">
                <span class="text-gray-600">Bons de commande</span>
                <span class="font-medium text-gray-900">-</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Montant total</span>
                <span class="font-medium text-gray-900">-</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Dernière commande</span>
                <span class="font-medium text-gray-900">-</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
