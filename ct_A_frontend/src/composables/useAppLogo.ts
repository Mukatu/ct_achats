import { ref, computed } from 'vue'
import api from '@/services/api'
import defaultLogo from '@/assets/logo-ct-small.jpeg'

// État global partagé entre tous les composants
const settingsLogoUrl = ref<string | null>(null)
const isLoaded = ref(false)

export function useAppLogo() {
  // Logo calculé avec fallback sur l'image par défaut
  const logoUrl = computed(() => {
    return settingsLogoUrl.value || defaultLogo
  })

  // Charger le logo depuis les paramètres
  async function loadLogo() {
    if (isLoaded.value) return

    try {
      const response = await api.get('/settings/general')
      const data = response.data.data || response.data
      if (data.logo_url) {
        settingsLogoUrl.value = data.logo_url
      }
      isLoaded.value = true
    } catch (error) {
      // En cas d'erreur (ex: non authentifié), on garde le logo par défaut
      console.warn('Impossible de charger le logo depuis les paramètres')
    }
  }

  // Mettre à jour le logo (appelé après un upload réussi)
  function updateLogo(newUrl: string | null) {
    settingsLogoUrl.value = newUrl
  }

  // Forcer le rechargement
  function refreshLogo() {
    isLoaded.value = false
    loadLogo()
  }

  return {
    logoUrl,
    defaultLogo,
    loadLogo,
    updateLogo,
    refreshLogo,
  }
}
