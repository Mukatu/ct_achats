<script setup lang="ts">
import { ref, computed } from 'vue'
import { importService } from '@/services/api'
import {
  XMarkIcon,
  ArrowDownTrayIcon,
  ArrowUpTrayIcon,
  DocumentArrowUpIcon,
  CheckCircleIcon,
  ExclamationCircleIcon,
  InformationCircleIcon,
} from '@heroicons/vue/24/outline'

type ImportType = 'eb' | 'da' | 'bc'

const props = defineProps<{
  show: boolean
  type: ImportType
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'success'): void
}>()

const file = ref<File | null>(null)
const isDragging = ref(false)
const isUploading = ref(false)
const isDownloading = ref(false)
const result = ref<{
  message: string
  total_lignes: number
  succes: number
  erreurs: Array<{
    ligne: number
    champ: string
    messages: string[]
  }>
} | null>(null)
const error = ref<string | null>(null)

const typeLabels: Record<ImportType, string> = {
  eb: 'Expressions de Besoins',
  da: 'Demandes d\'Achat',
  bc: 'Bons de Commande',
}

const typeLabel = computed(() => typeLabels[props.type])

function onDragOver(e: DragEvent) {
  e.preventDefault()
  isDragging.value = true
}

function onDragLeave() {
  isDragging.value = false
}

function onDrop(e: DragEvent) {
  e.preventDefault()
  isDragging.value = false
  const files = e.dataTransfer?.files
  if (files && files.length > 0) {
    handleFile(files[0])
  }
}

function onFileSelect(e: Event) {
  const target = e.target as HTMLInputElement
  if (target.files && target.files.length > 0) {
    handleFile(target.files[0])
  }
}

function handleFile(f: File) {
  // Valider le type de fichier
  const validTypes = [
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-excel',
    'text/csv',
  ]
  const validExtensions = ['.xlsx', '.xls', '.csv']

  const isValidType = validTypes.includes(f.type) ||
    validExtensions.some(ext => f.name.toLowerCase().endsWith(ext))

  if (!isValidType) {
    error.value = 'Format de fichier non supporte. Utilisez un fichier Excel (.xlsx, .xls) ou CSV.'
    return
  }

  if (f.size > 10 * 1024 * 1024) {
    error.value = 'Le fichier est trop volumineux (max 10 Mo)'
    return
  }

  file.value = f
  error.value = null
  result.value = null
}

