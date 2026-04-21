param(
    [string]$XlsxPath = "C:\Users\lua22\Desktop\creacionSoftware\respaldoOficios\BASE GENERAL.xlsx",
    [string]$OutPath  = "C:\Users\lua22\Desktop\creacionSoftware\respaldoOficios\tools\analisis_base\CONOCIMIENTO_raw.csv",
    [string]$SheetHint = "OFICIOS DE CONOCIMIENTO"
)
$ErrorActionPreference = 'Stop'
$excel = New-Object -ComObject Excel.Application
$excel.Visible = $false; $excel.DisplayAlerts = $false
try {
    $wb = $excel.Workbooks.Open($XlsxPath, 0, $true)
    $sheet = $null
    foreach ($s in $wb.Sheets) {
        if ($s.Name.Trim().ToUpper() -eq $SheetHint.ToUpper()) { $sheet = $s; break }
    }
    if (-not $sheet) { throw "No encontre hoja $SheetHint" }
    Write-Host "Hoja: [$($sheet.Name)]"
    $used = $sheet.UsedRange
    $rows = $used.Rows.Count; $cols = $used.Columns.Count
    Write-Host "Rango: $rows x $cols"
    $data = $used.Value2
    $sb = New-Object System.Text.StringBuilder
    for ($r = 1; $r -le $rows; $r++) {
        $line = New-Object System.Collections.ArrayList
        for ($c = 1; $c -le $cols; $c++) {
            $v = $data[$r, $c]
            if ($null -eq $v) { [void]$line.Add("") }
            else {
                $s = "$v"
                if ($v -is [double] -and $v -ge 20000 -and $v -le 80000) {
                    try {
                        $dt = [DateTime]::FromOADate($v)
                        if ($dt.Hour -eq 0 -and $dt.Minute -eq 0) { $s = $dt.ToString('dd/MM/yyyy') }
                    } catch {}
                }
                if ($s -match '[",;\r\n]') { $s = '"' + $s.Replace('"','""') + '"' }
                [void]$line.Add($s)
            }
        }
        [void]$sb.AppendLine(($line -join ','))
    }
    [System.IO.File]::WriteAllText($OutPath, $sb.ToString(), (New-Object System.Text.UTF8Encoding $true))
    Write-Host "OK - $OutPath ($rows filas)"
} finally {
    if ($wb) { $wb.Close($false) }
    $excel.Quit()
    [System.Runtime.InteropServices.Marshal]::ReleaseComObject($excel) | Out-Null
    [GC]::Collect(); [GC]::WaitForPendingFinalizers()
}
