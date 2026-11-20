<?php
$_SERVER['HTTP_HOST']='127.0.0.1:8899';
require __DIR__.'/wp/wp-load.php'; wp_set_current_user(1);
function show($label){ $m=SEOB_AP_Nav::menu_id(); echo "--- $label (menu $m)\n"; $items=wp_get_nav_menu_items($m); $by=[]; foreach($items as $i) $by[(int)$i->menu_item_parent][]=$i;
  $walk=function($p,$d) use(&$walk,$by){ foreach($by[$p]??[] as $i){ echo str_repeat('   ',$d),$i->menu_order,'. ',$i->title,"\n"; $walk($i->ID,$d+1);} }; $walk(0,0); }
// fresh menu assigned to header location
foreach (wp_get_nav_menus() as $m) if ($m->name==='Test Main') wp_delete_nav_menu($m->term_id);
$mid = wp_create_nav_menu('Test Main');
foreach(['Home','About','Services','Contact Us'] as $i=>$t) wp_update_nav_menu_item($mid,0,['menu-item-title'=>$t,'menu-item-url'=>home_url('/'.sanitize_title($t).'/'),'menu-item-status'=>'publish','menu-item-type'=>'custom']);
$locs = get_registered_nav_menus(); if(!$locs){ register_nav_menu('primary-menu','Primary'); $locs=get_registered_nav_menus(); }
set_theme_mod('nav_menu_locations',[array_key_first($locs)=>$mid]);
delete_option('seob_ap_nav'); delete_option('seob_ap_menu_first');
echo "live menu: ", SEOB_AP_Nav::live_menu()===$mid?'found':'NOT found', " (location ", array_key_first($locs), ")\n";
// unpublish all area pages, then publish 5 like a job would
$ids = get_posts(['post_type'=>'areas-we-serve','post_status'=>'any','numberposts'=>-1,'fields'=>'ids']);
foreach($ids as $id) wp_update_post(['ID'=>$id,'post_status'=>'draft']);
SEOB_AP_Nav::sync(); show('no published pages');
foreach(array_slice($ids,0,6) as $id) wp_update_post(['ID'=>$id,'post_status'=>'publish']);
echo "sync queued on shutdown: ", has_action('shutdown',['SEOB_AP_Nav','sync'])?'yes':'no', "\n";
SEOB_AP_Nav::sync(); show('after publishing 6 pages');
// user moves block to the top in Appearance > Menus
$top = get_posts(['post_type'=>'nav_menu_item','meta_key'=>'_seob_ap_menu_top','fields'=>'ids'])[0];
global $wpdb; $n=1; $wpdb->update($wpdb->posts,['menu_order'=>$n],['ID'=>$top]); clean_post_cache($top);
foreach(wp_get_nav_menu_items($mid) as $i){ if($i->ID===$top) continue; $n++; $wpdb->update($wpdb->posts,['menu_order'=>$n],['ID'=>$i->ID]); clean_post_cache($i->ID);} wp_cache_flush();
foreach(wp_get_nav_menu_items($mid) as $i){ if($i->ID!==$top && !get_post_meta($i->ID,'_seob_ap_menu',true) && (int)$i->menu_order < 99){} }
wp_update_post(['ID'=>$ids[6],'post_status'=>'publish']); SEOB_AP_Nav::sync(); show('user moved it first + 1 more page');
wp_trash_post($ids[6]); SEOB_AP_Nav::sync(); show('page trashed');
SEOB_AP_Nav::remove_menu(); SEOB_AP_Nav::save(['auto'=>0]); wp_update_post(['ID'=>$ids[7],'post_status'=>'publish']); SEOB_AP_Nav::sync(); show('removed on purpose, then publish (should NOT come back)');
