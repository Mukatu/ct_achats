<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { statistiquesService } from '@/services/api'
import { formatMontant } from '@/types'
import {
  ChartBarIcon,
  DocumentTextIcon,
  ClipboardDocumentListIcon,
  ShoppingCartIcon,
  TruckIcon,
  BanknotesIcon,
  UserGroupIcon,
  ExclamationTriangleIcon,
  ClockIcon,
  ArrowTrendingUpIcon,
  ArrowTrendingDownIcon,
  BuildingOfficeIcon,
  DocumentCheckIcon,
} from '@heroicons/vue/24/outline'

const loading = ref(true)
const annee = ref(new Date().getFullYear())
const anneesDisponibles = ref<number[]>([])

// Données
const overview = ref<any>(null)
const evolution = ref<any[]>([])
const topFournisseurs = ref<any>(null)
const parDirection = ref<any>(null)
const delais = ref<any>(null)
const alertes = ref<any>(null)

// Onglet actif pour les graphiques
const activeTab = ref<'evolution' | 'directions' | 'fournisseurs'>('evolution')

// Charger toutes les données
async function loadData() {
  loading.value = true
  try {
    const [
      anneesRes,
      overviewRes,
      evolutionRes,
      fournisseursRes,
      directionsRes,
      delaisRes,
      alertesRes
    ] = await Promise.all([
      statistiquesService.getAnneesDisponibles(),
      statistiquesService.getOverview(annee.value),
      statistiquesService.getEvolution(annee.value),
      statistiquesService.getTopFournisseurs(annee.value, 10),
      statistiquesService.getParDirection(annee.value),
      statistiquesService.getDelais(annee.value),
      statistiquesService.getAlertes(),
    ])

    anneesDisponibles.value = anneesRes.data.annees || [new Date().getFullYear()]
    overview.value = overviewRes.data
    evolution.value = evolutionRes.data.evolution || []
    topFournisseurs.value = fournisseursRes.data
    parDirection.value = directionsRes.data
    delais.value = delaisRes.data.delais
    alertes.value = alertesRes.data.alertes
  } catch (error) {
    console.error('Erreur chargement statistiques:', error)
  } finally {
    loading.value = false
  }
}

// Recharger quand l'année change
watch(annee, () => {
  loadData()
})

// Calculer le max pour les barres de progression
const maxMontantMensuel = computed(() => {
  if (!evolution.value.length) return 1
  return Math.max(...evolution.value.map(e => e.bc_montant || 0), 1)
})

const maxMontantDirection = computed(() => {
  if (!parDirection.value?.bons_commande?.length) return 1
  return Math.max(...parDirection.value.bons_commande.map((d: any) => d.total || 0), 1)
})

const maxMontantFournisseur = computed(() => {
  if (!topFournisseurs.value?.par_montant?.length) return 1
  return Math.max(...topFournisseurs.value.par_montant.map((f: any) => f.total || 0), 1)
})

// Total alertes
const totalAlertes = computed(() => {
  if (!alertes.value) return 0
  return (alertes.value.eb_en_retard || 0) +
         (alertes.value.da_en_attente || 0) +
         (alertes.value.bc_non_receptionnes || 0) +
         (alertes.value.factures_en_retard || 0)
})

