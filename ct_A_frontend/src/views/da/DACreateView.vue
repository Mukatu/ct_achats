<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { daService, referentielService, ebService } from '@/services/api'
import { formatMontant } from '@/types'
import {
  ArrowLeftIcon,
  CheckIcon,
  PlusIcon,
  TrashIcon,
} from '@heroicons/vue/24/outline'

const router = useRouter()
const loading = ref(false)
const submitting = ref(false)
const errors = ref<Record<string, string[]>>({})

// Référentiels
const zones = ref<any[]>([])
const directions = ref<any[]>([])
const services = ref<any[]>([])
const expressionsBesoins = ref<any[]>([])
const unitesMesure = ref<any[]>([])

// Formulaire
const form = ref({
  type_demande: 'DA',
  zone_id: '',
  direction_id: '',
  service_id: '',
  expression_besoin_id: '',
  objet: '',
  description: '',
})

// Lignes de la demande
interface LigneDA {
  designation: string
  description: string
  quantite: number
  unite_mesure_id: string
  prix_unitaire: number
}

const lignes = ref<LigneDA[]>([
  { designation: '', description: '', quantite: 1, unite_mesure_id: '', prix_unitaire: 0 },
])

// Les directions sont centrales - pas de filtrage par zone
const filteredDirections = computed(() => {
  return directions.value
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

async function loadReferentiels() {
  loading.value = true
  try {
    const [zonesRes, directionsRes, servicesRes, unitesRes, ebRes] = await Promise.all([
      referentielService.getZones(),
      referentielService.getDirections(),
      referentielService.getServices(),
      referentielService.getUnitesMesure(),
      ebService.getAll({ statut: 'TRAITE', per_page: 100 }),
    ])
    zones.value = zonesRes.data
    directions.value = directionsRes.data
    services.value = servicesRes.data
    unitesMesure.value = unitesRes.data
    expressionsBesoins.value = ebRes.data?.data || []
  } catch (error) {
    console.error('Erreur chargement référentiels:', error)
  } finally {
    loading.value = false
  }
}

function onZoneChange() {
  // Zone est optionnelle, pas besoin de réinitialiser les autres champs
}

function onDirectionChange() {
  form.value.service_id = ''
}

function onEBChange() {
  const eb = expressionsBesoins.value.find((e: any) => e.id === form.value.expression_besoin_id)
  if (eb) {
    form.value.zone_id = eb.zone_id || ''
    form.value.direction_id = eb.direction_id || ''
    form.value.service_id = eb.service_id || ''
    form.value.objet = eb.objet || ''
    form.value.description = eb.description_detaillee || ''
    if (eb.estimation) {
      lignes.value = [
        { designation: eb.objet, description: '', quantite: 1, unite_mesure_id: '', prix_unitaire: eb.estimation }
      ]
    }
  }
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

  // Validation basique
  const validLignes = lignes.value.filter(l => l.designation.trim() !== '')
  if (validLignes.length === 0) {
    errors.value.lignes = ['Ajoutez au moins une ligne avec une désignation']
    return
  }

  submitting.value = true

  try {
    const payload = {
      ...form.value,
      zone_id: form.value.zone_id || null,
      service_id: form.value.service_id || null,
      expression_besoin_id: form.value.expression_besoin_id || null,
      montant: montantTotal.value,
      lignes: validLignes.map((l, index) => ({
        numero_ligne: index + 1,
        designation: l.designation,
        description: l.description || null,
        quantite: l.quantite,
        unite_mesure_id: l.unite_mesure_id || null,
        prix_unitaire: l.prix_unitaire,
        montant: l.quantite * l.prix_unitaire,
      })),
    }

    const response = await daService.create(payload)

    router.push({
      name: 'da-show',
      params: { id: response.data.data.id },
    })
  } catch (error: any) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {}
    } else {
      console.error('Erreur création DA:', error)
      alert('Une erreur est survenue lors de la création')
    }
  } finally {
    submitting.value = false
  }
}

