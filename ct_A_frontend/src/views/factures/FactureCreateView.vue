<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { factureService, referentielService, bcService } from '@/services/api'
import { formatMontant } from '@/types'
import {
  ArrowLeftIcon,
  CheckIcon,
  PlusIcon,
  TrashIcon,
  MagnifyingGlassIcon,
} from '@heroicons/vue/24/outline'

const router = useRouter()
const route = useRoute()
const loading = ref(false)
const submitting = ref(false)
const errors = ref<Record<string, string[]>>({})

// Referentiels
const fournisseurs = ref<any[]>([])
const typesFacture = ref<any[]>([])
const bonsCommande = ref<any[]>([])

const TAUX_TVA_CONGO = 18

// Formulaire
const form = ref({
  type_facture: 'FACTURE',
  fournisseur_id: '',
  bon_commande_id: '',
  numero_fournisseur: '',
  date_facture: new Date().toISOString().split('T')[0],
  date_reception: new Date().toISOString().split('T')[0],
  date_echeance: '',
  montant_ht: 0,
  taux_tva: TAUX_TVA_CONGO,
  retenue_source: 0,
  commentaire: '',
})

// Lignes de facture
interface LigneFacture {
  designation: string
  quantite: number
  prix_unitaire_ht: number
  remise_percent: number
  taux_tva: number
  reference_fournisseur: string
  commentaire: string
}

const lignes = ref<LigneFacture[]>([
  { designation: '', quantite: 1, prix_unitaire_ht: 0, remise_percent: 0, taux_tva: TAUX_TVA_CONGO, reference_fournisseur: '', commentaire: '' },
])

// Calculs
const montantHT = computed(() => {
  return lignes.value.reduce((total, ligne) => {
    const montantBrut = ligne.quantite * ligne.prix_unitaire_ht
    const montantApresRemise = montantBrut * (1 - ligne.remise_percent / 100)
    return total + montantApresRemise
  }, 0)
})

const montantTVA = computed(() => {
  return lignes.value.reduce((total, ligne) => {
    const montantBrut = ligne.quantite * ligne.prix_unitaire_ht
    const montantApresRemise = montantBrut * (1 - ligne.remise_percent / 100)
    return total + Math.round(montantApresRemise * ligne.taux_tva / 100)
  }, 0)
})

const montantTTC = computed(() => montantHT.value + montantTVA.value)

const netAPayer = computed(() => montantTTC.value - (form.value.retenue_source || 0))

// Calculer la date d'echeance par defaut (30 jours)
watch(() => form.value.date_facture, (newDate) => {
  if (newDate && !form.value.date_echeance) {
    const date = new Date(newDate)
    date.setDate(date.getDate() + 30)
    form.value.date_echeance = date.toISOString().split('T')[0]
  }
})

async function loadReferentiels() {
  loading.value = true
  try {
    const [fournisseursRes, typesRes] = await Promise.all([
      referentielService.getFournisseurs(),
      referentielService.getTypesFacture(),
    ])
    fournisseurs.value = fournisseursRes.data
    typesFacture.value = typesRes.data
  } catch (error) {
    console.error('Erreur chargement referentiels:', error)
  } finally {
    loading.value = false
  }
}

// Charger les BC du fournisseur selectionne
async function onFournisseurChange() {
  if (!form.value.fournisseur_id) {
    bonsCommande.value = []
    return
  }

  try {
    const response = await bcService.getAll({
      fournisseur_id: form.value.fournisseur_id,
      statut: 'LIVRE',
      per_page: 100
    })
    bonsCommande.value = response.data?.data || []
  } catch (error) {
    console.error('Erreur chargement BC:', error)
  }
}

// Charger les lignes du BC selectionne
async function onBCChange() {
  if (!form.value.bon_commande_id) return

  try {
    const response = await bcService.get(form.value.bon_commande_id)
    const bc = response.data.data || response.data

    // Pre-remplir les lignes depuis le BC
    if (bc.lignes && bc.lignes.length > 0) {
      lignes.value = bc.lignes.map((l: any) => ({
        designation: l.designation,
        quantite: l.quantite,
        prix_unitaire_ht: l.prix_unitaire_xaf,
        remise_percent: 0,
        taux_tva: TAUX_TVA_CONGO,
        reference_fournisseur: '',
        commentaire: '',
      }))
    }
  } catch (error) {
    console.error('Erreur chargement BC:', error)
  }
}

