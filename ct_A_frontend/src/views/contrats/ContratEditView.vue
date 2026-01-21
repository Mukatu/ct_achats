<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { contratService, referentielService } from '@/services/api'
import type { SelectOption } from '@/types'
import { ArrowLeftIcon, CheckIcon } from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const typesContrat = ref<SelectOption[]>([])
const periodicites = ref<SelectOption[]>([])
const fournisseurs = ref<SelectOption[]>([])
const zones = ref<SelectOption[]>([])
const directions = ref<SelectOption[]>([])
const services = ref<SelectOption[]>([])
const responsables = ref<SelectOption[]>([])

const form = ref({
  type_contrat_id: '',
  fournisseur_id: '',
  reference_externe: '',
  zone_id: '',
  direction_id: '',
  service_id: '',
  objet: '',
  description: '',
  date_signature: '',
  date_debut: '',
  date_fin: '',
  reconduction_tacite: false,
  preavis_jours: null as number | null,
  periodicite: 'MENSUEL',
  montant_periodique: null as number | null,
  taux_tva: 19.25,
  tva_incluse: false,
  conditions_paiement: '',
  jour_facturation: 1,
  responsable_id: '',
  contact_fournisseur: '',
  commentaire: '',
})

const montantAnnuelEstime = computed(() => {
  if (!form.value.montant_periodique || !form.value.periodicite) return 0
  const multiplicateurs: Record<string, number> = {
    'MENSUEL': 12,
    'TRIMESTRIEL': 4,
    'SEMESTRIEL': 2,
    'ANNUEL': 1,
    'PONCTUEL': 1,
  }
  return form.value.montant_periodique * (multiplicateurs[form.value.periodicite] || 1)
})

async function loadReferentiels() {
  try {
    const [typesRes, periodicitesRes, fournisseursRes, zonesRes, directionsRes, servicesRes, usersRes] = await Promise.all([
      contratService.getTypesContrat(),
      contratService.getPeriodicites(),
      referentielService.getFournisseurs(),
      referentielService.getZones(),
      referentielService.getDirections(),
      referentielService.getServices(),
      referentielService.getUsers(),
    ])
    typesContrat.value = typesRes.data.map((t: any) => ({ value: t.id, label: t.libelle }))
    periodicites.value = periodicitesRes.data.map((p: any) => ({ value: p.value, label: p.label }))
    fournisseurs.value = fournisseursRes.data.map((f: any) => ({ value: f.id, label: f.raison_sociale || f.sigle }))
    zones.value = zonesRes.data.map((z: any) => ({ value: z.id, label: z.libelle }))
    directions.value = directionsRes.data.map((d: any) => ({ value: d.id, label: d.libelle }))
    services.value = servicesRes.data.map((s: any) => ({ value: s.id, label: s.libelle }))
    responsables.value = usersRes.data.map((u: any) => ({ value: u.id, label: `${u.prenom} ${u.nom}` }))
  } catch (error) {
    console.error('Erreur chargement referentiels:', error)
  }
}

async function loadContrat() {
  loading.value = true
  try {
    const response = await contratService.get(route.params.id as string)
    const contrat = response.data
    form.value = {
      type_contrat_id: contrat.type_contrat_id || '',
      fournisseur_id: contrat.fournisseur_id || '',
      reference_externe: contrat.reference_externe || '',
      zone_id: contrat.zone_id || '',
      direction_id: contrat.direction_id || '',
      service_id: contrat.service_id || '',
      objet: contrat.objet || '',
      description: contrat.description || '',
      date_signature: contrat.date_signature?.split('T')[0] || '',
      date_debut: contrat.date_debut?.split('T')[0] || '',
      date_fin: contrat.date_fin?.split('T')[0] || '',
      reconduction_tacite: contrat.reconduction_tacite || false,
      preavis_jours: contrat.preavis_jours || null,
      periodicite: contrat.periodicite || 'MENSUEL',
      montant_periodique: contrat.montant_periodique || null,
      taux_tva: contrat.taux_tva || 19.25,
      tva_incluse: contrat.tva_incluse || false,
      conditions_paiement: contrat.conditions_paiement || '',
      jour_facturation: contrat.jour_facturation || 1,
      responsable_id: contrat.responsable_id || '',
      contact_fournisseur: contrat.contact_fournisseur || '',
      commentaire: contrat.commentaire || '',
    }
  } catch (error) {
    console.error('Erreur chargement contrat:', error)
  } finally {
    loading.value = false
  }
}

async function submitForm() {
  saving.value = true
  errors.value = {}

  try {
    const data = { ...form.value }
    if (!data.zone_id) delete (data as any).zone_id
    if (!data.service_id) delete (data as any).service_id
    if (!data.responsable_id) delete (data as any).responsable_id
    if (!data.date_fin) delete (data as any).date_fin
    if (!data.preavis_jours) delete (data as any).preavis_jours

    await contratService.update(route.params.id as string, data)
    router.push(`/contrats/${route.params.id}`)
  } catch (error: any) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {}
    } else {
      alert(error.response?.data?.message || 'Erreur lors de la mise a jour')
    }
  } finally {
    saving.value = false
  }
}

function goBack() {
  router.push(`/contrats/${route.params.id}`)
}

onMounted(async () => {
  await loadReferentiels()
  await loadContrat()
})
</script>

