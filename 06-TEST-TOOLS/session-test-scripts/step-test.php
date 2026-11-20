<?php
$_SERVER['HTTP_HOST']='127.0.0.1:8899';
require __DIR__.'/wp/wp-load.php';
wp_set_current_user(1);
$s = SEOB_AP_Jobs::job(1)->settings; $s['services']=[['name'=>'Gutter Repair','keywords'=>['gutter repair']]]; $s['locations']=['Ocala, FL','Inverness, FL']; $s['existing']='skip';
$job = SEOB_AP_Jobs::create($s);
for($i=0;$i<6;$i++){ $r=SEOB_AP_Jobs::step($job); echo $r['status'],' ',json_encode($r['last']),"\n"; if($r['status']==='finished') break; }
foreach(SEOB_AP_Jobs::items($job) as $it) echo "$it->location $it->status post=$it->post_id kw=$it->keyword msg=$it->message\n";
echo "state options left: ", $GLOBALS['wpdb']->get_var("SELECT COUNT(*) FROM wp_options WHERE option_name LIKE 'seob_ap_state_%'"), "\n";
// same-failure auto pause
add_filter('pre_http_request', function($p,$a,$u){ return strpos($u,'anthropic')!==false ? ['headers'=>[],'body'=>'{"error":{"message":"model not found"}}','response'=>['code'=>404,'message'=>'x'],'cookies'=>[],'filename'=>null] : $p; }, 99, 3);
$s['locations']=['A1, FL','A2, FL','A3, FL','A4, FL','A5, FL','A6, FL']; $j2=SEOB_AP_Jobs::create($s);
for($i=0;$i<20;$i++){ $r=SEOB_AP_Jobs::step($j2); if($r['status']!=='running') break; }
$c=SEOB_AP_Jobs::counts($j2); echo "404 job -> ",SEOB_AP_Jobs::job($j2)->status," failed=",$c['failed']," pending=",$c['pending'],"\n";
