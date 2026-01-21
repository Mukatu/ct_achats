<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import { PlusIcon, PencilIcon, TrashIcon, XMarkIcon } from '@heroicons/vue/24/outline'

interface Zone {
  id: string
  libelle: string
}

interface Direction {
  id: string
  code: string
  libelle: string
  zone_id: string
  zone?: Zone
  actif: boolean
  services_count?: number
}

const loading = ref(true)
const directions = ref<Direction[]>([])
const zones = ref<Zone[]>([])
const showModal = ref(false)
const editing = ref<Direction | null>(null)
const saving = ref(false)

const form = ref({
  code: '',
  libelle: '',
  zone_id: '',
  actif: true,
})

async function loadData() {
  loading.value = true
  try {
    const [directionsRes, zonesRes] = await Promise.all([
      api.get('/directions'),
      api.get('/zones'),
    ])
    directions.value = directionsRes.data.data || directionsRes.data
    zones.value = zonesRes.data.data || zonesRes.data
  } catch (error) {
    console.error('Erreur chargement:', error)
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  form.value = { code: '', libelle: '', zone_id: '', actif: true }
  showModal.value = true
}

function openEdit(direction: Direction) {
  editing.value = direction
  form.value = {
    code: direction.code,
    libelle: direction.libelle,
    zone_id: direction.zone_id,
    actif: direction.actif,
  }
  showModal.value = true
}

async function saveDirection() {
  saving.value = true
  try {
    if (editing.value) {
      await api.put(`/directions/${editing.value.id}`, form.value)
    } else {
      await api.post('/directions', form.value)
    }
    showModal.value = false
    loadData()
  } catch (error) {
    console.error('Erreur sauvegarde:', error)
  } finally {
    saving.value = false
  }
}

async function deleteDirection(direction: Direction) {
  if (!confirm(`Supprimer la direction "${direction.libelle}" ?`)) return
  try {
    await api.delete(`/directions/${direction.id}`)
    loadData()
  } catch (error) {
    console.error('Erreur suppression:', error)
  }
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="card">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="text-lg font-semibold text-gray-900">Directions</h2>
        <p class="text-sm text-gray-500">Gerer les directions de l'organisation</p>
      </div>
      <button @click="openCreate" class="btn-primary inline-flex items-center">
        <PlusIcon class="w-5 h-5 mr-2" />
        Nouvelle direction
      </button>
    </div>

    <div v-if="loading" class="flex justify-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-ct-blue-500"></div>
    </div>

    <div v-else class="table-container">
      <table class="table">
        <thead>
          <tr>
            <th>Code</th>
            <th>Libelle</th>
            <th>Zone</th>
            <th>Services</th>
            <th>Statut</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="direction in directions" :key="direction.id" class="hover:bg-gray-50">
            <td class="font-mono text-sm">{{ direction.code }}</td>
            <td class="font-medium">{{ direction.libelle }}</td>
            <td>{{ direction.zone?.libelle || '-' }}</td>
            <td>{{ direction.services_count || 0 }}</td>
            <td>
              <span
                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                :class="direction.actif ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
              >
                {{ direction.actif ? 'Actif' : 'Inactif' }}
              </span>
            </td>
            <td class="text-right">
              <div class="flex justify-end gap-1">
                <button @click="openEdit(direction)" class="p-1.5 text-gray-500 hover:text-ct-blue-600 hover:bg-gray-100 rounded">
                  <PencilIcon class="w-4 h-4" />
                </button>
                <button @click="deleteDirection(direction)" class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-gray-100 rounded">
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="directions.length === 0">
            <td colspan="6" class="text-center py-8 text-gray-500">Aucune direction</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div class="flex items-center justify-between p-4 border-b">
          <h3 class="text-lg font-semibold">{{ editing ? 'Modifier la direction' : 'Nouvelle direction' }}</h3>
          <button @click="showModal = false" class="text-gray-500 hover:text-gray-700">
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>
        <form @submit.prevent="saveDirection" class="p-4 space-y-4">
          <div>
            <label class="label">Code <span class="text-red-500">*</span></label>
            <input v-model="form.code" type="text" class="input" required maxlength="10" placeholder="DG" />
          </div>
          <div>
            <label class="label">Libelle <span class="text-red-500">*</span></label>
            <input v-model="form.libelle" type="text" class="input" required placeholder="Direction Generale" />
          </div>
          <div>
            <label class="label">Zone <span class="text-red-500">*</span></label>
            <select v-model="form.zone_id" class="input" required>
              <option value="">-- Selectionner --</option>
              <option v-for="zone in zones" :key="zone.id" :value="zone.id">{{ zone.libelle }}</option>
            </select>
          </div>
          <div>
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="form.actif" type="checkbox" class="w-4 h-4 text-ct-blue-600 rounded" />
              <span>Actif</span>
            </label>
          </div>
          <div class="flex justify-end gap-3 pt-4">
            <button type="button" @click="showModal = false" class="btn-secondary">Annuler</button>
            <button type="submit" :disabled="saving" class="btn-primary">
              {{ saving ? 'Enregistrement...' : 'Enregistrer' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
