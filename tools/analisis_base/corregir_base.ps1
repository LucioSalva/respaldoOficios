# =============================================================================
# Genera BASE_GENERAL_corregida.csv aplicando todas las reglas acordadas con el usuario
# =============================================================================
param(
    [string]$InPath  = "C:\Users\lua22\Desktop\creacionSoftware\respaldoOficios\tools\analisis_base\BASE_GENERAL_raw.csv",
    [string]$OutPath = "C:\Users\lua22\Desktop\creacionSoftware\respaldoOficios\tools\analisis_base\BASE_GENERAL_corregida.csv",
    [string]$ReportPath = "C:\Users\lua22\Desktop\creacionSoftware\respaldoOficios\tools\analisis_base\reporte_correcciones.txt"
)

$ErrorActionPreference = 'Stop'

$data = Import-Csv -Path $InPath -Encoding UTF8
$col = $data[0].PSObject.Properties.Name
$cCon=$col[0]; $cFRec=$col[1]; $cFMin=$col[2]; $cDep=$col[3]; $cFDir=$col[4]
$cAsu=$col[5]; $cFTic=$col[6]; $cFTes=$col[7]; $cReal=$col[8]; $cFAcu=$col[9]
$cSta=$col[10]; $cFInt=$col[11]; $cCap=$col[12]

# Solo filas con datos reales
$real = $data | Where-Object {
    -not [string]::IsNullOrWhiteSpace($_.$cCon) -or
    -not [string]::IsNullOrWhiteSpace($_.$cFRec) -or
    -not [string]::IsNullOrWhiteSpace($_.$cDep) -or
    -not [string]::IsNullOrWhiteSpace($_.$cAsu)
}

$report = New-Object System.Text.StringBuilder
[void]$report.AppendLine("REPORTE DE CORRECCIONES - BASE GENERAL")
[void]$report.AppendLine("Generado: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')")
[void]$report.AppendLine(("=" * 80))
[void]$report.AppendLine("")
[void]$report.AppendLine("Filas reales detectadas: $($real.Count)")
[void]$report.AppendLine("")

# ===================== FUNCIONES DE TRANSFORMACION =====================

function Clean-Text([string]$s) {
    if ([string]::IsNullOrWhiteSpace($s)) { return "" }
    $t = $s.Trim()
    if ($t -eq '-') { return "" }
    # Colapsa espacios multiples
    $t = [regex]::Replace($t, '\s+', ' ')
    return $t
}

function Parse-DateES([string]$s) {
    $t = Clean-Text $s
    if ($t -eq "") { return $null }
    # Fecha inválida 04/01/1900 del CON. original 51 -> 04/01/2026
    if ($t -match '^04/01/1900$') { return '2026-01-04' }
    # dd/mm/yyyy
    if ($t -match '^(\d{1,2})/(\d{1,2})/(\d{4})$') {
        $d = [int]$Matches[1]; $m = [int]$Matches[2]; $y = [int]$Matches[3]
        try {
            $dt = New-Object DateTime($y, $m, $d)
            return $dt.ToString('yyyy-MM-dd')
        } catch { return $null }
    }
    # yyyy-mm-dd ya
    if ($t -match '^(\d{4})-(\d{1,2})-(\d{1,2})$') { return $t }
    return $null
}

function Parse-Folio([string]$s, [int]$anioRec) {
    # Devuelve objeto { numero, anio, raw_observacion }
    $t = Clean-Text $s
    if ($t -eq "") {
        return @{ numero = $null; anio = $anioRec; estado = 'PENDIENTE'; obs = "" }
    }

    # Caso 1: TM/ECA/STIyC/NNNN/YYYY (formato oficial - puede tener ST/YC con barra extra)
    if ($t -match '^TM/ECA/ST(?:/|I)?YC/(\d{1,5})/(\d{4})$') {
        $num = [int]$Matches[1]; $yr = [int]$Matches[2]
        if ($yr -ge 2020 -and $yr -le 2030 -and $num -ge 1 -and $num -le 9999) {
            $obs = ""
            if ($t -notmatch '^TM/ECA/STIyC/\d{1,5}/\d{4}$') { $obs = "Folio original con formato no estandar: $t" }
            return @{ numero = $num; anio = $yr; estado = 'OK'; obs = $obs }
        }
    }

    # Caso 2: TM/STIyC/NNNN/YYYY (falta ECA) -> NORMALIZAR agregando ECA
    if ($t -match '^TM/STIyC/(\d{1,5})/(\d{4})$') {
        $num = [int]$Matches[1]; $yr = [int]$Matches[2]
        if ($yr -ge 2020 -and $yr -le 2030 -and $num -ge 1 -and $num -le 9999) {
            return @{ numero = $num; anio = $yr; estado = 'OK'; obs = "Folio normalizado (agregado segmento /ECA/): TM/STIyC → TM/ECA/STIyC" }
        }
    }

    # Caso 3: folio claramente roto -> dejar tal cual en observaciones, NULL en numero/anio
    return @{ numero = $null; anio = $anioRec; estado = 'PENDIENTE'; obs = "Folio original no parseable: $t" }
}

