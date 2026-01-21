import axios from 'axios'
import router from '@/router'

// Déterminer la baseURL selon l'environnement
function getBaseURL(): string {
  // En mode Electron, utiliser l'API exposée par preload
  if ((window as any).electronAPI?.getApiBaseUrl) {
    return (window as any).electronAPI.getApiBaseUrl()
  }
  // En mode web, utiliser le proxy Vite
  return '/api/v1'
}

const api = axios.create({
  baseURL: getBaseURL(),
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

// Intercepteur pour ajouter le token d'authentification
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('ct_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => Promise.reject(error)
)

// Intercepteur pour gérer les erreurs
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Token expiré ou invalide
      localStorage.removeItem('ct_token')
      // Utiliser le router au lieu de window.location pour Electron
      router.push({ name: 'login' })
    }
    return Promise.reject(error)
  }
)

export default api

// Services spécifiques
export const ebService = {
  getAll: (params?: any) => api.get('/expressions-besoin', { params }),
  get: (id: string) => api.get(`/expressions-besoin/${id}`),
  create: (data: any) => api.post('/expressions-besoin', data),
  update: (id: string, data: any) => api.put(`/expressions-besoin/${id}`, data),
  delete: (id: string) => api.delete(`/expressions-besoin/${id}`),
  assigner: (id: string, acheteurId: string) => api.post(`/expressions-besoin/${id}/assigner`, { acheteur_id: acheteurId }),
  valider: (id: string, commentaire?: string) => api.post(`/expressions-besoin/${id}/valider`, { commentaire }),
  rejeter: (id: string, motif: string) => api.post(`/expressions-besoin/${id}/rejeter`, { motif_rejet: motif }),
  transformer: (id: string, data: any) => api.post(`/expressions-besoin/${id}/transformer`, data),
}

export const daService = {
  getAll: (params?: any) => api.get('/demandes-achat', { params }),
  get: (id: string) => api.get(`/demandes-achat/${id}`),
  create: (data: any) => api.post('/demandes-achat', data),
  update: (id: string, data: any) => api.put(`/demandes-achat/${id}`, data),
  delete: (id: string) => api.delete(`/demandes-achat/${id}`),
  valider: (id: string, commentaire?: string) => api.post(`/demandes-achat/${id}/valider`, { commentaire }),
  rejeter: (id: string, motif: string) => api.post(`/demandes-achat/${id}/rejeter`, { motif_rejet: motif }),
  transformer: (id: string, data: any) => api.post(`/demandes-achat/${id}/transformer`, data),
  // Lignes
  getLignes: (daId: string) => api.get(`/demandes-achat/${daId}/lignes`),
  createLigne: (daId: string, data: any) => api.post(`/demandes-achat/${daId}/lignes`, data),
  updateLigne: (daId: string, ligneId: string, data: any) => api.put(`/demandes-achat/${daId}/lignes/${ligneId}`, data),
  deleteLigne: (daId: string, ligneId: string) => api.delete(`/demandes-achat/${daId}/lignes/${ligneId}`),
  selectOffre: (daId: string, ligneId: string, offreId: string) => api.post(`/demandes-achat/${daId}/lignes/${ligneId}/select-offre`, { offre_id: offreId }),
}

export const ligneService = {
  getOffres: (ligneId: string) => api.get(`/lignes-da/${ligneId}/offres`),
  createOffre: (ligneId: string, data: any) => api.post(`/lignes-da/${ligneId}/offres`, data),
  updateOffre: (ligneId: string, offreId: string, data: any) => api.put(`/lignes-da/${ligneId}/offres/${offreId}`, data),
  deleteOffre: (ligneId: string, offreId: string) => api.delete(`/lignes-da/${ligneId}/offres/${offreId}`),
  compare: (ligneId: string) => api.get(`/lignes-da/${ligneId}/offres/compare`),
}

export const ponderationService = {
  getCriteres: () => api.get('/criteres-ponderation'),
  updatePoids: (ponderations: { id: string; poids: number }[]) => api.put('/criteres-ponderation/poids', { ponderations }),
  toggle: (id: string) => api.post(`/criteres-ponderation/${id}/toggle`),
}

