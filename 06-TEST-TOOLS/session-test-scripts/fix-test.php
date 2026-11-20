<?php
require __DIR__ . '/wp/wp-load.php';
// Same mapping as the demo site: brown -> light grey-blue, teal -> brand blue
$src = SEOB_AP_Design::adapt( SEOB_AP_Design::divi_source(), array( '#947d65' => '#aebbd2', '#5d7e81' => '#1448a0' ) );
$out = SEOB_AP_Bake::divi_fix( $src );
preg_match_all('/\[et_pb_section((?:\s+[A-Za-z0-9_]+="[^"]*")*)\s*\](.*?)\[\/et_pb_section\]/s', $out, $m, PREG_SET_ORDER);
foreach ($m as $i=>$x) {
  preg_match('/background_color="([^"]*)"/',$x[1],$bg); preg_match_all('/(?:header(?:_\d)?|text|body)_text_color="([^"]*)"/',$x[2],$tc);
  echo "S$i bg=".($bg[1]??'-')." overlay=".(strpos($x[1],'use_background_color_gradient="on"')!==false?'yes':'no')." img=".(strpos($x[1],'background_image=')!==false?'yes':'no')." text colors=".implode(',',array_unique($tc[1]))."\n";
}
preg_match('/\[et_pb_accordion [^\]]*\]/',$out,$a); foreach(['open_toggle_background_color','closed_toggle_background_color','open_toggle_text_color','closed_toggle_text_color','toggle_font_size','toggle_font_size_tablet','toggle_font_size_phone','toggle_line_height','closed_toggle_line_height'] as $k){ preg_match('/ '.$k.'="([^"]*)"/',$a[0],$v); echo "$k=".($v[1]??'-')."  "; } echo "\n";
preg_match_all('/\[dnxte_dual_button[^\]]*\]/',$out,$d); foreach($d[0] as $x){ preg_match_all('/custom_css_button_[a-z_]+="[^"]*"/',$x,$c); echo "dual: ".implode(' ',$c[0])."\n"; }
echo "idempotent: ".(SEOB_AP_Bake::divi_fix($out)===$out?'yes':'NO')."\n";
preg_match_all('/\[(\/?[a-z_]+)/', $src, $p1); preg_match_all('/\[(\/?[a-z_]+)/', $out, $p2); echo "structure same: ".($p1[1]===$p2[1]?'yes':'NO')."\n";
