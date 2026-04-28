# =============================================================================
# setup-subpath-oficios-production.ps1
# Despliegue de "Respaldo de Oficios" como subcarpeta /oficios en Apache.
# Tesoreria Municipal STIyC.
#
# Idempotente. Crea backups con sufijo .bak.YYYYMMDD-HHMMSS.
# NO modifica VirtualHosts existentes. NO cambia DocumentRoot global.
# Solo:
#   - Valida estructura.
#   - Asegura uploads/.
#   - Crea/actualiza .env conservando APP_SECRET y DB_PASS si ya existian.
#   - Imprime contenido de apache-oficios-subpath.conf y donde colocarlo.
#   - Verifica que httpd.conf incluya "Listen 8080".
#   - Imprime instrucciones para reiniciar Apache.
# =============================================================================

[CmdletBinding()]
param(
    [string]$ProjectPath = 'C:/laragon/www/oficios',
    [string]$LaragonHttpd = 'C:/laragon/etc/apache2/httpd.conf',
    [string]$IpServidor = '187.174.221.162',
    [int]$Puerto = 8080
)

$ErrorActionPreference = 'Stop'

function Get-Ts() { return (Get-Date -Format 'yyyyMMdd-HHmmss') }

function Backup-File([string]$Path) {
    if (Test-Path $Path) {
        $bak = "$Path.bak.$(Get-Ts)"
        Copy-Item $Path $bak -Force
        Write-Host "  Backup: $bak"
    }
}

Write-Host '============================================================'
Write-Host ' RESPALDO DE OFICIOS - Despliegue subcarpeta /oficios'
Write-Host '============================================================'
Write-Host ''

# 1) Validar estructura del proyecto
Write-Host '[1/12] Validando estructura del proyecto...'
$required = @(
    "$ProjectPath/src/public/index.php",
    "$ProjectPath/src/public/.htaccess",
    "$ProjectPath/src/core/UrlHelper.php",
    "$ProjectPath/deploy/apache-oficios-subpath.conf"
)
foreach ($f in $required) {
    if (-not (Test-Path $f)) {
        Write-Error "Falta archivo requerido: $f"
        exit 1
    }
}
Write-Host '  OK'

# 2) Asegurar carpeta uploads/
Write-Host '[2/12] Verificando carpeta uploads/...'
$uploads = "$ProjectPath/uploads"
if (-not (Test-Path $uploads)) {
    New-Item -ItemType Directory -Path $uploads | Out-Null
    Write-Host "  Creada: $uploads"
} else {
    Write-Host "  Existe: $uploads"
}
if (-not (Test-Path "$uploads/.gitkeep")) {
    Set-Content -Path "$uploads/.gitkeep" -Value '' -Encoding UTF8
}

# 3) Crear/actualizar .env preservando APP_SECRET y DB_PASS si ya existen
Write-Host '[3/12] Configurando .env (idempotente)...'
$envPath = "$ProjectPath/.env"
$existingSecret = $null
$existingDbPass = $null
if (Test-Path $envPath) {
    Backup-File $envPath
    $current = Get-Content -Raw -LiteralPath $envPath
    $mSecret = [regex]::Match($current, '(?m)^APP_SECRET=(.+)$')
    if ($mSecret.Success) { $existingSecret = $mSecret.Groups[1].Value.Trim() }
    $mDbPass = [regex]::Match($current, '(?m)^DB_PASS=(.+)$')
    if ($mDbPass.Success) { $existingDbPass = $mDbPass.Groups[1].Value.Trim() }
}

if (-not $existingSecret) {
    $bytes = New-Object byte[] 48
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    $existingSecret = [System.Convert]::ToBase64String($bytes).TrimEnd('=')
    Write-Host '  APP_SECRET nuevo generado.'
} else {
    Write-Host '  APP_SECRET preservado.'
}
if (-not $existingDbPass) {
    $existingDbPass = 'admin'
    Write-Host '  DB_PASS por defecto (admin). Editar manualmente si difiere.'
} else {
    Write-Host '  DB_PASS preservado.'
}

$envContent = @"
# Generado por setup-subpath-oficios-production.ps1
DB_HOST=127.0.0.1
DB_PORT=5432
DB_NAME=respaldooficios
DB_USER=postgres
DB_PASS=$existingDbPass

