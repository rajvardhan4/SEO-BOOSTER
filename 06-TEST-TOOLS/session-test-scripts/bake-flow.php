<?php
require __DIR__ . '/wp/wp-load.php';
wp_set_current_user(1);
$old = SEOB_AP_Design::settings();
$body = wp_insert_post(['post_type'=>'post','post_status'=>'draft','post_title'=>'fake body','post_content'=>SEOB_AP_Design::divi_source()]);
// register fake Divi post types so get_post_type checks pass
global $wpdb; $wpdb->update($wpdb->posts,['post_type'=>'et_body_layout'],['ID'=>$body]);
$tpl = wp_insert_post(['post_type'=>'post','post_status'=>'draft','post_title'=>'fake tpl']); $wpdb->update($wpdb->posts,['post_type'=>'et_template'],['ID'=>$tpl]); clean_post_cache($body); clean_post_cache($tpl);
update_post_meta($tpl,'_et_body_layout_id',$body);
SEOB_AP_Design::save(['mode'=>'divi','divi_template'=>$tpl]);
SEOB_AP_Style::save(['baked'=>'']);
echo "baked before: ", var_export(SEOB_AP_Bake::is_baked(),true), "\n";
SEOB_AP_Style::maybe_bake();
clean_post_cache($body);
echo "baked after: ", var_export(SEOB_AP_Bake::is_baked(),true), "\n";
$c = get_post($body)->post_content;
echo "template has site font: ", (strpos($c,'Manrope|300')!==false?'yes':'no'), ", buttons: ", (strpos($c,'button_bg_one="#c8102e"')!==false?'yes':'no'), ", FAQ title font: ", (preg_match('/toggle_font="Manrope/',$c)?'yes':'no'), "\n";
$css = SEOB_AP_Style::css();
echo "forced CSS on Theme Builder area: ", (strpos($css,'et-l--body')!==false?'YES (bad)':'no (good)'), "\n";
// restore
wp_delete_post($body,true); wp_delete_post($tpl,true);
SEOB_AP_Design::save(['mode'=>$old['mode'],'divi_template'=>$old['divi_template']]);
SEOB_AP_Style::save(['baked'=>'']);
echo "restored mode: ", SEOB_AP_Design::settings()['mode'], "\n";
