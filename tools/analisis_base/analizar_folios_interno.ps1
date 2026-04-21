# Analiza FOLIOS_INTERNO_SUB_raw.csv y genera reporte de problemas
param(
    [string]$InPath = "C:\Users\lua22\Desktop\creacionSoftware\respaldoOficios\tools\analisis_base\FOLIOS_INTERNO_SUB_raw.csv"
)

$ErrorActionPreference = 'Stop'
$data = Import-Csv -Path $InPath -Encoding UTF8
$col = $data[0].PSObject.Properties.Name
"Total filas (incl. vacias): $($data.Count)"
"Columnas detectadas: $($col.Count)"
"Primeras 11 columnas:"
for ($i=0; $i -lt [Math]::Min(11,$col.Count); $i++) {
    "  [$i] '$($col[$i])'"
}

# Columnas logicas (11 primeras)
$cFRec=$col[0]; $cFMin=$col[1]; $cDep=$col[2]; $cFDir=$col[3]; $cAsu=$col[4]
$cFTic=$col[5]; $cFTes=$col[6]; $cReal=$col[7]; $cFAcu=$col[8]; $cSta=$col[9]; $cCap=$col[10]

# Filas reales
$real = $data | Where-Object {
    -not [string]::IsNullOrWhiteSpace($_.$cFRec) -or
    -not [string]::IsNullOrWhiteSpace($_.$cDep)  -or
    -not [string]::IsNullOrWhiteSpace($_.$cAsu)  -or
    -not [string]::IsNullOrWhiteSpace($_.$cFTes)
}
""
"Filas reales: $($real.Count)"
""

# Folios TICs
"=== FOLIOS TESORERIA (columna FOLIO DE OFICIO TICS) ==="
$folios = @{}
$pend = 0; $ok = 0; $raro = 0; $dup = @{}
foreach ($r in $real) {
    $f = $r.$cFTes.Trim()
    if ($f -eq "" -or $f -eq "-") { $pend++; continue }
    if ($f -match '^TM/ECA/ST(?:/|I)?YC/(\d+)/(\d{4})$') {
        $key = "$($Matches[1])/$($Matches[2])"
        if ($folios.ContainsKey($key)) {
            if (-not $dup.ContainsKey($key)) { $dup[$key] = 1 }
            $dup[$key]++
        } else { $folios[$key] = 1 }
        $ok++
    } elseif ($f -match '^TM/STIyC/(\d+)/(\d{4})$') {
        $raro++
        "  [RARO-normalizable] $f"
    } else {
        $raro++
        "  [RARO-otro] $f"
    }
}
"Pendientes (vacio)     : $pend"
"Formato OK             : $ok"
"Formato raro           : $raro"
"Duplicados             : $($dup.Count)"
foreach ($k in $dup.Keys) { "  DUP: $k (x$($dup[$k] + 1))" }
""

# Status distintos
"=== STATUS DISTINTOS ==="
$statusGroup = $real | Group-Object -Property $cSta | Sort-Object Count -Descending
foreach ($g in $statusGroup) {
    $name = if ($g.Name -eq "") { "(vacio)" } else { $g.Name }
    "  $($g.Count.ToString().PadLeft(4))  '$name'"
}
""

# Fechas
"=== FECHAS DE RECEPCION ==="
$feBad = 0; $feOK = 0; $feBlank = 0
foreach ($r in $real) {
    $t = $r.$cFRec.Trim()
    if ($t -eq "") { $feBlank++; continue }
    if ($t -match '^(\d{1,2})/(\d{1,2})/(\d{4})$') {
        $d=[int]$Matches[1]; $m=[int]$Matches[2]; $y=[int]$Matches[3]
        try { $null = New-Object DateTime($y,$m,$d); $feOK++ } catch { $feBad++; "  [fecha rara] $t (dep=$($r.$cDep), asu=$($r.$cAsu.Substring(0,[Math]::Min(40,$r.$cAsu.Length))))" }
    } else { $feBad++; "  [fecha rara] $t" }
}
"Fechas OK      : $feOK"
"Fechas vacias  : $feBlank"
"Fechas raras   : $feBad"
""

# Dependencias distintas
"=== DEPENDENCIAS DISTINTAS ==="
$deps = $real | ForEach-Object { $_.$cDep.Trim() } | Where-Object { $_ -ne "" } | Sort-Object -Unique
"Total unicas: $($deps.Count)"
foreach ($d in $deps) { "  - $d" }
""

# Campos vacios clave
"=== FILAS SIN ASUNTO ==="
$sinAsu = $real | Where-Object { [string]::IsNullOrWhiteSpace($_.$cAsu) }
"Total: $($sinAsu.Count)"
foreach ($r in $sinAsu) { "  fecha=$($r.$cFRec) dep=$($r.$cDep) folio=$($r.$cFTes)" }
