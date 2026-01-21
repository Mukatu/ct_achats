<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import { PlusIcon, PencilIcon, TrashIcon, XMarkIcon } from '@heroicons/vue/24/outline'

interface Direction {
  id: string
  libelle: string
}

interface Service {
  id: string
  code: string
  libelle: string
  direction_id: string
  direction?: Direction
  actif: boolean
  users_count?: number
}

const loading = ref(true)
const services = ref<Service[]>([])
const directions = ref<Direction[]>([])
const showModal = ref(false)
const editing = ref<Service | null>(null)
const saving = ref(false)

const form = ref({
  code: '',
  libelle: '',
  direction_id: '',
  actif: true,
})

async function loadData() {
  loading.value = true
  try {
    const [servicesRes, directionsRes] = await Promise.all([
      api.get('/services'),
      api.get('/directions'),
    ])
    services.value = servicesRes.data.data || servicesRes.data
    directions.value = directionsRes.data.data || directionsRes.data
  } catch (error) {
    console.error('Erreur chargement:', error)
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  form.value = { code: '', libelle: '', direction_id: '', actif: true }
  showModal.value = true
}

function openEdit(service: Service) {
  editing.value = service
  form.value = {
    code: service.code,
    libelle: service.libelle,
    direction_id: service.direction_id,
    actif: service.actif,
  }
  showModal.value = true
}

async function saveService() {
  saving.value = true
  try {
    if (editing.value) {
      await api.put(`/services/${editing.value.id}`, form.value)
    } else {
      await api.post('/services', form.value)
    }
    showModal.value = false
    loadData()
  } catch (error) {
    console.error('Erreur sauvegarde:', error)
  } finally {
    saving.value = false
  }
}

async function deleteService(service: Service) {
  if (!confirm(`Supprimer le service "${service.libelle}" ?`)) return
  try {
    await api.delete(`/services/${service.id}`)
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
        <h2 class="text-lg font-semibold text-gray-900">Services</h2>
        <p class="text-sm text-gray-500">Gerer les services de l'organisation</p>
      </div>
      <button @click="openCreate" class="btn-primary inline-flex items-center">
        <PlusIcon class="w-5 h-5 mr-2" />
        Nouveau service
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
            <th>Direction</th>
            <th>Utilisateurs</th>
            <th>Statut</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="service in services" :key="service.id" class="hover:bg-gray-50">
            <td class="font-mono text-sm">{{ service.code }}</td>
            <td class="font-medium">{{ service.libelle }}</td>
            <td>{{ service.direction?.libelle || '-' }}</td>
            <td>{{ service.users_count || 0 }}</td>
            <td>
              <span
                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                :class="service.actif ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
              >
                {{ service.actif ? 'Actif' : 'Inactif' }}
              </span>
            </td>
            <td class="text-right">
              <div class="flex justify-end gap-1">
                <button @click="openEdit(service)" class="p-1.5 text-gray-500 hover:text-ct-blue-600 hover:bg-gray-100 rounded">
                  <PencilIcon class="w-4 h-4" />
                </button>
                <button @click="deleteService(service)" class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-gray-100 rounded">
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="services.length === 0">
            <td colspan="6" class="text-center py-8 text-gray-500">Aucun service</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div class="flex items-center justify-between p-4 border-b">
          <h3 class="text-lg font-semibold">{{ editing ? 'Modifier le service' : 'Nouveau service' }}</h3>
          <button @click="showModal = false" class="text-gray-500 hover:text-gray-700">
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>
        <form @submit.prevent="saveService" class="p-4 space-y-4">
          <div>
            <label class="label">Code <span class="text-red-500">*</span></label>
            <input v-model="form.code" type="text" class="input" required maxlength="10" placeholder="COMPTA" />
          </div>
          <div>
            <label class="label">Libelle <span class="text-red-500">*</span></label>
            <input v-model="form.libelle" type="text" class="input" required placeholder="Service Comptabilite" />
          </div>
          <div>
            <label class="label">Direction <span class="text-red-500">*</span></label>
            <select v-model="form.direction_id" class="input" required>
              <option value="">-- Selectionner --</option>
              <option v-for="dir in directions" :key="dir.id" :value="dir.id">{{ dir.libelle }}</option>
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