onMounted(() => {
  loadData()
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Statistiques</h1>
        <p class="text-gray-600 mt-1">Vue d'ensemble des achats et approvisionnements</p>
      </div>
      <div class="flex items-center gap-4">
        <select v-model="annee" class="input w-32">
          <option v-for="a in anneesDisponibles" :key="a" :value="a">{{ a }}</option>
        </select>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-ct-blue-500"></div>
    </div>

    <template v-else>
      <!-- Alertes -->
      <div v-if="totalAlertes > 0" class="mb-6 card bg-orange-50 border-orange-200">
        <div class="flex items-start gap-3">
          <ExclamationTriangleIcon class="w-6 h-6 text-orange-500 flex-shrink-0" />
          <div class="flex-1">
            <h3 class="font-semibold text-orange-800">Alertes en cours</h3>
            <div class="mt-2 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
              <div v-if="alertes?.eb_en_retard" class="flex items-center gap-2">
                <span class="w-2 h-2 bg-orange-500 rounded-full"></span>
                <span class="text-orange-700">{{ alertes.eb_en_retard }} EB en retard</span>
              </div>
              <div v-if="alertes?.da_en_attente" class="flex items-center gap-2">
                <span class="w-2 h-2 bg-orange-500 rounded-full"></span>
                <span class="text-orange-700">{{ alertes.da_en_attente }} DA en attente</span>
              </div>
              <div v-if="alertes?.bc_non_receptionnes" class="flex items-center gap-2">
                <span class="w-2 h-2 bg-orange-500 rounded-full"></span>
                <span class="text-orange-700">{{ alertes.bc_non_receptionnes }} BC non recu</span>
              </div>
              <div v-if="alertes?.factures_en_retard" class="flex items-center gap-2">
                <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                <span class="text-red-700">{{ alertes.factures_en_retard }} factures en retard</span>
              </div>
            </div>
            <p v-if="alertes?.montant_en_retard" class="mt-2 text-sm font-medium text-red-700">
              Montant en retard: {{ formatMontant(alertes.montant_en_retard) }}
            </p>
          </div>
        </div>
      </div>

      <!-- Compteurs principaux -->
      <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 mb-6">
        <!-- EB -->
        <div class="card">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-blue-100 rounded-lg">
              <DocumentTextIcon class="w-6 h-6 text-blue-600" />
            </div>
            <div>
              <p class="text-sm text-gray-500">Expr. Besoins</p>
              <p class="text-2xl font-bold text-gray-900">{{ overview?.expressions_besoin?.total || 0 }}</p>
            </div>
          </div>
          <div class="mt-3 flex gap-2 text-xs">
            <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded">
              {{ overview?.expressions_besoin?.en_cours || 0 }} en cours
            </span>
            <span class="px-2 py-1 bg-green-100 text-green-700 rounded">
              {{ overview?.expressions_besoin?.traitees || 0 }} traitees
            </span>
          </div>
        </div>

        <!-- DA -->
        <div class="card">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-indigo-100 rounded-lg">
              <ClipboardDocumentListIcon class="w-6 h-6 text-indigo-600" />
            </div>
            <div>
              <p class="text-sm text-gray-500">Dem. Achat</p>
              <p class="text-2xl font-bold text-gray-900">{{ overview?.demandes_achat?.total || 0 }}</p>
            </div>
          </div>
          <div class="mt-3 flex gap-2 text-xs">
            <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded">
              {{ overview?.demandes_achat?.en_cours || 0 }} en cours
            </span>
            <span class="px-2 py-1 bg-green-100 text-green-700 rounded">
              {{ overview?.demandes_achat?.traitees || 0 }} traitees
            </span>
          </div>
        </div>

        <!-- DAC -->
        <div class="card">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-emerald-100 rounded-lg">
              <DocumentCheckIcon class="w-6 h-6 text-emerald-600" />
            </div>
            <div>
              <p class="text-sm text-gray-500">Dem. Caisse</p>
              <p class="text-2xl font-bold text-gray-900">{{ overview?.demandes_achat_caisse?.total || 0 }}</p>
            </div>
          </div>
          <div class="mt-3 flex gap-2 text-xs">
            <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded">
              {{ overview?.demandes_achat_caisse?.en_attente_cloture || 0 }} a cloturer
            </span>
            <span class="px-2 py-1 bg-green-100 text-green-700 rounded">
              {{ overview?.demandes_achat_caisse?.cloturees || 0 }} cloturees
            </span>
          </div>
        </div>

        <!-- BC -->
        <div class="card">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-purple-100 rounded-lg">
              <ShoppingCartIcon class="w-6 h-6 text-purple-600" />
            </div>
            <div>
              <p class="text-sm text-gray-500">Bons Cmd</p>
              <p class="text-2xl font-bold text-gray-900">{{ overview?.bons_commande?.total || 0 }}</p>
            </div>
          </div>
          <div class="mt-3 flex gap-2 text-xs">
            <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded">
              {{ overview?.bons_commande?.envoyes || 0 }} envoyes
            </span>
            <span class="px-2 py-1 bg-green-100 text-green-700 rounded">
              {{ overview?.bons_commande?.livres || 0 }} livres
            </span>
          </div>
        </div>

        <!-- BR -->
        <div class="card">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-teal-100 rounded-lg">
              <TruckIcon class="w-6 h-6 text-teal-600" />
            </div>
            <div>
              <p class="text-sm text-gray-500">Receptions</p>
              <p class="text-2xl font-bold text-gray-900">{{ overview?.receptions?.total || 0 }}</p>
            </div>
          </div>
          <div class="mt-3 flex gap-2 text-xs">
            <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded">
              {{ overview?.receptions?.en_attente || 0 }} en attente
            </span>
            <span class="px-2 py-1 bg-green-100 text-green-700 rounded">
              {{ overview?.receptions?.validees || 0 }} validees
            </span>
          </div>
        </div>

        <!-- Factures -->
        <div class="card">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-amber-100 rounded-lg">
              <BanknotesIcon class="w-6 h-6 text-amber-600" />
            </div>
            <div>
              <p class="text-sm text-gray-500">Factures</p>
              <p class="text-2xl font-bold text-gray-900">{{ overview?.factures?.total || 0 }}</p>
            </div>
          </div>
          <div class="mt-3 flex gap-2 text-xs">
            <span class="px-2 py-1 bg-green-100 text-green-700 rounded">
              {{ overview?.factures?.validees || 0 }} validees
            </span>
            <span v-if="overview?.factures?.en_retard" class="px-2 py-1 bg-red-100 text-red-700 rounded">
              {{ overview.factures.en_retard }} en retard
            </span>
          </div>
        </div>

        <!-- Fournisseurs -->
        <div class="card">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-gray-100 rounded-lg">
              <UserGroupIcon class="w-6 h-6 text-gray-600" />
            </div>
            <div>
              <p class="text-sm text-gray-500">Fournisseurs</p>
              <p class="text-2xl font-bold text-gray-900">{{ overview?.fournisseurs?.total || 0 }}</p>
            </div>
          </div>
          <div class="mt-3 flex gap-2 text-xs">
            <span class="px-2 py-1 bg-green-100 text-green-700 rounded">
              {{ overview?.fournisseurs?.actifs || 0 }} actifs
            </span>
            <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded">
              {{ overview?.fournisseurs?.inactifs || 0 }} inactifs
            </span>
          </div>
        </div>
      </div>

      <!-- Engagements -->
      <div class="card mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Repartition des engagements</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="bg-purple-50 rounded-lg p-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-purple-600 text-sm font-medium">Engagements BC</p>
                <p class="text-2xl font-bold text-purple-700 mt-1">{{ formatMontant(overview?.engagements?.bc || 0) }}</p>
              </div>
              <ShoppingCartIcon class="w-10 h-10 text-purple-300" />
            </div>
          </div>

          <div class="bg-emerald-50 rounded-lg p-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-emerald-600 text-sm font-medium">Engagements DAC</p>
                <p class="text-2xl font-bold text-emerald-700 mt-1">{{ formatMontant(overview?.engagements?.dac || 0) }}</p>
              </div>
              <DocumentCheckIcon class="w-10 h-10 text-emerald-300" />
            </div>
          </div>

          <div class="bg-gradient-to-br from-ct-blue-500 to-ct-blue-600 rounded-lg p-4 text-white">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-ct-blue-100 text-sm font-medium">Total engagements</p>
                <p class="text-2xl font-bold mt-1">{{ formatMontant(overview?.engagements?.total || 0) }}</p>
              </div>
              <ChartBarIcon class="w-10 h-10 text-ct-blue-300" />
            </div>
          </div>
        </div>
      </div>

      <!-- Montants factures -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="card bg-gradient-to-br from-green-500 to-green-600 text-white">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-green-100 text-sm">Montant factures validees</p>
              <p class="text-2xl font-bold mt-1">{{ formatMontant(overview?.factures?.montant_total || 0) }}</p>
            </div>
            <BanknotesIcon class="w-10 h-10 text-green-300" />
          </div>
        </div>

        <div class="card bg-gradient-to-br from-blue-500 to-blue-600 text-white">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-blue-100 text-sm">Montant paye</p>
              <p class="text-2xl font-bold mt-1">{{ formatMontant(overview?.factures?.montant_paye || 0) }}</p>
            </div>
            <ArrowTrendingUpIcon class="w-10 h-10 text-blue-300" />
          </div>
        </div>

        <div class="card bg-gradient-to-br from-amber-500 to-amber-600 text-white">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-amber-100 text-sm">Reste a payer</p>
              <p class="text-2xl font-bold mt-1">{{ formatMontant(overview?.factures?.montant_restant || 0) }}</p>
            </div>
            <ArrowTrendingDownIcon class="w-10 h-10 text-amber-300" />
          </div>
        </div>
      </div>

      <!-- Delais moyens -->
      <div class="card mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
          <ClockIcon class="w-5 h-5 text-gray-500" />
          Delais moyens de traitement
        </h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div class="text-center p-4 bg-gray-50 rounded-lg">
            <p class="text-3xl font-bold text-ct-blue-600">{{ delais?.eb_vers_da || 0 }}</p>
            <p class="text-sm text-gray-500 mt-1">jours</p>
            <p class="text-xs text-gray-400 mt-1">EB vers DA</p>
          </div>
          <div class="text-center p-4 bg-gray-50 rounded-lg">
            <p class="text-3xl font-bold text-ct-blue-600">{{ delais?.da_vers_bc || 0 }}</p>
            <p class="text-sm text-gray-500 mt-1">jours</p>
            <p class="text-xs text-gray-400 mt-1">DA vers BC</p>
          </div>
          <div class="text-center p-4 bg-gray-50 rounded-lg">
            <p class="text-3xl font-bold text-ct-blue-600">{{ delais?.bc_vers_reception || 0 }}</p>
            <p class="text-sm text-gray-500 mt-1">jours</p>
            <p class="text-xs text-gray-400 mt-1">BC vers Reception</p>
          </div>
          <div class="text-center p-4 bg-gray-50 rounded-lg">
            <p class="text-3xl font-bold text-ct-blue-600">{{ delais?.facture_vers_paiement || 0 }}</p>
            <p class="text-sm text-gray-500 mt-1">jours</p>
            <p class="text-xs text-gray-400 mt-1">Facture vers Paiement</p>
          </div>
        </div>
      </div>

      <!-- Onglets graphiques -->
      <div class="card">
        <div class="flex gap-2 border-b border-gray-200 mb-6">
          <button
            @click="activeTab = 'evolution'"
            :class="[
              'px-4 py-2 text-sm font-medium border-b-2 -mb-px',
              activeTab === 'evolution'
                ? 'border-ct-blue-500 text-ct-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700'
            ]"
          >
            <ChartBarIcon class="w-4 h-4 inline mr-1" />
            Evolution mensuelle
          </button>
          <button
            @click="activeTab = 'directions'"
            :class="[
              'px-4 py-2 text-sm font-medium border-b-2 -mb-px',
              activeTab === 'directions'
                ? 'border-ct-blue-500 text-ct-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700'
            ]"
          >
            <BuildingOfficeIcon class="w-4 h-4 inline mr-1" />
            Par direction
          </button>
          <button
            @click="activeTab = 'fournisseurs'"
            :class="[
              'px-4 py-2 text-sm font-medium border-b-2 -mb-px',
              activeTab === 'fournisseurs'
                ? 'border-ct-blue-500 text-ct-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700'
            ]"
          >
            <UserGroupIcon class="w-4 h-4 inline mr-1" />
            Top fournisseurs
          </button>
        </div>

        <!-- Evolution mensuelle -->
        <div v-if="activeTab === 'evolution'">
          <h3 class="text-sm font-medium text-gray-700 mb-4">Montant des BC par mois ({{ annee }})</h3>
          <div class="space-y-3">
            <div v-for="item in evolution" :key="item.mois" class="flex items-center gap-3">
              <span class="w-8 text-sm text-gray-500">{{ item.label }}</span>
              <div class="flex-1 h-8 bg-gray-100 rounded-lg overflow-hidden relative">
                <div
                  class="h-full bg-gradient-to-r from-ct-blue-400 to-ct-blue-600 rounded-lg transition-all duration-300"
                  :style="{ width: `${(item.bc_montant / maxMontantMensuel) * 100}%` }"
                ></div>
                <span
                  v-if="item.bc_montant > 0"
                  class="absolute inset-y-0 right-2 flex items-center text-xs font-medium"
                  :class="item.bc_montant / maxMontantMensuel > 0.5 ? 'text-white' : 'text-gray-600'"
                >
                  {{ formatMontant(item.bc_montant) }}
                </span>
              </div>
              <span class="w-16 text-right text-sm text-gray-500">{{ item.bc_count }} BC</span>
            </div>
          </div>

          <div class="mt-6 pt-4 border-t border-gray-200">
            <h4 class="text-sm font-medium text-gray-700 mb-3">Nombre de documents par mois</h4>
            <div class="grid grid-cols-12 gap-1 items-end h-32">
              <div
                v-for="item in evolution"
                :key="'count-' + item.mois"
                class="flex flex-col items-center"
              >
                <div class="flex-1 w-full flex flex-col justify-end gap-0.5">
                  <div
                    class="w-full bg-blue-400 rounded-t"
                    :style="{ height: `${(item.eb / Math.max(...evolution.map(e => e.eb || 1))) * 80}%` }"
                    :title="`${item.eb} EB`"
                  ></div>
                  <div
                    class="w-full bg-indigo-400"
                    :style="{ height: `${(item.da / Math.max(...evolution.map(e => e.da || 1))) * 80}%` }"
                    :title="`${item.da} DA`"
                  ></div>
                  <div
                    class="w-full bg-purple-400 rounded-b"
                    :style="{ height: `${(item.bc_count / Math.max(...evolution.map(e => e.bc_count || 1))) * 80}%` }"
                    :title="`${item.bc_count} BC`"
                  ></div>
                </div>
                <span class="text-[10px] text-gray-400 mt-1">{{ item.label }}</span>
              </div>
            </div>
            <div class="flex justify-center gap-4 mt-3 text-xs">
              <span class="flex items-center gap-1"><span class="w-3 h-3 bg-blue-400 rounded"></span> EB</span>
              <span class="flex items-center gap-1"><span class="w-3 h-3 bg-indigo-400 rounded"></span> DA</span>
              <span class="flex items-center gap-1"><span class="w-3 h-3 bg-purple-400 rounded"></span> BC</span>
            </div>
          </div>
        </div>

        <!-- Par direction -->
        <div v-if="activeTab === 'directions'">
          <h3 class="text-sm font-medium text-gray-700 mb-4">Montant BC par direction ({{ annee }})</h3>
          <div v-if="parDirection?.bons_commande?.length" class="space-y-3">
            <div
              v-for="(dir, index) in parDirection.bons_commande"
              :key="dir.id"
              class="flex items-center gap-3"
            >
              <span class="w-6 text-sm font-medium text-gray-400">{{ index + 1 }}</span>
              <span class="w-24 text-sm text-gray-700 truncate" :title="dir.direction">{{ dir.direction }}</span>
              <div class="flex-1 h-6 bg-gray-100 rounded overflow-hidden relative">
                <div
                  class="h-full bg-gradient-to-r from-teal-400 to-teal-600 rounded transition-all duration-300"
                  :style="{ width: `${(dir.total / maxMontantDirection) * 100}%` }"
                ></div>
              </div>
              <span class="w-32 text-right text-sm font-medium text-gray-700">{{ formatMontant(dir.total) }}</span>
              <span class="w-16 text-right text-xs text-gray-500">{{ dir.nb_commandes }} BC</span>
            </div>
          </div>
          <p v-else class="text-gray-500 text-center py-8">Aucune donnee pour cette annee</p>
        </div>

        <!-- Top fournisseurs -->
        <div v-if="activeTab === 'fournisseurs'">
          <h3 class="text-sm font-medium text-gray-700 mb-4">Top 10 fournisseurs par montant ({{ annee }})</h3>
          <div v-if="topFournisseurs?.par_montant?.length" class="space-y-3">
            <div
              v-for="(four, index) in topFournisseurs.par_montant"
              :key="four.id"
              class="flex items-center gap-3"
            >
              <span
                class="w-6 h-6 flex items-center justify-center rounded-full text-xs font-bold"
                :class="[
                  index === 0 ? 'bg-yellow-100 text-yellow-700' :
                  index === 1 ? 'bg-gray-200 text-gray-700' :
                  index === 2 ? 'bg-orange-100 text-orange-700' :
                  'bg-gray-100 text-gray-500'
                ]"
              >
                {{ index + 1 }}
              </span>
              <span class="w-48 text-sm text-gray-700 truncate" :title="four.raison_sociale">
                {{ four.raison_sociale }}
              </span>
              <div class="flex-1 h-6 bg-gray-100 rounded overflow-hidden relative">
                <div
                  class="h-full bg-gradient-to-r from-amber-400 to-amber-600 rounded transition-all duration-300"
                  :style="{ width: `${(four.total / maxMontantFournisseur) * 100}%` }"
                ></div>
              </div>
              <span class="w-32 text-right text-sm font-medium text-gray-700">{{ formatMontant(four.total) }}</span>
              <span class="w-16 text-right text-xs text-gray-500">{{ four.nb_commandes }} BC</span>
            </div>
          </div>
          <p v-else class="text-gray-500 text-center py-8">Aucune donnee pour cette annee</p>
        </div>
      </div>
    </template>
  </div>
</template>
