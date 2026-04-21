# Exporta la hoja "FOLIOS INTERNO SUB" del Excel BASE GENERAL.xlsx a CSV UTF-8
param(
    [string]$XlsxPath = "C:\Users\lua22\Desktop\creacionSoftware\respaldoOficios\tools\analisis_base\BASE GENERAL.xlsx",
    [string]$OutPath  = "C:\Users\lua22\Desktop\creacionSoftware\respaldoOficios\tools\analisis_base\FOLIOS_INTERNO_SUB_raw.csv",
    [string]$SheetHint = "FOLIOS INTERNO SUB"
)

$ErrorActionPreference = 'Stop'
$excel = New-Object -ComObject Excel.Application
$excel.Visible = $false
$excel.DisplayAlerts = $false
try {
    $wb = $excel.Workbooks.Open($XlsxPath, 0, $true)

    # Listar hojas
    Write-Host "Hojas disponibles:" -ForegroundColor Cyan
    foreach ($s in $wb.Sheets) { Write-Host "  - [$($s.Name)]" }

    $sheet = $null
    foreach ($s in $wb.Sheets) {
        if ($s.Name.Trim().ToUpper() -eq $SheetHint.ToUpper()) { $sheet = $s; break }
    }
    if (-not $sheet) {
        foreach ($s in $wb.Sheets) {
            if ($s.Name.ToUpper() -match 'FOLIOS.*INTERNO.*SUB') { $sheet = $s; break }
        }
    }
    if (-not $sheet) { throw "No encontre la hoja FOLIOS INTERNO SUB" }

    Write-Host ""
    Write-Host "Hoja seleccionada: [$($sheet.Name)]" -ForegroundColor Green

    $used = $sheet.UsedRange
    $rows = $used.Rows.Count
    $cols = $used.Columns.Count
    Write-Host "Rango usado: $rows filas x $cols columnas"

    $data = $used.Value2
    $sb = New-Object System.Text.StringBuilder

    for ($r = 1; $r -le $rows; $r++) {
        $line = New-Object System.Collections.ArrayList
        for ($c = 1; $c -le $cols; $c++) {
            $v = $data[$r, $c]
            if ($null -eq $v) {
                [void]$line.Add("")
            } else {
                $s = "$v"
                # Si es numero OADate convertirlo a fecha si parece serlo
                if ($v -is [double] -and $v -ge 20000 -and $v -le 80000) {
                    try {
                        $dt = [DateTime]::FromOADate($v)
                        if ($dt.Hour -eq 0 -and $dt.Minute -eq 0) {
                            $s = $dt.ToString('dd/MM/yyyy')
                        }
                    } catch {}
                }
                if ($s -match '[",;\r\n]') { $s = '"' + $s.Replace('"','""') + '"' }
                [void]$line.Add($s)
            }
        }
        [void]$sb.AppendLine(($line -join ','))
    }

    $utf8 = New-Object System.Text.UTF8Encoding $true
    [System.IO.File]::WriteAllText($OutPath, $sb.ToString(), $utf8)

    Write-Host ""
    Write-Host "OK - CSV generado: $OutPath"
    Write-Host "Filas exportadas: $rows"
} finally {
    if ($wb) { $wb.Close($false) }
    $excel.Quit()
    [System.Runtime.InteropServices.Marshal]::ReleaseComObject($excel) | Out-Null
    [GC]::Collect(); [GC]::WaitForPendingFinalizers()
}
