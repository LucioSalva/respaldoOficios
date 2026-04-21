param(
    [string]$InPath  = "C:\Users\lua22\Desktop\creacionSoftware\respaldoOficios\tools\analisis_base\CONOCIMIENTO_raw.csv",
    [string]$OutPath = "C:\Users\lua22\Desktop\creacionSoftware\respaldoOficios\tools\analisis_base\CONOCIMIENTO_corregida.csv",
    [string]$ReportPath = "C:\Users\lua22\Desktop\creacionSoftware\respaldoOficios\tools\analisis_base\reporte_conocimiento.txt"
)
$ErrorActionPreference = 'Stop'
$data = Import-Csv -Path $InPath -Encoding UTF8
$col = $data[0].PSObject.Properties.Name
$cCon=$col[0]; $cFRec=$col[1]; $cDep=$col[2]; $cFDir=$col[3]; $cAsu=$col[4]
$cFTes=$col[5]; $cReal=$col[6]; $cFAcu=$col[7]; $cSta=$col[8]

$real = $data | Where-Object {
    -not [string]::IsNullOrWhiteSpace($_.$cCon) -or
    -not [string]::IsNullOrWhiteSpace($_.$cFRec) -or
    -not [string]::IsNullOrWhiteSpace($_.$cDep) -or
    -not [string]::IsNullOrWhiteSpace($_.$cAsu)
}
"Filas reales: $($real.Count)"

function Clean-Text([string]$s) {
    if ([string]::IsNullOrWhiteSpace($s)) { return "" }
    $t = $s.Trim(); if ($t -eq '-') { return "" }
    return [regex]::Replace($t, '\s+', ' ')
}
function Parse-DateES([string]$s) {
    $t = Clean-Text $s; if ($t -eq "") { return $null }
    if ($t -match '^04/01/1900$') { return '2026-01-04' }
    if ($t -match '^(\d{1,2})/(\d{1,2})/(\d{4})$') {
        $d=[int]$Matches[1]; $m=[int]$Matches[2]; $y=[int]$Matches[3]
        try { return (New-Object DateTime($y,$m,$d)).ToString('yyyy-MM-dd') } catch { return $null }
    }
    if ($t -match '^(\d{4})-(\d{1,2})-(\d{1,2})$') { return $t }
    return $null
}
function Parse-Folio([string]$s, [int]$anioRec) {
    $t = Clean-Text $s
    if ($t -eq "") { return @{ numero=$null; anio=$anioRec; obs="" } }
    if ($t -match '^TM/ECA/ST(?:/|I)?YC/(\d{1,5})/(\d{4})$') {
        return @{ numero=[int]$Matches[1]; anio=[int]$Matches[2]; obs="" }
    }
    if ($t -match '^TM/STIyC/(\d{1,5})/(\d{4})$') {
        return @{ numero=[int]$Matches[1]; anio=[int]$Matches[2]; obs="Folio normalizado (agregado /ECA/): $t" }
    }
    if ($t -match '^TM/STIYC/(\d{1,5})/(\d{4})$') {
        return @{ numero=[int]$Matches[1]; anio=[int]$Matches[2]; obs="Folio normalizado (STIYC+ECA): $t" }
    }
    if ($t -match '^TM/STyC/(\d{1,5})/(\d{4})$') {
        return @{ numero=[int]$Matches[1]; anio=[int]$Matches[2]; obs="Folio normalizado (typo STyC): $t" }
    }
    return @{ numero=$null; anio=$anioRec; obs="Folio original no parseable: $t" }
}
function Map-Status([string]$s) {
    $t = (Clean-Text $s).ToUpper()
    if ($t -eq "" -or $t -eq "-") { return @{ estado='De Conocimiento'; obs="" } }
    if ($t -match '^ARC.?IVADO$')  { return @{ estado='Archivado'; obs="" } }
    if ($t -eq 'DE CONOCIMIENTO')  { return @{ estado='De Conocimiento'; obs="" } }
    if ($t -match 'ESPERA.*ORIGINAL') { return @{ estado='En Proceso'; obs="En espera del oficio original" } }
    if ($t -match '^TURN[AR]+D?O?\s+(?:A\s+|AL\s+)?(.*)$') {
        $destino=$Matches[1].Trim(); if ($destino -eq "") { $destino="(no especificado)" }
        return @{ estado='Turnado'; obs="Turnado a $destino" }
    }
    return @{ estado='De Conocimiento'; obs="Status original: $t" }
}

$output = @()
$consec = 0
$stats = @{ pendientes=0; normalizado=0; roto=0; fecha_1900=0 }
foreach ($r in $real) {
    $consec++
    if ($r.$cFRec.Trim() -eq '04/01/1900') { $stats.fecha_1900++ }
    $fechaRec = Parse-DateES $r.$cFRec
    $anioRec = 2026
    if ($fechaRec) { $anioRec = [int]($fechaRec.Substring(0,4)) }
    $folio = Parse-Folio $r.$cFTes $anioRec
    if ($null -eq $folio.numero) { $stats.pendientes++ }
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
        folio_minutario   = ""
        dependencia_nombre= Clean-Text $r.$cDep
        folio_direccion   = Clean-Text $r.$cFDir
        asunto            = Clean-Text $r.$cAsu
        fecha_oficio_tics = $null
        numero_folio      = if ($null -ne $folio.numero) { $folio.numero } else { "" }
        anio_folio        = $folio.anio
        realizo           = Clean-Text $r.$cReal
        fecha_acuse       = Parse-DateES $r.$cFAcu
        estado_nombre     = $statusMap.estado
        capturo           = ""
        observaciones     = $observaciones
    }
    $output += $row
}
$output | Export-Csv -Path $OutPath -Encoding UTF8 -NoTypeInformation

$sb = New-Object System.Text.StringBuilder
[void]$sb.AppendLine("REPORTE CORRECCIONES - OFICIOS DE CONOCIMIENTO")
[void]$sb.AppendLine("Generado: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')")
[void]$sb.AppendLine(("=" * 60))
[void]$sb.AppendLine("Filas exportadas: $($output.Count)")
[void]$sb.AppendLine("Pendientes folio : $($stats.pendientes)")
[void]$sb.AppendLine("Normalizados     : $($stats.normalizado)")
[void]$sb.AppendLine("Rotos -> obs     : $($stats.roto)")
[void]$sb.AppendLine("Fechas 1900->2026: $($stats.fecha_1900)")

$statusGroup = $output | Group-Object -Property estado_nombre | Sort-Object Count -Descending
[void]$sb.AppendLine(""); [void]$sb.AppendLine("=== STATUS ===")
foreach ($g in $statusGroup) { [void]$sb.AppendLine("  $($g.Name): $($g.Count)") }

$sb.ToString() | Out-File -FilePath $ReportPath -Encoding UTF8
"OK - $OutPath"
Get-Content $ReportPath
