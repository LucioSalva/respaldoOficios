# Genera FOLIOS_INTERNO_SUB_corregida.csv aplicando reglas acordadas
param(
    [string]$InPath  = "C:\Users\lua22\Desktop\creacionSoftware\respaldoOficios\tools\analisis_base\FOLIOS_INTERNO_SUB_raw.csv",
    [string]$OutPath = "C:\Users\lua22\Desktop\creacionSoftware\respaldoOficios\tools\analisis_base\FOLIOS_INTERNO_SUB_corregida.csv",
    [string]$ReportPath = "C:\Users\lua22\Desktop\creacionSoftware\respaldoOficios\tools\analisis_base\reporte_folios_interno.txt"
)
$ErrorActionPreference = 'Stop'

$data = Import-Csv -Path $InPath -Encoding UTF8
$col = $data[0].PSObject.Properties.Name
$cFRec=$col[0]; $cFMin=$col[1]; $cDep=$col[2]; $cFDir=$col[3]; $cAsu=$col[4]
$cFTic=$col[5]; $cFTes=$col[6]; $cReal=$col[7]; $cFAcu=$col[8]; $cSta=$col[9]; $cCap=$col[10]

$real = $data | Where-Object {
    -not [string]::IsNullOrWhiteSpace($_.$cFRec) -or
    -not [string]::IsNullOrWhiteSpace($_.$cDep)  -or
    -not [string]::IsNullOrWhiteSpace($_.$cAsu)  -or
    -not [string]::IsNullOrWhiteSpace($_.$cFTes)
}

function Clean-Text([string]$s) {
    if ([string]::IsNullOrWhiteSpace($s)) { return "" }
    $t = $s.Trim(); if ($t -eq '-') { return "" }
    return [regex]::Replace($t, '\s+', ' ')
}
function Parse-DateES([string]$s) {
    $t = Clean-Text $s; if ($t -eq "") { return $null }
    if ($t -match '^(\d{1,2})/(\d{1,2})/(\d{4})$') {
        $d=[int]$Matches[1]; $m=[int]$Matches[2]; $y=[int]$Matches[3]
        try { return (New-Object DateTime($y,$m,$d)).ToString('yyyy-MM-dd') } catch { return $null }
    }
    if ($t -match '^(\d{4})-(\d{1,2})-(\d{1,2})$') { return $t }
    return $null
}
function Parse-FolioInt([string]$s, [int]$anioRec) {
    $t = Clean-Text $s
    if ($t -eq "") { return @{ numero=$null; anio=$anioRec; estado='PENDIENTE'; obs="" } }
    # TM/ECA/STIyC/NNN/YYYY
    if ($t -match '^TM/ECA/STIyC/(\d{1,5})/(\d{4})$') {
        $num=[int]$Matches[1]; $yr=[int]$Matches[2]
        return @{ numero=$num; anio=$yr; estado='OK'; obs="" }
    }
    # Variantes con Y mayuscula: TM/ECA/STIYC/NNN/YYYY
    if ($t -match '^TM/ECA/STIYC/(\d{1,5})/(\d{4})$') {
        $num=[int]$Matches[1]; $yr=[int]$Matches[2]
        return @{ numero=$num; anio=$yr; estado='OK'; obs="Folio normalizado (STIYC -> STIyC): $t" }
    }
    # Sin ECA: TM/STIyC/NNN/YYYY
    if ($t -match '^TM/STIyC/(\d{1,5})/(\d{4})$') {
        $num=[int]$Matches[1]; $yr=[int]$Matches[2]
        return @{ numero=$num; anio=$yr; estado='OK'; obs="Folio normalizado (agregado /ECA/): $t" }
    }
    # Sin ECA con Y mayuscula: TM/STIYC/NNN/YYYY
    if ($t -match '^TM/STIYC/(\d{1,5})/(\d{4})$') {
        $num=[int]$Matches[1]; $yr=[int]$Matches[2]
        return @{ numero=$num; anio=$yr; estado='OK'; obs="Folio normalizado (STIYC+ECA): $t" }
    }
    # Typo 1 letra: TM/STyC/NNN/YYYY (falta I)
    if ($t -match '^TM/STyC/(\d{1,5})/(\d{4})$') {
        $num=[int]$Matches[1]; $yr=[int]$Matches[2]
        return @{ numero=$num; anio=$yr; estado='OK'; obs="Folio normalizado (typo STyC -> STIyC): $t" }
    }
    # Cualquier otro: pendiente + raw
    return @{ numero=$null; anio=$anioRec; estado='PENDIENTE'; obs="Folio original no parseable: $t" }
}
function Map-Status([string]$s) {
    $t = (Clean-Text $s).ToUpper()
    if ($t -eq "" -or $t -eq "-") { return @{ estado='En Proceso'; obs="" } }
    if ($t -match '^ARC.?IVADO$')   { return @{ estado='Archivado'; obs="" } }
    if ($t -eq 'DE CONOCIMIENTO')   { return @{ estado='De Conocimiento'; obs="" } }
    if ($t -match 'ESPERA.*ORIGINAL'){ return @{ estado='En Proceso'; obs="En espera del oficio original" } }
    if ($t -match '^TURN[AR]+D?O?\s+(?:A\s+|AL\s+)?(.*)$') {
        $destino=$Matches[1].Trim(); if ($destino -eq "") { $destino="(no especificado)" }
        return @{ estado='Turnado'; obs="Turnado a $destino" }
    }
    return @{ estado='En Proceso'; obs="Status original: $t" }
}

$output = @()
$consec = 0
$stats = @{ pendientes=0; normalizado=0; roto=0 }

foreach ($r in $real) {
    $consec++
    $fechaRec = Parse-DateES $r.$cFRec
    $anioRec = 2026
    if ($fechaRec) { $anioRec = [int]($fechaRec.Substring(0,4)) }

    $folio = Parse-FolioInt $r.$cFTes $anioRec
    if ($folio.estado -eq 'PENDIENTE') { $stats.pendientes++ }
    if ($folio.obs -match 'normalizado') { $stats.normalizado++ }
    if ($folio.obs -match 'no parseable') { $stats.roto++ }

    $statusMap = Map-Status $r.$cSta

    $obsParts = @()
    if ($folio.obs)     { $obsParts += $folio.obs }
    if ($statusMap.obs) { $obsParts += $statusMap.obs }
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
        folio_interno_tesoreria = ""
        capturo           = Clean-Text $r.$cCap
        observaciones     = $observaciones
    }
    $output += $row
}

$output | Export-Csv -Path $OutPath -Encoding UTF8 -NoTypeInformation

$sb = New-Object System.Text.StringBuilder
[void]$sb.AppendLine("REPORTE CORRECCIONES - FOLIOS INTERNO SUB")
[void]$sb.AppendLine("Generado: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')")
[void]$sb.AppendLine(("=" * 60))
[void]$sb.AppendLine("Filas exportadas: $($output.Count)")
[void]$sb.AppendLine("Pendientes folio: $($stats.pendientes)")
[void]$sb.AppendLine("Normalizados    : $($stats.normalizado)")
[void]$sb.AppendLine("Rotos -> obs    : $($stats.roto)")
$sb.ToString() | Out-File -FilePath $ReportPath -Encoding UTF8

"OK - CSV: $OutPath"
"OK - Reporte: $ReportPath"
Get-Content $ReportPath
