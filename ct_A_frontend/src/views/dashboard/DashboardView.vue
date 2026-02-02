<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { dashboardService, statistiquesService } from '@/services/api'
import { formatMontant } from '@/types'
import {
  DocumentTextIcon,
  ClipboardDocumentListIcon,
  ShoppingCartIcon,
  ArrowTrendingUpIcon,
  ClockIcon,
  CheckCircleIcon,
  XCircleIcon,
  ChartBarIcon,
  BanknotesIcon,
  DocumentCheckIcon,
  CalendarDaysIcon,
  DocumentDuplicateIcon,
  ExclamationTriangleIcon,
  ArrowUpTrayIcon,
} from '@heroicons/vue/24/outline'
import ImportModal from '@/components/ImportModal.vue'

const authStore = useAuthStore()
const loading = ref(true)
const stats = ref<any>(null)
const annee = ref(new Date().getFullYear())
const anneesDisponibles = ref<number[]>([])
const showImportModal = ref(false)

async function loadData() {
  loading.value = true
  try {
    const response = await dashboardService.getStats(annee.value)
    stats.value = response.data
  } catch (error) {
    console.error('Erreur chargement dashboard:', error)
  } finally {
    loading.value = false
  }
}

// Recharger quand l'année change
watch(annee, () => {
  loadData()
})

