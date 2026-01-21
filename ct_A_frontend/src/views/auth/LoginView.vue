<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useAppLogo } from '@/composables/useAppLogo'
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const { logoUrl, defaultLogo } = useAppLogo()

// Sur la page login, on utilise le logo par défaut car l'API nécessite l'authentification
const displayLogo = defaultLogo

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)
const showPassword = ref(false)

const handleSubmit = async () => {
  error.value = ''
  loading.value = true

  try {
    await authStore.login(email.value, password.value)
    const redirect = route.query.redirect as string || '/'
    router.push(redirect)
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Identifiants incorrects'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-800 first-letter:px-4">
    <div class="w-full max-w-md">
      <!-- Logo -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-white shadow-lg mb-4 overflow-hidden">
          <img :src="displayLogo" alt="Congo Telecom" class="h-full w-full object-contain p-1">
        </div>
        <h1 class="text-3xl font-bold text-white">Congo Telecom</h1>
        <p class="text-ct-blue-200 mt-2">Gestion des Engagements</p>
      </div>

      <!-- Login form -->
      <div class="bg-white rounded-2xl shadow-xl p-8">
        <h2 class="text-2xl font-bold text-gray-900 text-center mb-6">Connexion</h2>

        <form @submit.prevent="handleSubmit" class="space-y-5">
          <!-- Email -->
          <div>
            <label for="email" class="label">Adresse email</label>
            <input
              id="email"
              v-model="email"
              type="email"
              required
              class="input"
              :class="{ 'input-error': error }"
              placeholder="votre@email.cg"
            />
          </div>

          <!-- Password -->
          <div>
            <label for="password" class="label">Mot de passe</label>
            <div class="relative">
              <input
                id="password"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                required
                class="input pr-10"
                :class="{ 'input-error': error }"
                placeholder="••••••••"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
              >
                <EyeSlashIcon v-if="showPassword" class="w-5 h-5" />
                <EyeIcon v-else class="w-5 h-5" />
              </button>
            </div>
          </div>

          <!-- Error message -->
          <div v-if="error" class="p-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            {{ error }}
          </div>

          <!-- Submit button -->
          <button
            type="submit"
            class="btn-primary w-full py-3"
            :disabled="loading"
          >
            <span v-if="loading" class="inline-flex items-center">
              <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              Connexion...
            </span>
            <span v-else>Se connecter</span>
          </button>
        </form>

        <!-- Footer -->
        <div class="mt-3 text-center text-sm text-gray-500">
          <p>Problème de connexion ?</p>
          <p>Contactez l'administrateur</p>
        </div>
      </div>

      <!-- Version -->
      <p class="text-center text-ct-blue-200 text-sm mt-4">
        watUneed -Tous drois réservés-
      </p>
    </div>
  </div>
</template>
