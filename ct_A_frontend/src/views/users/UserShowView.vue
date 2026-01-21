<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { userService } from '@/services/api'
import {
  ArrowLeftIcon,
  PencilIcon,
  UserIcon,
  EnvelopeIcon,
  PhoneIcon,
  MapPinIcon,
  BuildingOfficeIcon,
  ShieldCheckIcon,
  ShoppingCartIcon,
  CheckCircleIcon,
  XCircleIcon,
} from '@heroicons/vue/24/outline'

interface User {
  id: string
  matricule: string
  nom: string
  prenom: string
  nom_complet: string
  email: string
  telephone: string
  actif: boolean
  est_acheteur: boolean
  est_valideur: boolean
  seuil_validation?: number
  service?: {
    id: string
    libelle: string
    direction?: {
      id: string
      libelle: string
      zone?: {
        id: string
        libelle: string
      }
    }
  }
  created_at: string
  updated_at: string
}

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const user = ref<User | null>(null)

async function loadData() {
  loading.value = true
  try {
    const response = await userService.get(route.params.id as string)
    user.value = response.data.data || response.data
  } catch (error) {
    console.error('Erreur chargement utilisateur:', error)
  } finally {
    loading.value = false
  }
}

function goBack() {
  router.push('/utilisateurs')
}

function editUser() {
  router.push(`/utilisateurs/${route.params.id}/modifier`)
}

function formatDate(dateStr: string) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function formatMontant(montant: number | undefined) {
  if (!montant) return '-'
  return new Intl.NumberFormat('fr-FR').format(montant) + ' XAF'
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-4">
        <button @click="goBack" class="p-2 hover:bg-gray-100 rounded-lg">
          <ArrowLeftIcon class="w-5 h-5" />
        </button>
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Detail Utilisateur</h1>
          <p class="text-gray-600 mt-1">Informations completes de l'utilisateur</p>
        </div>
      </div>
      <button @click="editUser" class="btn-primary inline-flex items-center">
        <PencilIcon class="w-5 h-5 mr-2" />
        Modifier
      </button>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-ct-blue-500"></div>
    </div>

    <div v-else-if="user" class="space-y-6">
      <!-- En-tête utilisateur -->
      <div class="card">
        <div class="flex items-start gap-6">
          <div class="w-20 h-20 bg-gray-200 rounded-full flex items-center justify-center">
            <UserIcon class="w-10 h-10 text-gray-500" />
          </div>
          <div class="flex-1">
            <div class="flex items-center gap-3">
              <h2 class="text-xl font-bold text-gray-900">{{ user.nom }} {{ user.prenom }}</h2>
              <span
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                :class="user.actif ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
              >
                {{ user.actif ? 'Actif' : 'Inactif' }}
              </span>
            </div>
            <p class="text-gray-500 mt-1">{{ user.matricule || 'Sans matricule' }}</p>
            <div class="flex gap-4 mt-4">
              <span v-if="user.est_acheteur" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-purple-100 text-purple-800">
                <ShoppingCartIcon class="w-4 h-4 mr-2" />
                Acheteur
              </span>
              <span v-if="user.est_valideur" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                <ShieldCheckIcon class="w-4 h-4 mr-2" />
                Valideur
              </span>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Informations de contact -->
        <div class="card">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Informations de contact</h3>
          <div class="space-y-4">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                <EnvelopeIcon class="w-5 h-5 text-gray-500" />
              </div>
              <div>
                <p class="text-sm text-gray-500">Email</p>
                <p class="font-medium">{{ user.email }}</p>
              </div>
            </div>
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                <PhoneIcon class="w-5 h-5 text-gray-500" />
              </div>
              <div>
                <p class="text-sm text-gray-500">Telephone</p>
                <p class="font-medium">{{ user.telephone || '-' }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Rattachement organisationnel -->
        <div class="card">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Rattachement organisationnel</h3>
          <div class="space-y-4">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                <BuildingOfficeIcon class="w-5 h-5 text-gray-500" />
              </div>
              <div>
                <p class="text-sm text-gray-500">Service</p>
                <p class="font-medium">{{ user.service?.libelle || '-' }}</p>
              </div>
            </div>
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                <BuildingOfficeIcon class="w-5 h-5 text-gray-500" />
              </div>
              <div>
                <p class="text-sm text-gray-500">Direction</p>
                <p class="font-medium">{{ user.service?.direction?.libelle || '-' }}</p>
              </div>
            </div>
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                <MapPinIcon class="w-5 h-5 text-gray-500" />
              </div>
              <div>
                <p class="text-sm text-gray-500">Zone</p>
                <p class="font-medium">{{ user.service?.direction?.zone?.libelle || '-' }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Droits et permissions -->
        <div class="card">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Droits et permissions</h3>
          <div class="space-y-3">
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
              <div class="flex items-center gap-2">
                <ShoppingCartIcon class="w-5 h-5 text-gray-500" />
                <span>Acheteur</span>
              </div>
              <CheckCircleIcon v-if="user.est_acheteur" class="w-5 h-5 text-green-500" />
              <XCircleIcon v-else class="w-5 h-5 text-gray-300" />
            </div>
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
              <div class="flex items-center gap-2">
                <ShieldCheckIcon class="w-5 h-5 text-gray-500" />
                <span>Valideur</span>
              </div>
              <CheckCircleIcon v-if="user.est_valideur" class="w-5 h-5 text-green-500" />
              <XCircleIcon v-else class="w-5 h-5 text-gray-300" />
            </div>
            <div v-if="user.est_valideur && user.seuil_validation" class="p-3 bg-blue-50 rounded-lg">
              <p class="text-sm text-gray-600">Seuil de validation</p>
              <p class="font-semibold text-blue-700">{{ formatMontant(user.seuil_validation) }}</p>
            </div>
          </div>
        </div>

        <!-- Informations systeme -->
        <div class="card">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Informations systeme</h3>
          <div class="space-y-3">
            <div class="flex justify-between">
              <span class="text-gray-500">ID</span>
              <span class="font-mono text-sm">{{ user.id }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">Date de creation</span>
              <span>{{ formatDate(user.created_at) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">Derniere modification</span>
              <span>{{ formatDate(user.updated_at) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="card text-center py-12">
      <p class="text-gray-500">Utilisateur non trouve</p>
      <button @click="goBack" class="btn-primary mt-4">Retour a la liste</button>
    </div>
  </div>
</template>
