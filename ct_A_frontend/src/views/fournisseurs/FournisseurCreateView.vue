<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { fournisseurService } from '@/services/api'
import {
  ArrowLeftIcon,
  CheckIcon,
  BuildingOfficeIcon,
  GlobeAltIcon,
  MapPinIcon,
} from '@heroicons/vue/24/outline'

const router = useRouter()
const submitting = ref(false)
const errors = ref<Record<string, string[]>>({})

const TAUX_TVA_CONGO = 18

// Types de fournisseur
const typesFournisseur = [
  { value: 'LOCAL', label: 'Local (Congo)', description: 'Fournisseur basé au Congo', icon: MapPinIcon },
  { value: 'CEMAC', label: 'Zone CEMAC', description: 'Fournisseur de la zone CEMAC', icon: BuildingOfficeIcon },
  { value: 'INTERNATIONAL', label: 'International', description: 'Fournisseur hors zone CEMAC', icon: GlobeAltIcon },
]

// Pays courants
const paysListe = [
  'Congo',
  'Cameroun',
  'Gabon',
  'Tchad',
  'RCA',
  'Guinée Équatoriale',
  'France',
  'Belgique',
  'Chine',
  'États-Unis',
  'Maroc',
  'Côte d\'Ivoire',
  'Sénégal',
  'Autre',
]

// Formulaire
const form = ref({
  raison_sociale: '',
  sigle: '',
  niu: '',
  rccm: '',
  adresse: '',
  ville: '',
  pays: 'Congo',
  telephone: '',
  email: '',
  site_web: '',
  type_fournisseur: 'LOCAL',
  devise_defaut: 'XAF',
  taux_tva: TAUX_TVA_CONGO,
  compte_bancaire: '',
  banque: '',
  rib: '',
  commentaire: '',
})

// Ajuster le taux de TVA selon le type de fournisseur
function onTypeFournisseurChange() {
  if (form.value.type_fournisseur === 'INTERNATIONAL') {
    form.value.taux_tva = 0
    form.value.devise_defaut = 'EUR'
    form.value.pays = 'France'
  } else if (form.value.type_fournisseur === 'CEMAC') {
    form.value.taux_tva = TAUX_TVA_CONGO
    form.value.devise_defaut = 'XAF'
  } else {
    form.value.taux_tva = TAUX_TVA_CONGO
    form.value.devise_defaut = 'XAF'
    form.value.pays = 'Congo'
  }
}

async function submit() {
  errors.value = {}
  submitting.value = true

  try {
    const payload = {
      ...form.value,
      sigle: form.value.sigle || null,
      niu: form.value.niu || null,
      rccm: form.value.rccm || null,
      email: form.value.email || null,
      site_web: form.value.site_web || null,
      compte_bancaire: form.value.compte_bancaire || null,
      banque: form.value.banque || null,
      rib: form.value.rib || null,
      commentaire: form.value.commentaire || null,
    }

    const response = await fournisseurService.create(payload)
    const fournisseur = response.data.data || response.data

    router.push({
      name: 'fournisseur-show',
      params: { id: fournisseur.id },
    })
  } catch (error: any) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {}
    } else {
      console.error('Erreur création fournisseur:', error)
      alert('Une erreur est survenue lors de la création')
    }
  } finally {
    submitting.value = false
  }
}

