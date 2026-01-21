<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { bcService, daService, referentielService } from '@/services/api'
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
const fournisseurs = ref<any[]>([])
const demandesAchat = ref<any[]>([])
const unitesMesure = ref<any[]>([])
const typesBC = ref<any[]>([])
const conditionsPaiement = ref<any[]>([])

const TAUX_TVA_CONGO = 18

// Formulaire
const form = ref({
  type_bc: 'BCAL',
  date_bc: new Date().toISOString().split('T')[0],
  fournisseur_id: '',
  zone_id: '',
  direction_id: '',
  demande_achat_id: '',
  objet: '',
  nature_prestation: '',
  conditions_paiement: '',
  date_livraison_prevue: '',
  taux_tva: TAUX_TVA_CONGO,
})

// Lignes du bon de commande
interface LigneBC {
  designation: string
  description: string
  quantite: number
  unite_mesure_id: string
  prix_unitaire_xaf: number
}

const lignes = ref<LigneBC[]>([
  { designation: '', description: '', quantite: 1, unite_mesure_id: '', prix_unitaire_xaf: 0 },
])

// Les directions sont centrales - pas de filtrage par zone
const filteredDirections = computed(() => {
  return directions.value
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

async function loadReferentiels() {
  loading.value = true
  try {
    const [zonesRes, directionsRes, fournisseursRes, unitesRes, typesRes, conditionsRes, daRes] = await Promise.all([
      referentielService.getZones(),
      referentielService.getDirections(),
      referentielService.getFournisseurs(),
      referentielService.getUnitesMesure(),
      referentielService.getTypesBC(),
      referentielService.getConditionsPaiement(),
      daService.getAll({ statut: 'TRAITE', per_page: 100 }),
    ])
    zones.value = zonesRes.data
    directions.value = directionsRes.data
    fournisseurs.value = fournisseursRes.data
    unitesMesure.value = unitesRes.data
    typesBC.value = typesRes.data
    conditionsPaiement.value = conditionsRes.data
    demandesAchat.value = daRes.data?.data || []
  } catch (error) {
    console.error('Erreur chargement référentiels:', error)
  } finally {
    loading.value = false
  }
}

function onZoneChange() {
  // Zone est optionnelle, pas besoin de réinitialiser les autres champs
}

function onDAChange() {
  const da = demandesAchat.value.find((d: any) => d.id === form.value.demande_achat_id)
  if (da) {
    form.value.zone_id = da.zone_id || ''
    form.value.direction_id = da.direction_id || ''
    form.value.objet = da.objet || ''
    // Reprendre les lignes de la DA
    if (da.lignes && da.lignes.length > 0) {
      lignes.value = da.lignes.map((l: any) => ({
        designation: l.designation,
        description: l.description || '',
        quantite: l.quantite,
        unite_mesure_id: l.unite_mesure_id || '',
        prix_unitaire_xaf: l.prix_unitaire || 0,
      }))
    }
  }
}

function onFournisseurChange() {
  const fournisseur = fournisseurs.value.find((f: any) => f.value === form.value.fournisseur_id)
  if (fournisseur && fournisseur.taux_tva !== undefined) {
    form.value.taux_tva = fournisseur.taux_tva
  }
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

  // Validation basique
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
      zone_id: form.value.zone_id || null,
      demande_achat_id: form.value.demande_achat_id || null,
      montant_ht_xaf: montantHT.value,
      montant_tva: montantTVA.value,
      montant_ttc_xaf: montantTTC.value,
      lignes: validLignes.map((l, index) => ({
        numero_ligne: index + 1,
        designation: l.designation,
        description: l.description || null,
        quantite: l.quantite,
        unite_mesure_id: l.unite_mesure_id || null,
        prix_unitaire_xaf: l.prix_unitaire_xaf,
        montant_xaf: l.quantite * l.prix_unitaire_xaf,
      })),
    }

    const response = await bcService.create(payload)

    router.push({
      name: 'bc-show',
      params: { id: response.data.data.id },
    })
  } catch (error: any) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {}
    } else {
      console.error('Erreur création BC:', error)
      alert('Une erreur est survenue lors de la création')
    }
  } finally {
    submitting.value = false
  }
}

