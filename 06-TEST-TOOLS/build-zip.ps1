Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$sourceDir = "c:\Users\digit\OneDrive\Desktop\SEO-Boster-PROJECT\02-SOURCE-CODE\seo-boster"
$zipPath = "c:\Users\digit\OneDrive\Desktop\SEO-Boster-PROJECT\01-PLUGIN-INSTALL\seo-boster-v2.2.0.zip"

if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

$zipFile = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)

$files = Get-ChildItem -Path $sourceDir -Recurse | Where-Object { -not $_.PSIsContainer }
foreach ($file in $files) {
    $rel = $file.FullName.Substring($sourceDir.Length).TrimStart('\', '/')
    $rel = $rel -replace '\\', '/'
    $entryName = "seo-boster/" + $rel
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zipFile, $file.FullName, $entryName, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
}

$zipFile.Dispose()
Write-Host "Zip successfully created at $zipPath"
Write-Host "Size: $((Get-Item $zipPath).Length) bytes"