onMounted(async () => {
  try {
    // Charger les années disponibles
    const anneesRes = await statistiquesService.getAnneesDisponibles()
    anneesDisponibles.value = anneesRes.data.annees || [new Date().getFullYear()]

    // Charger les données du dashboard
    await loadData()
  } catch (error) {
    console.error('Erreur chargement dashboard:', error)
    loading.value = false
  }
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">
          Bonjour, {{ authStore.user?.prenom }} 👋
        </h1>
        <p class="text-gray-600 mt-1">
          Voici un aperçu de votre activité
        </p>
      </div>
      <div class="flex items-center gap-4">
        <button
          @click="showImportModal = true"
          class="btn btn-secondary flex items-center"
        >
          <ArrowUpTrayIcon class="w-5 h-5 mr-2" />
          Import CSV
        </button>
        <div class="flex items-center gap-2">
          <CalendarDaysIcon class="w-5 h-5 text-gray-400" />
          <select v-model="annee" class="input w-32">
            <option v-for="a in anneesDisponibles" :key="a" :value="a">{{ a }}</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Modal import engagements -->
    <ImportModal
      :show="showImportModal"
      type="engagements"
      @close="showImportModal = false"
      @success="loadData()"
    />

    <!-- Loading state -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-ct-blue-500"></div>
    </div>

    <template v-else>
      <!-- Stats cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
        <div class="card">
          <div class="flex items-center">
            <div class="p-3 rounded-lg bg-blue-100">
              <DocumentTextIcon class="w-6 h-6 text-blue-600" />
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-500">Expressions Besoins</p>
              <p class="text-2xl font-bold text-gray-900">
                {{ stats?.totaux?.eb_total || 0 }}
              </p>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="flex items-center">
            <div class="p-3 rounded-lg bg-yellow-100">
              <ClipboardDocumentListIcon class="w-6 h-6 text-yellow-600" />
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-500">Demandes d'Achat</p>
              <p class="text-2xl font-bold text-gray-900">
                {{ stats?.totaux?.da_total || 0 }}
              </p>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="flex items-center">
            <div class="p-3 rounded-lg bg-orange-100">
              <BanknotesIcon class="w-6 h-6 text-orange-600" />
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-500">DA Caisse</p>
              <p class="text-2xl font-bold text-gray-900">
                {{ stats?.totaux?.dac_total || 0 }}
              </p>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="flex items-center">
            <div class="p-3 rounded-lg bg-green-100">
              <ShoppingCartIcon class="w-6 h-6 text-green-600" />
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-500">Bons de Commande</p>
              <p class="text-2xl font-bold text-gray-900">
                {{ stats?.totaux?.bc_total || 0 }}
              </p>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="flex items-center">
            <div class="p-3 rounded-lg bg-teal-100">
              <DocumentDuplicateIcon class="w-6 h-6 text-teal-600" />
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-500">Contrats actifs</p>
              <p class="text-2xl font-bold text-gray-900">
                {{ stats?.totaux?.contrats_actifs || 0 }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Détail des engagements -->
      <div class="card mb-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Repartition des engagements ({{ stats?.annee }})</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div class="p-4 bg-green-50 rounded-lg">
            <div class="flex items-center justify-between">
              <div class="flex items-center">
                <ShoppingCartIcon class="w-5 h-5 text-green-600 mr-2" />
                <span class="text-sm font-medium text-gray-700">Engagements BC</span>
              </div>
              <span class="text-lg font-bold text-green-700">{{ formatMontant(stats?.totaux?.montant_engage_bc) }}</span>
            </div>
            <p class="text-xs text-gray-500 mt-1">Bons de commande valides</p>
          </div>
          <div class="p-4 bg-orange-50 rounded-lg">
            <div class="flex items-center justify-between">
              <div class="flex items-center">
                <BanknotesIcon class="w-5 h-5 text-orange-600 mr-2" />
                <span class="text-sm font-medium text-gray-700">Engagements DAC</span>
              </div>
              <span class="text-lg font-bold text-orange-700">{{ formatMontant(stats?.totaux?.montant_engage_dac) }}</span>
            </div>
            <p class="text-xs text-gray-500 mt-1">Depenses caisse traitees</p>
          </div>
          <div class="p-4 bg-teal-50 rounded-lg">
            <div class="flex items-center justify-between">
              <div class="flex items-center">
                <DocumentDuplicateIcon class="w-5 h-5 text-teal-600 mr-2" />
                <span class="text-sm font-medium text-gray-700">Engagements Contrats</span>
              </div>
              <span class="text-lg font-bold text-teal-700">{{ formatMontant(stats?.totaux?.montant_engage_contrat) }}</span>
            </div>
            <p class="text-xs text-gray-500 mt-1">Contrats actifs annuels</p>
          </div>
          <div class="p-4 bg-purple-50 rounded-lg">
            <div class="flex items-center justify-between">
              <div class="flex items-center">
                <ArrowTrendingUpIcon class="w-5 h-5 text-purple-600 mr-2" />
                <span class="text-sm font-medium text-gray-700">Total engage</span>
              </div>
              <span class="text-lg font-bold text-purple-700">{{ formatMontant(stats?.totaux?.montant_engage) }}</span>
            </div>
            <p class="text-xs text-gray-500 mt-1">BC + DAC + Contrats</p>
          </div>
        </div>
      </div>

      <!-- Statistiques EB - Aboutissement -->
      <div v-if="stats?.stats_eb" class="card mb-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
          <DocumentTextIcon class="w-5 h-5 mr-2 text-blue-500" />
          Statistiques des Expressions de Besoins ({{ stats?.annee }})
        </h2>

        <!-- Indicateurs principaux -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
          <div class="text-center p-4 bg-gray-50 rounded-lg">
            <p class="text-3xl font-bold text-gray-900">{{ stats.stats_eb.total }}</p>
            <p class="text-sm text-gray-500">Total EB</p>
          </div>
          <div class="text-center p-4 bg-green-50 rounded-lg">
            <p class="text-3xl font-bold text-green-600">{{ stats.stats_eb.aboutis }}</p>
            <p class="text-sm text-gray-500">Traites</p>
          </div>
          <div class="text-center p-4 bg-red-50 rounded-lg">
            <p class="text-3xl font-bold text-red-600">{{ stats.stats_eb.annules || 0 }}</p>
            <p class="text-sm text-gray-500">Annules</p>
          </div>
          <div class="text-center p-4 bg-cyan-50 rounded-lg">
            <p class="text-3xl font-bold text-cyan-600">{{ stats.stats_eb.en_cours_ach || 0 }}</p>
            <p class="text-sm text-gray-500">En cours ACH</p>
          </div>
          <div class="text-center p-4 bg-yellow-50 rounded-lg">
            <p class="text-3xl font-bold text-yellow-600">{{ stats.stats_eb.en_suspens || 0 }}</p>
            <p class="text-sm text-gray-500">En suspens</p>
          </div>
        </div>

        <!-- Taux d'aboutissement et délai moyen -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="p-4 border border-gray-200 rounded-lg">
            <div class="flex items-center justify-between mb-3">
              <span class="text-sm font-medium text-gray-700">Taux d'aboutissement</span>
              <span class="text-2xl font-bold" :class="stats.stats_eb.taux_aboutissement >= 80 ? 'text-green-600' : stats.stats_eb.taux_aboutissement >= 50 ? 'text-yellow-600' : 'text-red-600'">
                {{ stats.stats_eb.taux_aboutissement }}%
              </span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-3">
              <div
                class="h-3 rounded-full transition-all duration-500"
                :class="stats.stats_eb.taux_aboutissement >= 80 ? 'bg-green-500' : stats.stats_eb.taux_aboutissement >= 50 ? 'bg-yellow-500' : 'bg-red-500'"
                :style="{ width: stats.stats_eb.taux_aboutissement + '%' }"
              ></div>
            </div>
            <p class="text-xs text-gray-500 mt-2">Sur les EB termines (hors en cours)</p>
          </div>

          <div class="p-4 border border-gray-200 rounded-lg">
            <div class="flex items-center justify-between mb-3">
              <span class="text-sm font-medium text-gray-700">Delai moyen de traitement</span>
              <span class="text-2xl font-bold text-blue-600">
                {{ stats.stats_eb.delai_moyen_jours ? stats.stats_eb.delai_moyen_jours + ' j' : '-' }}
              </span>
            </div>
            <p class="text-xs text-gray-500">Entre la creation et le traitement</p>
          </div>
        </div>

        <!-- Montants par catégorie -->
        <div class="mt-6 pt-6 border-t border-gray-200">
          <h3 class="text-sm font-medium text-gray-700 mb-4">Montants estimes par categorie</h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
              <div class="flex items-center">
                <CheckCircleIcon class="w-5 h-5 text-green-500 mr-2" />
                <span class="text-sm text-gray-700">Traites</span>
              </div>
              <span class="font-semibold text-green-700">{{ formatMontant(stats.stats_eb.montants?.aboutis) }}</span>
            </div>
            <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
              <div class="flex items-center">
                <ClockIcon class="w-5 h-5 text-blue-500 mr-2" />
                <span class="text-sm text-gray-700">En cours</span>
              </div>
              <span class="font-semibold text-blue-700">{{ formatMontant(stats.stats_eb.montants?.en_cours) }}</span>
            </div>
            <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg">
              <div class="flex items-center">
                <XCircleIcon class="w-5 h-5 text-red-500 mr-2" />
                <span class="text-sm text-gray-700">Annules</span>
              </div>
              <span class="font-semibold text-red-700">{{ formatMontant(stats.stats_eb.montants?.annules) }}</span>
            </div>
          </div>
        </div>

        <!-- Répartition par Acheteur -->
        <div v-if="stats.stats_eb.repartition_acheteurs?.length" class="mt-6 pt-6 border-t border-gray-200">
          <h3 class="text-sm font-medium text-gray-700 mb-4">Repartition par Acheteur (en cours)</h3>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-gray-200">
                  <th class="text-left py-2 font-medium text-gray-600">Acheteur</th>
                  <th class="text-right py-2 font-medium text-gray-600">Nombre</th>
                  <th class="text-right py-2 font-medium text-gray-600">Montant</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in stats.stats_eb.repartition_acheteurs" :key="item.acheteur_id" class="border-b border-gray-100">
                  <td class="py-2">{{ item.acheteur }}</td>
                  <td class="text-right py-2 font-medium">{{ item.nombre }}</td>
                  <td class="text-right py-2 text-gray-600">{{ formatMontant(item.montant) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Statistiques DA - Aboutissement -->
      <div v-if="stats?.stats_da" class="card mb-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
          <ClipboardDocumentListIcon class="w-5 h-5 mr-2 text-yellow-500" />
          Statistiques des Demandes d'Achat ({{ stats?.annee }})
        </h2>

        <!-- Indicateurs principaux -->
        <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
          <div class="text-center p-4 bg-gray-50 rounded-lg">
            <p class="text-3xl font-bold text-gray-900">{{ stats.stats_da.total }}</p>
            <p class="text-sm text-gray-500">Total DA</p>
          </div>
          <div class="text-center p-4 bg-yellow-50 rounded-lg">
            <p class="text-3xl font-bold text-yellow-600">{{ stats.stats_da.en_suspens || 0 }}</p>
            <p class="text-sm text-gray-500">En suspens</p>
          </div>
          <div class="text-center p-4 bg-cyan-50 rounded-lg">
            <p class="text-3xl font-bold text-cyan-600">{{ stats.stats_da.en_cours_ach || 0 }}</p>
            <p class="text-sm text-gray-500">En cours ACH</p>
          </div>
          <div class="text-center p-4 bg-blue-50 rounded-lg">
            <p class="text-3xl font-bold text-blue-600">{{ stats.stats_da.cdg || 0 }}</p>
            <p class="text-sm text-gray-500">CDG</p>
          </div>
          <div class="text-center p-4 bg-purple-50 rounded-lg">
            <p class="text-3xl font-bold text-purple-600">{{ stats.stats_da.dfc || 0 }}</p>
            <p class="text-sm text-gray-500">DFC</p>
          </div>
          <div class="text-center p-4 bg-green-50 rounded-lg">
            <p class="text-3xl font-bold text-green-600">{{ stats.stats_da.aboutis }}</p>
            <p class="text-sm text-gray-500">Traitees</p>
          </div>
        </div>

        <!-- Taux d'aboutissement et délai moyen -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="p-4 border border-gray-200 rounded-lg">
            <div class="flex items-center justify-between mb-3">
              <span class="text-sm font-medium text-gray-700">Taux d'aboutissement</span>
              <span class="text-2xl font-bold" :class="stats.stats_da.taux_aboutissement >= 80 ? 'text-green-600' : stats.stats_da.taux_aboutissement >= 50 ? 'text-yellow-600' : 'text-red-600'">
                {{ stats.stats_da.taux_aboutissement }}%
              </span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-3">
              <div
                class="h-3 rounded-full transition-all duration-500"
                :class="stats.stats_da.taux_aboutissement >= 80 ? 'bg-green-500' : stats.stats_da.taux_aboutissement >= 50 ? 'bg-yellow-500' : 'bg-red-500'"
                :style="{ width: stats.stats_da.taux_aboutissement + '%' }"
              ></div>
            </div>
            <p class="text-xs text-gray-500 mt-2">Sur les DA terminees (hors en cours)</p>
          </div>

          <div class="p-4 border border-gray-200 rounded-lg">
            <div class="flex items-center justify-between mb-3">
              <span class="text-sm font-medium text-gray-700">Delai moyen de traitement</span>
              <span class="text-2xl font-bold text-yellow-600">
                {{ stats.stats_da.delai_moyen_jours ? stats.stats_da.delai_moyen_jours + ' j' : '-' }}
              </span>
            </div>
            <p class="text-xs text-gray-500">Entre la creation et le traitement</p>
          </div>
        </div>

        <!-- Montants par catégorie -->
        <div class="mt-6 pt-6 border-t border-gray-200">
          <h3 class="text-sm font-medium text-gray-700 mb-4">Montants estimes par categorie</h3>
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg">
              <div class="flex items-center">
                <ClockIcon class="w-5 h-5 text-yellow-500 mr-2" />
                <span class="text-sm text-gray-700">En suspens</span>
              </div>
              <span class="font-semibold text-yellow-700">{{ formatMontant(stats.stats_da.montants?.en_suspens) }}</span>
            </div>
            <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
              <div class="flex items-center">
                <ClockIcon class="w-5 h-5 text-blue-500 mr-2" />
                <span class="text-sm text-gray-700">CDG</span>
              </div>
              <span class="font-semibold text-blue-700">{{ formatMontant(stats.stats_da.montants?.cdg) }}</span>
            </div>
            <div class="flex items-center justify-between p-3 bg-purple-50 rounded-lg">
              <div class="flex items-center">
                <ClockIcon class="w-5 h-5 text-purple-500 mr-2" />
                <span class="text-sm text-gray-700">DFC</span>
              </div>
              <span class="font-semibold text-purple-700">{{ formatMontant(stats.stats_da.montants?.dfc) }}</span>
            </div>
            <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
              <div class="flex items-center">
                <CheckCircleIcon class="w-5 h-5 text-green-500 mr-2" />
                <span class="text-sm text-gray-700">Traitees</span>
              </div>
              <span class="font-semibold text-green-700">{{ formatMontant(stats.stats_da.montants?.aboutis) }}</span>
            </div>
          </div>
        </div>

        <!-- Répartition par Acheteur -->
        <div v-if="stats.stats_da.repartition_acheteurs?.length" class="mt-6 pt-6 border-t border-gray-200">
          <h3 class="text-sm font-medium text-gray-700 mb-4">Repartition par Acheteur (en cours)</h3>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-gray-200">
                  <th class="text-left py-2 font-medium text-gray-600">Acheteur</th>
                  <th class="text-right py-2 font-medium text-gray-600">Nombre</th>
                  <th class="text-right py-2 font-medium text-gray-600">Montant</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in stats.stats_da.repartition_acheteurs" :key="item.acheteur_id" class="border-b border-gray-100">
                  <td class="py-2">{{ item.acheteur }}</td>
                  <td class="text-right py-2 font-medium">{{ item.nombre }}</td>
                  <td class="text-right py-2 text-gray-600">{{ formatMontant(item.montant) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Statistiques DAC - Demandes Caisse -->
      <div v-if="stats?.stats_dac" class="card mb-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
          <BanknotesIcon class="w-5 h-5 mr-2 text-orange-500" />
          Statistiques des Demandes Caisse ({{ stats?.annee }})
        </h2>

        <!-- Indicateurs principaux -->
        <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
          <div class="text-center p-4 bg-gray-50 rounded-lg">
            <p class="text-3xl font-bold text-gray-900">{{ stats.stats_dac.total }}</p>
            <p class="text-sm text-gray-500">Total DAC</p>
          </div>
          <div class="text-center p-4 bg-yellow-50 rounded-lg">
            <p class="text-3xl font-bold text-yellow-600">{{ stats.stats_dac.en_suspens || 0 }}</p>
            <p class="text-sm text-gray-500">En suspens</p>
          </div>
          <div class="text-center p-4 bg-cyan-50 rounded-lg">
            <p class="text-3xl font-bold text-cyan-600">{{ stats.stats_dac.en_cours_ach || 0 }}</p>
            <p class="text-sm text-gray-500">En cours ACH</p>
          </div>
          <div class="text-center p-4 bg-blue-50 rounded-lg">
            <p class="text-3xl font-bold text-blue-600">{{ stats.stats_dac.cdg || 0 }}</p>
            <p class="text-sm text-gray-500">CDG</p>
          </div>
          <div class="text-center p-4 bg-purple-50 rounded-lg">
            <p class="text-3xl font-bold text-purple-600">{{ stats.stats_dac.dg || 0 }}</p>
            <p class="text-sm text-gray-500">DG</p>
          </div>
          <div class="text-center p-4 bg-green-50 rounded-lg">
            <p class="text-3xl font-bold text-green-600">{{ stats.stats_dac.traitees }}</p>
            <p class="text-sm text-gray-500">Traitees</p>
          </div>
        </div>

        <!-- Taux d'aboutissement et taux de clôture -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div class="p-4 border border-gray-200 rounded-lg">
            <div class="flex items-center justify-between mb-3">
              <span class="text-sm font-medium text-gray-700">Taux d'aboutissement</span>
              <span class="text-2xl font-bold" :class="stats.stats_dac.taux_aboutissement >= 80 ? 'text-green-600' : stats.stats_dac.taux_aboutissement >= 50 ? 'text-yellow-600' : 'text-red-600'">
                {{ stats.stats_dac.taux_aboutissement }}%
              </span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-3">
              <div
                class="h-3 rounded-full transition-all duration-500"
                :class="stats.stats_dac.taux_aboutissement >= 80 ? 'bg-green-500' : stats.stats_dac.taux_aboutissement >= 50 ? 'bg-yellow-500' : 'bg-red-500'"
                :style="{ width: stats.stats_dac.taux_aboutissement + '%' }"
              ></div>
            </div>
            <p class="text-xs text-gray-500 mt-2">DAC traitees / terminees</p>
          </div>

          <div class="p-4 border border-gray-200 rounded-lg">
            <div class="flex items-center justify-between mb-3">
              <span class="text-sm font-medium text-gray-700">Taux de cloture</span>
              <span class="text-2xl font-bold" :class="stats.stats_dac.taux_cloture >= 80 ? 'text-emerald-600' : stats.stats_dac.taux_cloture >= 50 ? 'text-yellow-600' : 'text-orange-600'">
                {{ stats.stats_dac.taux_cloture }}%
              </span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-3">
              <div
                class="h-3 rounded-full transition-all duration-500 bg-emerald-500"
                :style="{ width: stats.stats_dac.taux_cloture + '%' }"
              ></div>
            </div>
            <p class="text-xs text-gray-500 mt-2">Avec piece justificative</p>
          </div>

          <div class="p-4 border border-gray-200 rounded-lg">
            <div class="flex items-center justify-between mb-3">
              <span class="text-sm font-medium text-gray-700">Delai moyen de cloture</span>
              <span class="text-2xl font-bold text-orange-600">
                {{ stats.stats_dac.delai_moyen_cloture_jours ? stats.stats_dac.delai_moyen_cloture_jours + ' j' : '-' }}
              </span>
            </div>
            <p class="text-xs text-gray-500">Entre creation et paiement</p>
          </div>
        </div>

        <!-- En attente de clôture -->
        <div v-if="stats.stats_dac.en_attente_cloture > 0" class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
          <div class="flex items-center">
            <DocumentCheckIcon class="w-5 h-5 text-yellow-600 mr-2" />
            <span class="text-sm font-medium text-yellow-800">
              {{ stats.stats_dac.en_attente_cloture }} DAC traitee(s) en attente de cloture avec piece justificative
            </span>
          </div>
        </div>

        <!-- Montants par catégorie -->
        <div class="mt-6 pt-6 border-t border-gray-200">
          <h3 class="text-sm font-medium text-gray-700 mb-4">Montants par categorie</h3>
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
              <div class="flex items-center">
                <CheckCircleIcon class="w-5 h-5 text-green-500 mr-2" />
                <span class="text-sm text-gray-700">Traitees</span>
              </div>
              <span class="font-semibold text-green-700">{{ formatMontant(stats.stats_dac.montants?.traitees) }}</span>
            </div>
            <div class="flex items-center justify-between p-3 bg-emerald-50 rounded-lg">
              <div class="flex items-center">
                <DocumentCheckIcon class="w-5 h-5 text-emerald-500 mr-2" />
                <span class="text-sm text-gray-700">Cloturees</span>
              </div>
              <span class="font-semibold text-emerald-700">{{ formatMontant(stats.stats_dac.montants?.cloturees) }}</span>
            </div>
            <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
              <div class="flex items-center">
                <ClockIcon class="w-5 h-5 text-blue-500 mr-2" />
                <span class="text-sm text-gray-700">En cours</span>
              </div>
              <span class="font-semibold text-blue-700">{{ formatMontant(stats.stats_dac.montants?.en_cours) }}</span>
            </div>
            <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg">
              <div class="flex items-center">
                <XCircleIcon class="w-5 h-5 text-red-500 mr-2" />
                <span class="text-sm text-gray-700">Annulees</span>
              </div>
              <span class="font-semibold text-red-700">{{ formatMontant(stats.stats_dac.montants?.annulees) }}</span>
            </div>
          </div>
        </div>

        <!-- Répartition par Acheteur -->
        <div v-if="stats.stats_dac.repartition_acheteurs?.length" class="mt-6 pt-6 border-t border-gray-200">
          <h3 class="text-sm font-medium text-gray-700 mb-4">Repartition par Acheteur (en cours)</h3>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-gray-200">
                  <th class="text-left py-2 font-medium text-gray-600">Acheteur</th>
                  <th class="text-right py-2 font-medium text-gray-600">Nombre</th>
                  <th class="text-right py-2 font-medium text-gray-600">Montant</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in stats.stats_dac.repartition_acheteurs" :key="item.acheteur_id" class="border-b border-gray-100">
                  <td class="py-2">{{ item.acheteur }}</td>
                  <td class="text-right py-2 font-medium">{{ item.nombre }}</td>
                  <td class="text-right py-2 text-gray-600">{{ formatMontant(item.montant) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Statistiques BC - Aboutissement -->
      <div v-if="stats?.stats_bc" class="card mb-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
          <ChartBarIcon class="w-5 h-5 mr-2 text-ct-blue-500" />
          Statistiques des Bons de Commande ({{ stats?.annee }})
        </h2>

        <!-- Indicateurs principaux -->
        <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
          <div class="text-center p-4 bg-gray-50 rounded-lg">
            <p class="text-3xl font-bold text-gray-900">{{ stats.stats_bc.total }}</p>
            <p class="text-sm text-gray-500">Total BC</p>
          </div>
          <div class="text-center p-4 bg-yellow-50 rounded-lg">
            <p class="text-3xl font-bold text-yellow-600">{{ stats.stats_bc.en_suspens || 0 }}</p>
            <p class="text-sm text-gray-500">En suspens</p>
          </div>
          <div class="text-center p-4 bg-cyan-50 rounded-lg">
            <p class="text-3xl font-bold text-cyan-600">{{ stats.stats_bc.en_cours_a || 0 }}</p>
            <p class="text-sm text-gray-500">En cours A</p>
          </div>
          <div class="text-center p-4 bg-blue-50 rounded-lg">
            <p class="text-3xl font-bold text-blue-600">{{ stats.stats_bc.cdg || 0 }}</p>
            <p class="text-sm text-gray-500">CDG</p>
          </div>
          <div class="text-center p-4 bg-purple-50 rounded-lg">
            <p class="text-3xl font-bold text-purple-600">{{ stats.stats_bc.dg || 0 }}</p>
            <p class="text-sm text-gray-500">DG</p>
          </div>
          <div class="text-center p-4 bg-green-50 rounded-lg">
            <p class="text-3xl font-bold text-green-600">{{ stats.stats_bc.aboutis }}</p>
            <p class="text-sm text-gray-500">Aboutis (Livres)</p>
          </div>
        </div>

        <!-- Taux d'aboutissement et délai moyen -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Taux d'aboutissement -->
          <div class="p-4 border border-gray-200 rounded-lg">
            <div class="flex items-center justify-between mb-3">
              <span class="text-sm font-medium text-gray-700">Taux d'aboutissement</span>
              <span class="text-2xl font-bold" :class="stats.stats_bc.taux_aboutissement >= 80 ? 'text-green-600' : stats.stats_bc.taux_aboutissement >= 50 ? 'text-yellow-600' : 'text-red-600'">
                {{ stats.stats_bc.taux_aboutissement }}%
              </span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-3">
              <div
                class="h-3 rounded-full transition-all duration-500"
                :class="stats.stats_bc.taux_aboutissement >= 80 ? 'bg-green-500' : stats.stats_bc.taux_aboutissement >= 50 ? 'bg-yellow-500' : 'bg-red-500'"
                :style="{ width: stats.stats_bc.taux_aboutissement + '%' }"
              ></div>
            </div>
            <p class="text-xs text-gray-500 mt-2">
              Sur les BC termines (hors en cours)
            </p>
          </div>

          <!-- Délai moyen -->
          <div class="p-4 border border-gray-200 rounded-lg">
            <div class="flex items-center justify-between mb-3">
              <span class="text-sm font-medium text-gray-700">Delai moyen de livraison</span>
              <span class="text-2xl font-bold text-ct-blue-600">
                {{ stats.stats_bc.delai_moyen_jours ? stats.stats_bc.delai_moyen_jours + ' j' : '-' }}
              </span>
            </div>
            <p class="text-xs text-gray-500">
              Entre la creation du BC et la reception validee
            </p>
          </div>
        </div>

        <!-- Montants par catégorie -->
        <div class="mt-6 pt-6 border-t border-gray-200">
          <h3 class="text-sm font-medium text-gray-700 mb-4">Montants par categorie</h3>
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg">
              <div class="flex items-center">
                <ClockIcon class="w-5 h-5 text-yellow-500 mr-2" />
                <span class="text-sm text-gray-700">En suspens</span>
              </div>
              <span class="font-semibold text-yellow-700">{{ formatMontant(stats.stats_bc.montants?.en_suspens) }}</span>
            </div>
            <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
              <div class="flex items-center">
                <ClockIcon class="w-5 h-5 text-blue-500 mr-2" />
                <span class="text-sm text-gray-700">CDG</span>
              </div>
              <span class="font-semibold text-blue-700">{{ formatMontant(stats.stats_bc.montants?.cdg) }}</span>
            </div>
            <div class="flex items-center justify-between p-3 bg-purple-50 rounded-lg">
              <div class="flex items-center">
                <ClockIcon class="w-5 h-5 text-purple-500 mr-2" />
                <span class="text-sm text-gray-700">DG</span>
              </div>
              <span class="font-semibold text-purple-700">{{ formatMontant(stats.stats_bc.montants?.dg) }}</span>
            </div>
            <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
              <div class="flex items-center">
                <CheckCircleIcon class="w-5 h-5 text-green-500 mr-2" />
                <span class="text-sm text-gray-700">Aboutis</span>
              </div>
              <span class="font-semibold text-green-700">{{ formatMontant(stats.stats_bc.montants?.aboutis) }}</span>
            </div>
          </div>
        </div>

        <!-- Répartition par Direction -->
        <div v-if="stats.stats_bc.repartition_directions?.length" class="mt-6 pt-6 border-t border-gray-200">
          <h3 class="text-sm font-medium text-gray-700 mb-4">Repartition par Direction</h3>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-gray-200">
                  <th class="text-left py-2 font-medium text-gray-600">Direction</th>
                  <th class="text-right py-2 font-medium text-gray-600">Nombre</th>
                  <th class="text-right py-2 font-medium text-gray-600">Montant</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in stats.stats_bc.repartition_directions" :key="item.direction_id" class="border-b border-gray-100">
                  <td class="py-2">{{ item.direction }}</td>
                  <td class="text-right py-2 font-medium">{{ item.nombre }}</td>
                  <td class="text-right py-2 text-gray-600">{{ formatMontant(item.montant) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Répartition par Acheteur -->
        <div v-if="stats.stats_bc.repartition_acheteurs?.length" class="mt-6 pt-6 border-t border-gray-200">
          <h3 class="text-sm font-medium text-gray-700 mb-4">Repartition par Acheteur (en cours)</h3>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-gray-200">
                  <th class="text-left py-2 font-medium text-gray-600">Acheteur</th>
                  <th class="text-right py-2 font-medium text-gray-600">Nombre</th>
                  <th class="text-right py-2 font-medium text-gray-600">Montant</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in stats.stats_bc.repartition_acheteurs" :key="item.acheteur_id" class="border-b border-gray-100">
                  <td class="py-2">{{ item.acheteur }}</td>
                  <td class="text-right py-2 font-medium">{{ item.nombre }}</td>
                  <td class="text-right py-2 text-gray-600">{{ formatMontant(item.montant) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Statistiques Contrats -->
      <div v-if="stats?.stats_contrat" class="card mb-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
          <DocumentDuplicateIcon class="w-5 h-5 mr-2 text-teal-500" />
          Statistiques des Contrats ({{ stats?.annee }})
        </h2>

        <!-- Indicateurs principaux -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
          <div class="text-center p-4 bg-teal-50 rounded-lg">
            <p class="text-3xl font-bold text-teal-600">{{ stats.stats_contrat.contrats_actifs }}</p>
            <p class="text-sm text-gray-500">Contrats actifs</p>
          </div>
          <div class="text-center p-4 bg-yellow-50 rounded-lg">
            <p class="text-3xl font-bold text-yellow-600">{{ stats.stats_contrat.contrats_a_echeance }}</p>
            <p class="text-sm text-gray-500">A echeance (90j)</p>
          </div>
          <div class="text-center p-4 bg-red-50 rounded-lg">
            <p class="text-3xl font-bold text-red-600">{{ stats.stats_contrat.contrats_expires }}</p>
            <p class="text-sm text-gray-500">Expires</p>
          </div>
          <div class="text-center p-4 bg-purple-50 rounded-lg">
            <p class="text-lg font-bold text-purple-600">{{ formatMontant(stats.stats_contrat.montant_annuel_actif) }}</p>
            <p class="text-sm text-gray-500">Montant annuel</p>
          </div>
        </div>

        <!-- Alertes contrats -->
        <div v-if="stats.stats_contrat.contrats_expires > 0" class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
          <div class="flex items-center">
            <ExclamationTriangleIcon class="w-5 h-5 text-red-600 mr-2" />
            <span class="text-sm font-medium text-red-800">
              {{ stats.stats_contrat.contrats_expires }} contrat(s) expire(s) necessitant une action
            </span>
          </div>
        </div>
        <div v-if="stats.stats_contrat.contrats_a_echeance > 0" class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
          <div class="flex items-center">
            <CalendarDaysIcon class="w-5 h-5 text-yellow-600 mr-2" />
            <span class="text-sm font-medium text-yellow-800">
              {{ stats.stats_contrat.contrats_a_echeance }} contrat(s) arrivant a echeance dans les 90 prochains jours
            </span>
          </div>
        </div>

        <!-- Échéances de paiement -->
        <div class="mb-6">
          <h3 class="text-sm font-medium text-gray-700 mb-4">Echeances de paiement ({{ stats?.annee }})</h3>
          <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <div class="text-center p-4 bg-gray-50 rounded-lg">
              <p class="text-2xl font-bold text-gray-600">{{ stats.stats_contrat.echeances?.a_venir || 0 }}</p>
              <p class="text-sm text-gray-500">A venir</p>
              <p class="text-xs text-gray-600 mt-1">{{ formatMontant(stats.stats_contrat.montants_echeances?.a_venir) }}</p>
            </div>
            <div class="text-center p-4 bg-yellow-50 rounded-lg">
              <p class="text-2xl font-bold text-yellow-600">{{ stats.stats_contrat.echeances?.a_traiter || 0 }}</p>
              <p class="text-sm text-gray-500">A traiter</p>
              <p class="text-xs text-yellow-600 mt-1">{{ formatMontant(stats.stats_contrat.montants_echeances?.a_traiter) }}</p>
            </div>
            <div class="text-center p-4 bg-blue-50 rounded-lg">
              <p class="text-2xl font-bold text-blue-600">{{ stats.stats_contrat.echeances?.en_cours || 0 }}</p>
              <p class="text-sm text-gray-500">En cours</p>
            </div>
            <div class="text-center p-4 bg-green-50 rounded-lg">
              <p class="text-2xl font-bold text-green-600">{{ stats.stats_contrat.echeances?.payees || 0 }}</p>
              <p class="text-sm text-gray-500">Payees</p>
              <p class="text-xs text-green-600 mt-1">{{ formatMontant(stats.stats_contrat.montants_echeances?.payees) }}</p>
            </div>
            <div class="text-center p-4 bg-red-50 rounded-lg">
              <p class="text-2xl font-bold text-red-600">{{ stats.stats_contrat.echeances?.en_retard || 0 }}</p>
              <p class="text-sm text-gray-500">En retard</p>
              <p class="text-xs text-red-600 mt-1">{{ formatMontant(stats.stats_contrat.montants_echeances?.en_retard) }}</p>
            </div>
          </div>
        </div>

        <!-- Répartition par Direction -->
        <div v-if="stats.stats_contrat.repartition_directions?.length" class="pt-6 border-t border-gray-200">
          <h3 class="text-sm font-medium text-gray-700 mb-4">Repartition par Direction</h3>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-gray-200">
                  <th class="text-left py-2 font-medium text-gray-600">Direction</th>
                  <th class="text-right py-2 font-medium text-gray-600">Nombre</th>
                  <th class="text-right py-2 font-medium text-gray-600">Montant annuel</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in stats.stats_contrat.repartition_directions" :key="item.direction_id" class="border-b border-gray-100">
                  <td class="py-2">{{ item.direction }}</td>
                  <td class="text-right py-2 font-medium">{{ item.nombre }}</td>
                  <td class="text-right py-2 text-gray-600">{{ formatMontant(item.montant) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </template>
  </div>
</template>
