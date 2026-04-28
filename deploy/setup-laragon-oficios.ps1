# =============================================================================
# setup-laragon-oficios.ps1
# -----------------------------------------------------------------------------
# Script de despliegue idempotente para Respaldo de Oficios en Laragon.
# Ejecutar EN EL SERVIDOR (Windows + Laragon ya instalado).
#
# Uso:
#   1) Abrir PowerShell como Administrador.
#   2) cd C:/laragon/www/oficios/deploy
#   3) powershell -ExecutionPolicy Bypass -File .\setup-laragon-oficios.ps1
#
# Lo que hace (sin borrar nada, todo con backups timestamp):
#   - Valida que sea Windows.
#   - Valida que C:/laragon exista.
#   - Valida que C:/laragon/www/oficios exista.
#   - Crea C:/laragon/www/oficios/uploads si falta.
#   - Crea/corrige .env preservando APP_SECRET y DB_PASS si ya existian.
#   - Copia VHost a C:/laragon/etc/apache2/sites-enabled/auto.oficios.conf.
#   - Garantiza Listen 8080 en httpd.conf (sin borrar lineas).
#   - Imprime instrucciones finales para reiniciar Apache desde Laragon.
# =============================================================================

[CmdletBinding()]
param(
    [string]$AppRoot       = 'C:/laragon/www/oficios',
    [string]$LaragonRoot   = 'C:/laragon',
    [string]$ServerName    = 'oficios.test',
    [string]$ServerIP      = '187.174.221.162'
)

$ErrorActionPreference = 'Stop'

function Write-Section($msg) {
    Write-Host ''
    Write-Host ('==> ' + $msg) -ForegroundColor Cyan
}
function Write-Ok($msg)   { Write-Host ('   [OK]   ' + $msg) -ForegroundColor Green }
function Write-Warn2($msg){ Write-Host ('   [WARN] ' + $msg) -ForegroundColor Yellow }
function Write-Err2($msg) { Write-Host ('   [ERR]  ' + $msg) -ForegroundColor Red }

$Stamp = (Get-Date -Format 'yyyyMMdd-HHmmss')
$Report = [ordered]@{
    timestamp           = $Stamp
    so_validado         = $false
    laragon_validado    = $false
    app_validada        = $false
    uploads_creado      = $false
    uploads_existia     = $false
    env_creado          = $false
    env_actualizado     = $false
    env_app_secret_pres = $false
    env_db_pass_pres    = $false
    vhost_copiado       = $false
    listen8080_ok       = $false
    backups             = New-Object System.Collections.Generic.List[string]
    errores             = New-Object System.Collections.Generic.List[string]
}

function Backup-File([string]$path) {
    if (Test-Path -LiteralPath $path) {
        $bak = "$path.bak.$Stamp"
        Copy-Item -LiteralPath $path -Destination $bak -Force
        $Report.backups.Add($bak) | Out-Null
        Write-Ok ("Backup -> " + $bak)
    }
}

# ---------------------------------------------------------------------------
# 1) Validaciones
# ---------------------------------------------------------------------------
Write-Section "Validando entorno"

if ($env:OS -ne 'Windows_NT') {
    Write-Err2 "Este script solo se ejecuta en Windows."
    $Report.errores.Add("SO no es Windows") | Out-Null
    return
}
$Report.so_validado = $true
Write-Ok "Sistema operativo: Windows"

if (-not (Test-Path -LiteralPath $LaragonRoot)) {
    Write-Err2 ("No se encontro Laragon en " + $LaragonRoot)
    $Report.errores.Add("Falta carpeta Laragon") | Out-Null
    return
}
$Report.laragon_validado = $true
Write-Ok ("Laragon detectado en " + $LaragonRoot)

if (-not (Test-Path -LiteralPath $AppRoot)) {
    Write-Err2 ("No se encontro la app en " + $AppRoot + " - copia el proyecto antes de correr el script.")
    $Report.errores.Add("Falta carpeta de la app") | Out-Null
    return
}
$Report.app_validada = $true
Write-Ok ("App detectada en " + $AppRoot)

