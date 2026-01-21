<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { daService, referentielService } from '@/services/api'
import { formatMontant } from '@/types'
import {
  ArrowLeftIcon,
  CheckIcon,
  PlusIcon,
  TrashIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const submitting = ref(false)
const errors = ref<Record<string, string[]>>({})

// Référentiels
const zones = ref<any[]>([])
const directions = ref<any[]>([])
const services = ref<any[]>([])
const unitesMesure = ref<any[]>([])

// Formulaire
const form = ref({
  type_demande: 'DA',
  zone_id: '',
  direction_id: '',
  service_id: '',
  objet: '',
  description: '',
})

// Lignes de la demande
interface LigneDA {
  id?: string
  designation: string
  description: string
  quantite: number
  unite_mesure_id: string
  prix_unitaire: number
}

const lignes = ref<LigneDA[]>([])

// Filtrer les directions par zone
const filteredDirections = computed(() => {
  if (!form.value.zone_id) return directions.value
  return directions.value.filter((d: any) => d.zone_id === form.value.zone_id)
})

// Filtrer les services par direction
const filteredServices = computed(() => {
  if (!form.value.direction_id) return services.value
  return services.value.filter((s: any) => s.direction_id === form.value.direction_id)
})

// Calculer le montant total
const montantTotal = computed(() => {
  return lignes.value.reduce((total, ligne) => {
    return total + (ligne.quantite * ligne.prix_unitaire)
  }, 0)
})

async function loadData() {
  loading.value = true
  try {
    const [daRes, zonesRes, directionsRes, servicesRes, unitesRes] = await Promise.all([
      daService.get(route.params.id as string),
      referentielService.getZones(),
      referentielService.getDirections(),
      referentielService.getServices(),
      referentielService.getUnitesMesure(),
    ])

    zones.value = zonesRes.data
    directions.value = directionsRes.data
    services.value = servicesRes.data
    unitesMesure.value = unitesRes.data

    const da = daRes.data.data || daRes.data
    form.value = {
      type_demande: da.type_demande || 'DA',
      zone_id: da.zone_id || '',
      direction_id: da.direction_id || '',
      service_id: da.service_id || '',
      objet: da.objet || '',
      description: da.description || '',
    }

    if (da.lignes && da.lignes.length > 0) {
      lignes.value = da.lignes.map((l: any) => ({
        id: l.id,
        designation: l.designation || '',
        description: l.description || '',
        quantite: l.quantite || 1,
        unite_mesure_id: l.unite_mesure_id || '',
        prix_unitaire: l.prix_unitaire || 0,
      }))
    } else {
      lignes.value = [{ designation: '', description: '', quantite: 1, unite_mesure_id: '', prix_unitaire: 0 }]
    }
  } catch (error) {
    console.error('Erreur chargement DA:', error)
    router.push('/demandes-achat')
  } finally {
    loading.value = false
  }
}

function onZoneChange() {
  form.value.direction_id = ''
  form.value.service_id = ''
}

function onDirectionChange() {
  form.value.service_id = ''
}

function addLigne() {
  lignes.value.push({ designation: '', description: '', quantite: 1, unite_mesure_id: '', prix_unitaire: 0 })
}

function removeLigne(index: number) {
  if (lignes.value.length > 1) {
    lignes.value.splice(index, 1)
  }
}

function getMontantLigne(ligne: LigneDA): number {
  return ligne.quantite * ligne.prix_unitaire
}

async function submit() {
  errors.value = {}

  const validLignes = lignes.value.filter(l => l.designation.trim() !== '')
  if (validLignes.length === 0) {
    errors.value.lignes = ['Ajoutez au moins une ligne avec une désignation']
    return
  }

  submitting.value = true

  try {
    const payload = {
      ...form.value,
      service_id: form.value.service_id || null,
      lignes: validLignes.map((l, index) => ({
        id: l.id || undefined,
        numero_ligne: index + 1,
        designation: l.designation,
        description: l.description || null,
        quantite: l.quantite,
        unite_mesure_id: l.unite_mesure_id || null,
        prix_unitaire: l.prix_unitaire,
        montant: l.quantite * l.prix_unitaire,
      })),
    }

    await daService.update(route.params.id as string, payload)

    router.push({
      name: 'da-show',
      params: { id: route.params.id },
    })
  } catch (error: any) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {}
    } else {
      console.error('Erreur modification DA:', error)
      alert('Une erreur est survenue lors de la modification')
    }
  } finally {
    submitting.value = false
  }
}

function goBack() {
  router.push({ name: 'da-show', params: { id: route.params.id } })
}

