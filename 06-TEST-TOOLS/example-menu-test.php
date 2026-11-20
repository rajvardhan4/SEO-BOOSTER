<?php
$_SERVER['HTTP_HOST']='127.0.0.1:8899';
require __DIR__.'/wp/wp-load.php';
wp_set_current_user(1);
foreach(get_posts(['post_type'=>'areas-we-serve','post_status'=>'any','numberposts'=>-1]) as $p){ if($p->post_status!=='publish') wp_update_post(['ID'=>$p->ID,'post_status'=>'publish']); }
$menu = wp_get_nav_menu_object('Main') ?: wp_get_nav_menu_object(wp_create_nav_menu('Main'));
$home = wp_update_nav_menu_item($menu->term_id,0,['menu-item-title'=>'Home','menu-item-url'=>home_url('/'),'menu-item-status'=>'publish','menu-item-type'=>'custom']);
SEOB_AP_Nav::save(['menu_id'=>$menu->term_id,'loc_order'=>['Inverness, FL','Dunnellon, FL'],'items'=>[]]);
$r = SEOB_AP_Nav::build_menu(); echo "built items: ", is_wp_error($r)?$r->get_error_message():$r, "\n";
$print=function($menu){ $items=wp_get_nav_menu_items($menu->term_id); $by=[]; foreach($items as $i) $by[$i->menu_item_parent][]=$i; $walk=function($pid,$d) use (&$walk,$by){ foreach($by[$pid]??[] as $i){ echo str_repeat('  ',$d),'- ',$i->title,'  (',$i->url,")\n"; $walk($i->ID,$d+1);} }; $walk(0,0); };
$print($menu);
$h = SEOB_AP_Nav::build_hub(); echo "hub id: $h ", get_permalink($h), "\n";
SEOB_AP_Nav::save(['group'=>'service']); SEOB_AP_Nav::build_menu(); echo "--- grouped by service, menu points to hub:\n"; $print($menu);
SEOB_AP_Nav::remove_menu(); echo "--- after remove:\n"; $print($menu);
SEOB_AP_Nav::save(['group'=>'location']); SEOB_AP_Nav::build_menu();
