<?php
$_SERVER['HTTP_HOST']='127.0.0.1:8899';
require __DIR__.'/wp/wp-load.php';
wp_set_current_user(1);
// 1) bad key -> 401 -> job pauses, nothing retried forever
add_filter('pre_http_request', function($p,$a,$u){ return strpos($u,'anthropic')!==false ? ['headers'=>[],'body'=>'{"error":{"message":"invalid x-api-key"}}','response'=>['code'=>401,'message'=>'x'],'cookies'=>[],'filename'=>null] : $p; }, 1, 3);
$s = SEOB_AP_Jobs::job(1)->settings; $s['services']=[['name'=>'Duct Cleaning','keywords'=>[]]]; $s['locations']=['Inglis, FL','Homosassa, FL'];
$job = SEOB_AP_Jobs::create($s);
$r = SEOB_AP_Jobs::step($job); echo "401 -> job status: ", SEOB_AP_Jobs::job($job)->status, " | item: ", SEOB_AP_Jobs::items($job)[0]->status, " | msg: ", SEOB_AP_Jobs::items($job)[0]->message, "\n";
// 2) existing page skip
remove_all_filters('pre_http_request'); require WPMU_PLUGIN_DIR.'/fake-ai.php';
$s2 = SEOB_AP_Jobs::job(1)->settings; $s2['services']=[['name'=>'AC Repair','keywords'=>[]]]; $s2['locations']=['Beverly Hills, FL'];
$j2 = SEOB_AP_Jobs::create($s2); SEOB_AP_Jobs::step($j2); $it=SEOB_AP_Jobs::items($j2)[0]; echo "duplicate -> ", $it->status, " (post $it->post_id) $it->message\n";
// 3) cron path finishes a running job
SEOB_AP_Jobs::set_job_status($job,'running'); global $wpdb; $wpdb->update(SEOB_AP_Jobs::items_table(),['status'=>'pending','attempts'=>0],['job_id'=>$job]);
SEOB_AP_Jobs::cron(); $c=SEOB_AP_Jobs::counts($job); echo "cron -> ", SEOB_AP_Jobs::job($job)->status, " done=", $c['done']+$c['warning'], "/", $c['total'], " next cron scheduled: ", wp_next_scheduled('seob_ap_run_jobs')?'yes':'no', "\n";
// 4) publish all + trash
echo "published: ", SEOB_AP_Jobs::publish_all($job), " | trashed: ", SEOB_AP_Jobs::trash_pages($job), "\n";
// 5) parsing helpers
print_r(SEOB_Area_Pages::split_keywords("ac repair, air conditioner repair || ac installation\n||heat pump"));
echo implode(' | ', SEOB_Area_Pages::split_list("Beverly Hills, FL || Citrus Springs, FL \n 3. Dunnellon, FL\nDunnellon, FL", 200)), "\n";
echo SEOB_AP_Writer::tel('(732) 3606065'), ' ', SEOB_AP_Writer::tel('+1 352-489-6673'), ' ', SEOB_AP_Writer::tel('020 7946 0958','44'), "\n";
echo SEOB_AP_Writer::clean("high\u{2011}quality \u{2014} fast\u{00A0}service"), "\n";
