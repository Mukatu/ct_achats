<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import { PlusIcon, PencilIcon, TrashIcon, XMarkIcon, CheckIcon } from '@heroicons/vue/24/outline'

interface Role {
  id: string
  code: string
  libelle: string
  description?: string
  permissions: string[]
  actif: boolean
  users_count?: number
}

const loading = ref(true)
const roles = ref<Role[]>([])
const showModal = ref(false)
const editing = ref<Role | null>(null)
const saving = ref(false)

const availablePermissions = [
  { code: 'eb.view', label: 'Voir les Expressions de Besoin' },
  { code: 'eb.create', label: 'Creer des EB' },
  { code: 'eb.edit', label: 'Modifier des EB' },
  { code: 'eb.validate', label: 'Valider des EB' },
  { code: 'da.view', label: 'Voir les Demandes d\'Achat' },
  { code: 'da.create', label: 'Creer des DA' },
  { code: 'da.edit', label: 'Modifier des DA' },
  { code: 'da.validate', label: 'Valider des DA' },
  { code: 'bc.view', label: 'Voir les Bons de Commande' },
  { code: 'bc.create', label: 'Creer des BC' },
  { code: 'bc.edit', label: 'Modifier des BC' },
  { code: 'bc.validate', label: 'Valider des BC' },
  { code: 'br.view', label: 'Voir les Receptions' },
  { code: 'br.create', label: 'Creer des BR' },
  { code: 'br.validate', label: 'Valider des BR' },
  { code: 'facture.view', label: 'Voir les Factures' },
  { code: 'facture.create', label: 'Creer des Factures' },
  { code: 'facture.validate', label: 'Valider des Factures' },
  { code: 'fournisseur.view', label: 'Voir les Fournisseurs' },
  { code: 'fournisseur.manage', label: 'Gerer les Fournisseurs' },
  { code: 'user.view', label: 'Voir les Utilisateurs' },
  { code: 'user.manage', label: 'Gerer les Utilisateurs' },
  { code: 'settings.view', label: 'Voir les Parametres' },
  { code: 'settings.manage', label: 'Gerer les Parametres' },
  { code: 'stats.view', label: 'Voir les Statistiques' },
]

const form = ref({
  code: '',
  libelle: '',
  description: '',
  permissions: [] as string[],
  actif: true,
})

async function loadData() {
  loading.value = true
  try {
    const response = await api.get('/roles')
    roles.value = response.data.data || response.data
  } catch (error) {
    console.error('Erreur chargement:', error)
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  form.value = { code: '', libelle: '', description: '', permissions: [], actif: true }
  showModal.value = true
}

function openEdit(role: Role) {
  editing.value = role
  form.value = {
    code: role.code,
    libelle: role.libelle,
    description: role.description || '',
    permissions: [...role.permissions],
    actif: role.actif,
  }
  showModal.value = true
}

function togglePermission(code: string) {
  const idx = form.value.permissions.indexOf(code)
  if (idx === -1) {
    form.value.permissions.push(code)
  } else {
    form.value.permissions.splice(idx, 1)
  }
}

function selectAllPermissions() {
  form.value.permissions = availablePermissions.map(p => p.code)
}

function clearAllPermissions() {
  form.value.permissions = []
}

async function saveRole() {
  saving.value = true
  try {
    if (editing.value) {
      await api.put(`/roles/${editing.value.id}`, form.value)
    } else {
      await api.post('/roles', form.value)
    }
    showModal.value = false
    loadData()
  } catch (error) {
    console.error('Erreur sauvegarde:', error)
  } finally {
    saving.value = false
  }
}

async function deleteRole(role: Role) {
  if (!confirm(`Supprimer le role "${role.libelle}" ?`)) return
  try {
    await api.delete(`/roles/${role.id}`)
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
        <h2 class="text-lg font-semibold text-gray-900">Roles et permissions</h2>
        <p class="text-sm text-gray-500">Definir les roles et leurs droits d'acces</p>
      </div>
      <button @click="openCreate" class="btn-primary inline-flex items-center">
        <PlusIcon class="w-5 h-5 mr-2" />
        Nouveau role
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
            <th>Description</th>
            <th>Permissions</th>
            <th>Utilisateurs</th>
            <th>Statut</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="role in roles" :key="role.id" class="hover:bg-gray-50">
            <td class="font-mono text-sm">{{ role.code }}</td>
            <td class="font-medium">{{ role.libelle }}</td>
            <td class="text-sm text-gray-500 max-w-xs truncate">{{ role.description || '-' }}</td>
            <td>
              <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                {{ role.permissions?.length || 0 }} droits
              </span>
            </td>
            <td>{{ role.users_count || 0 }}</td>
            <td>
              <span
                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                :class="role.actif ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
              >
                {{ role.actif ? 'Actif' : 'Inactif' }}
              </span>
            </td>
            <td class="text-right">
              <div class="flex justify-end gap-1">
                <button @click="openEdit(role)" class="p-1.5 text-gray-500 hover:text-ct-blue-600 hover:bg-gray-100 rounded">
                  <PencilIcon class="w-4 h-4" />
                </button>
                <button @click="deleteRole(role)" class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-gray-100 rounded">
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="roles.length === 0">
            <td colspan="7" class="text-center py-8 text-gray-500">Aucun role</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 overflow-y-auto py-8">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4">
        <div class="flex items-center justify-between p-4 border-b">
          <h3 class="text-lg font-semibold">{{ editing ? 'Modifier le role' : 'Nouveau role' }}</h3>
          <button @click="showModal = false" class="text-gray-500 hover:text-gray-700">
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>
        <form @submit.prevent="saveRole" class="p-4 space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="label">Code <span class="text-red-500">*</span></label>
              <input v-model="form.code" type="text" class="input" required maxlength="20" placeholder="ADMIN" />
            </div>
            <div>
              <label class="label">Libelle <span class="text-red-500">*</span></label>
              <input v-model="form.libelle" type="text" class="input" required placeholder="Administrateur" />
            </div>
          </div>
          <div>
            <label class="label">Description</label>
            <textarea v-model="form.description" class="input" rows="2" placeholder="Description du role..."></textarea>
          </div>

          <!-- Permissions -->
          <div>
            <div class="flex items-center justify-between mb-2">
              <label class="label mb-0">Permissions</label>
              <div class="flex gap-2">
                <button type="button" @click="selectAllPermissions" class="text-xs text-ct-blue-600 hover:underline">
                  Tout selectionner
                </button>
                <button type="button" @click="clearAllPermissions" class="text-xs text-gray-500 hover:underline">
                  Tout deselectionner
                </button>
              </div>
            </div>
            <div class="border rounded-lg p-3 max-h-64 overflow-y-auto">
              <div class="grid grid-cols-2 gap-2">
                <label
                  v-for="perm in availablePermissions"
                  :key="perm.code"
                  class="flex items-center gap-2 p-2 rounded hover:bg-gray-50 cursor-pointer"
                >
                  <input
                    type="checkbox"
                    :checked="form.permissions.includes(perm.code)"
                    @change="togglePermission(perm.code)"
                    class="w-4 h-4 text-ct-blue-600 rounded"
                  />
                  <span class="text-sm">{{ perm.label }}</span>
                </label>
              </div>
            </div>
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
