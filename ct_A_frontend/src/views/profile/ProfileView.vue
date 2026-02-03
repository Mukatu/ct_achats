<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { profileService } from '@/services/api'
import {
  UserIcon,
  KeyIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'

const authStore = useAuthStore()

const profileForm = ref({
  nom: '',
  prenom: '',
  telephone: '',
})

const passwordForm = ref({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const profileErrors = ref<Record<string, string[]>>({})
const passwordErrors = ref<Record<string, string[]>>({})
const savingProfile = ref(false)
const savingPassword = ref(false)
const profileSuccess = ref(false)
const passwordSuccess = ref(false)

function loadProfile() {
  if (authStore.user) {
    profileForm.value = {
      nom: authStore.user.nom || '',
      prenom: authStore.user.prenom || '',
      telephone: (authStore.user as any).telephone || '',
    }
  }
}

async function saveProfile() {
  savingProfile.value = true
  profileErrors.value = {}
  profileSuccess.value = false
  try {
    await profileService.updateProfile(profileForm.value)
    await authStore.fetchUser()
    profileSuccess.value = true
    setTimeout(() => profileSuccess.value = false, 3000)
  } catch (error: any) {
    if (error.response?.status === 422) {
      profileErrors.value = error.response.data.errors || {}
    }
  } finally {
    savingProfile.value = false
  }
}

async function savePassword() {
  savingPassword.value = true
  passwordErrors.value = {}
  passwordSuccess.value = false
  try {
    await profileService.updatePassword(passwordForm.value)
    passwordSuccess.value = true
    passwordForm.value = { current_password: '', password: '', password_confirmation: '' }
    setTimeout(() => passwordSuccess.value = false, 3000)
  } catch (error: any) {
    if (error.response?.status === 422) {
      passwordErrors.value = error.response.data.errors || {}
    }
  } finally {
    savingPassword.value = false
  }
}

onMounted(() => {
  loadProfile()
})
</script>

<template>
  <div class="max-w-3xl mx-auto space-y-6">
    <h1 class="text-2xl font-bold text-gray-900">Mon profil</h1>

    <!-- Informations personnelles -->
    <div class="card">
      <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-full bg-ct-blue-100 flex items-center justify-center">
          <UserIcon class="w-5 h-5 text-ct-blue-600" />
        </div>
        <div>
          <h2 class="text-lg font-semibold text-gray-900">Informations personnelles</h2>
          <p class="text-sm text-gray-500">Modifier vos informations de base</p>
        </div>
      </div>

      <div v-if="profileSuccess" class="mb-4 flex items-center gap-2 p-3 bg-green-50 text-green-700 rounded-lg text-sm">
        <CheckCircleIcon class="w-5 h-5" />
        Profil mis a jour avec succes
      </div>

      <form @submit.prevent="saveProfile" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="label">Nom</label>
            <input v-model="profileForm.nom" type="text" class="input" required />
            <p v-if="profileErrors.nom" class="text-red-500 text-xs mt-1">{{ profileErrors.nom[0] }}</p>
          </div>
          <div>
            <label class="label">Prenom</label>
            <input v-model="profileForm.prenom" type="text" class="input" required />
            <p v-if="profileErrors.prenom" class="text-red-500 text-xs mt-1">{{ profileErrors.prenom[0] }}</p>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="label">Email</label>
            <input :value="authStore.user?.email" type="email" class="input bg-gray-50" disabled />
            <p class="text-xs text-gray-400 mt-1">L'email ne peut pas etre modifie</p>
          </div>
          <div>
            <label class="label">Telephone</label>
            <input v-model="profileForm.telephone" type="tel" class="input" />
            <p v-if="profileErrors.telephone" class="text-red-500 text-xs mt-1">{{ profileErrors.telephone[0] }}</p>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="label">Service</label>
            <input :value="authStore.user?.service?.libelle || '-'" type="text" class="input bg-gray-50" disabled />
          </div>
          <div>
            <label class="label">Matricule</label>
            <input :value="(authStore.user as any)?.matricule || '-'" type="text" class="input bg-gray-50" disabled />
          </div>
        </div>

        <div class="flex justify-end">
          <button type="submit" :disabled="savingProfile" class="btn-primary">
            {{ savingProfile ? 'Enregistrement...' : 'Enregistrer' }}
          </button>
        </div>
      </form>
    </div>

    <!-- Changer le mot de passe -->
    <div class="card">
      <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center">
          <KeyIcon class="w-5 h-5 text-orange-600" />
        </div>
        <div>
          <h2 class="text-lg font-semibold text-gray-900">Changer le mot de passe</h2>
          <p class="text-sm text-gray-500">Assurez-vous d'utiliser un mot de passe solide</p>
        </div>
      </div>

      <div v-if="passwordSuccess" class="mb-4 flex items-center gap-2 p-3 bg-green-50 text-green-700 rounded-lg text-sm">
        <CheckCircleIcon class="w-5 h-5" />
        Mot de passe modifie avec succes
      </div>

      <div v-if="passwordErrors.current_password" class="mb-4 flex items-center gap-2 p-3 bg-red-50 text-red-700 rounded-lg text-sm">
        <ExclamationTriangleIcon class="w-5 h-5" />
        {{ passwordErrors.current_password[0] }}
      </div>

      <form @submit.prevent="savePassword" class="space-y-4">
        <div>
          <label class="label">Mot de passe actuel</label>
          <input v-model="passwordForm.current_password" type="password" class="input max-w-md" required />
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="label">Nouveau mot de passe</label>
            <input v-model="passwordForm.password" type="password" class="input" required minlength="8" />
            <p v-if="passwordErrors.password" class="text-red-500 text-xs mt-1">{{ passwordErrors.password[0] }}</p>
            <p class="text-xs text-gray-400 mt-1">Minimum 8 caracteres</p>
          </div>
          <div>
            <label class="label">Confirmer le nouveau mot de passe</label>
            <input v-model="passwordForm.password_confirmation" type="password" class="input" required />
          </div>
        </div>

        <div class="flex justify-end">
          <button type="submit" :disabled="savingPassword" class="btn-primary">
            {{ savingPassword ? 'Modification...' : 'Changer le mot de passe' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
