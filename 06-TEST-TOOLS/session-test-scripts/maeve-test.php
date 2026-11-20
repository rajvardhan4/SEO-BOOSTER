<?php
$_SERVER['HTTP_HOST']='127.0.0.1:8899';
require __DIR__.'/wp/wp-load.php';
add_filter('seo_boster_image_library', function(){ $lib=[]; $names=[];
 foreach(file(__DIR__.'/maeve.txt') as $l){ $p=explode('|',trim($l)); $t=$p[1].' '.$p[2]; $P=!empty($p[3]);
  $w=$P?1000:1600;$h=$P?1400:1000;
  $lib[(int)$p[0]]=['words'=>SEOB_AP_Images::words($t),'width'=>$w,'height'=>$h,'logo'=>(bool)preg_match('/logo|icon|favicon|avatar|badge|chamber|member|certif|award|seal|partner/i',$t),'copy'=>false,'person'=>$h>$w && (bool)preg_match('/headshot|staff|team|owner|founder|director|manager|stylist|technician|employee|portrait|our-|meet/i',$t)];
  $GLOBALS['N'][(int)$p[0]]=$p[1]; }
 return $lib; });
$svc=[['name'=>'Balayage','keywords'=>[]],['name'=>'Hair Color','keywords'=>['hair color','highlights']],['name'=>'Haircuts','keywords'=>[]],['name'=>'Bridal Hair','keywords'=>[]],['name'=>'Nail Salon','keywords'=>['manicure','gel nails']],['name'=>'Hair Extensions','keywords'=>[]],['name'=>'Hair Salon','keywords'=>[]]];
foreach(SEOB_AP_Images::pools($svc,false,'Maeve Salon & Color Bar') as $i=>$p){ echo str_pad($svc[$i]['name'],16),count($p),': ',implode(', ',array_map(fn($id)=>preg_replace('/-maeve-salon|-apex-nc|-by-tess/','',$GLOBALS['N'][$id]),array_slice($p,0,9))),"\n"; }
