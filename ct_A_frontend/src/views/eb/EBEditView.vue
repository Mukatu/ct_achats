<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ebService, referentielService } from '@/services/api'
import { ArrowLeftIcon, CheckIcon } from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const submitting = ref(false)
const errors = ref<Record<string, string[]>>({})

// Référentiels
const zones = ref<any[]>([])
const directions = ref<any[]>([])
const services = ref<any[]>([])
const users = ref<any[]>([])
const fournisseurs = ref<any[]>([])

// Formulaire
const form = ref({
  numero: '',
  date_expression: '',
  zone_id: '',
  direction_id: '',
  service_id: '',
  demandeur_id: '',
  demandeur_nom: '',
  objet: '',
  description_detaillee: '',
  quantite_souhaitee: '',
  date_besoin: '',
  estimation: '',
  fournisseur_suggere_id: '',
  commentaire: '',
})

// Filtrer les directions par zone
const filteredDirections = computed(() => {
  if (!form.value.zone_id) return directions.value
  return directions.value.filter((d: any) => d.zone_id === form.value.zone_id)
})

// Filtrer les services par direction
const filteredServices = computed(() => {
  if (!form.value.direction_id) return services.value
  return services.value.filter((s: any) => s.direction_id === form.value.direction_id)
})

// Filtrer les utilisateurs par service ou direction
const filteredUsers = computed(() => {
  if (form.value.service_id) {
    return users.value.filter((u: any) => u.service_id === form.value.service_id)
  }
  if (form.value.direction_id) {
    const serviceIds = services.value
      .filter((s: any) => s.direction_id === form.value.direction_id)
      .map((s: any) => s.id)
    return users.value.filter((u: any) => serviceIds.includes(u.service_id))
  }
  return users.value
})

async function loadData() {
  loading.value = true
  try {
    const [ebRes, zonesRes, directionsRes, servicesRes, usersRes, fournisseursRes] = await Promise.all([
      ebService.get(route.params.id as string),
      referentielService.getZones(),
      referentielService.getDirections(),
      referentielService.getServices(),
      referentielService.getUsers(),
      referentielService.getFournisseurs(),
    ])

    zones.value = zonesRes.data
    directions.value = directionsRes.data
    services.value = servicesRes.data
    users.value = usersRes.data?.data || usersRes.data || []
    fournisseurs.value = fournisseursRes.data

    const eb = ebRes.data.data || ebRes.data
    form.value = {
      numero: eb.numero || '',
      date_expression: eb.date_expression?.split('T')[0] || '',
      zone_id: eb.zone_id || '',
      direction_id: eb.direction_id || '',
      service_id: eb.service_id || '',
      demandeur_id: eb.demandeur_id || '',
      demandeur_nom: eb.demandeur_nom || '',
      objet: eb.objet || '',
      description_detaillee: eb.description_detaillee || '',
      quantite_souhaitee: eb.quantite_souhaitee || '',
      date_besoin: eb.date_besoin || '',
      estimation: eb.estimation?.toString() || '',
      fournisseur_suggere_id: eb.fournisseur_suggere_id || '',
      commentaire: eb.commentaire || '',
    }
  } catch (error) {
    console.error('Erreur chargement EB:', error)
    router.push('/expressions-besoin')
  } finally {
    loading.value = false
  }
}

function onZoneChange() {
  form.value.direction_id = ''
  form.value.service_id = ''
  form.value.demandeur_id = ''
}

function onDirectionChange() {
  form.value.service_id = ''
  form.value.demandeur_id = ''
}

function onServiceChange() {
  form.value.demandeur_id = ''
}

async function submit() {
  errors.value = {}
  submitting.value = true

  try {
    const payload = {
      ...form.value,
      estimation: form.value.estimation ? parseInt(form.value.estimation) : null,
      service_id: form.value.service_id || null,
      demandeur_id: form.value.demandeur_id || null,
      demandeur_nom: form.value.demandeur_nom || null,
      fournisseur_suggere_id: form.value.fournisseur_suggere_id || null,
    }

    await ebService.update(route.params.id as string, payload)

    router.push({
      name: 'eb-show',
      params: { id: route.params.id },
    })
  } catch (error: any) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {}
    } else {
      console.error('Erreur modification EB:', error)
      alert('Une erreur est survenue lors de la modification')
    }
  } finally {
    submitting.value = false
  }
}

