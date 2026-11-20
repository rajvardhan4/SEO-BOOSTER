<?php
$_SERVER['HTTP_HOST']='127.0.0.1:8899';
require __DIR__.'/wp/wp-load.php';
require_once ABSPATH.'wp-admin/includes/plugin.php'; require_once ABSPATH.'wp-admin/includes/image.php';
wp_set_current_user(1);
var_dump(activate_plugin('advanced-custom-fields/acf.php'));
var_dump(activate_plugin('seo-boster/seo-boster.php'));
update_option('permalink_structure','/%postname%/');
// two test images
$ids=[];
foreach(['red'=>[200,40,40],'blue'=>[40,80,200]] as $n=>$c){ $im=imagecreatetruecolor(400,300); imagefill($im,0,0,imagecolorallocate($im,...$c)); $f=wp_upload_dir()['path']."/$n.jpg"; imagejpeg($im,$f);
 $id=wp_insert_attachment(['post_mime_type'=>'image/jpeg','post_title'=>$n,'post_status'=>'inherit'],$f); wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id,$f)); $ids[]=$id; }
echo "imgs ".implode(',',$ids)."\n";