async function downloadTemplate() {
  isDownloading.value = true
  try {
    const response = await importService.downloadTemplate(props.type)

    // Créer un blob et le télécharger
    const blob = new Blob([response.data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `template_${props.type}.xlsx`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
  } catch (err: any) {
    error.value = 'Erreur lors du telechargement du template'
    console.error(err)
  } finally {
    isDownloading.value = false
  }
}

async function uploadFile() {
  if (!file.value) return

  isUploading.value = true
  error.value = null
  result.value = null

  try {
    const formData = new FormData()
    formData.append('file', file.value)

    const response = await importService.importFile(props.type, formData)
    result.value = response.data

    if (result.value && result.value.succes > 0) {
      emit('success')
    }
  } catch (err: any) {
    if (err.response?.data?.error) {
      error.value = err.response.data.error
    } else if (err.response?.data?.message) {
      error.value = err.response.data.message
    } else {
      error.value = 'Erreur lors de l\'import du fichier'
    }
    console.error(err)
  } finally {
    isUploading.value = false
  }
}

function resetState() {
  file.value = null
  result.value = null
  error.value = null
}

function close() {
  resetState()
  emit('close')
}
</script>

<template>
  <Teleport to="body">
    <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
      <!-- Backdrop -->
      <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" @click="close"></div>

      <!-- Modal -->
      <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-2xl transform transition-all">
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">
              Import de masse - {{ typeLabel }}
            </h3>
            <button @click="close" class="text-gray-400 hover:text-gray-500">
              <XMarkIcon class="w-6 h-6" />
            </button>
          </div>

          <!-- Body -->
          <div class="px-6 py-4">
            <!-- Etape 1: Télécharger le template -->
            <div class="mb-6">
              <h4 class="text-sm font-medium text-gray-900 mb-2 flex items-center">
                <span class="w-6 h-6 bg-ct-blue-100 text-ct-blue-600 rounded-full flex items-center justify-center text-xs font-bold mr-2">1</span>
                Telecharger le modele Excel
              </h4>
              <p class="text-sm text-gray-500 mb-3">
                Commencez par telecharger le modele avec les champs requis et les referentiels disponibles.
              </p>
              <button
                @click="downloadTemplate"
                :disabled="isDownloading"
                class="btn btn-secondary flex items-center"
              >
                <ArrowDownTrayIcon class="w-5 h-5 mr-2" />
                {{ isDownloading ? 'Telechargement...' : 'Telecharger le template' }}
              </button>
            </div>

            <!-- Etape 2: Remplir et uploader -->
            <div>
              <h4 class="text-sm font-medium text-gray-900 mb-2 flex items-center">
                <span class="w-6 h-6 bg-ct-blue-100 text-ct-blue-600 rounded-full flex items-center justify-center text-xs font-bold mr-2">2</span>
                Importer le fichier rempli
              </h4>
              <p class="text-sm text-gray-500 mb-3">
                Remplissez le modele et importez-le ici. Les champs marques d'un * sont obligatoires.
              </p>

              <!-- Zone de drop -->
              <div
                class="border-2 border-dashed rounded-lg p-6 text-center transition-colors"
                :class="[
                  isDragging ? 'border-ct-blue-500 bg-ct-blue-50' : 'border-gray-300',
                  file ? 'bg-green-50 border-green-500' : ''
                ]"
                @dragover="onDragOver"
                @dragleave="onDragLeave"
                @drop="onDrop"
              >
                <input
                  type="file"
                  class="hidden"
                  accept=".xlsx,.xls,.csv"
                  @change="onFileSelect"
                  ref="fileInput"
                />

                <div v-if="!file">
                  <DocumentArrowUpIcon class="w-12 h-12 mx-auto text-gray-400 mb-3" />
                  <p class="text-sm text-gray-600 mb-2">
                    Glissez-deposez votre fichier ici ou
                  </p>
                  <button
                    @click="($refs.fileInput as HTMLInputElement).click()"
                    class="text-ct-blue-600 hover:text-ct-blue-700 font-medium text-sm"
                  >
                    parcourir vos fichiers
                  </button>
                  <p class="text-xs text-gray-400 mt-2">
                    Formats acceptes: .xlsx, .xls, .csv (max 10 Mo)
                  </p>
                </div>

                <div v-else class="flex items-center justify-center">
                  <CheckCircleIcon class="w-8 h-8 text-green-500 mr-3" />
                  <div class="text-left">
                    <p class="text-sm font-medium text-gray-900">{{ file.name }}</p>
                    <p class="text-xs text-gray-500">{{ (file.size / 1024).toFixed(1) }} Ko</p>
                  </div>
                  <button
                    @click="file = null; result = null"
                    class="ml-4 text-gray-400 hover:text-gray-500"
                  >
                    <XMarkIcon class="w-5 h-5" />
                  </button>
                </div>
              </div>

              <!-- Erreur -->
              <div v-if="error" class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex items-start">
                  <ExclamationCircleIcon class="w-5 h-5 text-red-500 mr-2 flex-shrink-0" />
                  <p class="text-sm text-red-700">{{ error }}</p>
                </div>
              </div>

              <!-- Résultat -->
              <div v-if="result" class="mt-4 p-4 rounded-lg" :class="result.erreurs.length === 0 ? 'bg-green-50 border border-green-200' : 'bg-yellow-50 border border-yellow-200'">
                <div class="flex items-start">
                  <CheckCircleIcon v-if="result.erreurs.length === 0" class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" />
                  <InformationCircleIcon v-else class="w-5 h-5 text-yellow-500 mr-2 flex-shrink-0" />
                  <div class="flex-1">
                    <p class="text-sm font-medium" :class="result.erreurs.length === 0 ? 'text-green-800' : 'text-yellow-800'">
                      {{ result.message }}
                    </p>
                    <div class="mt-2 text-sm" :class="result.erreurs.length === 0 ? 'text-green-700' : 'text-yellow-700'">
                      <p>Total lignes: {{ result.total_lignes }}</p>
                      <p>Importees avec succes: {{ result.succes }}</p>
                      <p v-if="result.erreurs.length > 0">Erreurs: {{ result.erreurs.length }}</p>
                    </div>

                    <!-- Détails des erreurs -->
                    <div v-if="result.erreurs.length > 0" class="mt-3 max-h-40 overflow-y-auto">
                      <p class="text-xs font-medium text-yellow-800 mb-1">Details des erreurs:</p>
                      <ul class="text-xs text-yellow-700 space-y-1">
                        <li v-for="(err, idx) in result.erreurs.slice(0, 10)" :key="idx">
                          <span v-if="err.ligne > 0">Ligne {{ err.ligne }}: </span>
                          <span>{{ err.messages.join(', ') }}</span>
                        </li>
                        <li v-if="result.erreurs.length > 10" class="italic">
                          ... et {{ result.erreurs.length - 10 }} autres erreurs
                        </li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-xl">
            <button @click="close" class="btn btn-secondary">
              Fermer
            </button>
            <button
              @click="uploadFile"
              :disabled="!file || isUploading"
              class="btn btn-primary flex items-center"
            >
              <ArrowUpTrayIcon class="w-5 h-5 mr-2" />
              {{ isUploading ? 'Import en cours...' : 'Lancer l\'import' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