function goBack() {
  router.push('/demandes-achat')
}

onMounted(() => {
  loadReferentiels()
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
        <h1 class="text-2xl font-bold text-gray-900">Nouvelle Demande d'Achat</h1>
        <p class="text-gray-600 mt-1">Créez une nouvelle demande d'achat (DA) ou demande achat caisse (DAC)</p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-ct-blue-500"></div>
    </div>

    <!-- Form -->
    <form v-else @submit.prevent="submit" class="space-y-6">
      <!-- Type et EB source -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Type de demande</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="label">Type <span class="text-red-500">*</span></label>
            <select v-model="form.type_demande" class="input" required>
              <option value="DA">DA - Demande d'Achat</option>
              <option value="DAC">DAC - Demande Achat Caisse</option>
            </select>
            <p class="text-xs text-gray-500 mt-1">
              {{ form.type_demande === 'DA' ? 'Pour les achats standards avec bon de commande' : 'Pour les achats urgents en caisse' }}
            </p>
          </div>

          <div>
            <label class="label">Lier à une Expression de Besoin</label>
            <select v-model="form.expression_besoin_id" @change="onEBChange" class="input">
              <option value="">Aucune (création directe)</option>
              <option v-for="eb in expressionsBesoins" :key="eb.id" :value="eb.id">
                {{ eb.numero }} - {{ eb.objet?.substring(0, 50) }}...
              </option>
            </select>
            <p class="text-xs text-gray-500 mt-1">Optionnel: reprendre les informations d'une EB validée</p>
          </div>
        </div>
      </div>

      <!-- Localisation -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Localisation</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="label">Zone</label>
            <select v-model="form.zone_id" @change="onZoneChange" class="input">
              <option value="">Zone par défaut (utilisateur)</option>
              <option v-for="z in zones" :key="z.id" :value="z.id">
                {{ z.libelle }}
              </option>
            </select>
            <p class="text-xs text-gray-500 mt-1">Optionnel - utilise votre zone par défaut si non spécifié</p>
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
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Description de la demande</h2>
        <div class="space-y-4">
          <div>
            <label class="label">Objet <span class="text-red-500">*</span></label>
            <input
              v-model="form.objet"
              type="text"
              class="input"
              placeholder="Décrivez brièvement votre demande..."
              maxlength="500"
              required
            />
            <p class="text-xs text-gray-500 mt-1">{{ form.objet.length }}/500 caractères</p>
            <p v-if="errors.objet" class="text-red-500 text-sm mt-1">{{ errors.objet[0] }}</p>
          </div>

          <div>
            <label class="label">Description complémentaire</label>
            <textarea
              v-model="form.description"
              class="input"
              rows="3"
              placeholder="Informations complémentaires sur la demande..."
            ></textarea>
          </div>
        </div>
      </div>

      <!-- Lignes de la demande -->
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
                  required
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
                <label class="label text-xs">Prix unitaire (FCFA)</label>
                <input
                  v-model.number="ligne.prix_unitaire"
                  type="number"
                  class="input text-sm"
                  min="0"
                  step="1"
                />
              </div>
              <div>
                <label class="label text-xs">Montant</label>
                <div class="input text-sm bg-gray-100 text-gray-700">
                  {{ formatMontant(getMontantLigne(ligne)) }}
                </div>
              </div>
            </div>

            <div class="mt-3">
              <label class="label text-xs">Description de la ligne</label>
              <input
                v-model="ligne.description"
                type="text"
                class="input text-sm"
                placeholder="Détails supplémentaires..."
              />
            </div>
          </div>
        </div>

        <!-- Total -->
        <div class="mt-4 pt-4 border-t border-gray-200 flex justify-end">
          <div class="text-right">
            <p class="text-sm text-gray-600">Montant total estimé</p>
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
          {{ submitting ? 'Création...' : 'Créer la demande d\'achat' }}
        </button>
      </div>
    </form>
  </div>
</template>