export const bcService = {
  getAll: (params?: any) => api.get('/bons-commande', { params }),
  get: (id: string) => api.get(`/bons-commande/${id}`),
  create: (data: any) => api.post('/bons-commande', data),
  update: (id: string, data: any) => api.put(`/bons-commande/${id}`, data),
  delete: (id: string) => api.delete(`/bons-commande/${id}`),
  valider: (id: string, commentaire?: string) => api.post(`/bons-commande/${id}/valider`, { commentaire }),
  rejeter: (id: string, motif: string) => api.post(`/bons-commande/${id}/rejeter`, { motif_rejet: motif }),
  envoyer: (id: string) => api.post(`/bons-commande/${id}/envoyer`),
  getPdf: (id: string) => api.get(`/bons-commande/${id}/pdf`, { responseType: 'blob' }),
}

export const fournisseurService = {
  getAll: (params?: any) => api.get('/fournisseurs', { params }),
  get: (id: string) => api.get(`/fournisseurs/${id}`),
  create: (data: any) => api.post('/fournisseurs', data),
  update: (id: string, data: any) => api.put(`/fournisseurs/${id}`, data),
  delete: (id: string) => api.delete(`/fournisseurs/${id}`),
  getContacts: (id: string) => api.get(`/fournisseurs/${id}/contacts`),
  addContact: (id: string, data: any) => api.post(`/fournisseurs/${id}/contacts`, data),
  getDocuments: (id: string) => api.get(`/fournisseurs/${id}/documents`),
  addDocument: (id: string, data: FormData) => api.post(`/fournisseurs/${id}/documents`, data, {
    headers: { 'Content-Type': 'multipart/form-data' }
  }),
}

export const receptionService = {
  getAll: (params?: any) => api.get('/receptions', { params }),
  get: (id: string) => api.get(`/receptions/${id}`),
  create: (data: any) => api.post('/receptions', data),
  update: (id: string, data: any) => api.put(`/receptions/${id}`, data),
  delete: (id: string) => api.delete(`/receptions/${id}`),
  valider: (id: string) => api.post(`/receptions/${id}/valider`),
  getLignesBC: (bcId: string) => api.get(`/bons-commande/${bcId}/lignes-pour-reception`),
}

export const factureService = {
  getAll: (params?: any) => api.get('/factures', { params }),
  get: (id: string) => api.get(`/factures/${id}`),
  create: (data: any) => api.post('/factures', data),
  update: (id: string, data: any) => api.put(`/factures/${id}`, data),
  delete: (id: string) => api.delete(`/factures/${id}`),
  soumettre: (id: string) => api.post(`/factures/${id}/soumettre`),
  valider: (id: string) => api.post(`/factures/${id}/valider`),
  rejeter: (id: string, motif: string) => api.post(`/factures/${id}/rejeter`, { motif }),
  enregistrerPaiement: (id: string, data: any) => api.post(`/factures/${id}/paiement`, data),
  getStats: () => api.get('/factures-stats'),
}

export const referentielService = {
  getZones: () => api.get('/referentiels/zones'),
  getDirections: () => api.get('/referentiels/directions'),
  getServices: () => api.get('/referentiels/services'),
  getAcheteurs: () => api.get('/referentiels/acheteurs'),
  getFournisseurs: () => api.get('/referentiels/fournisseurs'),
  getUsers: () => api.get('/users', { params: { actif: true } }),
  getUnitesMesure: () => api.get('/referentiels/unites-mesure'),
  getNaturesDepense: () => api.get('/referentiels/natures-depense'),
  getStatutsEB: () => api.get('/referentiels/statuts-eb'),
  getStatutsDA: () => api.get('/referentiels/statuts-da'),
  getStatutsBC: () => api.get('/referentiels/statuts-bc'),
  getStatutsBR: () => api.get('/referentiels/statuts-br'),
  getStatutsFacture: () => api.get('/referentiels/statuts-facture'),
  getStatutsPaiement: () => api.get('/referentiels/statuts-paiement'),
  getTypesFacture: () => api.get('/referentiels/types-facture'),
  getTypesBC: () => api.get('/referentiels/types-bc'),
  getConditionsPaiement: () => api.get('/referentiels/conditions-paiement'),
}

