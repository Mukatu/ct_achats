// Types principaux CT_Achats

export interface User {
  id: string
  matricule?: string
  nom: string
  prenom: string
  email: string
  telephone?: string
  service_id?: string
  service?: Service
  manager_id?: string
  manager?: User
  est_acheteur: boolean
  est_valideur: boolean
  seuil_validation?: number
  actif: boolean
  roles?: Role[]
  nom_complet: string
}

export interface Role {
  id: string
  code: string
  libelle: string
  description?: string
  permissions: string[]
  est_systeme: boolean
  actif: boolean
}

export interface Societe {
  id: string
  code: string
  raison_sociale: string
  sigle?: string
  niu?: string
  rccm?: string
  adresse_siege?: string
  ville?: string
  pays: string
  devise_defaut: string
  actif: boolean
}

export interface Zone {
  id: string
  code: string
  libelle: string
  ville?: string
  actif: boolean
}

export interface Direction {
  id: string
  zone_id: string
  zone?: Zone
  code: string
  libelle: string
  libelle_court?: string
  actif: boolean
}

export interface Service {
  id: string
  direction_id: string
  direction?: Direction
  code: string
  libelle: string
  actif: boolean
}

export interface Fournisseur {
  id: string
  code: string
  raison_sociale: string
  sigle?: string
  niu?: string
  rccm?: string
  adresse?: string
  ville?: string
  pays: string
  telephone?: string
  email?: string
  type_fournisseur: 'LOCAL' | 'CEMAC' | 'INTERNATIONAL'
  statut: 'PROSPECT' | 'EN_VALIDATION' | 'ACTIF' | 'SUSPENDU' | 'BLOQUE' | 'INACTIF'
  devise_defaut: string
  taux_tva: number
  actif: boolean
}

export interface ExpressionBesoin {
  id: string
  numero: string
  date_expression: string
  zone_id: string
  zone?: Zone
  direction_id: string
  direction?: Direction
  service_id?: string
  service?: Service
  demandeur_id: string
  demandeur?: User
  objet: string
  description_detaillee?: string
  quantite_souhaitee?: string
  date_besoin?: string
  estimation?: number
  acheteur_id?: string
  acheteur?: User
  fournisseur_suggere_id?: string
  fournisseur_suggere?: Fournisseur
  statut: StatutEB
  date_validation?: string
  motif_rejet?: string
  commentaire?: string
  created_at: string
  estimation_formatee?: string
  statut_libelle?: string
}

export type StatutEB = 
  | 'EN_SUSPENS' 
  | 'EN_COURS_ACH' 
  | 'EN_COURS_CDG' 
  | 'EN_COURS_DFC' 
  | 'EN_COURS_DG' 
  | 'TRAITE' 
  | 'ANNULE' 
  | 'NA'

export interface DemandeAchat {
  id: string
  numero: string
  type_demande: 'DA' | 'DAC'
  date_demande: string
  expression_besoin_id?: string
  expression_besoin?: ExpressionBesoin
  zone_id: string
  zone?: Zone
  direction_id: string
  direction?: Direction
  service_id?: string
  service?: Service
  demandeur_id: string
  demandeur?: User
  objet: string
  description?: string
  acheteur_id?: string
  acheteur?: User
  montant: number
  statut: StatutDA
  date_validation?: string
  motif_rejet?: string
  lignes?: LigneDemandeAchat[]
  created_at: string
}

export type StatutDA = StatutEB

export interface LigneDemandeAchat {
  id: string
  demande_achat_id: string
  numero_ligne: number
  designation: string
  description?: string
  quantite: number
  unite_mesure_id?: string
  unite_mesure?: UniteMesure
  prix_unitaire_estime?: number
  montant_estime?: number
  offre_selectionnee_id?: string
  offres?: OffreFournisseur[]
  offreSelectionnee?: OffreFournisseur
}

export type OffreTechnique = 'CONFORME' | 'NON_CONFORME'

export interface OffreFournisseur {
  id: string
  ligne_demande_achat_id: string
  fournisseur_id: string
  fournisseur?: Fournisseur
  prix_unitaire: number
  delai_livraison_jours: number
  conditions_paiement_jours: number
  garantie?: string
  garantie_mois?: number
  offre_technique: OffreTechnique
  commentaire?: string
  score?: number
  est_selectionnee: boolean
  created_at?: string
  updated_at?: string
}

export interface CriterePonderation {
  id: string
  code: string
  libelle: string
  description?: string
  poids: number
  actif: boolean
  ordre: number
}

export interface BonCommande {
  id: string
  numero: string
  type_bc: 'BCAL' | 'BCL' | 'BCAI' | 'BCI' | 'IPO'
  date_bc: string
  demande_achat_id?: string
  demande_achat?: DemandeAchat
  expression_besoin_id?: string
  expression_besoin?: ExpressionBesoin
  fournisseur_id: string
  fournisseur?: Fournisseur
  zone_id: string
  zone?: Zone
  direction_id: string
  direction?: Direction
  demandeur_id: string
  demandeur?: User
  acheteur_id?: string
  acheteur?: User
  objet: string
  nature_prestation?: string
  montant_ht_xaf: number
  taux_tva: number
  montant_tva: number
  montant_ttc_xaf: number
  devise_etrangere?: string
  montant_devise?: number
  taux_change?: number
  conditions_paiement?: string
  date_livraison_prevue?: string
  statut: StatutBC
  date_envoi_fournisseur?: string
  lignes?: LigneBonCommande[]
  created_at: string
}

