<?php
require __DIR__ . '/wp/wp-load.php';
$map = SEOB_AP_Design::auto_color_map( 'divi' );
echo "color map: ", json_encode( $map ), "\n";
// Simulate a light site accent like the demo site
$src = SEOB_AP_Design::adapt( SEOB_AP_Design::divi_source(), array( '#1b6d87' => '#aebbd2', '#947d65' => '#1448a0' ) );
$d = SEOB_AP_Style::get()['data'];
$out = SEOB_AP_Bake::divi_fix( SEOB_AP_Bake::divi( $src, $d ) );
preg_match_all('/\[et_pb_section((?:\s+[A-Za-z0-9_]+="[^"]*")*)\s*\](.*?)\[\/et_pb_section\]/s', $out, $m, PREG_SET_ORDER);
foreach ($m as $i=>$x) { preg_match('/ background_color="([^"]*)"/',$x[1],$bg); echo "S$i bg=".($bg[1]??'-')." overlay=".(strpos($x[1],'use_background_color_gradient="on"')!==false?'yes':'no')." img=".(strpos($x[1],'background_image=')!==false?'yes':'no')."\n"; }
preg_match_all('/\[dnxte_dual_button[^\]]*\]/',$out,$dd); foreach($dd[0] as $x){ preg_match_all('/custom_css_button_[a-z_]+="[^"]*"/',$x,$c); echo "dual: ".implode(' ',$c[0])."\n"; }
preg_match('/\[et_pb_accordion [^\]]*\]/',$out,$a); foreach(['open_toggle_background_color','closed_toggle_background_color','toggle_font','toggle_font_size','toggle_font_size_tablet','toggle_font_size_phone'] as $k){ preg_match('/ '.$k.'="([^"]*)"/',$a[0],$v); echo "$k=".($v[1]??'-')."  "; } echo "\n";
preg_match_all('/\[et_pb_text[^\]]*\]/',$out,$t); foreach([0,1,5] as $n){ if(isset($t[0][$n])){ preg_match_all('/ (header(?:_\d)?_font(?:_size)?|text_font_size)="([^"]*)"/',$t[0][$n],$z); echo "text$n: ".implode(' ',array_map(fn($a,$b)=>"$a=$b",$z[1],$z[2]))."\n"; } }
preg_match_all('/\[(\/?[a-z_]+)/', $src, $p1); preg_match_all('/\[(\/?[a-z_]+)/', $out, $p2); echo "structure same: ".($p1[1]===$p2[1]?'yes':'NO')."  idempotent: ".(SEOB_AP_Bake::divi_fix($out)===$out?'yes':'NO')."\n";
SEOB_AP_Design::save(['mode'=>'divi']); echo "image columns (divi, source layout): ", implode(', ', SEOB_AP_Design::used_image_fields()), "\n"; SEOB_AP_Design::save(['mode'=>'builtin']);