function goBack() {
  router.push('/bons-commande')
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
        <h1 class="text-2xl font-bold text-gray-900">Nouveau Bon de Commande</h1>
        <p class="text-gray-600 mt-1">Créez un bon de commande (BCAL, BCAI, IPO)</p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-ct-blue-500"></div>
    </div>

    <!-- Form -->
    <form v-else @submit.prevent="submit" class="space-y-6">
      <!-- Type et DA source -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Type de bon de commande</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="label">Type BC <span class="text-red-500">*</span></label>
            <select v-model="form.type_bc" class="input" required>
              <option v-for="t in typesBC" :key="t.value" :value="t.value">
                {{ t.label }}
              </option>
              <option v-if="typesBC.length === 0" value="BCAL">BCAL - Bon de Commande Achat Local</option>
              <option v-if="typesBC.length === 0" value="BCAI">BCAI - Bon de Commande Achat International</option>
              <option v-if="typesBC.length === 0" value="IPO">IPO - Imputation Provisionnelle</option>
            </select>
          </div>

          <div>
            <label class="label">Date BC <span class="text-red-500">*</span></label>
            <input v-model="form.date_bc" type="date" class="input" required />
          </div>

          <div>
            <label class="label">Lier à une Demande d'Achat</label>
            <select v-model="form.demande_achat_id" @change="onDAChange" class="input">
              <option value="">Aucune (création directe)</option>
              <option v-for="da in demandesAchat" :key="da.id" :value="da.id">
                {{ da.numero }} - {{ da.objet?.substring(0, 40) }}...
              </option>
            </select>
          </div>
        </div>
      </div>

      <!-- Fournisseur -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Fournisseur</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="label">Fournisseur <span class="text-red-500">*</span></label>
            <select v-model="form.fournisseur_id" @change="onFournisseurChange" class="input" required>
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
              <option v-if="conditionsPaiement.length === 0" value="COMPTANT">Comptant</option>
              <option v-if="conditionsPaiement.length === 0" value="30_JOURS">30 jours</option>
              <option v-if="conditionsPaiement.length === 0" value="60_JOURS">60 jours</option>
              <option v-if="conditionsPaiement.length === 0" value="90_JOURS">90 jours</option>
            </select>
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
            <p class="text-xs text-gray-500 mt-1">Optionnel</p>
            <p v-if="errors.zone_id" class="text-red-500 text-sm mt-1">{{ errors.zone_id[0] }}</p>
          </div>

          <div>
            <label class="label">Direction <span class="text-red-500">*</span></label>
            <select v-model="form.direction_id" class="input" required>
              <option value="">Sélectionner une direction</option>
              <option v-for="d in filteredDirections" :key="d.id" :value="d.id">
                {{ d.libelle_court || d.libelle }}
              </option>
            </select>
            <p v-if="errors.direction_id" class="text-red-500 text-sm mt-1">{{ errors.direction_id[0] }}</p>
          </div>

          <div>
            <label class="label">Date de livraison prévue</label>
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
              maxlength="500"
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

      <!-- Lignes du bon de commande -->
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
                <label class="label text-xs">Prix unitaire (FCFA)</label>
                <input
                  v-model.number="ligne.prix_unitaire_xaf"
                  type="number"
                  class="input text-sm"
                  min="0"
                  step="1"
                />
              </div>
              <div>
                <label class="label text-xs">Montant HT</label>
                <div class="input text-sm bg-gray-100 text-gray-700">
                  {{ formatMontant(getMontantLigne(ligne)) }}
                </div>
              </div>
            </div>

            <div class="mt-3">
              <label class="label text-xs">Description</label>
              <input
                v-model="ligne.description"
                type="text"
                class="input text-sm"
                placeholder="Spécifications, références..."
              />
            </div>
          </div>
        </div>

        <!-- Récapitulatif TVA et Total -->
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
                    step="0.5"
                  />
                  <span class="text-gray-500 text-xs">%</span>
                </div>
                <span class="font-medium">{{ formatMontant(montantTVA) }}</span>
              </div>
              <div class="flex justify-between pt-2 border-t border-gray-300">
                <span class="text-lg font-semibold text-gray-900">Total TTC</span>
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
          {{ submitting ? 'Création...' : 'Créer le bon de commande' }}
        </button>
      </div>
    </form>
  </div>
</template>