APP_ENV=production
APP_DEBUG=false
APP_URL=http://${IpServidor}:${Puerto}
APP_BASE_PATH=/oficios
APP_SECRET=$existingSecret

SESSION_LIFETIME=7200
SESSION_NAME=respaldo_oficios_sess

UPLOAD_MAX_SIZE=10485760
UPLOAD_DIR=$ProjectPath/uploads

APP_TIMEZONE=America/Mexico_City
"@
Set-Content -LiteralPath $envPath -Value $envContent -Encoding UTF8
Write-Host '  .env escrito.'

# 4) Validar Listen 8080 en httpd.conf
Write-Host '[4/12] Verificando Listen 8080 en httpd.conf...'
if (Test-Path $LaragonHttpd) {
    $http = Get-Content -Raw -LiteralPath $LaragonHttpd
    if ($http -notmatch "(?m)^\s*Listen\s+$Puerto\b") {
        Write-Warning "Falta 'Listen $Puerto' en $LaragonHttpd."
        Write-Warning "  Agregalo manualmente cerca de las otras lineas Listen."
    } else {
        Write-Host '  OK'
    }
} else {
    Write-Warning "No se encontro $LaragonHttpd. Verifica la ruta de Laragon."
}

# 5) Mostrar el contenido del Alias para colocar en sites-enabled
Write-Host '[5/12] Configuracion Apache Alias para colocar en sites-enabled:'
$aliasConf = "$ProjectPath/deploy/apache-oficios-subpath.conf"
$dest = 'C:/laragon/etc/apache2/sites-enabled/apache-oficios-subpath.conf'
Write-Host "  Origen : $aliasConf"
Write-Host "  Destino: $dest"
Write-Host '  CONTENIDO (revisar y copiar manualmente):'
Write-Host '  -------------------------------------------------------------'
Get-Content -LiteralPath $aliasConf | ForEach-Object { Write-Host "    $_" }
Write-Host '  -------------------------------------------------------------'

# 6) Verificar modulos requeridos
Write-Host '[6/12] Verificando modulos Apache (informativo)...'
if (Test-Path $LaragonHttpd) {
    $http = Get-Content -Raw -LiteralPath $LaragonHttpd
    foreach ($mod in @('rewrite_module','alias_module','headers_module')) {
        if ($http -match "(?m)^\s*LoadModule\s+$mod\b") {
            Write-Host "  $mod: OK"
        } else {
            Write-Warning "  $mod: revisa que este habilitado en httpd.conf"
        }
    }
}

# 7) Permisos basicos sobre uploads
Write-Host '[7/12] Permisos uploads/ (informativo)...'
Write-Host "  Verifica que el usuario de Apache pueda escribir en $uploads"

# 8) Resumen URLs
Write-Host '[8/12] URLs esperadas tras reiniciar Apache:'
Write-Host "  http://${IpServidor}:${Puerto}/oficios"
Write-Host "  http://${IpServidor}:${Puerto}/oficios/login"
Write-Host "  http://${IpServidor}:${Puerto}/oficios/dashboard"

# 9) Reiniciar Apache (instruccion manual)
Write-Host '[9/12] Pasos manuales restantes:'
Write-Host '  a) Copia deploy/apache-oficios-subpath.conf a sites-enabled o haz Include en httpd.conf.'
Write-Host '  b) Confirma "Listen 8080" en httpd.conf.'
Write-Host '  c) Reinicia Apache (Laragon -> Reload, o "httpd -k restart").'

# 10) Verificacion post-reinicio (manual)
Write-Host '[10/12] Verificacion sugerida (manual):'
Write-Host "  curl -I http://${IpServidor}:${Puerto}/oficios/login"
Write-Host '  Debe devolver HTTP/1.1 200 OK.'

# 11) Validar que /oficios/.env devuelve 403/404 (manual)
Write-Host '[11/12] Verificacion de seguridad (manual):'
Write-Host "  curl -I http://${IpServidor}:${Puerto}/oficios/.env"
Write-Host '  Debe devolver 403 o 404 (NO 200).'

# 12) Fin
Write-Host '[12/12] Listo.'
Write-Host ''
Write-Host '============================================================'
Write-Host '  Script idempotente. Puedes volver a ejecutarlo sin riesgo.'
Write-Host '  No se modifico ningun VirtualHost ni DocumentRoot global.'
Write-Host '============================================================'