function addLigne() {
  lignes.value.push({
    designation: '',
    quantite: 1,
    prix_unitaire_ht: 0,
    remise_percent: 0,
    taux_tva: TAUX_TVA_CONGO,
    reference_fournisseur: '',
    commentaire: '',
  })
}

function removeLigne(index: number) {
  if (lignes.value.length > 1) {
    lignes.value.splice(index, 1)
  }
}

function getMontantLigne(ligne: LigneFacture): number {
  const montantBrut = ligne.quantite * ligne.prix_unitaire_ht
  return montantBrut * (1 - ligne.remise_percent / 100)
}

async function submit() {
  errors.value = {}

  if (!form.value.fournisseur_id) {
    errors.value.fournisseur_id = ['Le fournisseur est obligatoire']
    return
  }

  if (!form.value.numero_fournisseur) {
    errors.value.numero_fournisseur = ['Le numero de facture fournisseur est obligatoire']
    return
  }

  const validLignes = lignes.value.filter(l => l.designation.trim() !== '')
  if (validLignes.length === 0) {
    errors.value.lignes = ['Ajoutez au moins une ligne avec une designation']
    return
  }

  submitting.value = true

  try {
    const payload = {
      ...form.value,
      bon_commande_id: form.value.bon_commande_id || null,
      montant_ht: montantHT.value,
      lignes: validLignes.map((l, index) => ({
        numero_ligne: index + 1,
        designation: l.designation,
        quantite: l.quantite,
        prix_unitaire_ht: l.prix_unitaire_ht,
        remise_percent: l.remise_percent,
        taux_tva: l.taux_tva,
        reference_fournisseur: l.reference_fournisseur || null,
        commentaire: l.commentaire || null,
      })),
    }

    const response = await factureService.create(payload)

    router.push({
      name: 'facture-show',
      params: { id: response.data.data.id },
    })
  } catch (error: any) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {}
    } else {
      console.error('Erreur creation facture:', error)
      alert('Une erreur est survenue lors de la creation')
    }
  } finally {
    submitting.value = false
  }
}

function goBack() {
  router.push('/factures')
}

