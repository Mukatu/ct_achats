<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { userService, referentielService, roleService } from '@/services/api'
import {
  ArrowLeftIcon,
  CheckIcon,
  ShieldCheckIcon,
} from '@heroicons/vue/24/outline'

interface Role {
  id: string
  code: string
  libelle: string
  description?: string
  permissions?: string[] | Record<string, boolean>
}

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const services = ref<{ value: string; label: string }[]>([])
const roles = ref<Role[]>([])

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
    const [userResponse, servicesResponse, rolesResponse] = await Promise.all([
      userService.get(route.params.id as string),
      referentielService.getServices(),
      roleService.getAll(),
    ])

    const user = userResponse.data.data || userResponse.data
    services.value = servicesResponse.data.data || servicesResponse.data
    roles.value = rolesResponse.data.data || rolesResponse.data || []

    form.value = {
      matricule: user.matricule || '',
      nom: user.nom || '',
      prenom: user.prenom || '',
      email: user.email || '',
      password: '',
      password_confirmation: '',
      telephone: user.telephone || '',
      service_id: user.service_id || user.service?.id || '',
      actif: user.actif ?? true,
      est_acheteur: user.est_acheteur ?? false,
      est_valideur: user.est_valideur ?? false,
      seuil_validation: user.seuil_validation || null,
      role_ids: user.roles?.map((r: Role) => r.id) || [],
    }
  } catch (error) {
    console.error('Erreur chargement utilisateur:', error)
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

function getRolePermissionsSummary(role: Role): string {
  if (!role.permissions) return 'Aucune permission'

  // Gérer le cas où permissions est un objet {"all": true} ou un tableau
  if (typeof role.permissions === 'object' && !Array.isArray(role.permissions)) {
    const keys = Object.keys(role.permissions)
    if (keys.length === 0) return 'Aucune permission'
    if ('all' in role.permissions) return 'Toutes les permissions'
    return `${keys.length} permission(s)`
  }

  // Cas tableau
  if (Array.isArray(role.permissions)) {
    if (role.permissions.length === 0) return 'Aucune permission'
    if (role.permissions.includes('all')) return 'Toutes les permissions'
    return `${role.permissions.length} permission(s)`
  }

  return 'Aucune permission'
}

async function submitForm() {
  saving.value = true
  errors.value = {}

  try {
    const data: any = { ...form.value }

    // Ne pas envoyer le mot de passe si vide
    if (!data.password) {
      delete data.password
      delete data.password_confirmation
    }

    if (!data.seuil_validation) {
      delete data.seuil_validation
    }

    await userService.update(route.params.id as string, data)
    router.push(`/utilisateurs/${route.params.id}`)
  } catch (error: any) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {}
    } else {
      console.error('Erreur modification utilisateur:', error)
    }
  } finally {
    saving.value = false
  }
}

