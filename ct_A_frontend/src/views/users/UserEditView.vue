<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { userService, referentielService } from '@/services/api'
import {
  ArrowLeftIcon,
  CheckIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const services = ref<{ value: string; label: string }[]>([])

const form = ref({
  matricule: '',
  nom: '',
  prenom: '',
  email: '',
  password: '',
  password_confirmation: '',
  telephone: '',
  service_id: '',
  actif: true,
  est_acheteur: false,
  est_valideur: false,
  seuil_validation: null as number | null,
})

async function loadData() {
  loading.value = true
  try {
    const [userResponse, servicesResponse] = await Promise.all([
      userService.get(route.params.id as string),
      referentielService.getServices(),
    ])

    const user = userResponse.data.data || userResponse.data
    services.value = servicesResponse.data.data || servicesResponse.data

    form.value = {
      matricule: user.matricule || '',
      nom: user.nom || '',
      prenom: user.prenom || '',
      email: user.email || '',
      password: '',
      password_confirmation: '',
      telephone: user.telephone || '',
      service_id: user.service_id || user.service?.id || '',
      actif: user.actif ?? true,
      est_acheteur: user.est_acheteur ?? false,
      est_valideur: user.est_valideur ?? false,
      seuil_validation: user.seuil_validation || null,
    }
  } catch (error) {
    console.error('Erreur chargement utilisateur:', error)
  } finally {
    loading.value = false
  }
}

async function submitForm() {
  saving.value = true
  errors.value = {}

  try {
    const data: any = { ...form.value }

    // Ne pas envoyer le mot de passe si vide
    if (!data.password) {
      delete data.password
      delete data.password_confirmation
    }

    if (!data.seuil_validation) {
      delete data.seuil_validation
    }

    await userService.update(route.params.id as string, data)
    router.push(`/utilisateurs/${route.params.id}`)
  } catch (error: any) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {}
    } else {
      console.error('Erreur modification utilisateur:', error)
    }
  } finally {
    saving.value = false
  }
}

function goBack() {
  router.push(`/utilisateurs/${route.params.id}`)
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div>
    <div class="flex items-center gap-4 mb-6">
      <button @click="goBack" class="p-2 hover:bg-gray-100 rounded-lg">
        <ArrowLeftIcon class="w-5 h-5" />
      </button>
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Modifier l'utilisateur</h1>
        <p class="text-gray-600 mt-1">Modifier les informations de l'utilisateur</p>
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-ct-blue-500"></div>
    </div>

    <form v-else @submit.prevent="submitForm" class="space-y-6">
      <!-- Informations personnelles -->
      <div class="card">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Informations personnelles</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="label">Matricule</label>
            <input v-model="form.matricule" type="text" class="input" placeholder="MAT001" />
            <p v-if="errors.matricule" class="text-red-500 text-sm mt-1">{{ errors.matricule[0] }}</p>
          </div>
          <div></div>
          <div>
            <label class="label">Nom <span class="text-red-500">*</span></label>
            <input v-model="form.nom" type="text" class="input" required />
            <p v-if="errors.nom" class="text-red-500 text-sm mt-1">{{ errors.nom[0] }}</p>
          </div>
          <div>
            <label class="label">Prenom <span class="text-red-500">*</span></label>
            <input v-model="form.prenom" type="text" class="input" required />
            <p v-if="errors.prenom" class="text-red-500 text-sm mt-1">{{ errors.prenom[0] }}</p>
          </div>
          <div>
            <label class="label">Email <span class="text-red-500">*</span></label>
            <input v-model="form.email" type="email" class="input" required />
            <p v-if="errors.email" class="text-red-500 text-sm mt-1">{{ errors.email[0] }}</p>
          </div>
          <div>
            <label class="label">Telephone</label>
            <input v-model="form.telephone" type="tel" class="input" placeholder="+237 6XX XXX XXX" />
            <p v-if="errors.telephone" class="text-red-500 text-sm mt-1">{{ errors.telephone[0] }}</p>
          </div>
        </div>
      </div>

      <!-- Mot de passe -->
      <div class="card">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Mot de passe</h3>
        <p class="text-sm text-gray-500 mb-4">Laissez vide pour conserver le mot de passe actuel</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="label">Nouveau mot de passe</label>
            <input v-model="form.password" type="password" class="input" />
            <p v-if="errors.password" class="text-red-500 text-sm mt-1">{{ errors.password[0] }}</p>
          </div>
          <div>
            <label class="label">Confirmer le mot de passe</label>
            <input v-model="form.password_confirmation" type="password" class="input" />
          </div>
        </div>
      </div>

      <!-- Rattachement organisationnel -->
      <div class="card">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Rattachement organisationnel</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="label">Service</label>
            <select v-model="form.service_id" class="input">
              <option value="">-- Selectionner un service --</option>
              <option v-for="s in services" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
            <p v-if="errors.service_id" class="text-red-500 text-sm mt-1">{{ errors.service_id[0] }}</p>
          </div>
        </div>
      </div>

      <!-- Droits et permissions -->
      <div class="card">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Droits et permissions</h3>
        <div class="space-y-4">
          <div class="flex items-center gap-6">
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="form.actif" type="checkbox" class="w-4 h-4 text-ct-blue-600 rounded" />
              <span>Compte actif</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="form.est_acheteur" type="checkbox" class="w-4 h-4 text-ct-blue-600 rounded" />
              <span>Acheteur</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="form.est_valideur" type="checkbox" class="w-4 h-4 text-ct-blue-600 rounded" />
              <span>Valideur</span>
            </label>
          </div>

          <div v-if="form.est_valideur" class="mt-4">
            <label class="label">Seuil de validation (XAF)</label>
            <input v-model.number="form.seuil_validation" type="number" class="input w-64" placeholder="5000000" />
            <p class="text-sm text-gray-500 mt-1">Montant maximum que ce valideur peut approuver</p>
            <p v-if="errors.seuil_validation" class="text-red-500 text-sm mt-1">{{ errors.seuil_validation[0] }}</p>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-4">
        <button type="button" @click="goBack" class="btn-secondary">Annuler</button>
        <button type="submit" :disabled="saving" class="btn-primary inline-flex items-center">
          <CheckIcon class="w-5 h-5 mr-2" />
          {{ saving ? 'Enregistrement...' : 'Enregistrer les modifications' }}
        </button>
      </div>
    </form>
  </div>
</template>
