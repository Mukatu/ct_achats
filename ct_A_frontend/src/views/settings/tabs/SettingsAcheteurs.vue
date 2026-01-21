<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { userService, referentielService } from '@/services/api'
import {
  PlusIcon,
  PencilIcon,
  TrashIcon,
  XMarkIcon,
  MagnifyingGlassIcon,
  ShoppingCartIcon,
  CheckCircleIcon,
  XCircleIcon,
  ClipboardDocumentIcon,
} from '@heroicons/vue/24/outline'

interface Acheteur {
  id: string
  matricule: string
  nom: string
  prenom: string
  nom_complet: string
  email: string
  telephone: string
  service_id: string
  service?: { id: string; libelle: string; direction?: { libelle: string } }
  actif: boolean
  est_acheteur: boolean
}

const loading = ref(true)
const acheteurs = ref<Acheteur[]>([])
const allUsers = ref<Acheteur[]>([])
const services = ref<{ value: string; label: string }[]>([])
const showModal = ref(false)
const editing = ref<Acheteur | null>(null)
const saving = ref(false)
const search = ref('')
const errors = ref<Record<string, string[]>>({})
const showInactifs = ref(false)
const copiedMatricule = ref('')

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
})

// Statistiques
const stats = computed(() => ({
  total: acheteurs.value.length,
  actifs: acheteurs.value.filter(a => a.actif).length,
  inactifs: acheteurs.value.filter(a => !a.actif).length,
}))

// Filtrer les acheteurs affichés
const filteredAcheteurs = computed(() => {
  let result = acheteurs.value

  if (!showInactifs.value) {
    result = result.filter(a => a.actif)
  }

  if (search.value) {
    const s = search.value.toLowerCase()
    result = result.filter(a =>
      a.matricule?.toLowerCase().includes(s) ||
      a.nom.toLowerCase().includes(s) ||
      a.prenom.toLowerCase().includes(s) ||
      a.email.toLowerCase().includes(s)
    )
  }

  return result
})

async function loadData() {
  loading.value = true
  try {
    const [usersRes, servicesRes] = await Promise.all([
      userService.getAll({ per_page: 1000 }),
      referentielService.getServices(),
    ])

    allUsers.value = usersRes.data.data || usersRes.data
    // Filtrer uniquement les acheteurs
    acheteurs.value = allUsers.value.filter((u: Acheteur) => u.est_acheteur)
    services.value = servicesRes.data.data || servicesRes.data
  } catch (error) {
    console.error('Erreur chargement:', error)
  } finally {
    loading.value = false
  }
}

function generateMatricule(): string {
  // Générer un matricule unique basé sur le nombre d'acheteurs
  const count = acheteurs.value.length + 1
  return `ACH${String(count).padStart(3, '0')}`
}

function openCreate() {
  editing.value = null
  errors.value = {}
  form.value = {
    matricule: generateMatricule(),
    nom: '',
    prenom: '',
    email: '',
    password: '',
    password_confirmation: '',
    telephone: '',
    service_id: '',
    actif: true,
  }
  showModal.value = true
}

function openEdit(acheteur: Acheteur) {
  editing.value = acheteur
  errors.value = {}
  form.value = {
    matricule: acheteur.matricule || '',
    nom: acheteur.nom,
    prenom: acheteur.prenom,
    email: acheteur.email,
    password: '',
    password_confirmation: '',
    telephone: acheteur.telephone || '',
    service_id: acheteur.service_id || acheteur.service?.id || '',
    actif: acheteur.actif,
  }
  showModal.value = true
}

