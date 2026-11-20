Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::OpenRead('c:\Users\digit\OneDrive\Desktop\SEO-Boster-PROJECT\01-PLUGIN-INSTALL\seo-boster-v2.2.0.zip')
Write-Host "Total entries: $($zip.Entries.Count)"
Write-Host "Sample 10 entries:"
$zip.Entries | Select-Object -First 10 | ForEach-Object { Write-Host " - $($_.FullName)" }
$mainFile = $zip.Entries | Where-Object { $_.FullName -eq 'seo-boster/seo-boster.php' }
Write-Host "Main file exists: $($mainFile -ne $null)"
$backslashes = $zip.Entries | Where-Object { $_.FullName -like '*\*' }
Write-Host "Backslash entries: $($backslashes.Count)"
$zip.Dispose()
