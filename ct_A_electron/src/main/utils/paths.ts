import { app } from 'electron'
import path from 'path'

const isDev = !app.isPackaged

export interface AppPaths {
  php: string
  backend: string
  frontend: string
  userData: string
  icon: string
}

export function getPaths(): AppPaths {
  if (isDev) {
    // Mode developpement: chemins relatifs au projet
    const projectRoot = path.join(__dirname, '../../..')

    return {
      php: path.join(projectRoot, 'resources/php-portable'),
      backend: path.join(projectRoot, '../ct_A_backend'),
      frontend: '', // Utilise le serveur Vite en dev
      userData: app.getPath('userData'),
      icon: path.join(projectRoot, 'resources/icons/icon.png'),
    }
  } else {
    // Mode production: chemins dans le bundle
    const resourcesPath = process.resourcesPath

    return {
      php: path.join(resourcesPath, 'php'),
      backend: path.join(resourcesPath, 'backend'),
      frontend: path.join(resourcesPath, 'frontend'),
      userData: app.getPath('userData'),
      icon: path.join(resourcesPath, 'icons/icon.png'),
    }
  }
}
