<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { userService, referentielService, roleService } from '@/services/api'
import {
  PlusIcon,
  PencilIcon,
  TrashIcon,
  XMarkIcon,
  MagnifyingGlassIcon,
  ShoppingCartIcon,
  ShieldCheckIcon,
} from '@heroicons/vue/24/outline'

interface Role {
  id: string
  code: string
  libelle: string
  description?: string
  permissions?: string[] | Record<string, boolean>
}

interface User {
  id: string
  matricule: string
  nom: string
  prenom: string
  email: string
  telephone: string
  service_id: string
  service?: { id: string; libelle: string }
  roles?: Role[]
  actif: boolean
  est_acheteur: boolean
  est_valideur: boolean
  seuil_validation?: number
}

const loading = ref(true)
const users = ref<User[]>([])
const services = ref<{ value: string; label: string }[]>([])
const roles = ref<Role[]>([])
const showModal = ref(false)
const editing = ref<User | null>(null)
const saving = ref(false)
const search = ref('')
const errors = ref<Record<string, string[]>>({})

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
  role_ids: [] as string[],
})

async function loadData() {
  loading.value = true
  try {
    const params: any = {}
    if (search.value) params.search = search.value

    const [usersRes, servicesRes, rolesRes] = await Promise.all([
      userService.getAll(params),
      referentielService.getServices(),
      roleService.getAll(),
    ])
    users.value = usersRes.data.data || usersRes.data
    services.value = servicesRes.data.data || servicesRes.data
    roles.value = rolesRes.data.data || rolesRes.data || []
  } catch (error) {
    console.error('Erreur chargement:', error)
  } finally {
    loading.value = false
  }
}

function isRoleSelected(roleId: string): boolean {
  return form.value.role_ids.includes(roleId)
}

function toggleRole(roleId: string) {
  const index = form.value.role_ids.indexOf(roleId)
  if (index === -1) {
    form.value.role_ids.push(roleId)
  } else {
    form.value.role_ids.splice(index, 1)
  }
}

function openCreate() {
  editing.value = null
  errors.value = {}
  form.value = {
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
    seuil_validation: null,
    role_ids: [],
  }
  showModal.value = true
}

function openEdit(user: User) {
  editing.value = user
  errors.value = {}
  form.value = {
    matricule: user.matricule || '',
    nom: user.nom,
    prenom: user.prenom,
    email: user.email,
    password: '',
    password_confirmation: '',
    telephone: user.telephone || '',
    service_id: user.service_id || user.service?.id || '',
    actif: user.actif,
    est_acheteur: user.est_acheteur,
    est_valideur: user.est_valideur,
    seuil_validation: user.seuil_validation || null,
    role_ids: user.roles?.map(r => r.id) || [],
  }
  showModal.value = true
}

async function saveUser() {
  saving.value = true
  errors.value = {}
  try {
    const data: any = { ...form.value }

    // Ne pas envoyer le mot de passe si vide (en mode edition)
    if (editing.value && !data.password) {
      delete data.password
      delete data.password_confirmation
    }

    if (!data.seuil_validation) {
      delete data.seuil_validation
    }

    if (editing.value) {
      await userService.update(editing.value.id, data)
    } else {
      await userService.create(data)
    }
    showModal.value = false
    loadData()
  } catch (error: any) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {}
    } else {
      console.error('Erreur sauvegarde:', error)
    }
  } finally {
    saving.value = false
  }
}

async function deleteUser(user: User) {
  if (!confirm(`Desactiver l'utilisateur "${user.nom} ${user.prenom}" ?`)) return
  try {
    await userService.delete(user.id)
    loadData()
  } catch (error) {
    console.error('Erreur suppression:', error)
  }
}

