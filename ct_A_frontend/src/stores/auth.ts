import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'
import type { User } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem('ct_token'))
  const permissions = ref<string[]>([])
  const loading = ref(false)

  const isAuthenticated = computed(() => !!token.value && !!user.value)
  
  const isAcheteur = computed(() => 
    user.value?.est_acheteur || permissions.value.includes('all') || 
    user.value?.roles?.some(r => r.code === 'ACHETEUR')
  )

  const isValideur = computed(() => 
    user.value?.est_valideur || permissions.value.includes('all')
  )

  async function login(email: string, password: string) {
    loading.value = true
    try {
      const response = await api.post('/login', { email, password })
      token.value = response.data.token
      user.value = response.data.user
      localStorage.setItem('ct_token', response.data.token)
      api.defaults.headers.common['Authorization'] = `Bearer ${response.data.token}`
      return true
    } catch (error: any) {
      throw error
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    try {
      await api.post('/logout')
    } catch (error) {
      // Ignore l'erreur
    } finally {
      token.value = null
      user.value = null
      permissions.value = []
      localStorage.removeItem('ct_token')
      delete api.defaults.headers.common['Authorization']
    }
  }

  async function fetchUser() {
    if (!token.value) return
    
    loading.value = true
    try {
      api.defaults.headers.common['Authorization'] = `Bearer ${token.value}`
      const response = await api.get('/me')
      user.value = response.data.user
      permissions.value = response.data.permissions || []
    } catch (error) {
      logout()
    } finally {
      loading.value = false
    }
  }

  function hasPermission(permission: string): boolean {
    return permissions.value.includes('all') || permissions.value.includes(permission)
  }

  function hasRole(roleCode: string): boolean {
    return user.value?.roles?.some(r => r.code === roleCode) || false
  }

  return {
    user,
    token,
    permissions,
    loading,
    isAuthenticated,
    isAcheteur,
    isValideur,
    login,
    logout,
    fetchUser,
    hasPermission,
    hasRole,
  }
})
