$key = 'semrtkn-pat-Gl4hYDW0TXWMa0PskGeX-g-uY0T2gCP43jSFYh0y3pRD8J7_2R10Vjh'

function Test-Request($name, $url, $headers) {
    Write-Host "--- $name ---"
    try {
        $req = [System.Net.HttpWebRequest]::Create($url)
        if ($headers) {
            foreach ($k in $headers.Keys) {
                $req.Headers.Add($k, $headers[$k])
            }
        }
        $resp = $req.GetResponse()
        $reader = New-Object System.IO.StreamReader($resp.GetResponseStream())
        $body = $reader.ReadToEnd()
        Write-Host "Success ($($resp.StatusCode)): $body"
    } catch [System.Net.WebException] {
        $resp = $_.Exception.Response
        if ($resp) {
            $reader = New-Object System.IO.StreamReader($resp.GetResponseStream())
            $body = $reader.ReadToEnd()
            Write-Host "Error ($($resp.StatusCode)): $body"
        } else {
            Write-Host "Error: $($_.Exception.Message)"
        }
    }
}

Test-Request "V4 Apikey" "https://api.semrush.com/apis/v4/keywords/v1/metrics?keyword=plumber&country=us" @{ "Authorization" = "Apikey $key" }
Test-Request "V4 Bearer" "https://api.semrush.com/apis/v4/keywords/v1/metrics?keyword=plumber&country=us" @{ "Authorization" = "Bearer $key" }
Test-Request "V3 key param" "https://api.semrush.com/?type=phrase_this&key=$key&export_columns=Ph,Nq,Cp,Kd&phrase=plumber&database=us" $null
Test-Request "V3 with Bearer" "https://api.semrush.com/?type=phrase_this&export_columns=Ph,Nq,Cp,Kd&phrase=plumber&database=us" @{ "Authorization" = "Bearer $key" }
Test-Request "V4 App Center / User" "https://api.semrush.com/apis/v4/user" @{ "Authorization" = "Apikey $key" }