onMounted(() => {
  loadData()
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
        <h1 class="text-2xl font-bold text-gray-900">Modifier la Demande d'Achat</h1>
        <p class="text-gray-600 mt-1">Modifiez les informations de cette demande</p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-ct-blue-500"></div>
    </div>

    <!-- Form -->
    <form v-else @submit.prevent="submit" class="space-y-6">
      <!-- Type -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Type de demande</h2>
        <div>
          <label class="label">Type <span class="text-red-500">*</span></label>
          <select v-model="form.type_demande" class="input" required>
            <option value="DA">DA - Demande d'Achat</option>
            <option value="DAC">DAC - Demande Achat Caisse</option>
          </select>
        </div>
      </div>

      <!-- Localisation -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Localisation</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="label">Zone <span class="text-red-500">*</span></label>
            <select v-model="form.zone_id" @change="onZoneChange" class="input" required>
              <option value="">Sélectionner une zone</option>
              <option v-for="z in zones" :key="z.id" :value="z.id">
                {{ z.libelle }}
              </option>
            </select>
            <p v-if="errors.zone_id" class="text-red-500 text-sm mt-1">{{ errors.zone_id[0] }}</p>
          </div>

          <div>
            <label class="label">Direction <span class="text-red-500">*</span></label>
            <select v-model="form.direction_id" @change="onDirectionChange" class="input" required>
              <option value="">Sélectionner une direction</option>
              <option v-for="d in filteredDirections" :key="d.id" :value="d.id">
                {{ d.libelle_court || d.libelle }}
              </option>
            </select>
            <p v-if="errors.direction_id" class="text-red-500 text-sm mt-1">{{ errors.direction_id[0] }}</p>
          </div>

          <div>
            <label class="label">Service</label>
            <select v-model="form.service_id" class="input">
              <option value="">Sélectionner un service</option>
              <option v-for="s in filteredServices" :key="s.id" :value="s.id">
                {{ s.libelle }}
              </option>
            </select>
          </div>
        </div>
      </div>

      <!-- Description -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Description</h2>
        <div class="space-y-4">
          <div>
            <label class="label">Objet <span class="text-red-500">*</span></label>
            <input
              v-model="form.objet"
              type="text"
              class="input"
              placeholder="Objet de la demande..."
              maxlength="500"
              required
            />
            <p v-if="errors.objet" class="text-red-500 text-sm mt-1">{{ errors.objet[0] }}</p>
          </div>

          <div>
            <label class="label">Description complémentaire</label>
            <textarea
              v-model="form.description"
              class="input"
              rows="3"
              placeholder="Informations complémentaires..."
            ></textarea>
          </div>
        </div>
      </div>

      <!-- Lignes -->
      <div class="card">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-gray-900">Lignes de la demande</h2>
          <button type="button" @click="addLigne" class="btn-secondary inline-flex items-center text-sm">
            <PlusIcon class="w-4 h-4 mr-1" />
            Ajouter une ligne
          </button>
        </div>

        <p v-if="errors.lignes" class="text-red-500 text-sm mb-4">{{ errors.lignes[0] }}</p>

        <div class="space-y-4">
          <div
            v-for="(ligne, index) in lignes"
            :key="index"
            class="border border-gray-200 rounded-lg p-4 bg-gray-50"
          >
            <div class="flex items-start justify-between mb-3">
              <span class="text-sm font-medium text-gray-700">Ligne {{ index + 1 }}</span>
              <button
                v-if="lignes.length > 1"
                type="button"
                @click="removeLigne(index)"
                class="p-1 text-red-500 hover:text-red-700 hover:bg-red-50 rounded"
              >
                <TrashIcon class="w-4 h-4" />
              </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
              <div class="md:col-span-2">
                <label class="label text-xs">Désignation <span class="text-red-500">*</span></label>
                <input
                  v-model="ligne.designation"
                  type="text"
                  class="input text-sm"
                  placeholder="Article ou prestation..."
                />
              </div>
              <div>
                <label class="label text-xs">Quantité</label>
                <input
                  v-model.number="ligne.quantite"
                  type="number"
                  class="input text-sm"
                  min="1"
                  step="1"
                />
              </div>
              <div>
                <label class="label text-xs">Unité</label>
                <select v-model="ligne.unite_mesure_id" class="input text-sm">
                  <option value="">-</option>
                  <option v-for="u in unitesMesure" :key="u.value" :value="u.value">
                    {{ u.symbole || u.label }}
                  </option>
                </select>
              </div>
              <div>
                <label class="label text-xs">Prix unitaire</label>
                <input
                  v-model.number="ligne.prix_unitaire"
                  type="number"
                  class="input text-sm"
                  min="0"
                />
              </div>
              <div>
                <label class="label text-xs">Montant</label>
                <div class="input text-sm bg-gray-100 text-gray-700">
                  {{ formatMontant(getMontantLigne(ligne)) }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Total -->
        <div class="mt-4 pt-4 border-t border-gray-200 flex justify-end">
          <div class="text-right">
            <p class="text-sm text-gray-600">Montant total</p>
            <p class="text-2xl font-bold text-ct-blue-600">{{ formatMontant(montantTotal) }}</p>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-4">
        <button type="button" @click="goBack" class="btn-secondary">
          Annuler
        </button>
        <button type="submit" :disabled="submitting" class="btn-primary inline-flex items-center">
          <span v-if="submitting" class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></span>
          <CheckIcon v-else class="w-5 h-5 mr-2" />
          {{ submitting ? 'Enregistrement...' : 'Enregistrer les modifications' }}
        </button>
      </div>
    </form>
  </div>
</template>
