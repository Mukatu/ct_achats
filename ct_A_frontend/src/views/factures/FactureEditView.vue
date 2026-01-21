<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { factureService, referentielService } from '@/services/api'
import { formatMontant, type Facture } from '@/types'
import {
  ArrowLeftIcon,
  CheckIcon,
  PlusIcon,
  TrashIcon,
} from '@heroicons/vue/24/outline'

const router = useRouter()
const route = useRoute()
const loading = ref(true)
const submitting = ref(false)
const errors = ref<Record<string, string[]>>({})
const data = ref<Facture | null>(null)

// Referentiels
const typesFacture = ref<any[]>([])

const TAUX_TVA_CONGO = 18

// Formulaire
const form = ref({
  type_facture: 'FACTURE',
  numero_fournisseur: '',
  date_facture: '',
  date_reception: '',
  date_echeance: '',
  taux_tva: TAUX_TVA_CONGO,
  retenue_source: 0,
  commentaire: '',
})

// Lignes de facture
interface LigneFacture {
  id: string
  designation: string
  quantite: number
  prix_unitaire_ht: number
  remise_percent: number
  taux_tva: number
  reference_fournisseur: string
  commentaire: string
}

const lignes = ref<LigneFacture[]>([])

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

async function loadData() {
  loading.value = true
  try {
    const [factureRes, typesRes] = await Promise.all([
      factureService.get(route.params.id as string),
      referentielService.getTypesFacture(),
    ])

    data.value = factureRes.data.data || factureRes.data
    typesFacture.value = typesRes.data

    // Remplir le formulaire
    form.value = {
      type_facture: data.value.type_facture || 'FACTURE',
      numero_fournisseur: data.value.numero_fournisseur || '',
      date_facture: data.value.date_facture?.split('T')[0] || '',
      date_reception: data.value.date_reception?.split('T')[0] || '',
      date_echeance: data.value.date_echeance?.split('T')[0] || '',
      taux_tva: data.value.taux_tva || TAUX_TVA_CONGO,
      retenue_source: data.value.retenue_source || 0,
      commentaire: data.value.commentaire || '',
    }

    // Remplir les lignes
    lignes.value = (data.value.lignes || []).map((l: any) => ({
      id: l.id,
      designation: l.designation || '',
      quantite: l.quantite || 0,
      prix_unitaire_ht: l.prix_unitaire_ht || 0,
      remise_percent: l.remise_percent || 0,
      taux_tva: l.taux_tva || TAUX_TVA_CONGO,
      reference_fournisseur: l.reference_fournisseur || '',
      commentaire: l.commentaire || '',
    }))

    if (lignes.value.length === 0) {
      lignes.value.push({
        id: '',
        designation: '',
        quantite: 1,
        prix_unitaire_ht: 0,
        remise_percent: 0,
        taux_tva: TAUX_TVA_CONGO,
        reference_fournisseur: '',
        commentaire: '',
      })
    }
  } catch (error) {
    console.error('Erreur chargement facture:', error)
    router.push('/factures')
  } finally {
    loading.value = false
  }
}

function addLigne() {
  lignes.value.push({
    id: '',
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
      montant_ht: montantHT.value,
      lignes: validLignes.map((l, index) => ({
        id: l.id || undefined,
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

    await factureService.update(route.params.id as string, payload)

    router.push({
      name: 'facture-show',
      params: { id: route.params.id },
    })
  } catch (error: any) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {}
      if (error.response.data.message) {
        alert(error.response.data.message)
      }
    } else {
      console.error('Erreur modification facture:', error)
      alert('Une erreur est survenue lors de la modification')
    }
  } finally {
    submitting.value = false
  }
}

function goBack() {
  router.push(`/factures/${route.params.id}`)
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
        <h1 class="text-2xl font-bold text-gray-900">Modifier la Facture</h1>
        <p v-if="data" class="text-gray-600 mt-1">{{ data.numero_interne }}</p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-ct-blue-500"></div>
    </div>

    <!-- Form -->
    <form v-else @submit.prevent="submit" class="space-y-6">
      <!-- Fournisseur (lecture seule) -->
      <div v-if="data?.fournisseur" class="card bg-blue-50 border-blue-200">
        <h2 class="text-sm font-medium text-blue-600 mb-2">Fournisseur</h2>
        <p class="font-semibold text-blue-900">{{ data.fournisseur.raison_sociale }}</p>
      </div>

      <!-- Informations generales -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Informations generales</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="label">Type <span class="text-red-500">*</span></label>
            <select v-model="form.type_facture" class="input" required>
              <option v-for="t in typesFacture" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
          </div>

          <div>
            <label class="label">N Facture fournisseur <span class="text-red-500">*</span></label>
            <input v-model="form.numero_fournisseur" type="text" class="input" required />
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
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
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
          {{ submitting ? 'Enregistrement...' : 'Enregistrer les modifications' }}
        </button>
      </div>
    </form>
  </div>
</template>