# ---------------------------------------------------------------------------
# 2) uploads/
# ---------------------------------------------------------------------------
Write-Section "Asegurando carpeta uploads/"
$Uploads = Join-Path $AppRoot 'uploads'
if (Test-Path -LiteralPath $Uploads) {
    Write-Ok ("uploads ya existe: " + $Uploads)
    $Report.uploads_existia = $true
} else {
    New-Item -ItemType Directory -Path $Uploads -Force | Out-Null
    Write-Ok ("uploads creado: " + $Uploads)
    $Report.uploads_creado = $true
}

# storage/logs tambien
$LogsDir = Join-Path $AppRoot 'storage/logs'
if (-not (Test-Path -LiteralPath $LogsDir)) {
    New-Item -ItemType Directory -Path $LogsDir -Force | Out-Null
    Write-Ok ("storage/logs creado: " + $LogsDir)
}

# ---------------------------------------------------------------------------
# 3) .env idempotente
# ---------------------------------------------------------------------------
Write-Section "Generando/corrigiendo .env"
$EnvPath = Join-Path $AppRoot '.env'

# Defaults deseados para Laragon
$desired = [ordered]@{
    'DB_HOST'          = '127.0.0.1'
    'DB_PORT'          = '5432'
    'DB_NAME'          = 'respaldooficios'
    'DB_USER'          = 'postgres'
    'DB_PASS'          = 'admin'
    'APP_ENV'          = 'production'
    'APP_DEBUG'        = 'false'
    'APP_URL'          = ('http://' + $ServerName + ':8080')
    'APP_SECRET'       = ''
    'SESSION_LIFETIME' = '7200'
    'SESSION_NAME'     = 'respaldo_oficios_sess'
    'UPLOAD_MAX_SIZE'  = '10485760'
    'UPLOAD_DIR'       = ($AppRoot + '/uploads').Replace('\','/')
    'APP_TIMEZONE'     = 'America/Mexico_City'
}

$existing = @{}
if (Test-Path -LiteralPath $EnvPath) {
    Backup-File $EnvPath
    Get-Content -LiteralPath $EnvPath | ForEach-Object {
        $line = $_.Trim()
        if ($line -and -not $line.StartsWith('#') -and $line.Contains('=')) {
            $idx = $line.IndexOf('=')
            $k = $line.Substring(0,$idx).Trim()
            $v = $line.Substring($idx+1).Trim()
            $existing[$k] = $v
        }
    }
    $Report.env_actualizado = $true
} else {
    $Report.env_creado = $true
}

# Conservar APP_SECRET si ya existia y no es el placeholder
if ($existing.ContainsKey('APP_SECRET') -and $existing['APP_SECRET'] -and $existing['APP_SECRET'] -notmatch '^GENERAR|^cambiar|^default') {
    $desired['APP_SECRET'] = $existing['APP_SECRET']
    $Report.env_app_secret_pres = $true
    Write-Ok "APP_SECRET existente conservado."
} else {
    $bytes = New-Object byte[] 48
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    $desired['APP_SECRET'] = ([System.Convert]::ToBase64String($bytes)).Replace('+','x').Replace('/','y').Replace('=','')
    Write-Warn2 "APP_SECRET nuevo generado. Si ya tenias uno, recupera del backup."
}

# Conservar DB_PASS si ya existia y no es placeholder
if ($existing.ContainsKey('DB_PASS') -and $existing['DB_PASS'] -and $existing['DB_PASS'] -notmatch '^CAMBIAR|^cambiar') {
    if ($existing['DB_PASS'] -ne $desired['DB_PASS']) {
        Write-Warn2 ("DB_PASS existente difiere del default. Conservando el existente: " + $existing['DB_PASS'])
    }
    $desired['DB_PASS'] = $existing['DB_PASS']
    $Report.env_db_pass_pres = $true
}

# Construir el archivo .env final
$envLines = @(
    '# ============================================================================='
    '# RESPALDO DE OFICIOS - Variables de Entorno (Laragon)'
    '# Generado/actualizado por setup-laragon-oficios.ps1'
    ('# Timestamp: ' + $Stamp)
    '# ============================================================================='
    ''
)
foreach ($k in $desired.Keys) {
    $envLines += ($k + '=' + $desired[$k])
}
$envLines += ''
$envLines += '# AJUSTE MANUAL: si DB_PASS no coincide con tu PostgreSQL, edita aqui.'

Set-Content -LiteralPath $EnvPath -Value ($envLines -join "`r`n") -Encoding UTF8
Write-Ok (".env escrito en " + $EnvPath)

# ---------------------------------------------------------------------------
# 4) VHost auto.oficios.conf
# ---------------------------------------------------------------------------
Write-Section "Instalando VirtualHost de Apache"
$SitesEnabled = Join-Path $LaragonRoot 'etc/apache2/sites-enabled'
if (-not (Test-Path -LiteralPath $SitesEnabled)) {
    Write-Err2 ("No se encontro sites-enabled en " + $SitesEnabled)
    $Report.errores.Add("sites-enabled inexistente") | Out-Null
} else {
    $VHostSrc = Join-Path $AppRoot 'deploy/auto.oficios.conf'
    $VHostDst = Join-Path $SitesEnabled 'auto.oficios.conf'
    if (-not (Test-Path -LiteralPath $VHostSrc)) {
        Write-Err2 ("No se encontro plantilla VHost en " + $VHostSrc)
        $Report.errores.Add("Falta deploy/auto.oficios.conf en el repo") | Out-Null
    } else {
        if (Test-Path -LiteralPath $VHostDst) {
            Backup-File $VHostDst
        }
        Copy-Item -LiteralPath $VHostSrc -Destination $VHostDst -Force
        Write-Ok ("VHost copiado -> " + $VHostDst)
        $Report.vhost_copiado = $true
    }
}

# ---------------------------------------------------------------------------
# 5) httpd.conf - garantizar Listen 8080
# ---------------------------------------------------------------------------
Write-Section "Verificando Listen 8080 en httpd.conf"
$HttpdConf = Join-Path $LaragonRoot 'etc/apache2/httpd.conf'
if (-not (Test-Path -LiteralPath $HttpdConf)) {
    Write-Err2 ("No se encontro httpd.conf en " + $HttpdConf)
    $Report.errores.Add("httpd.conf no encontrado") | Out-Null
} else {
    $content = Get-Content -LiteralPath $HttpdConf -Raw
    $hasListen8080 = ($content -match '(?m)^\s*Listen\s+8080\b')
    if ($hasListen8080) {
        Write-Ok "Listen 8080 ya esta presente."
        $Report.listen8080_ok = $true
    } else {
        Backup-File $HttpdConf
        $append = "`r`n# Agregado por setup-laragon-oficios.ps1 ($Stamp)`r`nListen 8080`r`n"
        Add-Content -LiteralPath $HttpdConf -Value $append
        Write-Ok "Listen 8080 agregado al final de httpd.conf."
        $Report.listen8080_ok = $true
    }
}

# ---------------------------------------------------------------------------
# 6) Reporte final
# ---------------------------------------------------------------------------
Write-Section "Reporte"
$Report.GetEnumerator() | ForEach-Object {
    $val = $_.Value
    if ($val -is [System.Collections.IEnumerable] -and -not ($val -is [string])) {
        Write-Host ("   " + $_.Key + ":")
        foreach ($x in $val) { Write-Host ("     - " + $x) }
    } else {
        Write-Host ("   " + $_.Key + ": " + $val)
    }
}

Write-Section "Pasos manuales restantes"
Write-Host ""
Write-Host "1) En Laragon: menu -> Apache -> Modules -> activar:" -ForegroundColor White
Write-Host "     mod_rewrite     (rewrite_module)"
Write-Host "     mod_headers     (headers_module)"
Write-Host ""
Write-Host "2) Editar el archivo HOSTS de Windows como administrador:" -ForegroundColor White
Write-Host "     C:\Windows\System32\drivers\etc\hosts"
Write-Host "   y agregar la linea:"
Write-Host "     127.0.0.1   $ServerName"
Write-Host ""
Write-Host "3) Reiniciar Apache: Laragon -> Stop All -> Start All" -ForegroundColor White
Write-Host ""
Write-Host "4) Verificar PostgreSQL respaldooficios:" -ForegroundColor White
Write-Host "     psql -h 127.0.0.1 -U postgres -d respaldooficios -c \"SELECT COUNT(*) FROM oficios;\""
Write-Host ""
Write-Host "5) Probar en navegador:" -ForegroundColor White
Write-Host ("     http://" + $ServerName + ":8080")
Write-Host ("     http://" + $ServerIP + ":8080")
Write-Host ""
Write-Host "FIN setup-laragon-oficios.ps1" -ForegroundColor Cyan
