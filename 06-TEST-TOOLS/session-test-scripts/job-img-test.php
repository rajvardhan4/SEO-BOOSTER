<?php
$_SERVER['HTTP_HOST']='127.0.0.1:8899';
require __DIR__.'/wp/wp-load.php'; wp_set_current_user(1);
$s = SEOB_AP_Jobs::job(1)->settings;
$s['business']='Maeve Salon';
$s['services']=[['name'=>'Balayage','keywords'=>['balayage']],['name'=>'Bridal Hair','keywords'=>['bridal hair']]];
$s['locations']=['Cary, NC','Holly Springs, NC','Fuquay-Varina, NC'];
$s['existing']='update'; $s['images']=[];
$s['image_mode']='page'; $s['image_copies']=true;
$s['image_pool']=SEOB_AP_Images::pools($s['services'],false,'Maeve Salon');
$job = SEOB_AP_Jobs::create($s);
for($i=0;$i<30;$i++){ $r=SEOB_AP_Jobs::step($job); if($r['status']==='finished') break; }
foreach(SEOB_AP_Jobs::items($job) as $it){
  echo "$it->service | $it->location | $it->status\n";
  foreach(SEOB_AP_Writer::photo_fields() as $f){ $id=(int)get_post_meta($it->post_id,$f,true); $src=(int)get_post_meta($id,'_seob_ap_copy_of',true);
    echo "   $f => ",basename(get_attached_file($id))," (from ",basename(get_attached_file($src)),") alt=",get_post_meta($id,'_wp_attachment_image_alt',true)," parent=",wp_get_post_parent_id($id),"\n"; }
  echo "   thumb=",basename(get_attached_file(get_post_thumbnail_id($it->post_id))),"\n";
}
$id=(int)get_post_meta(SEOB_AP_Jobs::items($job)[0]->post_id,'section_2_left_image',true);
print_r(array_map(fn($x)=>$x['file'],wp_get_attachment_metadata($id)['sizes'])); echo wp_get_attachment_image_url($id,'medium'),"\n";
echo "exists: ", file_exists(get_attached_file($id))?'yes':'no', "\n";
// re-run refresh (should reuse copies, not create new)
$before = wp_count_posts('attachment')->inherit;
echo "refresh: ", SEOB_AP_Jobs::refresh_images($job), " pages; attachments before $before after ", wp_count_posts('attachment')->inherit, "\n";
