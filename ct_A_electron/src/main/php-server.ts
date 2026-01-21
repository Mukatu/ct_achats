import { spawn, ChildProcess, execSync } from 'child_process'
import path from 'path'
import fs from 'fs'
import { app } from 'electron'

interface PHPServerConfig {
  phpPath: string
  backendPath: string
  port: number
  host: string
}

export class PHPServer {
  private process: ChildProcess | null = null
  private config: PHPServerConfig

  constructor(config: PHPServerConfig) {
    this.config = config
  }

  async start(): Promise<void> {
    const { backendPath, port, host } = this.config

    // Preparer l'environnement Laravel
    await this.prepareLaravelEnvironment()

    // Determiner l'executable PHP
    const phpExecutable = this.getPhpExecutable()

    // Verifier que PHP est disponible (fichier ou dans le PATH)
    const phpAvailable = this.isPhpAvailable(phpExecutable)
    if (!phpAvailable) {
      throw new Error(`PHP executable not found: ${phpExecutable}\n\nSur macOS, installez PHP via: brew install php\nSur Windows, assurez-vous que Laragon est installe ou que PHP est dans le PATH`)
    }

    // Verifier la version PHP
    try {
      const version = execSync(`"${phpExecutable}" -v`).toString()
      console.log('PHP Version:', version.split('\n')[0])
    } catch (error) {
      throw new Error(`PHP non fonctionnel: ${error}`)
    }

    // Demarrer le serveur PHP built-in
    const artisanPath = path.join(backendPath, 'artisan')

    console.log(`Starting PHP server: ${phpExecutable} ${artisanPath} serve --host=${host} --port=${port}`)

    this.process = spawn(phpExecutable, [
      artisanPath,
      'serve',
      `--host=${host}`,
      `--port=${port}`,
    ], {
      cwd: backendPath,
      env: {
        ...process.env,
        APP_ENV: 'production',
        APP_DEBUG: 'false',
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: this.getSqlitePath(),
      },
      shell: true,
    })

    this.process.stdout?.on('data', (data) => {
      console.log(`PHP: ${data}`)
    })

    this.process.stderr?.on('data', (data) => {
      console.error(`PHP Error: ${data}`)
    })

    this.process.on('error', (error) => {
      console.error('PHP process error:', error)
    })

    this.process.on('exit', (code) => {
      console.log(`PHP process exited with code ${code}`)
    })

    // Attendre que le serveur soit pret
    await this.waitForServer()
  }

  private getPhpExecutable(): string {
    const { phpPath } = this.config

    if (process.platform === 'win32') {
      // Windows: utiliser PHP portable embarque
      const portablePath = path.join(phpPath, 'php.exe')
      if (fs.existsSync(portablePath)) {
        return portablePath
      }
      // Fallback: PHP dans le PATH (Laragon)
      return 'php'
    } else {
      // macOS/Linux: chercher PHP systeme
      const possiblePaths = [
        '/usr/bin/php',
        '/opt/homebrew/bin/php',
        '/usr/local/bin/php',
      ]

      for (const phpPath of possiblePaths) {
        if (fs.existsSync(phpPath)) {
          return phpPath
        }
      }

      // Fallback: esperer que php est dans le PATH
      return 'php'
    }
  }

  private isPhpAvailable(phpExecutable: string): boolean {
    // Si c'est un chemin absolu, verifier que le fichier existe
    if (path.isAbsolute(phpExecutable)) {
      return fs.existsSync(phpExecutable)
    }

    // Sinon, verifier si PHP est dans le PATH
    try {
      const cmd = process.platform === 'win32' ? 'where' : 'which'
      execSync(`${cmd} ${phpExecutable}`, { stdio: 'ignore' })
      return true
    } catch {
      return false
    }
  }

  private getSqlitePath(): string {
    const userDataPath = app.getPath('userData')
    return path.join(userDataPath, 'database.sqlite')
  }

  private async prepareLaravelEnvironment(): Promise<void> {
    const { backendPath } = this.config
    const userDataPath = app.getPath('userData')

    // Creer le repertoire userData si necessaire
    if (!fs.existsSync(userDataPath)) {
      fs.mkdirSync(userDataPath, { recursive: true })
    }

    // Creer le fichier SQLite si necessaire
    const sqlitePath = this.getSqlitePath()
    if (!fs.existsSync(sqlitePath)) {
      console.log('Creating SQLite database at:', sqlitePath)
      fs.writeFileSync(sqlitePath, '')

      // Executer les migrations
      await this.runMigrations()
    }

    // Creer les repertoires storage necessaires
    const storageDirs = [
      'storage/app/public',
      'storage/framework/cache/data',
      'storage/framework/sessions',
      'storage/framework/views',
      'storage/logs',
      'bootstrap/cache',
    ]

    for (const dir of storageDirs) {
      const fullPath = path.join(backendPath, dir)
      if (!fs.existsSync(fullPath)) {
        fs.mkdirSync(fullPath, { recursive: true })
      }
    }

    // Copier .env.electron vers .env si necessaire
    const envPath = path.join(backendPath, '.env')
    const envElectronPath = path.join(backendPath, '.env.electron')

    if (fs.existsSync(envElectronPath) && !fs.existsSync(envPath)) {
      let envContent = fs.readFileSync(envElectronPath, 'utf8')
      envContent = envContent.replace('${USER_DATA}', userDataPath.replace(/\\/g, '/'))
      fs.writeFileSync(envPath, envContent)
    }
  }

  private async runMigrations(): Promise<void> {
    const { backendPath } = this.config
    const phpExecutable = this.getPhpExecutable()
    const artisanPath = path.join(backendPath, 'artisan')

    console.log('Running database migrations...')

    try {
      const result = execSync(`"${phpExecutable}" "${artisanPath}" migrate --force`, {
        cwd: backendPath,
        env: {
          ...process.env,
          DB_CONNECTION: 'sqlite',
          DB_DATABASE: this.getSqlitePath(),
        },
      })
      console.log('Migrations completed:', result.toString())
    } catch (error) {
      console.error('Migration error:', error)
      // Ne pas bloquer le demarrage, les migrations peuvent echouer si deja faites
    }
  }

  private async waitForServer(timeout = 30000): Promise<void> {
    const startTime = Date.now()
    const { port, host } = this.config

    console.log(`Waiting for PHP server at http://${host}:${port}...`)

    while (Date.now() - startTime < timeout) {
      try {
        const response = await fetch(`http://${host}:${port}/api/v1/health`)
        if (response.ok) {
          console.log('PHP server is ready!')
          return
        }
      } catch {
        // Serveur pas encore pret
      }
      await new Promise(resolve => setTimeout(resolve, 500))
    }

    throw new Error('PHP server failed to start within timeout')
  }

  async stop(): Promise<void> {
    if (this.process) {
      console.log('Stopping PHP server...')

      if (process.platform === 'win32') {
        // Windows: kill le processus et ses enfants
        spawn('taskkill', ['/pid', this.process.pid!.toString(), '/f', '/t'])
      } else {
        this.process.kill('SIGTERM')
      }

      this.process = null
    }
  }
}
