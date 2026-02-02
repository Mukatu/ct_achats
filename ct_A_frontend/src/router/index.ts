import { createRouter, createWebHistory, createWebHashHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

// Utiliser HashHistory pour Electron (file://), WebHistory pour le web
const isElectron = !!(window as any).electronAPI?.isElectron

const router = createRouter({
  history: isElectron ? createWebHashHistory() : createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/auth/LoginView.vue'),
      meta: { guest: true }
    },
    {
      path: '/',
      component: () => import('@/components/layout/MainLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'dashboard',
          component: () => import('@/views/dashboard/DashboardView.vue'),
        },
        // Expressions de Besoins
        {
          path: 'expressions-besoin',
          name: 'eb-list',
          component: () => import('@/views/eb/EBListView.vue'),
        },
        {
          path: 'expressions-besoin/nouveau',
          name: 'eb-create',
          component: () => import('@/views/eb/EBCreateView.vue'),
        },
        {
          path: 'expressions-besoin/:id',
          name: 'eb-show',
          component: () => import('@/views/eb/EBShowView.vue'),
        },
        {
          path: 'expressions-besoin/:id/modifier',
          name: 'eb-edit',
          component: () => import('@/views/eb/EBEditView.vue'),
        },
        // Demandes d'Achat
        {
          path: 'demandes-achat',
          name: 'da-list',
          component: () => import('@/views/da/DAListView.vue'),
        },
        {
          path: 'demandes-achat/nouveau',
          name: 'da-create',
          component: () => import('@/views/da/DACreateView.vue'),
        },
        {
          path: 'demandes-achat/:id',
          name: 'da-show',
          component: () => import('@/views/da/DAShowView.vue'),
        },
        {
          path: 'demandes-achat/:id/modifier',
          name: 'da-edit',
          component: () => import('@/views/da/DAEditView.vue'),
        },
        // Bons de Commande
        {
          path: 'bons-commande',
          name: 'bc-list',
          component: () => import('@/views/bc/BCListView.vue'),
        },
        {
          path: 'bons-commande/nouveau',
          name: 'bc-create',
          component: () => import('@/views/bc/BCCreateView.vue'),
        },
        {
          path: 'bons-commande/:id',
          name: 'bc-show',
          component: () => import('@/views/bc/BCShowView.vue'),
        },
        {
          path: 'bons-commande/:id/modifier',
          name: 'bc-edit',
          component: () => import('@/views/bc/BCEditView.vue'),
        },
        // Fournisseurs
        {
          path: 'fournisseurs',
          name: 'fournisseurs-list',
          component: () => import('@/views/fournisseurs/FournisseurListView.vue'),
        },
        {
          path: 'fournisseurs/nouveau',
          name: 'fournisseur-create',
          component: () => import('@/views/fournisseurs/FournisseurCreateView.vue'),
        },
        {
          path: 'fournisseurs/:id',
          name: 'fournisseur-show',
          component: () => import('@/views/fournisseurs/FournisseurShowView.vue'),
        },
        {
          path: 'fournisseurs/:id/modifier',
          name: 'fournisseur-edit',
          component: () => import('@/views/fournisseurs/FournisseurEditView.vue'),
        },
        // Receptions (Bons de Reception)
        {
          path: 'receptions',
          name: 'br-list',
          component: () => import('@/views/br/BRListView.vue'),
        },
        {
          path: 'receptions/nouveau',
          name: 'br-create',
          component: () => import('@/views/br/BRCreateView.vue'),
        },
        {
          path: 'receptions/:id',
          name: 'br-show',
          component: () => import('@/views/br/BRShowView.vue'),
        },
        {
          path: 'receptions/:id/modifier',
          name: 'br-edit',
          component: () => import('@/views/br/BREditView.vue'),
        },
        // Statistiques
        {
          path: 'stats',
          name: 'stats',
          component: () => import('@/views/stats/StatsView.vue'),
        },
        // Factures
        {
          path: 'factures',
          name: 'facture-list',
          component: () => import('@/views/factures/FactureListView.vue'),
        },
        {
          path: 'factures/nouveau',
          name: 'facture-create',
          component: () => import('@/views/factures/FactureCreateView.vue'),
        },
        {
          path: 'factures/:id',
          name: 'facture-show',
          component: () => import('@/views/factures/FactureShowView.vue'),
        },
        {
          path: 'factures/:id/modifier',
          name: 'facture-edit',
          component: () => import('@/views/factures/FactureEditView.vue'),
        },
        // Contrats
        {
          path: 'contrats',
          name: 'contrat-list',
          component: () => import('@/views/contrats/ContratListView.vue'),
        },
        {
          path: 'contrats/nouveau',
          name: 'contrat-create',
          component: () => import('@/views/contrats/ContratCreateView.vue'),
        },
        {
          path: 'contrats/:id',
          name: 'contrat-show',
          component: () => import('@/views/contrats/ContratShowView.vue'),
        },
        {
          path: 'contrats/:id/modifier',
          name: 'contrat-edit',
          component: () => import('@/views/contrats/ContratEditView.vue'),
        },
        {
          path: 'echeances',
          name: 'echeance-list',
          component: () => import('@/views/contrats/EcheanceListView.vue'),
        },
        // Parametres (Admin uniquement)
        {
          path: 'parametres',
          name: 'settings',
          component: () => import('@/views/settings/SettingsView.vue'),
          meta: { requiresAdmin: true },
        },
        // Gestion des utilisateurs (Admin uniquement)
        {
          path: 'utilisateurs',
          name: 'users-list',
          component: () => import('@/views/users/UserListView.vue'),
          meta: { requiresAdmin: true },
        },
        {
          path: 'utilisateurs/nouveau',
          name: 'user-create',
          component: () => import('@/views/users/UserCreateView.vue'),
          meta: { requiresAdmin: true },
        },
        {
          path: 'utilisateurs/:id',
          name: 'user-show',
          component: () => import('@/views/users/UserShowView.vue'),
          meta: { requiresAdmin: true },
        },
        {
          path: 'utilisateurs/:id/modifier',
          name: 'user-edit',
          component: () => import('@/views/users/UserEditView.vue'),
          meta: { requiresAdmin: true },
        },
      ]
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/views/NotFoundView.vue'),
    }
  ],
})

// Navigation guard
router.beforeEach((to, from, next) => {
  const authStore = useAuthStore()

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next({ name: 'login', query: { redirect: to.fullPath } })
  } else if (to.meta.guest && authStore.isAuthenticated) {
    next({ name: 'dashboard' })
  } else if (to.meta.requiresAdmin && !authStore.isAdmin) {
    // Accès refusé aux pages admin si non admin
    next({ name: 'dashboard' })
  } else {
    next()
  }
})

export default router
