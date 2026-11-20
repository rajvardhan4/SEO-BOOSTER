<?php
$_SERVER['HTTP_HOST']='127.0.0.1:8899';
require __DIR__.'/wp/wp-load.php'; wp_set_current_user(1);
$calls=['search'=>0,'download'=>0,'file'=>0,'keyhdr'=>''];
add_filter('pre_http_request', function($pre,$a,$url) use (&$calls){
  $R=fn($c,$d)=>['headers'=>[],'body'=>json_encode($d),'response'=>['code'=>$c,'message'=>''],'cookies'=>[],'filename'=>null];
  if (strpos($url,'https://api.magnific.com/v1/resources?')===0){ $calls['search']++; $calls['keyhdr']=$a['headers']['x-magnific-api-key']??''; parse_str(parse_url($url,PHP_URL_QUERY),$q);
    if (empty($q['filters']['content_type']['photo'])) return $R(400,['message'=>'filters missing']);
    $t=$q['term']; $rows=[]; for($i=0;$i<30;$i++) $rows[]=['id'=>crc32($t)%100000*100+$i,'title'=>ucfirst($t)." photo $i",'author'=>['name'=>'Studio'],'image'=>['source'=>['url'=>'x']]];
    return $R(200,['data'=>$rows,'meta'=>['last_page'=>1]]); }
  if (preg_match('#api\.magnific\.com/v1/resources/(\d+)/download#',$url,$m)){ $calls['download']++; if ($m[1]%30===2) return $R(403,['message'=>'Premium resource']); return $R(200,['data'=>['url'=>'https://cdn.example-files.com/'.$m[1].'.jpg','filename'=>$m[1].'.jpg']]); }
  if (strpos($url,'https://cdn.example-files.com/')===0){ $calls['file']++; $im=imagecreatetruecolor(1500,1000); imagefill($im,0,0,imagecolorallocate($im,rand(0,255),90,90)); imagejpeg($im,$a['filename']); return ['headers'=>[],'body'=>'','response'=>['code'=>200,'message'=>'OK'],'cookies'=>[],'filename'=>$a['filename']]; }
  return $pre; },10,3);
echo "key source: ",SEOB_AP_Images::magnific_key_source()," use: ",(int)SEOB_AP_Images::use_magnific(),"\n";
echo "test: "; var_dump(SEOB_AP_Images::test_magnific());
$s = SEOB_AP_Jobs::job(1)->settings;
$s['services']=[['name'=>'Balayage','keywords'=>['balayage']],['name'=>'Bridal Hair','keywords'=>['bridal hair']]];
$s['locations']=['Cary, NC','Holly Springs, NC','Apex, NC']; $s['existing']='update'; $s['images']=[];
$s['image_mode']='page'; $s['image_source']='magnific'; $s['image_copies']=true; $s['image_pool']=SEOB_AP_Images::pools($s['services'],false,'Maeve');
$before = wp_count_posts('attachment')->inherit;
$job = SEOB_AP_Jobs::create($s);
for($i=0;$i<40;$i++){ $r=SEOB_AP_Jobs::step($job); if($r['status']==='finished') break; }
$all=[];
foreach(SEOB_AP_Jobs::items($job) as $it){ $ids=[]; foreach(SEOB_AP_Writer::photo_fields() as $f){ $ids[]=(int)get_post_meta($it->post_id,$f,true);} $all=array_merge($all,$ids);
  echo "$it->service | $it->location | $it->status\n";
  foreach($ids as $id) echo "     ",basename(get_attached_file($id))," | alt: ",get_post_meta($id,'_wp_attachment_image_alt',true)," | parent ",wp_get_post_parent_id($id)==$it->post_id?'this page':'OTHER',"\n"; }
echo "unique images: ",count(array_unique($all)),"/",count($all)," | new attachments: ",wp_count_posts('attachment')->inherit-$before," | calls ",json_encode(['search'=>$calls['search'],'download'=>$calls['download'],'file'=>$calls['file']])," | key sent: ",$calls['keyhdr']===SEOB_DEFAULT_MAGNIFIC_KEY?'agency key':'?',"\n";
