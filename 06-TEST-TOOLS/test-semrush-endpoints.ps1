$key = 'semrtkn-pat-Gl4hYDW0TXWMa0PskGeX-g-uY0T2gCP43jSFYh0y3pRD8J7_2R10Vjh'
$urls = @(
    'https://api.semrush.com/apis/v4/backlinks/v1/links?url=example.com&scope=ROOT_DOMAIN',
    'https://api.semrush.com/apis/v4/keywords/v1/metrics?keyword=seo&country=us',
    'https://api.semrush.com/apis/v4/management/v1/keys',
    'https://api.semrush.com/apis/v4/management/v1/account'
)
foreach ($u in $urls) {
    try {
        $req = [System.Net.HttpWebRequest]::Create($u)
        $req.Headers.Add('Authorization', "Apikey $key")
        $resp = $req.GetResponse()
        $reader = New-Object System.IO.StreamReader($resp.GetResponseStream())
        Write-Host "OK: $u : $($reader.ReadToEnd())"
    } catch [System.Net.WebException] {
        $r = $_.Exception.Response
        if ($r) {
            $reader = New-Object System.IO.StreamReader($r.GetResponseStream())
            Write-Host "[$($r.StatusCode)] $u : $($reader.ReadToEnd())"
        } else {
            Write-Host "ERR: $u : $($_.Exception.Message)"
        }
    }
}
