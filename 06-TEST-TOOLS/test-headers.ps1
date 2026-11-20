$key = 'semrtkn-pat-Gl4hYDW0TXWMa0PskGeX-g-uY0T2gCP43jSFYh0y3pRD8J7_2R10Vjh'

$headers_list = @(
    @{ 'Authorization' = "Apikey $key" },
    @{ 'Authorization' = "Bearer $key" },
    @{ 'Authorization' = "$key" },
    @{ 'Apikey' = "$key" },
    @{ 'x-api-key' = "$key" },
    @{ 'X-Api-Key' = "$key" },
    @{ 'Authorization' = "Token $key" }
)

foreach ($h in $headers_list) {
    try {
        $req = [System.Net.HttpWebRequest]::Create('https://api.semrush.com/apis/v4/keywords/v1/metrics?keyword=plumber&country=us')
        foreach ($k in $h.Keys) {
            $req.Headers.Add($k, $h[$k])
        }
        $resp = $req.GetResponse()
        $reader = New-Object System.IO.StreamReader($resp.GetResponseStream())
        Write-Host "SUCCESS: $($h.Keys): $($reader.ReadToEnd())"
    } catch [System.Net.WebException] {
        $r = $_.Exception.Response
        if ($r) {
            $reader = New-Object System.IO.StreamReader($r.GetResponseStream())
            Write-Host "[$($r.StatusCode)] $($h.Keys): $($reader.ReadToEnd())"
        }
    }
}
