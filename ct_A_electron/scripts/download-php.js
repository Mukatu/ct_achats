const https = require('https')
const fs = require('fs')
const path = require('path')
const AdmZip = require('adm-zip')

const PHP_VERSION = '8.2.30'
const PHP_URL_WIN = `https://windows.php.net/downloads/releases/php-${PHP_VERSION}-Win32-vs16-x64.zip`

const targetDir = path.join(__dirname, '../resources/php-portable')

function downloadFile(url, dest) {
  return new Promise((resolve, reject) => {
    console.log(`Downloading from: ${url}`)

    const file = fs.createWriteStream(dest)

    const options = {
      headers: {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) CT_Achats-Electron/1.0'
      }
    }

    https.get(url, options, (response) => {
      // Handle redirects
      if (response.statusCode === 302 || response.statusCode === 301) {
        console.log(`Redirecting to: ${response.headers.location}`)
        file.close()
        fs.unlinkSync(dest)
        downloadFile(response.headers.location, dest)
          .then(resolve)
          .catch(reject)
        return
      }

      if (response.statusCode !== 200) {
        reject(new Error(`Failed to download: ${response.statusCode}`))
        return
      }

      const totalSize = parseInt(response.headers['content-length'], 10)
      let downloadedSize = 0

      response.on('data', (chunk) => {
        downloadedSize += chunk.length
        const percent = ((downloadedSize / totalSize) * 100).toFixed(1)
        process.stdout.write(`\rDownloading... ${percent}%`)
      })

      response.pipe(file)

      file.on('finish', () => {
        file.close()
        console.log('\nDownload complete!')
        resolve()
      })
    }).on('error', (err) => {
      fs.unlink(dest, () => {})
      reject(err)
    })
  })
}

async function downloadPHP() {
  if (process.platform !== 'win32') {
    console.log('----------------------------------------')
    console.log('Skipping PHP download on non-Windows platform')
    console.log('macOS/Linux will use system PHP')
    console.log('')
    console.log('Sur macOS, assurez-vous que PHP 8.x est installe:')
    console.log('  brew install php')
    console.log('----------------------------------------')
    return
  }

  console.log('========================================')
  console.log(`Downloading PHP ${PHP_VERSION} for Windows...`)
  console.log('========================================')

  // Creer le repertoire cible
  if (!fs.existsSync(targetDir)) {
    fs.mkdirSync(targetDir, { recursive: true })
  }

  const zipPath = path.join(targetDir, 'php.zip')

  try {
    // Telecharger le fichier
    await downloadFile(PHP_URL_WIN, zipPath)

    // Extraire l'archive avec adm-zip
    console.log('Extracting PHP...')
    const zip = new AdmZip(zipPath)
    zip.extractAllTo(targetDir, true)

    // Supprimer l'archive
    fs.unlinkSync(zipPath)

    // Configurer php.ini
    const iniSource = path.join(targetDir, 'php.ini-production')
    const iniTarget = path.join(targetDir, 'php.ini')

    if (fs.existsSync(iniSource)) {
      let iniContent = fs.readFileSync(iniSource, 'utf8')

      // Activer les extensions necessaires pour Laravel
      const extensionsToEnable = [
        'pdo_sqlite',
        'sqlite3',
        'mbstring',
        'openssl',
        'curl',
        'fileinfo',
        'gd',
        'exif',
        'intl',
      ]

      for (const ext of extensionsToEnable) {
        iniContent = iniContent.replace(
          new RegExp(`;extension=${ext}`, 'g'),
          `extension=${ext}`
        )
      }

      // Configurer le chemin des extensions
      const extDir = path.join(targetDir, 'ext').replace(/\\/g, '/')
      iniContent = iniContent.replace(
        /;extension_dir = "ext"/,
        `extension_dir = "${extDir}"`
      )

      fs.writeFileSync(iniTarget, iniContent)
      console.log('php.ini configured successfully!')
    }

    console.log('')
    console.log('========================================')
    console.log('PHP downloaded and configured successfully!')
    console.log(`Location: ${targetDir}`)
    console.log('========================================')

  } catch (error) {
    console.error('Error downloading PHP:', error)
    process.exit(1)
  }
}

downloadPHP().catch(console.error)