function goBack() {
  router.push(`/utilisateurs/${route.params.id}`)
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div>
    <div class="flex items-center gap-4 mb-6">
      <button @click="goBack" class="p-2 hover:bg-gray-100 rounded-lg">
        <ArrowLeftIcon class="w-5 h-5" />
      </button>
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Modifier l'utilisateur</h1>
        <p class="text-gray-600 mt-1">Modifier les informations de l'utilisateur</p>
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-ct-blue-500"></div>
    </div>

    <form v-else @submit.prevent="submitForm" class="space-y-6">
      <!-- Informations personnelles -->
      <div class="card">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Informations personnelles</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="label">Matricule</label>
            <input v-model="form.matricule" type="text" class="input" placeholder="MAT001" />
            <p v-if="errors.matricule" class="text-red-500 text-sm mt-1">{{ errors.matricule[0] }}</p>
          </div>
          <div></div>
          <div>
            <label class="label">Nom <span class="text-red-500">*</span></label>
            <input v-model="form.nom" type="text" class="input" required />
            <p v-if="errors.nom" class="text-red-500 text-sm mt-1">{{ errors.nom[0] }}</p>
          </div>
          <div>
            <label class="label">Prenom <span class="text-red-500">*</span></label>
            <input v-model="form.prenom" type="text" class="input" required />
            <p v-if="errors.prenom" class="text-red-500 text-sm mt-1">{{ errors.prenom[0] }}</p>
          </div>
          <div>
            <label class="label">Email <span class="text-red-500">*</span></label>
            <input v-model="form.email" type="email" class="input" required />
            <p v-if="errors.email" class="text-red-500 text-sm mt-1">{{ errors.email[0] }}</p>
          </div>
          <div>
            <label class="label">Telephone</label>
            <input v-model="form.telephone" type="tel" class="input" placeholder="+237 6XX XXX XXX" />
            <p v-if="errors.telephone" class="text-red-500 text-sm mt-1">{{ errors.telephone[0] }}</p>
          </div>
        </div>
      </div>

      <!-- Mot de passe -->
      <div class="card">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Mot de passe</h3>
        <p class="text-sm text-gray-500 mb-4">Laissez vide pour conserver le mot de passe actuel</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="label">Nouveau mot de passe</label>
            <input v-model="form.password" type="password" class="input" />
            <p v-if="errors.password" class="text-red-500 text-sm mt-1">{{ errors.password[0] }}</p>
          </div>
          <div>
            <label class="label">Confirmer le mot de passe</label>
            <input v-model="form.password_confirmation" type="password" class="input" />
          </div>
        </div>
      </div>

      <!-- Rattachement organisationnel -->
      <div class="card">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Rattachement organisationnel</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="label">Service</label>
            <select v-model="form.service_id" class="input">
              <option value="">-- Selectionner un service --</option>
              <option v-for="s in services" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
            <p v-if="errors.service_id" class="text-red-500 text-sm mt-1">{{ errors.service_id[0] }}</p>
          </div>
        </div>
      </div>

      <!-- Droits et permissions -->
      <div class="card">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Droits et permissions</h3>
        <div class="space-y-4">
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

          <div v-if="form.est_valideur" class="mt-4">
            <label class="label">Seuil de validation (XAF)</label>
            <input v-model.number="form.seuil_validation" type="number" class="input w-64" placeholder="5000000" />
            <p class="text-sm text-gray-500 mt-1">Montant maximum que ce valideur peut approuver</p>
            <p v-if="errors.seuil_validation" class="text-red-500 text-sm mt-1">{{ errors.seuil_validation[0] }}</p>
          </div>
        </div>
      </div>

      <!-- Roles -->
      <div class="card">
        <div class="flex items-center gap-2 mb-4">
          <ShieldCheckIcon class="w-5 h-5 text-ct-blue-600" />
          <h3 class="text-lg font-semibold text-gray-900">Roles</h3>
        </div>
        <p class="text-sm text-gray-500 mb-4">Selectionnez les roles a attribuer a cet utilisateur. Les roles determinent les permissions d'acces.</p>

        <div v-if="roles.length === 0" class="text-gray-500 text-sm py-4">
          Aucun role disponible
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div
            v-for="role in roles"
            :key="role.id"
            @click="toggleRole(role.id)"
            :class="[
              'border rounded-lg p-4 cursor-pointer transition-all',
              isRoleSelected(role.id)
                ? 'border-ct-blue-500 bg-ct-blue-50 ring-1 ring-ct-blue-500'
                : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'
            ]"
          >
            <div class="flex items-start gap-3">
              <input
                type="checkbox"
                :checked="isRoleSelected(role.id)"
                @click.stop
                @change="toggleRole(role.id)"
                class="mt-1 w-4 h-4 text-ct-blue-600 rounded"
              />
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <span class="font-medium text-gray-900">{{ role.libelle }}</span>
                  <span
                    v-if="role.code === 'ADMIN'"
                    class="px-2 py-0.5 text-xs font-medium bg-red-100 text-red-700 rounded"
                  >
                    Super Admin
                  </span>
                </div>
                <p class="text-sm text-gray-500 mt-1">{{ role.description || 'Pas de description' }}</p>
                <p class="text-xs text-gray-400 mt-1">{{ getRolePermissionsSummary(role) }}</p>
              </div>
            </div>
          </div>
        </div>
        <p v-if="errors.role_ids" class="text-red-500 text-sm mt-2">{{ errors.role_ids[0] }}</p>
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-4">
        <button type="button" @click="goBack" class="btn-secondary">Annuler</button>
        <button type="submit" :disabled="saving" class="btn-primary inline-flex items-center">
          <CheckIcon class="w-5 h-5 mr-2" />
          {{ saving ? 'Enregistrement...' : 'Enregistrer les modifications' }}
        </button>
      </div>
    </form>
  </div>
</template>
