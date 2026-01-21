<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import api from '@/services/api'
import { useAppLogo } from '@/composables/useAppLogo'
import { CheckIcon, PhotoIcon, XMarkIcon, ArrowUpTrayIcon } from '@heroicons/vue/24/outline'

const { updateLogo } = useAppLogo()

const loading = ref(true)
const saving = ref(false)
const success = ref(false)
const uploading = ref(false)
const logoPreview = ref<string | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const uploadError = ref('')

const form = ref({
  nom_entreprise: '',
  adresse: '',
  telephone: '',
  email: '',
  site_web: '',
  devise: 'XAF',
  exercice_debut: '',
  exercice_fin: '',
  logo_url: '',
})

async function loadSettings() {
  loading.value = true
  try {
    const response = await api.get('/settings/general')
    const data = response.data.data || response.data
    form.value = { ...form.value, ...data }
    if (form.value.logo_url) {
      logoPreview.value = form.value.logo_url
    }
  } catch (error) {
    console.error('Erreur chargement parametres:', error)
  } finally {
    loading.value = false
  }
}

async function saveSettings() {
  saving.value = true
  success.value = false
  try {
    await api.put('/settings/general', form.value)
    success.value = true
    setTimeout(() => success.value = false, 3000)
  } catch (error) {
    console.error('Erreur sauvegarde:', error)
  } finally {
    saving.value = false
  }
}

function triggerFileInput() {
  fileInput.value?.click()
}

async function handleFileSelect(event: Event) {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file) return

  // Validation du fichier
  const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml']
  if (!allowedTypes.includes(file.type)) {
    uploadError.value = 'Format non supporte. Utilisez JPG, PNG, GIF, WebP ou SVG.'
    return
  }

  const maxSize = 2 * 1024 * 1024 // 2MB
  if (file.size > maxSize) {
    uploadError.value = 'Fichier trop volumineux. Maximum 2 Mo.'
    return
  }

  uploadError.value = ''
  await uploadLogo(file)
}

async function uploadLogo(file: File) {
  uploading.value = true
  try {
    const formData = new FormData()
    formData.append('logo', file)

    const response = await api.post('/settings/logo', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    const logoUrl = response.data.url || response.data.logo_url
    form.value.logo_url = logoUrl
    logoPreview.value = logoUrl
    // Mettre à jour le logo global (sidebar)
    updateLogo(logoUrl)
  } catch (error: any) {
    console.error('Erreur upload:', error)
    uploadError.value = error.response?.data?.message || 'Erreur lors de l\'upload'
  } finally {
    uploading.value = false
    // Reset input
    if (fileInput.value) fileInput.value.value = ''
  }
}

async function removeLogo() {
  if (!confirm('Supprimer le logo ?')) return

  try {
    await api.delete('/settings/logo')
    form.value.logo_url = ''
    logoPreview.value = null
    // Remettre le logo par défaut (sidebar)
    updateLogo(null)
  } catch (error) {
    console.error('Erreur suppression logo:', error)
  }
}

onMounted(() => {
  loadSettings()
})
</script>

<template>
  <div class="card">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="text-lg font-semibold text-gray-900">Configuration generale</h2>
        <p class="text-sm text-gray-500">Informations de l'entreprise et parametres globaux</p>
      </div>
      <div v-if="success" class="flex items-center text-green-600 text-sm">
        <CheckIcon class="w-5 h-5 mr-1" />
        Enregistre
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-ct-blue-500"></div>
    </div>

    <form v-else @submit.prevent="saveSettings" class="space-y-6">
      <!-- Informations entreprise -->
      <div class="border-b border-gray-200 pb-6">
        <h3 class="text-sm font-medium text-gray-900 mb-4">Informations de l'entreprise</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="md:col-span-2">
            <label class="label">Nom de l'entreprise</label>
            <input v-model="form.nom_entreprise" type="text" class="input" placeholder="Congo Telecom" />
          </div>
          <div class="md:col-span-2">
            <label class="label">Adresse</label>
            <textarea v-model="form.adresse" class="input" rows="2" placeholder="BP 1571, Boulevard Denis Sassou"></textarea>
          </div>
          <div>
            <label class="label">Telephone</label>
            <input v-model="form.telephone" type="tel" class="input" placeholder="+242 660 1616" />
          </div>
          <div>
            <label class="label">Email</label>
            <input v-model="form.email" type="email" class="input" placeholder="contact@congotelecom.cg" />
          </div>
          <div>
            <label class="label">Site web</label>
            <input v-model="form.site_web" type="url" class="input" placeholder="https://www.camtel.cm" />
          </div>
        </div>

        <!-- Logo upload -->
        <div class="mt-4">
          <label class="label">Logo de l'entreprise</label>
          <input
            ref="fileInput"
            type="file"
            accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml"
            class="hidden"
            @change="handleFileSelect"
          />

          <div class="flex items-start gap-4">
            <!-- Preview -->
            <div
              v-if="logoPreview"
              class="relative w-32 h-32 rounded-lg border border-gray-200 overflow-hidden bg-white flex items-center justify-center"
            >
              <img :src="logoPreview" alt="Logo" class="max-w-full max-h-full object-contain" />
              <button
                type="button"
                @click="removeLogo"
                class="absolute top-1 right-1 p-1 bg-red-500 text-white rounded-full hover:bg-red-600 transition-colors"
                title="Supprimer le logo"
              >
                <XMarkIcon class="w-4 h-4" />
              </button>
            </div>

            <!-- Upload zone -->
            <div
              v-else
              @click="triggerFileInput"
              class="w-32 h-32 rounded-lg border-2 border-dashed border-gray-300 flex flex-col items-center justify-center cursor-pointer hover:border-ct-blue-500 hover:bg-ct-blue-50 transition-colors"
            >
              <PhotoIcon class="w-8 h-8 text-gray-400" />
              <span class="text-xs text-gray-500 mt-2 text-center px-2">Cliquer pour ajouter</span>
            </div>

            <!-- Upload button / status -->
            <div class="flex flex-col justify-center">
              <button
                type="button"
                @click="triggerFileInput"
                :disabled="uploading"
                class="btn-secondary inline-flex items-center text-sm"
              >
                <ArrowUpTrayIcon class="w-4 h-4 mr-2" />
                {{ uploading ? 'Upload...' : (logoPreview ? 'Changer' : 'Parcourir') }}
              </button>
              <p class="text-xs text-gray-500 mt-2">JPG, PNG, GIF, WebP, SVG. Max 2 Mo.</p>
              <p v-if="uploadError" class="text-xs text-red-500 mt-1">{{ uploadError }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Exercice comptable -->
      <div class="border-b border-gray-200 pb-6">
        <h3 class="text-sm font-medium text-gray-900 mb-4">Exercice comptable</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="label">Devise</label>
            <select v-model="form.devise" class="input">
              <option value="XAF">XAF - Franc CFA</option>
              <option value="EUR">EUR - Euro</option>
              <option value="USD">USD - Dollar US</option>
            </select>
          </div>
          <div>
            <label class="label">Debut exercice</label>
            <input v-model="form.exercice_debut" type="date" class="input" />
          </div>
          <div>
            <label class="label">Fin exercice</label>
            <input v-model="form.exercice_fin" type="date" class="input" />
          </div>
        </div>
      </div>

      <div class="flex justify-end">
        <button type="submit" :disabled="saving" class="btn-primary">
          {{ saving ? 'Enregistrement...' : 'Enregistrer' }}
        </button>
      </div>
    </form>
  </div>
</template>
