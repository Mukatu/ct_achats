<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { receptionService } from '@/services/api'
import { type Reception } from '@/types'
import {
  ArrowLeftIcon,
  CheckIcon,
} from '@heroicons/vue/24/outline'

const router = useRouter()
const route = useRoute()
const loading = ref(true)
const submitting = ref(false)
const errors = ref<Record<string, string[]>>({})
const data = ref<Reception | null>(null)

// Formulaire
const form = ref({
  date_reception: '',
  type_reception: 'LIVRAISON',
  lieu_reception: '',
  numero_bl_fournisseur: '',
  date_bl_fournisseur: '',
  numero_tracking: '',
  transporteur: '',
  commentaire: '',
})

// Lignes de reception
interface LigneReception {
  id: string
  ligne_bon_commande_id: string
  designation: string
  quantite_attendue: number
  quantite_recue: number
  quantite_conforme: number
  quantite_non_conforme: number
  quantite_refusee: number
  motif_non_conformite: string
  motif_refus: string
  numero_lot: string
  numero_serie: string
  commentaire: string
}

const lignes = ref<LigneReception[]>([])

async function loadData() {
  loading.value = true
  try {
    const response = await receptionService.get(route.params.id as string)
    data.value = response.data.data || response.data

    // Remplir le formulaire
    form.value = {
      date_reception: data.value.date_reception?.split('T')[0] || '',
      type_reception: data.value.type_reception || 'LIVRAISON',
      lieu_reception: data.value.lieu_reception || '',
      numero_bl_fournisseur: data.value.numero_bl_fournisseur || '',
      date_bl_fournisseur: data.value.date_bl_fournisseur?.split('T')[0] || '',
      numero_tracking: data.value.numero_tracking || '',
      transporteur: data.value.transporteur || '',
      commentaire: data.value.commentaire || '',
    }

    // Remplir les lignes
    lignes.value = (data.value.lignes || []).map((l: any) => ({
      id: l.id,
      ligne_bon_commande_id: l.ligne_bon_commande_id,
      designation: l.ligne_bon_commande?.designation || '',
      quantite_attendue: l.quantite_attendue || 0,
      quantite_recue: l.quantite_recue || 0,
      quantite_conforme: l.quantite_conforme || 0,
      quantite_non_conforme: l.quantite_non_conforme || 0,
      quantite_refusee: l.quantite_refusee || 0,
      motif_non_conformite: l.motif_non_conformite || '',
      motif_refus: l.motif_refus || '',
      numero_lot: l.numero_lot || '',
      numero_serie: l.numero_serie || '',
      commentaire: l.commentaire || '',
    }))
  } catch (error) {
    console.error('Erreur chargement BR:', error)
    router.push('/receptions')
  } finally {
    loading.value = false
  }
}

// Calculer quantite conforme automatiquement
function updateQuantiteConforme(ligne: LigneReception) {
  ligne.quantite_conforme = Math.max(0,
    ligne.quantite_recue - ligne.quantite_non_conforme - ligne.quantite_refusee
  )
}

async function submit() {
  errors.value = {}

  const validLignes = lignes.value.filter(l => l.quantite_recue > 0)
  if (validLignes.length === 0) {
    errors.value.lignes = ['Ajoutez au moins une ligne avec une quantite recue']
    return
  }

  submitting.value = true

  try {
    const payload = {
      ...form.value,
      lignes: validLignes.map(l => ({
        id: l.id,
        ligne_bon_commande_id: l.ligne_bon_commande_id,
        quantite_recue: l.quantite_recue,
        quantite_conforme: l.quantite_conforme,
        quantite_non_conforme: l.quantite_non_conforme,
        quantite_refusee: l.quantite_refusee,
        motif_non_conformite: l.motif_non_conformite || null,
        motif_refus: l.motif_refus || null,
        numero_lot: l.numero_lot || null,
        numero_serie: l.numero_serie || null,
        commentaire: l.commentaire || null,
      })),
    }

    await receptionService.update(route.params.id as string, payload)

    router.push({
      name: 'br-show',
      params: { id: route.params.id },
    })
  } catch (error: any) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {}
      if (error.response.data.message) {
        alert(error.response.data.message)
      }
    } else {
      console.error('Erreur modification BR:', error)
      alert('Une erreur est survenue lors de la modification')
    }
  } finally {
    submitting.value = false
  }
}

