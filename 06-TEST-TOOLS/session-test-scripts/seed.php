<?php
$_SERVER['HTTP_HOST']='127.0.0.1:8899';
require __DIR__.'/wp/wp-load.php';
require_once ABSPATH.'wp-admin/includes/plugin.php';
wp_set_current_user(1);
$r = activate_plugin('seo-boster/seo-boster.php'); var_dump(is_wp_error($r)?$r->get_error_message():'activated');
$mk=function($t,$s,$c,$fk=''){ $id=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>$t,'post_name'=>$s,'post_content'=>$c]); if($fk) update_post_meta($id,'_yoast_wpseo_focuskw',$fk); return $id;};
$home=$mk('Home','home','<p>Buy shelf corporations, aged companies instantly now!</p><p>We help since 1993.</p>');
update_option('show_on_front','page'); update_option('page_on_front',$home);
$mk('Aged Corporations','aged-corporations','[et_pb_section][et_pb_text]<h1>Best Aged Corporations for Sale</h1><p>An aged corporation has history.</p><p>Second para.</p>[/et_pb_text][/et_pb_section]');
$mk('Shelf Corporations','shelf-corporations','<p>Best Shelf Corporations. Ready made shelf corporation.</p>');
$mk('Shelf List','shelf-list','<p>Shelf Corporations for Sale. Originally filed in Nevada, now current in Wyoming. CORP LLC</p>','shelf LLC for sale');
$mk('LLC & Corporation Formation Services','llc-corporation-formation-services','<p>Form your LLC or corporation.</p>');
$mk('Order Forms','order-forms','<p>Wyoming LLC order form. Nevada corporation order form.</p>','Nevada corp for sale');
$mk('About Us','about-us','<p>About Inc Legal Services <script>alert(1)</script></p>');
echo "pages seeded\n";
echo "table: ".($GLOBALS['wpdb']->get_var("SELECT COUNT(*) FROM wp_seob_placements")===null?'missing':'ok')."\n";