function goBack() {
  router.push('/fournisseurs')
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="flex items-center gap-4 mb-6">
      <button @click="goBack" class="p-2 hover:bg-gray-100 rounded-lg">
        <ArrowLeftIcon class="w-5 h-5 text-gray-600" />
      </button>
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Nouveau Fournisseur</h1>
        <p class="text-gray-600 mt-1">Ajoutez un nouveau fournisseur au référentiel</p>
      </div>
    </div>

    <!-- Form -->
    <form @submit.prevent="submit" class="space-y-6">
      <!-- Type de fournisseur -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Type de fournisseur</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <label
            v-for="type in typesFournisseur"
            :key="type.value"
            class="relative flex items-start p-4 border rounded-lg cursor-pointer transition-all"
            :class="form.type_fournisseur === type.value ? 'border-ct-blue-500 bg-ct-blue-50' : 'border-gray-200 hover:border-gray-300'"
          >
            <input
              type="radio"
              v-model="form.type_fournisseur"
              :value="type.value"
              @change="onTypeFournisseurChange"
              class="sr-only"
            />
            <component :is="type.icon" class="w-6 h-6 text-gray-500 mr-3 flex-shrink-0" />
            <div>
              <p class="font-medium text-gray-900">{{ type.label }}</p>
              <p class="text-sm text-gray-500">{{ type.description }}</p>
            </div>
            <div
              v-if="form.type_fournisseur === type.value"
              class="absolute top-2 right-2 w-4 h-4 bg-ct-blue-500 rounded-full flex items-center justify-center"
            >
              <CheckIcon class="w-3 h-3 text-white" />
            </div>
          </label>
        </div>
      </div>

      <!-- Identification -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Identification</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="label">Raison sociale <span class="text-red-500">*</span></label>
            <input
              v-model="form.raison_sociale"
              type="text"
              class="input"
              placeholder="Nom complet de l'entreprise"
              required
            />
            <p v-if="errors.raison_sociale" class="text-red-500 text-sm mt-1">{{ errors.raison_sociale[0] }}</p>
          </div>

          <div>
            <label class="label">Sigle / Nom court</label>
            <input
              v-model="form.sigle"
              type="text"
              class="input"
              placeholder="Acronyme ou abréviation"
            />
          </div>

          <div>
            <label class="label">NIU (Numéro d'Identification Unique)</label>
            <input
              v-model="form.niu"
              type="text"
              class="input"
              placeholder="Ex: M202300001234X"
            />
            <p class="text-xs text-gray-500 mt-1">Identifiant fiscal congolais</p>
            <p v-if="errors.niu" class="text-red-500 text-sm mt-1">{{ errors.niu[0] }}</p>
          </div>

          <div>
            <label class="label">RCCM (Registre du Commerce)</label>
            <input
              v-model="form.rccm"
              type="text"
              class="input"
              placeholder="Ex: CG-BZV-01-2023-B12-00001"
            />
            <p class="text-xs text-gray-500 mt-1">Numéro d'immatriculation au registre du commerce</p>
            <p v-if="errors.rccm" class="text-red-500 text-sm mt-1">{{ errors.rccm[0] }}</p>
          </div>
        </div>
      </div>

      <!-- Coordonnées -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Coordonnées</h2>
        <div class="space-y-4">
          <div>
            <label class="label">Adresse</label>
            <textarea
              v-model="form.adresse"
              class="input"
              rows="2"
              placeholder="Adresse postale complète"
            ></textarea>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="label">Ville</label>
              <input
                v-model="form.ville"
                type="text"
                class="input"
                placeholder="Brazzaville, Pointe-Noire..."
              />
            </div>

            <div>
              <label class="label">Pays <span class="text-red-500">*</span></label>
              <select v-model="form.pays" class="input" required>
                <option v-for="pays in paysListe" :key="pays" :value="pays">{{ pays }}</option>
              </select>
            </div>

            <div>
              <label class="label">Téléphone</label>
              <input
                v-model="form.telephone"
                type="tel"
                class="input"
                placeholder="+242 06 XXX XX XX"
              />
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="label">Email</label>
              <input
                v-model="form.email"
                type="email"
                class="input"
                placeholder="contact@entreprise.cg"
              />
              <p v-if="errors.email" class="text-red-500 text-sm mt-1">{{ errors.email[0] }}</p>
            </div>

            <div>
              <label class="label">Site web</label>
              <input
                v-model="form.site_web"
                type="url"
                class="input"
                placeholder="https://www.entreprise.cg"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Informations fiscales et bancaires -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Informations fiscales et bancaires</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="label">Devise par défaut</label>
            <select v-model="form.devise_defaut" class="input">
              <option value="XAF">XAF - Franc CFA</option>
              <option value="EUR">EUR - Euro</option>
              <option value="USD">USD - Dollar US</option>
            </select>
          </div>

          <div>
            <label class="label">Taux TVA (%)</label>
            <input
              v-model.number="form.taux_tva"
              type="number"
              class="input"
              min="0"
              max="100"
              step="0.5"
            />
            <p class="text-xs text-gray-500 mt-1">TVA Congo: 18%</p>
          </div>

          <div>
            <label class="label">Banque</label>
            <input
              v-model="form.banque"
              type="text"
              class="input"
              placeholder="Nom de la banque"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
          <div>
            <label class="label">Numéro de compte bancaire</label>
            <input
              v-model="form.compte_bancaire"
              type="text"
              class="input"
              placeholder="Numéro de compte"
            />
          </div>

          <div>
            <label class="label">RIB / IBAN</label>
            <input
              v-model="form.rib"
              type="text"
              class="input"
              placeholder="RIB ou IBAN"
            />
          </div>
        </div>
      </div>

      <!-- Commentaire -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Notes</h2>
        <div>
          <label class="label">Commentaire</label>
          <textarea
            v-model="form.commentaire"
            class="input"
            rows="3"
            placeholder="Informations complémentaires sur le fournisseur..."
          ></textarea>
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
          {{ submitting ? 'Création...' : 'Créer le fournisseur' }}
        </button>
      </div>
    </form>
  </div>
</template>