onMounted(() => {
  loadReferentiels()

  // Calculer date echeance par defaut
  const date = new Date()
  date.setDate(date.getDate() + 30)
  form.value.date_echeance = date.toISOString().split('T')[0]
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
        <h1 class="text-2xl font-bold text-gray-900">Nouvelle Facture</h1>
        <p class="text-gray-600 mt-1">Enregistrez une facture fournisseur</p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-ct-blue-500"></div>
    </div>

    <!-- Form -->
    <form v-else @submit.prevent="submit" class="space-y-6">
      <!-- Type et Fournisseur -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Informations generales</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="label">Type <span class="text-red-500">*</span></label>
            <select v-model="form.type_facture" class="input" required>
              <option v-for="t in typesFacture" :key="t.value" :value="t.value">{{ t.label }}</option>
              <option v-if="typesFacture.length === 0" value="FACTURE">Facture</option>
              <option v-if="typesFacture.length === 0" value="AVOIR">Avoir</option>
              <option v-if="typesFacture.length === 0" value="ACOMPTE">Acompte</option>
            </select>
          </div>

          <div class="md:col-span-2">
            <label class="label">Fournisseur <span class="text-red-500">*</span></label>
            <select v-model="form.fournisseur_id" @change="onFournisseurChange" class="input" required>
              <option value="">Selectionner un fournisseur</option>
              <option v-for="f in fournisseurs" :key="f.value" :value="f.value">
                {{ f.label }}
              </option>
            </select>
            <p v-if="errors.fournisseur_id" class="text-red-500 text-sm mt-1">{{ errors.fournisseur_id[0] }}</p>
          </div>

          <div>
            <label class="label">Lier a un BC</label>
            <select v-model="form.bon_commande_id" @change="onBCChange" class="input">
              <option value="">Aucun</option>
              <option v-for="bc in bonsCommande" :key="bc.id" :value="bc.id">
                {{ bc.numero }}
              </option>
            </select>
          </div>
        </div>
      </div>

      <!-- Numeros et dates -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">References</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="label">N Facture fournisseur <span class="text-red-500">*</span></label>
            <input v-model="form.numero_fournisseur" type="text" class="input" placeholder="FAC-2024-001" required />
            <p v-if="errors.numero_fournisseur" class="text-red-500 text-sm mt-1">{{ errors.numero_fournisseur[0] }}</p>
          </div>

          <div>
            <label class="label">Date facture <span class="text-red-500">*</span></label>
            <input v-model="form.date_facture" type="date" class="input" required />
          </div>

          <div>
            <label class="label">Date reception <span class="text-red-500">*</span></label>
            <input v-model="form.date_reception" type="date" class="input" required />
          </div>

          <div>
            <label class="label">Date echeance <span class="text-red-500">*</span></label>
            <input v-model="form.date_echeance" type="date" class="input" required />
          </div>
        </div>
      </div>

      <!-- Lignes -->
      <div class="card">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-gray-900">Lignes de facture</h2>
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
                <label class="label text-xs">Designation <span class="text-red-500">*</span></label>
                <input
                  v-model="ligne.designation"
                  type="text"
                  class="input text-sm"
                  placeholder="Description..."
                />
              </div>
              <div>
                <label class="label text-xs">Quantite</label>
                <input
                  v-model.number="ligne.quantite"
                  type="number"
                  class="input text-sm"
                  min="0.01"
                  step="0.01"
                />
              </div>
              <div>
                <label class="label text-xs">Prix unit. HT</label>
                <input
                  v-model.number="ligne.prix_unitaire_ht"
                  type="number"
                  class="input text-sm"
                  min="0"
                  step="1"
                />
              </div>
              <div>
                <label class="label text-xs">Remise %</label>
                <input
                  v-model.number="ligne.remise_percent"
                  type="number"
                  class="input text-sm"
                  min="0"
                  max="100"
                  step="0.5"
                />
              </div>
              <div>
                <label class="label text-xs">Montant HT</label>
                <div class="input text-sm bg-gray-100 text-gray-700">
                  {{ formatMontant(getMontantLigne(ligne)) }}
                </div>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-3">
              <div>
                <label class="label text-xs">Ref. fournisseur</label>
                <input
                  v-model="ligne.reference_fournisseur"
                  type="text"
                  class="input text-sm"
                  placeholder="REF-001"
                />
              </div>
              <div>
                <label class="label text-xs">TVA %</label>
                <input
                  v-model.number="ligne.taux_tva"
                  type="number"
                  class="input text-sm"
                  min="0"
                  max="100"
                  step="0.5"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Recapitulatif -->
        <div class="mt-6 pt-4 border-t border-gray-200">
          <div class="flex justify-end">
            <div class="w-80 space-y-2">
              <div class="flex justify-between text-sm">
                <span class="text-gray-600">Total HT</span>
                <span class="font-medium">{{ formatMontant(montantHT) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-gray-600">Total TVA</span>
                <span class="font-medium">{{ formatMontant(montantTVA) }}</span>
              </div>
              <div class="flex justify-between text-sm pt-2 border-t border-gray-200">
                <span class="font-semibold">Total TTC</span>
                <span class="font-bold">{{ formatMontant(montantTTC) }}</span>
              </div>
              <div class="flex justify-between items-center text-sm">
                <div class="flex items-center gap-2">
                  <span class="text-gray-600">Retenue source</span>
                  <input
                    v-model.number="form.retenue_source"
                    type="number"
                    class="w-24 px-2 py-1 text-xs border border-gray-300 rounded"
                    min="0"
                    step="1"
                  />
                </div>
                <span class="font-medium text-red-600">-{{ formatMontant(form.retenue_source) }}</span>
              </div>
              <div class="flex justify-between pt-2 border-t border-gray-300">
                <span class="text-lg font-semibold text-gray-900">Net a payer</span>
                <span class="text-xl font-bold text-ct-blue-600">{{ formatMontant(netAPayer) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Commentaire -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Commentaire</h2>
        <textarea
          v-model="form.commentaire"
          class="input"
          rows="2"
          placeholder="Notes sur cette facture..."
        ></textarea>
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-4">
        <button type="button" @click="goBack" class="btn-secondary">
          Annuler
        </button>
        <button type="submit" :disabled="submitting" class="btn-primary inline-flex items-center">
          <span v-if="submitting" class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></span>
          <CheckIcon v-else class="w-5 h-5 mr-2" />
          {{ submitting ? 'Creation...' : 'Creer la facture' }}
        </button>
      </div>
    </form>
  </div>
</template>