function Map-Status([string]$s) {
    # Devuelve objeto { estado_nombre, obs_extra }
    $t = (Clean-Text $s).ToUpper()
    if ($t -eq "" -or $t -eq "-") {
        return @{ estado = 'En Proceso'; obs = "" }
    }
    if ($t -match '^ARC.?IVADO$') {
        return @{ estado = 'Archivado'; obs = "" }
    }
    if ($t -eq 'DE CONOCIMIENTO') {
        return @{ estado = 'De Conocimiento'; obs = "" }
    }
    if ($t -match 'ESPERA.*ORIGINAL') {
        return @{ estado = 'En Proceso'; obs = "En espera del oficio original" }
    }
    # TURNADO A X (con typos)
    if ($t -match '^TURN[AR]+D?O?\s+(?:A\s+|AL\s+)?(.*)$') {
        $destino = $Matches[1].Trim()
        if ($destino -eq "") { $destino = "(no especificado)" }
        # Normalizar typos de nombres
        $destino = $destino -replace '^MONIUCA$', 'MONICA'
        $destino = $destino -replace '^MONI$',    'MONICA'
        return @{ estado = 'Turnado'; obs = "Turnado a $destino" }
    }
    # "SE LE DIO COPIA A X"
    if ($t -match 'COPIA\s+A\s+(.+)$') {
        return @{ estado = 'Turnado'; obs = "Se le dio copia a $($Matches[1].Trim())" }
    }
    # Nombre solo (p.ej. "MONICA") - sin acentos en la regex para evitar problemas de encoding
    if ($t -match '^[A-Z]+$') {
        return @{ estado = 'Turnado'; obs = "Turnado a $t" }
    }
    # Default: dejar como En Proceso y registrar el valor original
    return @{ estado = 'En Proceso'; obs = "Status original: $t" }
}

# ===================== TRANSFORMACION =====================

$output = @()
$consec = 0
$stats = @{
    pendientes_folio = 0
    folio_normalizado = 0
    folio_roto        = 0
    fecha_1900_fix    = 0
    status_por_estado = @{}
}

foreach ($r in $real) {
    $consec++

    $fechaRec = Parse-DateES $r.$cFRec
    if ($r.$cFRec.Trim() -eq '04/01/1900') { $stats.fecha_1900_fix++ }

    $anioRec = 2026
    if ($fechaRec) { $anioRec = [int]($fechaRec.Substring(0,4)) }

    $folio = Parse-Folio $r.$cFTes $anioRec
    if ($folio.estado -eq 'PENDIENTE') { $stats.pendientes_folio++ }
    if ($folio.obs -match 'normalizado') { $stats.folio_normalizado++ }
    if ($folio.obs -match 'no parseable') { $stats.folio_roto++ }

    $statusMap = Map-Status $r.$cSta
    if (-not $stats.status_por_estado.ContainsKey($statusMap.estado)) {
        $stats.status_por_estado[$statusMap.estado] = 0
    }
    $stats.status_por_estado[$statusMap.estado]++

    # Armar observaciones concatenadas
    $obsParts = @()
    if ($folio.obs)       { $obsParts += $folio.obs }
    if ($statusMap.obs)   { $obsParts += $statusMap.obs }
    $observaciones = ($obsParts -join ' | ').Trim()

    $row = [PSCustomObject]@{
        consecutivo       = $consec
        fecha_recepcion   = $fechaRec
        folio_minutario   = Clean-Text $r.$cFMin
        dependencia_nombre= Clean-Text $r.$cDep
        folio_direccion   = Clean-Text $r.$cFDir
        asunto            = Clean-Text $r.$cAsu
        fecha_oficio_tics = Parse-DateES $r.$cFTic
        numero_folio      = if ($null -ne $folio.numero) { $folio.numero } else { "" }
        anio_folio        = $folio.anio
        realizo           = Clean-Text $r.$cReal
        fecha_acuse       = Parse-DateES $r.$cFAcu
        estado_nombre     = $statusMap.estado
        folio_interno_tesoreria = Clean-Text $r.$cFInt
        capturo           = Clean-Text $r.$cCap
        observaciones     = $observaciones
    }
    $output += $row
}

# Exportar CSV UTF-8 con BOM
$output | Export-Csv -Path $OutPath -Encoding UTF8 -NoTypeInformation

[void]$report.AppendLine("=== ESTADISTICAS DE CORRECCION ===")
[void]$report.AppendLine("Filas exportadas: $($output.Count)")
[void]$report.AppendLine("Consecutivos renumerados: 1 a $($output.Count)")
[void]$report.AppendLine("")
[void]$report.AppendLine("Pendientes de folio (numero_folio vacio): $($stats.pendientes_folio)")
[void]$report.AppendLine("Folios normalizados (TM/STIyC -> TM/ECA/STIyC): $($stats.folio_normalizado)")
[void]$report.AppendLine("Folios rotos movidos a observaciones: $($stats.folio_roto)")
[void]$report.AppendLine("Fechas 04/01/1900 -> 04/01/2026: $($stats.fecha_1900_fix)")
[void]$report.AppendLine("")
[void]$report.AppendLine("=== STATUS MAPEADO ===")
foreach ($k in $stats.status_por_estado.Keys | Sort-Object) {
    [void]$report.AppendLine("  $k`: $($stats.status_por_estado[$k])")
}

$report.ToString() | Out-File -FilePath $ReportPath -Encoding UTF8

"OK - CSV generado: $OutPath"
"OK - Reporte: $ReportPath"
Get-Content $ReportPath