function goBack() {
  router.push({ name: 'eb-show', params: { id: route.params.id } })
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="flex items-center gap-4 mb-6">
      <button @click="goBack" class="p-2 hover:bg-gray-100 rounded-lg">
        <ArrowLeftIcon class="w-5 h-5 text-gray-600" />
      </button>
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Modifier l'Expression de Besoin</h1>
        <p class="text-gray-600 mt-1">Modifiez les informations de cette expression de besoin</p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-ct-blue-500"></div>
    </div>

    <!-- Form -->
    <form v-else @submit.prevent="submit" class="space-y-6">
      <!-- Référence et Date -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Référence</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="label">Numéro de référence <span class="text-red-500">*</span></label>
            <input
              v-model="form.numero"
              type="text"
              class="input"
              placeholder="EB-2026-00001"
              required
            />
            <p class="text-xs text-gray-500 mt-1">Modifiable si nécessaire (ex: pour correspondre à l'EB physique)</p>
            <p v-if="errors.numero" class="text-red-500 text-sm mt-1">{{ errors.numero[0] }}</p>
          </div>
          <div>
            <label class="label">Date d'expression</label>
            <input
              v-model="form.date_expression"
              type="date"
              class="input"
            />
            <p class="text-xs text-gray-500 mt-1">Date de l'EB physique</p>
          </div>
        </div>
      </div>

      <!-- Localisation -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Localisation</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="label">Zone <span class="text-red-500">*</span></label>
            <select v-model="form.zone_id" @change="onZoneChange" class="input" required>
              <option value="">Sélectionner une zone</option>
              <option v-for="z in zones" :key="z.id" :value="z.id">
                {{ z.libelle }}
              </option>
            </select>
            <p v-if="errors.zone_id" class="text-red-500 text-sm mt-1">{{ errors.zone_id[0] }}</p>
          </div>

          <div>
            <label class="label">Direction <span class="text-red-500">*</span></label>
            <select v-model="form.direction_id" @change="onDirectionChange" class="input" required>
              <option value="">Sélectionner une direction</option>
              <option v-for="d in filteredDirections" :key="d.id" :value="d.id">
                {{ d.libelle_court || d.libelle }}
              </option>
            </select>
            <p v-if="errors.direction_id" class="text-red-500 text-sm mt-1">{{ errors.direction_id[0] }}</p>
          </div>

          <div>
            <label class="label">Service</label>
            <select v-model="form.service_id" @change="onServiceChange" class="input">
              <option value="">Sélectionner un service</option>
              <option v-for="s in filteredServices" :key="s.id" :value="s.id">
                {{ s.libelle }}
              </option>
            </select>
          </div>
        </div>

        <!-- Demandeur -->
        <div class="mt-4 pt-4 border-t border-gray-200">
          <h3 class="text-sm font-medium text-gray-700 mb-3">Demandeur (employé ayant rempli l'EB physique)</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="label">Sélectionner un employé</label>
              <select v-model="form.demandeur_id" class="input">
                <option value="">-- Sélectionner --</option>
                <option v-for="u in filteredUsers" :key="u.id" :value="u.id">
                  {{ u.nom }} {{ u.prenom }} {{ u.matricule ? `(${u.matricule})` : '' }}
                </option>
              </select>
            </div>
            <div>
              <label class="label">Ou saisir le nom manuellement</label>
              <input
                v-model="form.demandeur_nom"
                type="text"
                class="input"
                placeholder="Nom et prénom du demandeur"
                :disabled="!!form.demandeur_id"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Description du besoin -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Description du besoin</h2>
        <div class="space-y-4">
          <div>
            <label class="label">Objet <span class="text-red-500">*</span></label>
            <input
              v-model="form.objet"
              type="text"
              class="input"
              placeholder="Décrivez brièvement votre besoin..."
              maxlength="500"
              required
            />
            <p class="text-xs text-gray-500 mt-1">{{ form.objet.length }}/500 caractères</p>
            <p v-if="errors.objet" class="text-red-500 text-sm mt-1">{{ errors.objet[0] }}</p>
          </div>

          <div>
            <label class="label">Description détaillée</label>
            <textarea
              v-model="form.description_detaillee"
              class="input"
              rows="4"
              placeholder="Détaillez les spécifications, quantités, caractéristiques techniques..."
            ></textarea>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="label">Quantité souhaitée</label>
              <input
                v-model="form.quantite_souhaitee"
                type="text"
                class="input"
                placeholder="Ex: 10 unités, 100 mètres..."
              />
            </div>

            <div>
              <label class="label">Date de besoin souhaitée</label>
              <input
                v-model="form.date_besoin"
                type="date"
                class="input"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Estimation et fournisseur -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Budget et fournisseur</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="label">Estimation budgétaire (FCFA)</label>
            <input
              v-model="form.estimation"
              type="number"
              class="input"
              placeholder="Ex: 5000000"
              min="0"
              step="1"
            />
            <p class="text-xs text-gray-500 mt-1">Montant estimé en Francs CFA</p>
          </div>

          <div>
            <label class="label">Fournisseur suggéré</label>
            <select v-model="form.fournisseur_suggere_id" class="input">
              <option value="">Aucun fournisseur suggéré</option>
              <option v-for="f in fournisseurs" :key="f.value" :value="f.value">
                {{ f.label }}
              </option>
            </select>
          </div>
        </div>

        <div class="mt-4">
          <label class="label">Commentaire</label>
          <textarea
            v-model="form.commentaire"
            class="input"
            rows="2"
            placeholder="Informations complémentaires, urgence, contexte..."
          ></textarea>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-4">
        <button type="button" @click="goBack" class="btn-secondary">
          Annuler
        </button>
        <button type="submit" :disabled="submitting" class="btn-primary inline-flex items-center">
          <span v-if="submitting" class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></span>
          <CheckIcon v-else class="w-5 h-5 mr-2" />
          {{ submitting ? 'Enregistrement...' : 'Enregistrer les modifications' }}
        </button>
      </div>
    </form>
  </div>
</template>
