<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { bcService, referentielService } from '@/services/api'
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
const fournisseurs = ref<any[]>([])
const unitesMesure = ref<any[]>([])
const typesBC = ref<any[]>([])
const conditionsPaiement = ref<any[]>([])

const TAUX_TVA_CONGO = 18

// Formulaire
const form = ref({
  type_bc: 'BCAL',
  date_bc: '',
  fournisseur_id: '',
  zone_id: '',
  direction_id: '',
  objet: '',
  nature_prestation: '',
  conditions_paiement: '',
  date_livraison_prevue: '',
  taux_tva: TAUX_TVA_CONGO,
})

// Lignes du BC
interface LigneBC {
  id?: string
  designation: string
  description: string
  quantite: number
  unite_mesure_id: string
  prix_unitaire_xaf: number
}

const lignes = ref<LigneBC[]>([])

// Filtrer les directions par zone
const filteredDirections = computed(() => {
  if (!form.value.zone_id) return directions.value
  return directions.value.filter((d: any) => d.zone_id === form.value.zone_id)
})

// Calculer les montants
const montantHT = computed(() => {
  return lignes.value.reduce((total, ligne) => {
    return total + (ligne.quantite * ligne.prix_unitaire_xaf)
  }, 0)
})

const montantTVA = computed(() => {
  return Math.round(montantHT.value * form.value.taux_tva / 100)
})

const montantTTC = computed(() => {
  return montantHT.value + montantTVA.value
})

async function loadData() {
  loading.value = true
  try {
    const [bcRes, zonesRes, directionsRes, fournisseursRes, unitesRes, typesRes, conditionsRes] = await Promise.all([
      bcService.get(route.params.id as string),
      referentielService.getZones(),
      referentielService.getDirections(),
      referentielService.getFournisseurs(),
      referentielService.getUnitesMesure(),
      referentielService.getTypesBC(),
      referentielService.getConditionsPaiement(),
    ])

    zones.value = zonesRes.data
    directions.value = directionsRes.data
    fournisseurs.value = fournisseursRes.data
    unitesMesure.value = unitesRes.data
    typesBC.value = typesRes.data
    conditionsPaiement.value = conditionsRes.data

    const bc = bcRes.data.data || bcRes.data
    form.value = {
      type_bc: bc.type_bc || 'BCAL',
      date_bc: bc.date_bc || '',
      fournisseur_id: bc.fournisseur_id || '',
      zone_id: bc.zone_id || '',
      direction_id: bc.direction_id || '',
      objet: bc.objet || '',
      nature_prestation: bc.nature_prestation || '',
      conditions_paiement: bc.conditions_paiement || '',
      date_livraison_prevue: bc.date_livraison_prevue || '',
      taux_tva: bc.taux_tva ?? TAUX_TVA_CONGO,
    }

    if (bc.lignes && bc.lignes.length > 0) {
      lignes.value = bc.lignes.map((l: any) => ({
        id: l.id,
        designation: l.designation || '',
        description: l.description || '',
        quantite: l.quantite || 1,
        unite_mesure_id: l.unite_mesure_id || '',
        prix_unitaire_xaf: l.prix_unitaire_xaf || 0,
      }))
    } else {
      lignes.value = [{ designation: '', description: '', quantite: 1, unite_mesure_id: '', prix_unitaire_xaf: 0 }]
    }
  } catch (error) {
    console.error('Erreur chargement BC:', error)
    router.push('/bons-commande')
  } finally {
    loading.value = false
  }
}

function onZoneChange() {
  form.value.direction_id = ''
}

function addLigne() {
  lignes.value.push({ designation: '', description: '', quantite: 1, unite_mesure_id: '', prix_unitaire_xaf: 0 })
}

function removeLigne(index: number) {
  if (lignes.value.length > 1) {
    lignes.value.splice(index, 1)
  }
}

function getMontantLigne(ligne: LigneBC): number {
  return ligne.quantite * ligne.prix_unitaire_xaf
}

async function submit() {
  errors.value = {}

  if (!form.value.fournisseur_id) {
    errors.value.fournisseur_id = ['Le fournisseur est obligatoire']
    return
  }

  const validLignes = lignes.value.filter(l => l.designation.trim() !== '')
  if (validLignes.length === 0) {
    errors.value.lignes = ['Ajoutez au moins une ligne avec une désignation']
    return
  }

  submitting.value = true

  try {
    const payload = {
      ...form.value,
      montant_ht_xaf: montantHT.value,
      montant_tva: montantTVA.value,
      montant_ttc_xaf: montantTTC.value,
      lignes: validLignes.map((l, index) => ({
        id: l.id || undefined,
        numero_ligne: index + 1,
        designation: l.designation,
        description: l.description || null,
        quantite: l.quantite,
        unite_mesure_id: l.unite_mesure_id || null,
        prix_unitaire_xaf: l.prix_unitaire_xaf,
        montant_xaf: l.quantite * l.prix_unitaire_xaf,
      })),
    }

    await bcService.update(route.params.id as string, payload)

    router.push({
      name: 'bc-show',
      params: { id: route.params.id },
    })
  } catch (error: any) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {}
    } else {
      console.error('Erreur modification BC:', error)
      alert('Une erreur est survenue lors de la modification')
    }
  } finally {
    submitting.value = false
  }
}

