<?php
$_SERVER['HTTP_HOST']='127.0.0.1:8899';
require __DIR__.'/wp/wp-load.php'; wp_set_current_user(1);
$s = SEOB_AP_Jobs::job(1)->settings; $s['services']=[['name'=>'Roof Repair','keywords'=>[]],['name'=>'Roof Cleaning','keywords'=>[]]]; $s['locations']=['Lecanto, FL','Hernando, FL','Floral City, FL','Crystal River, FL'];
echo SEOB_AP_Jobs::create($s);
