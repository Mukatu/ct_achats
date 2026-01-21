<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import api from '@/services/api'
import { CheckIcon, InformationCircleIcon } from '@heroicons/vue/24/outline'

const loading = ref(true)
const saving = ref(false)
const success = ref(false)

const config = ref({
  prefixe_eb: 'EB',
  prefixe_da: 'DA',
  prefixe_bc: 'BC',
  prefixe_br: 'BR',
  prefixe_facture: 'FAC',
  format: '{prefixe}-{annee}-{numero}',
  longueur_numero: 5,
  compteur_eb: 1,
  compteur_da: 1,
  compteur_bc: 1,
  compteur_br: 1,
  compteur_facture: 1,
  reinitialiser_annuellement: true,
})

const currentYear = new Date().getFullYear()

const exemples = computed(() => {
  const pad = (n: number) => String(n).padStart(config.value.longueur_numero, '0')
  return {
    eb: `${config.value.prefixe_eb}-${currentYear}-${pad(config.value.compteur_eb)}`,
    da: `${config.value.prefixe_da}-${currentYear}-${pad(config.value.compteur_da)}`,
    bc: `${config.value.prefixe_bc}-${currentYear}-${pad(config.value.compteur_bc)}`,
    br: `${config.value.prefixe_br}-${currentYear}-${pad(config.value.compteur_br)}`,
    facture: `${config.value.prefixe_facture}-${currentYear}-${pad(config.value.compteur_facture)}`,
  }
})

async function loadSettings() {
  loading.value = true
  try {
    const response = await api.get('/settings/numerotation')
    const data = response.data.data || response.data
    config.value = { ...config.value, ...data }
  } catch (error) {
    console.error('Erreur chargement:', error)
  } finally {
    loading.value = false
  }
}

async function saveSettings() {
  saving.value = true
  success.value = false
  try {
    await api.put('/settings/numerotation', config.value)
    success.value = true
    setTimeout(() => success.value = false, 3000)
  } catch (error) {
    console.error('Erreur sauvegarde:', error)
  } finally {
    saving.value = false
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
        <h2 class="text-lg font-semibold text-gray-900">Numerotation</h2>
        <p class="text-sm text-gray-500">Configuration des prefixes et compteurs de documents</p>
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
      <!-- Format general -->
      <div class="border-b border-gray-200 pb-6">
        <h3 class="text-sm font-medium text-gray-900 mb-4">Format general</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="label">Longueur du numero</label>
            <select v-model.number="config.longueur_numero" class="input">
              <option :value="4">4 chiffres (0001)</option>
              <option :value="5">5 chiffres (00001)</option>
              <option :value="6">6 chiffres (000001)</option>
            </select>
          </div>
          <div class="flex items-center">
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="config.reinitialiser_annuellement" type="checkbox" class="w-4 h-4 text-ct-blue-600 rounded" />
              <span>Reinitialiser les compteurs chaque annee</span>
            </label>
          </div>
        </div>
      </div>

      <!-- Prefixes -->
      <div class="border-b border-gray-200 pb-6">
        <h3 class="text-sm font-medium text-gray-900 mb-4">Prefixes par type de document</h3>
        <div class="space-y-3">
          <div class="grid grid-cols-3 gap-4 items-center p-3 bg-gray-50 rounded-lg">
            <div>
              <label class="label mb-0">Expression de Besoin</label>
            </div>
            <input v-model="config.prefixe_eb" type="text" class="input" maxlength="5" />
            <div class="text-sm text-gray-500 font-mono">{{ exemples.eb }}</div>
          </div>

          <div class="grid grid-cols-3 gap-4 items-center p-3 bg-gray-50 rounded-lg">
            <div>
              <label class="label mb-0">Demande d'Achat</label>
            </div>
            <input v-model="config.prefixe_da" type="text" class="input" maxlength="5" />
            <div class="text-sm text-gray-500 font-mono">{{ exemples.da }}</div>
          </div>

          <div class="grid grid-cols-3 gap-4 items-center p-3 bg-gray-50 rounded-lg">
            <div>
              <label class="label mb-0">Bon de Commande</label>
            </div>
            <input v-model="config.prefixe_bc" type="text" class="input" maxlength="5" />
            <div class="text-sm text-gray-500 font-mono">{{ exemples.bc }}</div>
          </div>

          <div class="grid grid-cols-3 gap-4 items-center p-3 bg-gray-50 rounded-lg">
            <div>
              <label class="label mb-0">Bon de Reception</label>
            </div>
            <input v-model="config.prefixe_br" type="text" class="input" maxlength="5" />
            <div class="text-sm text-gray-500 font-mono">{{ exemples.br }}</div>
          </div>

          <div class="grid grid-cols-3 gap-4 items-center p-3 bg-gray-50 rounded-lg">
            <div>
              <label class="label mb-0">Facture</label>
            </div>
            <input v-model="config.prefixe_facture" type="text" class="input" maxlength="5" />
            <div class="text-sm text-gray-500 font-mono">{{ exemples.facture }}</div>
          </div>
        </div>
      </div>

      <!-- Compteurs actuels -->
      <div class="border-b border-gray-200 pb-6">
        <h3 class="text-sm font-medium text-gray-900 mb-4">Compteurs actuels ({{ currentYear }})</h3>
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4 flex gap-3">
          <InformationCircleIcon class="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5" />
          <p class="text-sm text-yellow-700">
            Attention : modifier les compteurs peut creer des doublons ou des sauts de numerotation.
          </p>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
          <div>
            <label class="label">EB</label>
            <input v-model.number="config.compteur_eb" type="number" min="1" class="input" />
          </div>
          <div>
            <label class="label">DA</label>
            <input v-model.number="config.compteur_da" type="number" min="1" class="input" />
          </div>
          <div>
            <label class="label">BC</label>
            <input v-model.number="config.compteur_bc" type="number" min="1" class="input" />
          </div>
          <div>
            <label class="label">BR</label>
            <input v-model.number="config.compteur_br" type="number" min="1" class="input" />
          </div>
          <div>
            <label class="label">Facture</label>
            <input v-model.number="config.compteur_facture" type="number" min="1" class="input" />
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