let searchTimeout: number | null = null
function onSearch() {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => loadData(), 300) as unknown as number
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="card">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="text-lg font-semibold text-gray-900">Utilisateurs</h2>
        <p class="text-sm text-gray-500">Gerer les comptes utilisateurs et leurs droits</p>
      </div>
      <button @click="openCreate" class="btn-primary inline-flex items-center">
        <PlusIcon class="w-5 h-5 mr-2" />
        Nouvel utilisateur
      </button>
    </div>

    <!-- Recherche -->
    <div class="mb-4">
      <div class="relative w-64">
        <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
        <input
          v-model="search"
          @input="onSearch"
          type="text"
          placeholder="Rechercher..."
          class="input pl-10"
        />
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-ct-blue-500"></div>
    </div>

    <div v-else class="table-container">
      <table class="table">
        <thead>
          <tr>
            <th>Matricule</th>
            <th>Nom complet</th>
            <th>Email</th>
            <th>Service</th>
            <th>Roles</th>
            <th>Statut</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50">
            <td class="font-mono text-sm">{{ user.matricule || '-' }}</td>
            <td class="font-medium">{{ user.nom }} {{ user.prenom }}</td>
            <td class="text-gray-600">{{ user.email }}</td>
            <td>{{ user.service?.libelle || '-' }}</td>
            <td>
              <div class="flex flex-wrap gap-1">
                <span
                  v-for="role in user.roles"
                  :key="role.id"
                  :class="[
                    'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                    role.code === 'ADMIN' ? 'bg-red-100 text-red-800' : 'bg-ct-blue-100 text-ct-blue-800'
                  ]"
                >
                  {{ role.code }}
                </span>
                <span v-if="user.est_acheteur" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800" title="Acheteur">
                  <ShoppingCartIcon class="w-3 h-3 mr-1" />
                  ACH
                </span>
                <span v-if="user.est_valideur" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800" title="Valideur">
                  <ShieldCheckIcon class="w-3 h-3 mr-1" />
                  VAL
                </span>
                <span v-if="!user.roles?.length && !user.est_acheteur && !user.est_valideur" class="text-gray-400 text-xs">-</span>
              </div>
            </td>
            <td>
              <span
                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                :class="user.actif ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
              >
                {{ user.actif ? 'Actif' : 'Inactif' }}
              </span>
            </td>
            <td class="text-right">
              <div class="flex justify-end gap-1">
                <button @click="openEdit(user)" class="p-1.5 text-gray-500 hover:text-ct-blue-600 hover:bg-gray-100 rounded">
                  <PencilIcon class="w-4 h-4" />
                </button>
                <button @click="deleteUser(user)" class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-gray-100 rounded">
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="users.length === 0">
            <td colspan="7" class="text-center py-8 text-gray-500">Aucun utilisateur</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 overflow-y-auto py-8">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4">
        <div class="flex items-center justify-between p-4 border-b">
          <h3 class="text-lg font-semibold">{{ editing ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur' }}</h3>
          <button @click="showModal = false" class="text-gray-500 hover:text-gray-700">
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>
        <form @submit.prevent="saveUser" class="p-4 space-y-4">
          <!-- Informations personnelles -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="label">Matricule</label>
              <input v-model="form.matricule" type="text" class="input" placeholder="MAT001" />
              <p v-if="errors.matricule" class="text-red-500 text-xs mt-1">{{ errors.matricule[0] }}</p>
            </div>
            <div>
              <label class="label">Service</label>
              <select v-model="form.service_id" class="input">
                <option value="">-- Selectionner --</option>
                <option v-for="s in services" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
            </div>
            <div>
              <label class="label">Nom <span class="text-red-500">*</span></label>
              <input v-model="form.nom" type="text" class="input" required />
              <p v-if="errors.nom" class="text-red-500 text-xs mt-1">{{ errors.nom[0] }}</p>
            </div>
            <div>
              <label class="label">Prenom <span class="text-red-500">*</span></label>
              <input v-model="form.prenom" type="text" class="input" required />
              <p v-if="errors.prenom" class="text-red-500 text-xs mt-1">{{ errors.prenom[0] }}</p>
            </div>
            <div>
              <label class="label">Email <span class="text-red-500">*</span></label>
              <input v-model="form.email" type="email" class="input" required />
              <p v-if="errors.email" class="text-red-500 text-xs mt-1">{{ errors.email[0] }}</p>
            </div>
            <div>
              <label class="label">Telephone</label>
              <input v-model="form.telephone" type="tel" class="input" />
            </div>
          </div>

          <!-- Mot de passe -->
          <div class="border-t pt-4">
            <p v-if="editing" class="text-sm text-gray-500 mb-3">Laissez vide pour conserver le mot de passe actuel</p>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="label">{{ editing ? 'Nouveau mot de passe' : 'Mot de passe' }} <span v-if="!editing" class="text-red-500">*</span></label>
                <input v-model="form.password" type="password" class="input" :required="!editing" />
                <p v-if="errors.password" class="text-red-500 text-xs mt-1">{{ errors.password[0] }}</p>
              </div>
              <div>
                <label class="label">Confirmation</label>
                <input v-model="form.password_confirmation" type="password" class="input" :required="!!form.password" />
              </div>
            </div>
          </div>

          <!-- Droits -->
          <div class="border-t pt-4">
            <label class="label mb-3">Droits et permissions</label>
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

            <div v-if="form.est_valideur" class="mt-3">
              <label class="label">Seuil de validation (XAF)</label>
              <input v-model.number="form.seuil_validation" type="number" class="input w-48" placeholder="5000000" />
            </div>
          </div>

          <!-- Roles -->
          <div class="border-t pt-4">
            <label class="label mb-3">Roles</label>
            <div v-if="roles.length === 0" class="text-gray-500 text-sm">Aucun role disponible</div>
            <div v-else class="grid grid-cols-2 gap-2">
              <div
                v-for="role in roles"
                :key="role.id"
                @click="toggleRole(role.id)"
                :class="[
                  'border rounded-lg p-3 cursor-pointer transition-all',
                  isRoleSelected(role.id)
                    ? 'border-ct-blue-500 bg-ct-blue-50'
                    : 'border-gray-200 hover:border-gray-300'
                ]"
              >
                <div class="flex items-center gap-2">
                  <input
                    type="checkbox"
                    :checked="isRoleSelected(role.id)"
                    @click.stop
                    @change="toggleRole(role.id)"
                    class="w-4 h-4 text-ct-blue-600 rounded"
                  />
                  <span class="font-medium text-sm">{{ role.libelle }}</span>
                  <span v-if="role.code === 'ADMIN'" class="px-1.5 py-0.5 text-xs bg-red-100 text-red-700 rounded">Admin</span>
                </div>
                <p v-if="role.description" class="text-xs text-gray-500 mt-1 ml-6">{{ role.description }}</p>
              </div>
            </div>
          </div>

          <div class="flex justify-end gap-3 pt-4 border-t">
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
