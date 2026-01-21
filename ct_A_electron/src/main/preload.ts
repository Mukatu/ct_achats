import { contextBridge, ipcRenderer } from 'electron'

// Exposer une API securisee au renderer
contextBridge.exposeInMainWorld('electronAPI', {
  // Informations systeme
  platform: process.platform,
  isElectron: true,

  // Configuration API
  getApiBaseUrl: () => 'http://127.0.0.1:8001/api/v1',

  // Actions systeme
  openExternal: (url: string) => ipcRenderer.invoke('open-external', url),

  // Gestion des fichiers
  showSaveDialog: (options: any) => ipcRenderer.invoke('show-save-dialog', options),
  showOpenDialog: (options: any) => ipcRenderer.invoke('show-open-dialog', options),

  // Gestion de l'application
  getAppPath: () => ipcRenderer.invoke('get-app-path'),
  getVersion: () => ipcRenderer.invoke('get-version'),
})