export type StatutBC = 
  | 'NC' 
  | 'EN_COURS_A' 
  | 'EN_COURS_CDG' 
  | 'EN_COURS_DFC'
  | 'DAC_CDG'
  | 'DAC_DG'
  | 'DAC_TRESO'
  | 'EN_COURS_FSSEUR'
  | 'LIVRAISON_PARTIELLE'
  | 'LIVRE'
  | 'TRAITE'
  | 'ANNULE'

export interface LigneBonCommande {
  id: string
  bon_commande_id: string
  numero_ligne: number
  designation: string
  description?: string
  quantite: number
  unite_mesure_id?: string
  unite_mesure?: UniteMesure
  prix_unitaire_xaf: number
  montant_xaf: number
  quantite_recue: number
  quantite_facturee: number
  statut_ligne: string
}

export interface UniteMesure {
  id: string
  code: string
  libelle: string
  symbole?: string
  type?: string
  actif: boolean
}

export interface Reception {
  id: string
  numero: string
  bon_commande_id: string
  bon_commande?: BonCommande
  date_reception: string
  receptionnaire_id: string
  receptionnaire?: User
  type_reception: 'LIVRAISON' | 'SERVICE_FAIT' | 'PARTIELLE'
  lieu_reception?: string
  numero_bl_fournisseur?: string
  date_bl_fournisseur?: string
  numero_tracking?: string
  transporteur?: string
  commentaire?: string
  statut: StatutBR
  lignes?: LigneReception[]
  created_at: string
}

export type StatutBR = 'BROUILLON' | 'VALIDEE' | 'EN_LITIGE' | 'ANNULEE'

export interface LigneReception {
  id: string
  reception_id: string
  ligne_bon_commande_id: string
  ligne_bon_commande?: LigneBonCommande
  numero_ligne: number
  quantite_attendue: number
  quantite_recue: number
  quantite_conforme: number
  quantite_non_conforme: number
  quantite_refusee: number
  motif_non_conformite?: string
  motif_refus?: string
  numero_lot?: string
  date_peremption?: string
  numero_serie?: string
  commentaire?: string
}

export interface Facture {
  id: string
  numero_interne: string
  numero_fournisseur: string
  fournisseur_id: string
  fournisseur?: Fournisseur
  bon_commande_id?: string
  bon_commande?: BonCommande
  type_facture: 'FACTURE' | 'AVOIR' | 'ACOMPTE' | 'SITUATION'
  date_facture: string
  date_reception: string
  date_echeance: string
  montant_ht: number
  taux_tva: number
  montant_tva: number
  montant_ttc: number
  retenue_source?: number
  net_a_payer: number
  devise: string
  statut: StatutFacture
  statut_paiement: StatutPaiement
  date_validation?: string
  valideur_id?: string
  valideur?: User
  date_paiement?: string
  reference_paiement?: string
  montant_paye?: number
  ecart_rapprochement?: number
  motif_ecart?: string
  commentaire?: string
  lignes?: LigneFacture[]
  created_at: string
}

export type StatutFacture =
  | 'BROUILLON'
  | 'A_RAPPROCHER'
  | 'EN_RAPPROCHEMENT'
  | 'RAPPROCHEE'
  | 'A_VALIDER'
  | 'VALIDEE'
  | 'EN_LITIGE'
  | 'REJETEE'
  | 'ANNULEE'

export type StatutPaiement =
  | 'NON_PAYEE'
  | 'PARTIEL'
  | 'EN_PAIEMENT'
  | 'PAYEE'
  | 'SUSPENDUE'

export interface LigneFacture {
  id: string
  facture_id: string
  numero_ligne: number
  ligne_bon_commande_id?: string
  ligne_bon_commande?: LigneBonCommande
  ligne_reception_id?: string
  ligne_reception?: LigneReception
  reference_fournisseur?: string
  designation: string
  quantite: number
  unite_mesure_id?: string
  unite_mesure?: UniteMesure
  prix_unitaire_ht: number
  remise_percent?: number
  montant_ht: number
  taux_tva: number
  montant_tva: number
  montant_ttc: number
  ecart_prix?: number
  ecart_quantite?: number
  ecart_montant?: number
  statut_rapprochement: string
  commentaire?: string
}

export interface Pagination<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface SelectOption {
  value: string
  label: string
  color?: string
}

// Utilitaire pour formater les montants en FCFA
export function formatMontant(montant: number | undefined | null): string {
  if (montant === undefined || montant === null) return '0 FCFA'
  return new Intl.NumberFormat('fr-FR', {
    style: 'decimal',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(montant) + ' FCFA'
}

// Utilitaire pour formater les dates
export function formatDate(date: string | undefined | null): string {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}