async function saveAcheteur() {
  saving.value = true
  errors.value = {}
  try {
    const data: any = {
      ...form.value,
      est_acheteur: true, // Toujours true pour un acheteur
    }

    // Ne pas envoyer le mot de passe si vide (en mode edition)
    if (editing.value && !data.password) {
      delete data.password
      delete data.password_confirmation
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

async function toggleActif(acheteur: Acheteur) {
  try {
    await userService.update(acheteur.id, { actif: !acheteur.actif })
    loadData()
  } catch (error) {
    console.error('Erreur modification statut:', error)
  }
}

async function removeAcheteur(acheteur: Acheteur) {
  if (!confirm(`Retirer "${acheteur.prenom} ${acheteur.nom}" de la liste des acheteurs ?`)) return
  try {
    await userService.update(acheteur.id, { est_acheteur: false })
    loadData()
  } catch (error) {
    console.error('Erreur suppression:', error)
  }
}

function copyMatricule(matricule: string) {
  navigator.clipboard.writeText(matricule)
  copiedMatricule.value = matricule
  setTimeout(() => { copiedMatricule.value = '' }, 2000)
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="card">
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-3">
        <div class="p-2 bg-purple-100 rounded-lg">
          <ShoppingCartIcon class="w-6 h-6 text-purple-600" />
        </div>
        <div>
          <h2 class="text-lg font-semibold text-gray-900">Acheteurs</h2>
          <p class="text-sm text-gray-500">Gerer les acheteurs et leurs matricules pour l'import</p>
        </div>
      </div>
      <button @click="openCreate" class="btn-primary inline-flex items-center">
        <PlusIcon class="w-5 h-5 mr-2" />
        Nouvel acheteur
      </button>
    </div>

    <!-- Statistiques -->
    <div class="grid grid-cols-3 gap-4 mb-6">
      <div class="p-4 bg-gray-50 rounded-lg text-center">
        <p class="text-2xl font-bold text-gray-900">{{ stats.total }}</p>
        <p class="text-sm text-gray-500">Total acheteurs</p>
      </div>
      <div class="p-4 bg-green-50 rounded-lg text-center">
        <p class="text-2xl font-bold text-green-600">{{ stats.actifs }}</p>
        <p class="text-sm text-gray-500">Actifs</p>
      </div>
      <div class="p-4 bg-gray-50 rounded-lg text-center">
        <p class="text-2xl font-bold text-gray-400">{{ stats.inactifs }}</p>
        <p class="text-sm text-gray-500">Inactifs</p>
      </div>
    </div>

    <!-- Filtres -->
    <div class="flex items-center gap-4 mb-4">
      <div class="relative flex-1 max-w-xs">
        <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
        <input
          v-model="search"
          type="text"
          placeholder="Rechercher par matricule, nom..."
          class="input pl-10"
        />
      </div>
      <label class="flex items-center gap-2 text-sm cursor-pointer">
        <input v-model="showInactifs" type="checkbox" class="w-4 h-4 text-ct-blue-600 rounded" />
        <span>Afficher les inactifs</span>
      </label>
    </div>

    <!-- Info pour l'import -->
    <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
      <p class="text-sm text-blue-800">
        <strong>Pour l'import :</strong> Utilisez le matricule de l'acheteur dans la colonne "acheteur_matricule" du fichier Excel.
      </p>
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
            <th>Telephone</th>
            <th>Service / Direction</th>
            <th>Statut</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="acheteur in filteredAcheteurs" :key="acheteur.id" class="hover:bg-gray-50">
            <td>
              <div class="flex items-center gap-2">
                <code class="px-2 py-1 bg-purple-100 text-purple-800 rounded font-mono text-sm font-semibold">
                  {{ acheteur.matricule || '-' }}
                </code>
                <button
                  v-if="acheteur.matricule"
                  @click="copyMatricule(acheteur.matricule)"
                  class="p-1 text-gray-400 hover:text-purple-600 rounded"
                  :title="copiedMatricule === acheteur.matricule ? 'Copie!' : 'Copier le matricule'"
                >
                  <ClipboardDocumentIcon class="w-4 h-4" />
                </button>
                <span v-if="copiedMatricule === acheteur.matricule" class="text-xs text-green-600">Copie!</span>
              </div>
            </td>
            <td class="font-medium">{{ acheteur.prenom }} {{ acheteur.nom }}</td>
            <td class="text-gray-600">{{ acheteur.email }}</td>
            <td class="text-gray-600">{{ acheteur.telephone || '-' }}</td>
            <td>
              <div v-if="acheteur.service">
                <div class="text-sm">{{ acheteur.service.libelle }}</div>
                <div v-if="acheteur.service.direction" class="text-xs text-gray-500">
                  {{ acheteur.service.direction.libelle }}
                </div>
              </div>
              <span v-else class="text-gray-400">-</span>
            </td>
            <td>
              <button
                @click="toggleActif(acheteur)"
                class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium transition-colors"
                :class="acheteur.actif
                  ? 'bg-green-100 text-green-800 hover:bg-green-200'
                  : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
              >
                <CheckCircleIcon v-if="acheteur.actif" class="w-3.5 h-3.5" />
                <XCircleIcon v-else class="w-3.5 h-3.5" />
                {{ acheteur.actif ? 'Actif' : 'Inactif' }}
              </button>
            </td>
            <td class="text-right">
              <div class="flex justify-end gap-1">
                <button
                  @click="openEdit(acheteur)"
                  class="p-1.5 text-gray-500 hover:text-ct-blue-600 hover:bg-gray-100 rounded"
                  title="Modifier"
                >
                  <PencilIcon class="w-4 h-4" />
                </button>
                <button
                  @click="removeAcheteur(acheteur)"
                  class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-gray-100 rounded"
                  title="Retirer des acheteurs"
                >
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="filteredAcheteurs.length === 0">
            <td colspan="7" class="text-center py-8 text-gray-500">
              {{ search ? 'Aucun acheteur correspondant' : 'Aucun acheteur configure' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal Creation/Edition -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 overflow-y-auto py-8">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4">
        <div class="flex items-center justify-between p-4 border-b">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-purple-100 rounded-lg">
              <ShoppingCartIcon class="w-5 h-5 text-purple-600" />
            </div>
            <h3 class="text-lg font-semibold">{{ editing ? 'Modifier l\'acheteur' : 'Nouvel acheteur' }}</h3>
          </div>
          <button @click="showModal = false" class="text-gray-500 hover:text-gray-700">
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="saveAcheteur" class="p-4 space-y-4">
          <!-- Matricule -->
          <div class="p-3 bg-purple-50 rounded-lg">
            <label class="label text-purple-800">Matricule acheteur <span class="text-red-500">*</span></label>
            <input
              v-model="form.matricule"
              type="text"
              class="input font-mono font-semibold text-purple-800"
              placeholder="ACH001"
              required
            />
            <p class="text-xs text-purple-600 mt-1">Ce matricule sera utilise pour l'import Excel</p>
            <p v-if="errors.matricule" class="text-red-500 text-xs mt-1">{{ errors.matricule[0] }}</p>
          </div>

          <!-- Informations personnelles -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="label">Prenom <span class="text-red-500">*</span></label>
              <input v-model="form.prenom" type="text" class="input" required />
              <p v-if="errors.prenom" class="text-red-500 text-xs mt-1">{{ errors.prenom[0] }}</p>
            </div>
            <div>
              <label class="label">Nom <span class="text-red-500">*</span></label>
              <input v-model="form.nom" type="text" class="input" required />
              <p v-if="errors.nom" class="text-red-500 text-xs mt-1">{{ errors.nom[0] }}</p>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
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

          <div>
            <label class="label">Service</label>
            <select v-model="form.service_id" class="input">
              <option value="">-- Selectionner un service --</option>
              <option v-for="s in services" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
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

          <!-- Statut -->
          <div class="border-t pt-4">
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="form.actif" type="checkbox" class="w-4 h-4 text-ct-blue-600 rounded" />
              <span>Acheteur actif</span>
            </label>
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