function goBack() {
  router.push(`/receptions/${route.params.id}`)
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
        <h1 class="text-2xl font-bold text-gray-900">Modifier la Reception</h1>
        <p v-if="data" class="text-gray-600 mt-1">{{ data.numero }}</p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-ct-blue-500"></div>
    </div>

    <!-- Form -->
    <form v-else @submit.prevent="submit" class="space-y-6">
      <!-- BC associe (lecture seule) -->
      <div v-if="data?.bon_commande" class="card bg-blue-50 border-blue-200">
        <h2 class="text-sm font-medium text-blue-600 mb-2">Bon de Commande</h2>
        <p class="font-semibold text-blue-900">{{ data.bon_commande.numero }}</p>
        <p class="text-sm text-blue-700">{{ data.bon_commande.fournisseur?.raison_sociale }}</p>
      </div>

      <!-- Informations de reception -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Informations de reception</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="label">Date de reception <span class="text-red-500">*</span></label>
            <input v-model="form.date_reception" type="date" class="input" required />
          </div>

          <div>
            <label class="label">Type de reception <span class="text-red-500">*</span></label>
            <select v-model="form.type_reception" class="input" required>
              <option value="LIVRAISON">Livraison</option>
              <option value="SERVICE_FAIT">Service fait</option>
              <option value="PARTIELLE">Partielle</option>
            </select>
          </div>

          <div>
            <label class="label">Lieu de reception</label>
            <input v-model="form.lieu_reception" type="text" class="input" placeholder="Magasin, entrepot..." />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4">
          <div>
            <label class="label">N BL Fournisseur</label>
            <input v-model="form.numero_bl_fournisseur" type="text" class="input" placeholder="N du bon de livraison" />
          </div>

          <div>
            <label class="label">Date BL</label>
            <input v-model="form.date_bl_fournisseur" type="date" class="input" />
          </div>

          <div>
            <label class="label">N Tracking</label>
            <input v-model="form.numero_tracking" type="text" class="input" placeholder="Numero de suivi" />
          </div>

          <div>
            <label class="label">Transporteur</label>
            <input v-model="form.transporteur" type="text" class="input" placeholder="Nom du transporteur" />
          </div>
        </div>

        <div class="mt-4">
          <label class="label">Commentaire</label>
          <textarea v-model="form.commentaire" class="input" rows="2" placeholder="Observations sur la reception..."></textarea>
        </div>
      </div>

      <!-- Lignes de reception -->
      <div v-if="lignes.length > 0" class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Articles receptiones</h2>

        <p v-if="errors.lignes" class="text-red-500 text-sm mb-4">{{ errors.lignes[0] }}</p>

        <div class="space-y-4">
          <div
            v-for="(ligne, index) in lignes"
            :key="ligne.id"
            class="border border-gray-200 rounded-lg p-4 bg-gray-50"
          >
            <div class="flex items-start justify-between mb-3">
              <div>
                <span class="font-medium text-gray-900">{{ ligne.designation }}</span>
                <span class="text-sm text-gray-500 ml-2">(Attendue: {{ ligne.quantite_attendue }})</span>
              </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
              <div>
                <label class="label text-xs">Qte recue</label>
                <input
                  v-model.number="ligne.quantite_recue"
                  type="number"
                  class="input text-sm"
                  min="0"
                  step="0.01"
                  @change="updateQuantiteConforme(ligne)"
                />
              </div>

              <div>
                <label class="label text-xs">Qte conforme</label>
                <input
                  v-model.number="ligne.quantite_conforme"
                  type="number"
                  class="input text-sm bg-green-50"
                  min="0"
                  step="0.01"
                  readonly
                />
              </div>

              <div>
                <label class="label text-xs">Qte non conforme</label>
                <input
                  v-model.number="ligne.quantite_non_conforme"
                  type="number"
                  class="input text-sm"
                  min="0"
                  step="0.01"
                  @change="updateQuantiteConforme(ligne)"
                />
              </div>

              <div>
                <label class="label text-xs">Qte refusee</label>
                <input
                  v-model.number="ligne.quantite_refusee"
                  type="number"
                  class="input text-sm"
                  min="0"
                  step="0.01"
                  @change="updateQuantiteConforme(ligne)"
                />
              </div>

              <div>
                <label class="label text-xs">N Lot</label>
                <input
                  v-model="ligne.numero_lot"
                  type="text"
                  class="input text-sm"
                  placeholder="Lot..."
                />
              </div>

              <div>
                <label class="label text-xs">N Serie</label>
                <input
                  v-model="ligne.numero_serie"
                  type="text"
                  class="input text-sm"
                  placeholder="Serie..."
                />
              </div>
            </div>

            <div v-if="ligne.quantite_non_conforme > 0" class="mt-3">
              <label class="label text-xs">Motif non-conformite</label>
              <input
                v-model="ligne.motif_non_conformite"
                type="text"
                class="input text-sm"
                placeholder="Expliquer la non-conformite..."
              />
            </div>

            <div v-if="ligne.quantite_refusee > 0" class="mt-3">
              <label class="label text-xs">Motif refus</label>
              <input
                v-model="ligne.motif_refus"
                type="text"
                class="input text-sm"
                placeholder="Expliquer le refus..."
              />
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
