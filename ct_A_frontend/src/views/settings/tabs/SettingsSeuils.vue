<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import { CheckIcon, InformationCircleIcon } from '@heroicons/vue/24/outline'

const loading = ref(true)
const saving = ref(false)
const success = ref(false)

const seuils = ref({
  seuil_chef_service: 500000,
  seuil_directeur: 5000000,
  seuil_dg: 50000000,
  delai_validation_eb: 48,
  delai_validation_da: 72,
  delai_validation_bc: 48,
})

function formatMontant(value: number): string {
  return new Intl.NumberFormat('fr-FR').format(value)
}

async function loadSettings() {
  loading.value = true
  try {
    const response = await api.get('/settings/seuils')
    const data = response.data.data || response.data
    seuils.value = { ...seuils.value, ...data }
  } catch (error) {
    console.error('Erreur chargement seuils:', error)
  } finally {
    loading.value = false
  }
}

async function saveSettings() {
  saving.value = true
  success.value = false
  try {
    await api.put('/settings/seuils', seuils.value)
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
        <h2 class="text-lg font-semibold text-gray-900">Seuils de validation</h2>
        <p class="text-sm text-gray-500">Definir les montants maximums par niveau de validation</p>
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
      <!-- Info box -->
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 flex gap-3">
        <InformationCircleIcon class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" />
        <div class="text-sm text-blue-700">
          <p class="font-medium">Hierarchie de validation</p>
          <p class="mt-1">Les demandes sont automatiquement routees vers le niveau de validation approprie selon leur montant total.</p>
        </div>
      </div>

      <!-- Seuils par niveau -->
      <div class="border-b border-gray-200 pb-6">
        <h3 class="text-sm font-medium text-gray-900 mb-4">Seuils par niveau hierarchique (XAF)</h3>
        <div class="space-y-4">
          <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
            <div>
              <p class="font-medium text-gray-900">Chef de Service</p>
              <p class="text-sm text-gray-500">Validation des demandes jusqu'a ce montant</p>
            </div>
            <div class="w-48">
              <input v-model.number="seuils.seuil_chef_service" type="number" class="input text-right" />
              <p class="text-xs text-gray-500 text-right mt-1">{{ formatMontant(seuils.seuil_chef_service) }} XAF</p>
            </div>
          </div>

          <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
            <div>
              <p class="font-medium text-gray-900">Directeur</p>
              <p class="text-sm text-gray-500">Validation des demandes jusqu'a ce montant</p>
            </div>
            <div class="w-48">
              <input v-model.number="seuils.seuil_directeur" type="number" class="input text-right" />
              <p class="text-xs text-gray-500 text-right mt-1">{{ formatMontant(seuils.seuil_directeur) }} XAF</p>
            </div>
          </div>

          <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
            <div>
              <p class="font-medium text-gray-900">Directeur General</p>
              <p class="text-sm text-gray-500">Validation des demandes au-dela du seuil directeur</p>
            </div>
            <div class="w-48">
              <input v-model.number="seuils.seuil_dg" type="number" class="input text-right" />
              <p class="text-xs text-gray-500 text-right mt-1">{{ formatMontant(seuils.seuil_dg) }} XAF</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Delais de validation -->
      <div class="border-b border-gray-200 pb-6">
        <h3 class="text-sm font-medium text-gray-900 mb-4">Delais de validation (heures)</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="label">Expression de Besoin</label>
            <input v-model.number="seuils.delai_validation_eb" type="number" class="input" />
          </div>
          <div>
            <label class="label">Demande d'Achat</label>
            <input v-model.number="seuils.delai_validation_da" type="number" class="input" />
          </div>
          <div>
            <label class="label">Bon de Commande</label>
            <input v-model.number="seuils.delai_validation_bc" type="number" class="input" />
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
