import { app, BrowserWindow, ipcMain, shell, dialog } from 'electron'
import path from 'path'
import { PHPServer } from './php-server'
import { getPaths } from './utils/paths'

const isDev = !app.isPackaged

let phpServer: PHPServer | null = null
let mainWindow: BrowserWindow | null = null

async function createWindow(): Promise<void> {
  const paths = getPaths()

  mainWindow = new BrowserWindow({
    width: 1400,
    height: 900,
    minWidth: 1024,
    minHeight: 768,
    title: 'CT_Achats',
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      nodeIntegration: false,
      contextIsolation: true,
    },
    icon: paths.icon,
    show: false,
  })

  // Afficher quand pret
  mainWindow.once('ready-to-show', () => {
    mainWindow?.show()
  })

  // Charger l'application
  if (isDev) {
    await mainWindow.loadURL('http://localhost:3000')
    mainWindow.webContents.openDevTools()
  } else {
    await mainWindow.loadFile(path.join(paths.frontend, 'index.html'))
  }

  // Gerer les liens externes
  mainWindow.webContents.setWindowOpenHandler(({ url }) => {
    shell.openExternal(url)
    return { action: 'deny' }
  })

  mainWindow.on('closed', () => {
    mainWindow = null
  })
}

async function isServerRunning(port: number): Promise<boolean> {
  try {
    const response = await fetch(`http://127.0.0.1:${port}/api/v1/health`)
    return response.ok
  } catch {
    return false
  }
}

async function startPHPServer(): Promise<void> {
  const paths = getPaths()

  // En dev, verifier si le serveur tourne deja
  if (isDev) {
    const alreadyRunning = await isServerRunning(8001)
    if (alreadyRunning) {
      console.log('PHP Server already running on port 8001 (dev mode)')
      return
    }
  }

  phpServer = new PHPServer({
    phpPath: paths.php,
    backendPath: paths.backend,
    port: 8001,
    host: '127.0.0.1',
  })

  try {
    await phpServer.start()
    console.log('PHP Server started on port 8001')
  } catch (error) {
    console.error('Failed to start PHP server:', error)

    dialog.showErrorBox(
      'Erreur de demarrage',
      `Impossible de demarrer le serveur PHP.\n\n${error}`
    )

    app.quit()
  }
}

// IPC Handlers
function setupIpcHandlers(): void {
  ipcMain.handle('open-external', async (_, url: string) => {
    await shell.openExternal(url)
  })

  ipcMain.handle('get-app-path', () => {
    return app.getPath('userData')
  })

  ipcMain.handle('get-version', () => {
    return app.getVersion()
  })

  ipcMain.handle('show-save-dialog', async (_, options) => {
    return dialog.showSaveDialog(mainWindow!, options)
  })

  ipcMain.handle('show-open-dialog', async (_, options) => {
    return dialog.showOpenDialog(mainWindow!, options)
  })
}

// Demarrage de l'application
app.whenReady().then(async () => {
  try {
    // Configurer les handlers IPC
    setupIpcHandlers()

    // Demarrer le serveur PHP
    await startPHPServer()

    // Creer la fenetre principale
    await createWindow()

  } catch (error) {
    console.error('Failed to start application:', error)
    app.quit()
  }
})

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit()
  }
})

app.on('before-quit', async () => {
  if (phpServer) {
    await phpServer.stop()
  }
})

app.on('activate', async () => {
  if (BrowserWindow.getAllWindows().length === 0) {
    await createWindow()
  }
})
