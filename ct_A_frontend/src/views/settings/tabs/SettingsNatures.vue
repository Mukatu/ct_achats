<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import { PlusIcon, PencilIcon, TrashIcon, XMarkIcon } from '@heroicons/vue/24/outline'

interface NatureDepense {
  id: string
  code: string
  libelle: string
  description?: string
  type?: string
  compte_ohada?: string
  actif: boolean
}

const loading = ref(true)
const natures = ref<NatureDepense[]>([])
const showModal = ref(false)
const editing = ref<NatureDepense | null>(null)
const saving = ref(false)

const form = ref({
  code: '',
  libelle: '',
  compte_ohada: '',
  type: '',
  actif: true,
})

async function loadData() {
  loading.value = true
  try {
    const response = await api.get('/natures-depense')
    natures.value = response.data.data || response.data
  } catch (error) {
    console.error('Erreur chargement:', error)
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  form.value = { code: '', libelle: '', compte_ohada: '', type: '', actif: true }
  showModal.value = true
}

function openEdit(nature: NatureDepense) {
  editing.value = nature
  form.value = {
    code: nature.code,
    libelle: nature.libelle,
    compte_ohada: nature.compte_ohada || '',
    type: nature.type || '',
    actif: nature.actif,
  }
  showModal.value = true
}

async function saveNature() {
  saving.value = true
  try {
    if (editing.value) {
      await api.put(`/natures-depense/${editing.value.id}`, form.value)
    } else {
      await api.post('/natures-depense', form.value)
    }
    showModal.value = false
    loadData()
  } catch (error) {
    console.error('Erreur sauvegarde:', error)
  } finally {
    saving.value = false
  }
}

async function deleteNature(nature: NatureDepense) {
  if (!confirm(`Supprimer la nature "${nature.libelle}" ?`)) return
  try {
    await api.delete(`/natures-depense/${nature.id}`)
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
        <h2 class="text-lg font-semibold text-gray-900">Natures de depense</h2>
        <p class="text-sm text-gray-500">Categoriser les depenses pour le suivi budgetaire</p>
      </div>
      <button @click="openCreate" class="btn-primary inline-flex items-center">
        <PlusIcon class="w-5 h-5 mr-2" />
        Nouvelle nature
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
            <th>Type</th>
            <th>Compte OHADA</th>
            <th>Statut</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="nature in natures" :key="nature.id" class="hover:bg-gray-50">
            <td class="font-mono text-sm">{{ nature.code }}</td>
            <td class="font-medium">{{ nature.libelle }}</td>
            <td class="text-sm text-gray-500">{{ nature.type || '-' }}</td>
            <td class="font-mono text-sm">{{ nature.compte_ohada || '-' }}</td>
            <td>
              <span
                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                :class="nature.actif ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
              >
                {{ nature.actif ? 'Actif' : 'Inactif' }}
              </span>
            </td>
            <td class="text-right">
              <div class="flex justify-end gap-1">
                <button @click="openEdit(nature)" class="p-1.5 text-gray-500 hover:text-ct-blue-600 hover:bg-gray-100 rounded">
                  <PencilIcon class="w-4 h-4" />
                </button>
                <button @click="deleteNature(nature)" class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-gray-100 rounded">
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="natures.length === 0">
            <td colspan="6" class="text-center py-8 text-gray-500">Aucune nature de depense</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div class="flex items-center justify-between p-4 border-b">
          <h3 class="text-lg font-semibold">{{ editing ? 'Modifier la nature' : 'Nouvelle nature' }}</h3>
          <button @click="showModal = false" class="text-gray-500 hover:text-gray-700">
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>
        <form @submit.prevent="saveNature" class="p-4 space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="label">Code <span class="text-red-500">*</span></label>
              <input v-model="form.code" type="text" class="input" required maxlength="10" placeholder="601" />
            </div>
            <div>
              <label class="label">Type</label>
              <select v-model="form.type" class="input">
                <option value="">-- Selectionner --</option>
                <option value="FONCTIONNEMENT">Fonctionnement</option>
                <option value="INVESTISSEMENT">Investissement</option>
              </select>
            </div>
          </div>
          <div>
            <label class="label">Libelle <span class="text-red-500">*</span></label>
            <input v-model="form.libelle" type="text" class="input" required placeholder="Achats de marchandises" />
          </div>
          <div>
            <label class="label">Compte OHADA</label>
            <input v-model="form.compte_ohada" type="text" class="input" placeholder="601" />
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