<template>
  <div>
    <div class="flex items-center gap-4 mb-6">
      <button @click="goBack" class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg">
        <ArrowLeftIcon class="w-5 h-5" />
      </button>
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Modifier le contrat</h1>
        <p class="text-gray-600 mt-1">Modifiez les informations du contrat</p>
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-ct-blue-500"></div>
    </div>

    <form v-else @submit.prevent="submitForm" class="space-y-6">
      <!-- Informations generales -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Informations generales</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="label">Type de contrat *</label>
            <select v-model="form.type_contrat_id" class="input" :class="{ 'border-red-500': errors.type_contrat_id }">
              <option value="">Selectionnez un type</option>
              <option v-for="t in typesContrat" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
          </div>
          <div>
            <label class="label">Fournisseur *</label>
            <select v-model="form.fournisseur_id" class="input" :class="{ 'border-red-500': errors.fournisseur_id }">
              <option value="">Selectionnez un fournisseur</option>
              <option v-for="f in fournisseurs" :key="f.value" :value="f.value">{{ f.label }}</option>
            </select>
          </div>
          <div>
            <label class="label">Reference externe</label>
            <input v-model="form.reference_externe" type="text" class="input" />
          </div>
          <div>
            <label class="label">Contact fournisseur</label>
            <input v-model="form.contact_fournisseur" type="text" class="input" />
          </div>
          <div class="md:col-span-2">
            <label class="label">Objet du contrat *</label>
            <input v-model="form.objet" type="text" class="input" :class="{ 'border-red-500': errors.objet }" />
          </div>
          <div class="md:col-span-2">
            <label class="label">Description detaillee</label>
            <textarea v-model="form.description" class="input" rows="3"></textarea>
          </div>
        </div>
      </div>

      <!-- Localisation -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Localisation</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="label">Zone</label>
            <select v-model="form.zone_id" class="input">
              <option value="">Selectionnez une zone</option>
              <option v-for="z in zones" :key="z.value" :value="z.value">{{ z.label }}</option>
            </select>
          </div>
          <div>
            <label class="label">Direction *</label>
            <select v-model="form.direction_id" class="input" :class="{ 'border-red-500': errors.direction_id }">
              <option value="">Selectionnez une direction</option>
              <option v-for="d in directions" :key="d.value" :value="d.value">{{ d.label }}</option>
            </select>
          </div>
          <div>
            <label class="label">Service</label>
            <select v-model="form.service_id" class="input">
              <option value="">Selectionnez un service</option>
              <option v-for="s in services" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
          </div>
          <div>
            <label class="label">Responsable interne</label>
            <select v-model="form.responsable_id" class="input">
              <option value="">Selectionnez un responsable</option>
              <option v-for="r in responsables" :key="r.value" :value="r.value">{{ r.label }}</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Duree -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Duree du contrat</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="label">Date de signature *</label>
            <input v-model="form.date_signature" type="date" class="input" />
          </div>
          <div>
            <label class="label">Date de debut *</label>
            <input v-model="form.date_debut" type="date" class="input" />
          </div>
          <div>
            <label class="label">Date de fin</label>
            <input v-model="form.date_fin" type="date" class="input" />
          </div>
          <div>
            <label class="flex items-center gap-2 cursor-pointer mt-6">
              <input type="checkbox" v-model="form.reconduction_tacite" class="w-4 h-4 text-ct-blue-600 rounded" />
              <span class="text-sm text-gray-700">Reconduction tacite</span>
            </label>
          </div>
          <div v-if="form.date_fin">
            <label class="label">Preavis (jours)</label>
            <input v-model.number="form.preavis_jours" type="number" min="0" class="input" />
          </div>
        </div>
      </div>

      <!-- Montants -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Montants et facturation</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="label">Periodicite *</label>
            <select v-model="form.periodicite" class="input">
              <option v-for="p in periodicites" :key="p.value" :value="p.value">{{ p.label }}</option>
            </select>
          </div>
          <div>
            <label class="label">Montant periodique (FCFA) *</label>
            <input v-model.number="form.montant_periodique" type="number" min="0" class="input" />
          </div>
          <div>
            <label class="label">Montant annuel estime</label>
            <div class="input bg-gray-50 text-gray-700">
              {{ new Intl.NumberFormat('fr-FR').format(montantAnnuelEstime) }} FCFA
            </div>
          </div>
          <div>
            <label class="label">Taux TVA (%)</label>
            <input v-model.number="form.taux_tva" type="number" step="0.01" min="0" max="100" class="input" />
          </div>
          <div>
            <label class="flex items-center gap-2 cursor-pointer mt-6">
              <input type="checkbox" v-model="form.tva_incluse" class="w-4 h-4 text-ct-blue-600 rounded" />
              <span class="text-sm text-gray-700">TVA incluse</span>
            </label>
          </div>
          <div>
            <label class="label">Jour de facturation</label>
            <input v-model.number="form.jour_facturation" type="number" min="1" max="31" class="input" />
          </div>
          <div class="md:col-span-3">
            <label class="label">Conditions de paiement</label>
            <input v-model="form.conditions_paiement" type="text" class="input" />
          </div>
        </div>
      </div>

      <!-- Commentaire -->
      <div class="card">
        <label class="label">Commentaire</label>
        <textarea v-model="form.commentaire" class="input" rows="2"></textarea>
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-4">
        <button type="button" @click="goBack" class="btn-secondary">Annuler</button>
        <button type="submit" :disabled="saving" class="btn-primary inline-flex items-center">
          <CheckIcon v-if="!saving" class="w-5 h-5 mr-2" />
          {{ saving ? 'Enregistrement...' : 'Enregistrer' }}
        </button>
      </div>
    </form>
  </div>
</template>