export const dashboardService = {
  getData: () => api.get('/dashboard'),
  getStats: (annee?: number) => api.get('/dashboard/stats', { params: { annee } }),
}

export const statistiquesService = {
  getOverview: (annee?: number) => api.get('/statistiques/overview', { params: { annee } }),
  getEvolution: (annee?: number) => api.get('/statistiques/evolution', { params: { annee } }),
  getTopFournisseurs: (annee?: number, limit?: number) => api.get('/statistiques/fournisseurs', { params: { annee, limit } }),
  getParDirection: (annee?: number) => api.get('/statistiques/directions', { params: { annee } }),
  getDelais: (annee?: number) => api.get('/statistiques/delais', { params: { annee } }),
  getAlertes: () => api.get('/statistiques/alertes'),
  getAnneesDisponibles: () => api.get('/statistiques/annees'),
}

export const importService = {
  downloadTemplate: (type: 'eb' | 'da' | 'bc') => api.get(`/import/template/${type}`, { responseType: 'blob' }),
  importFile: (type: 'eb' | 'da' | 'bc', formData: FormData) => api.post(`/import/${type}`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  }),
}

export const userService = {
  getAll: (params?: any) => api.get('/users', { params }),
  get: (id: string) => api.get(`/users/${id}`),
  create: (data: any) => api.post('/users', data),
  update: (id: string, data: any) => api.put(`/users/${id}`, data),
  delete: (id: string) => api.delete(`/users/${id}`),
  getAcheteurs: () => api.get('/users/acheteurs'),
  getValideurs: () => api.get('/users/valideurs'),
}

export const contratService = {
  getAll: (params?: any) => api.get('/contrats', { params }),
  get: (id: string) => api.get(`/contrats/${id}`),
  create: (data: any) => api.post('/contrats', data),
  update: (id: string, data: any) => api.put(`/contrats/${id}`, data),
  delete: (id: string) => api.delete(`/contrats/${id}`),
  activer: (id: string) => api.post(`/contrats/${id}/activer`),
  suspendre: (id: string, motif?: string) => api.post(`/contrats/${id}/suspendre`, { motif }),
  reactiver: (id: string) => api.post(`/contrats/${id}/reactiver`),
  terminer: (id: string, data?: any) => api.post(`/contrats/${id}/terminer`, data),
  resilier: (id: string, motif: string, date?: string) => api.post(`/contrats/${id}/resilier`, { motif, date_resiliation: date }),
  genererEcheances: (id: string, data?: any) => api.post(`/contrats/${id}/generer-echeances`, data),
  getEcheances: (id: string, params?: any) => api.get(`/contrats/${id}/echeances`, { params }),
  getStats: () => api.get('/contrats-stats'),
  getTypesContrat: () => api.get('/types-contrat'),
  getStatuts: () => api.get('/referentiels/statuts-contrat'),
  getPeriodicites: () => api.get('/referentiels/periodicites-contrat'),
}

export const echeanceService = {
  getAll: (params?: any) => api.get('/echeances-contrat', { params }),
  get: (id: string) => api.get(`/echeances-contrat/${id}`),
  update: (id: string, data: any) => api.put(`/echeances-contrat/${id}`, data),
  enregistrerFacture: (id: string, data: any) => api.post(`/echeances-contrat/${id}/facture`, data),
  marquerPayee: (id: string, data: any) => api.post(`/echeances-contrat/${id}/payer`, data),
  annuler: (id: string, motif?: string) => api.post(`/echeances-contrat/${id}/annuler`, { motif }),
  aTraiter: (id: string) => api.post(`/echeances-contrat/${id}/a-traiter`),
  getStats: (annee?: number) => api.get('/echeances-contrat-stats', { params: { annee } }),
  getCalendrier: (annee?: number, mois?: number) => api.get('/echeances-contrat-calendrier', { params: { annee, mois } }),
}