function goBack() {
  router.push({ name: 'bc-show', params: { id: route.params.id } })
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
        <h1 class="text-2xl font-bold text-gray-900">Modifier le Bon de Commande</h1>
        <p class="text-gray-600 mt-1">Modifiez les informations de ce bon de commande</p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-ct-blue-500"></div>
    </div>

    <!-- Form -->
    <form v-else @submit.prevent="submit" class="space-y-6">
      <!-- Type et Date -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Informations générales</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="label">Type BC <span class="text-red-500">*</span></label>
            <select v-model="form.type_bc" class="input" required>
              <option v-for="t in typesBC" :key="t.value" :value="t.value">
                {{ t.label }}
              </option>
              <option v-if="typesBC.length === 0" value="BCAL">BCAL</option>
              <option v-if="typesBC.length === 0" value="BCAI">BCAI</option>
              <option v-if="typesBC.length === 0" value="IPO">IPO</option>
            </select>
          </div>

          <div>
            <label class="label">Date BC <span class="text-red-500">*</span></label>
            <input v-model="form.date_bc" type="date" class="input" required />
          </div>
        </div>
      </div>

      <!-- Fournisseur -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Fournisseur</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="label">Fournisseur <span class="text-red-500">*</span></label>
            <select v-model="form.fournisseur_id" class="input" required>
              <option value="">Sélectionner un fournisseur</option>
              <option v-for="f in fournisseurs" :key="f.value" :value="f.value">
                {{ f.label }}
              </option>
            </select>
            <p v-if="errors.fournisseur_id" class="text-red-500 text-sm mt-1">{{ errors.fournisseur_id[0] }}</p>
          </div>

          <div>
            <label class="label">Conditions de paiement</label>
            <select v-model="form.conditions_paiement" class="input">
              <option value="">Sélectionner</option>
              <option v-for="c in conditionsPaiement" :key="c.value" :value="c.value">
                {{ c.label }}
              </option>
            </select>
          </div>
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
          </div>

          <div>
            <label class="label">Direction <span class="text-red-500">*</span></label>
            <select v-model="form.direction_id" class="input" required>
              <option value="">Sélectionner une direction</option>
              <option v-for="d in filteredDirections" :key="d.id" :value="d.id">
                {{ d.libelle_court || d.libelle }}
              </option>
            </select>
          </div>

          <div>
            <label class="label">Date livraison prévue</label>
            <input v-model="form.date_livraison_prevue" type="date" class="input" />
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
              placeholder="Objet du bon de commande..."
              required
            />
            <p v-if="errors.objet" class="text-red-500 text-sm mt-1">{{ errors.objet[0] }}</p>
          </div>

          <div>
            <label class="label">Nature de la prestation</label>
            <textarea
              v-model="form.nature_prestation"
              class="input"
              rows="2"
              placeholder="Travaux, fournitures, services..."
            ></textarea>
          </div>
        </div>
      </div>

      <!-- Lignes -->
      <div class="card">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-gray-900">Lignes de commande</h2>
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
                  step="0.01"
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
                <label class="label text-xs">Prix unit. (FCFA)</label>
                <input
                  v-model.number="ligne.prix_unitaire_xaf"
                  type="number"
                  class="input text-sm"
                  min="0"
                />
              </div>
              <div>
                <label class="label text-xs">Montant HT</label>
                <div class="input text-sm bg-gray-100 text-gray-700">
                  {{ formatMontant(getMontantLigne(ligne)) }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Récapitulatif -->
        <div class="mt-6 pt-4 border-t border-gray-200">
          <div class="flex justify-end">
            <div class="w-80 space-y-2">
              <div class="flex justify-between text-sm">
                <span class="text-gray-600">Total HT</span>
                <span class="font-medium">{{ formatMontant(montantHT) }}</span>
              </div>
              <div class="flex justify-between items-center text-sm">
                <div class="flex items-center gap-2">
                  <span class="text-gray-600">TVA</span>
                  <input
                    v-model.number="form.taux_tva"
                    type="number"
                    class="w-16 px-2 py-1 text-xs border border-gray-300 rounded"
                    min="0"
                    max="100"
                  />
                  <span class="text-gray-500 text-xs">%</span>
                </div>
                <span class="font-medium">{{ formatMontant(montantTVA) }}</span>
              </div>
              <div class="flex justify-between pt-2 border-t border-gray-300">
                <span class="text-lg font-semibold">Total TTC</span>
                <span class="text-xl font-bold text-ct-blue-600">{{ formatMontant(montantTTC) }}</span>
              </div>
            </div>
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
