<?php
$_SERVER['HTTP_HOST']='127.0.0.1:8899';
require __DIR__.'/wp/wp-load.php';
wp_set_current_user(1);
add_filter('pre_http_request', function($p,$a,$u){ return strpos($u,'anthropic')!==false ? ['headers'=>[],'body'=>'{"error":{"message":"invalid x-api-key"}}','response'=>['code'=>401,'message'=>'x'],'cookies'=>[],'filename'=>null] : $p; }, 99, 3);
$s = SEOB_AP_Jobs::job(1)->settings; $s['services']=[['name'=>'Vent Repair','keywords'=>[]]]; $s['locations']=['Inglis, FL','Homosassa, FL'];
$job = SEOB_AP_Jobs::create($s);
$before=(int)get_option('fake_ai_calls');
SEOB_AP_Jobs::step($job); SEOB_AP_Jobs::step($job);
$it=SEOB_AP_Jobs::items($job);
echo "401 -> job: ", SEOB_AP_Jobs::job($job)->status, " | item1: ", $it[0]->status, " '", $it[0]->message, "' | item2: ", $it[1]->status, "\n";
echo SEOB_AP_Writer::tel('020 7946 0958','44'), "\n";
