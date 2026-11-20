<?php
$_SERVER['HTTP_HOST']='127.0.0.1:8899';
require __DIR__.'/wp/wp-load.php'; wp_set_current_user(1);
require_once ABSPATH.'wp-admin/includes/image.php';
// Seed library like a salon site (once).
if ( ! get_option('seob_img_seeded') ) {
  $items = [
   ['blonde-balayage-maeve-salon-apex-nc',1200,800,'Soft blonde balayage at Maeve Salon in Apex, NC'],
   ['brunette-balayage-apex-nc',1200,800,'Brunette balayage with caramel'],
   ['golden-blonde-balayage-maeve-salon-apex-nc',1000,1300,'Golden blonde balayage'],
   ['icy-blonde-highlights-maeve-salon-apex-nc',1200,800,'Icy blonde highlights'],
   ['bronde-balayage-waves-maeve-salon-apex-nc',1200,900,'Bronde balayage waves'],
   ['foil-highlights-color-apex',1200,800,'Colorist applying foil highlights'],
   ['stylist-precision-haircut-apex',1200,800,'Stylist giving a precision haircut'],
   ['stylist-cutting-hair-maeve-salon',1200,800,'A Maeve stylist cutting hair'],
   ['dark-brunette-layers-maeve-salon-apex-nc',900,1200,'Dark brunette layered haircut'],
   ['bridal-hair-apex-nc-1',1200,800,'Bride with an elegant low updo'],
   ['bridal-party-hair-apex-nc',1200,800,'Bridal party hair and makeup'],
   ['maeve-salon-apex-nc-interior',1600,900,'Bright styling floor inside Maeve Salon'],
   ['styling-stations-maeve-salon',1600,900,'Styling stations with natural light'],
   ['color-bar-maeve-salon-apex-nc',1600,900,'The color bar at Maeve Salon'],
   ['anna-maeve-salon-stylist-apex-nc',800,1200,'Anna, hair stylist at Maeve Salon'],
   ['carson-maeve-salon-stylist-apex-nc',800,1200,'Carson, hair stylist at Maeve Salon'],
   ['maeve-salon-logo',600,300,'Maeve Salon logo'],
   ['kerastase-logo',500,300,'Kerastase hair care'],
   ['apex-chamber-of-commerce-member',600,400,'Apex Chamber of Commerce member'],
  ];
  $up = wp_upload_dir();
  foreach ($items as $i=>[$n,$w,$h,$alt]) {
    $im = imagecreatetruecolor($w,$h); imagefill($im,0,0,imagecolorallocate($im,40+$i*10,100,150)); 
    $f = $up['path']."/$n.jpg"; imagejpeg($im,$f,70);
    $id = wp_insert_attachment(['post_mime_type'=>'image/jpeg','post_title'=>$n,'post_status'=>'inherit'],$f);
    wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id,$f));
    update_post_meta($id,'_wp_attachment_image_alt',$alt);
  }
  update_option('seob_img_seeded',1);
}
$svc=[['name'=>'Balayage','keywords'=>['balayage','blonde balayage']],['name'=>'Haircuts','keywords'=>['haircut','precision haircut']],['name'=>'Bridal Hair','keywords'=>['bridal hair','wedding hair']],['name'=>'Keratin Treatment','keywords'=>[]]];
$pools = SEOB_AP_Images::pools($svc,false,'Maeve Salon');
$lib = SEOB_AP_Images::library();
foreach ($pools as $i=>$p){ echo $svc[$i]['name'],": "; foreach($p as $id) echo basename(get_attached_file($id)),' '; echo "\n"; }
echo "\nPer page:\n";
$job=['services'=>$svc,'images'=>[],'image_pool'=>$pools,'image_mode'=>'page'];
foreach ([0,2,3] as $si) for($l=0;$l<4;$l++){ $r=SEOB_AP_Writer::page_images($job,['service_index'=>$si,'location_index'=>$l]); echo $svc[$si]['name']," L$l: ", implode(' | ', array_map(fn($id)=>preg_replace('/-maeve-salon|-apex-nc|\.jpg/','',basename(get_attached_file($id))),$r)),"\n"; }
