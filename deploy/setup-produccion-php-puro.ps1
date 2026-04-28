# =============================================================================
# setup-produccion-php-puro.ps1
# Configura el alias /oficios sobre el layout PHP puro (public/ + app/).
#
# Idempotente: realiza un backup con timestamp del archivo de Apache afectado
# antes de cualquier escritura, y nunca borra archivos existentes.
#
# Uso (PowerShell como Administrador en el servidor):
#   cd C:\laragon\www\oficios\deploy
#   .\setup-produccion-php-puro.ps1
# =============================================================================

[CmdletBinding()]
param(
    [string]$ProjectPath  = 'C:/laragon/www/oficios',
    [string]$ApacheSites  = 'C:/laragon/etc/apache2/sites-enabled',
    [string]$ApacheConfig = 'C:/laragon/etc/apache2/httpd.conf'
)

$ErrorActionPreference = 'Stop'
$ts = Get-Date -Format 'yyyyMMdd-HHmmss'

Write-Host '=== Setup Respaldo de Oficios (PHP puro) ===' -ForegroundColor Cyan
Write-Host "Timestamp: $ts" -ForegroundColor DarkGray
Write-Host "ProjectPath: $ProjectPath"

# 1) Verificar layout esperado.
$expected = @(
    "$ProjectPath/public/index.php",
    "$ProjectPath/public/.htaccess",
    "$ProjectPath/app/config/paths.php",
    "$ProjectPath/app/config/config.php",
    "$ProjectPath/app/helpers/url_helper.php",
    "$ProjectPath/.env"
)
foreach ($f in $expected) {
    if (-not (Test-Path $f)) {
        Write-Error "Falta archivo requerido: $f"
        exit 1
    }
}
Write-Host '[OK] Layout PHP puro detectado.' -ForegroundColor Green

# 2) Crear carpetas runtime si faltan.
foreach ($d in @("$ProjectPath/storage/logs", "$ProjectPath/storage/cache", "$ProjectPath/uploads")) {
    if (-not (Test-Path $d)) {
        New-Item -ItemType Directory -Path $d -Force | Out-Null
        Write-Host "[+] Carpeta creada: $d"
    }
}

# 3) Copiar Alias de Apache.
$source = Join-Path $PSScriptRoot 'apache-alias-oficios.conf'
$target = Join-Path $ApacheSites  'apache-alias-oficios.conf'

if (-not (Test-Path $source)) {
    Write-Error "No se encontro el archivo fuente: $source"
    exit 1
}
if (-not (Test-Path $ApacheSites)) {
    Write-Error "No existe el directorio de sites: $ApacheSites"
    exit 1
}

if (Test-Path $target) {
    $bak = "$target.bak.$ts"
    Copy-Item $target $bak -Force
    Write-Host "[bak] $bak" -ForegroundColor DarkYellow
}
Copy-Item $source $target -Force
Write-Host "[OK] Alias instalado: $target" -ForegroundColor Green

# 4) Verificar que httpd.conf tiene los modulos requeridos sin comentar.
if (Test-Path $ApacheConfig) {
    $needed = @('rewrite_module', 'alias_module', 'headers_module')
    $contents = Get-Content $ApacheConfig -Raw
    foreach ($mod in $needed) {
        $pattern = "(?m)^\s*LoadModule\s+$mod\s+"
        if ($contents -match $pattern) {
            Write-Host "[OK] LoadModule $mod activo." -ForegroundColor Green
        } else {
            Write-Warning "[WARN] LoadModule $mod no esta activo. Editar httpd.conf manualmente."
        }
    }
} else {
    Write-Warning "[WARN] No se encontro $ApacheConfig. Verifique manualmente."
}

# 5) Tip final.
Write-Host ''
Write-Host '=== SIGUIENTE PASO MANUAL ===' -ForegroundColor Cyan
Write-Host 'Reiniciar Apache desde Laragon, luego abrir:'
Write-Host '  http://187.174.221.162:8080/oficios'
Write-Host ''
Write-Host 'Si Postgres rechaza la clave, editar .env (DB_PASS) y reintentar.'
