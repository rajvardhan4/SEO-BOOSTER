<?php
require __DIR__ . '/wp/wp-load.php';
$d = SEOB_AP_Style::get()['data'];
echo "measured btn: ", json_encode($d['desktop']['btn'] ?? null), "\nhover: ", json_encode($d['desktop']['btn_hover'] ?? null), "\nbtn2: ", json_encode($d['desktop']['btn2'] ?? null), "\n\n";
$src = SEOB_AP_Design::divi_source();
$out = SEOB_AP_Bake::divi( $src, $d );
preg_match_all('/\[(\/?[a-z_]+)/', $src, $a); preg_match_all('/\[(\/?[a-z_]+)/', $out, $b);
echo "shortcodes before/after: ", count($a[1]), " / ", count($b[1]), " same order: ", ($a[1]===$b[1]?'yes':'NO'), "\n";
preg_match_all('/@ET-DC@[^@]+@/', $src, $x); preg_match_all('/@ET-DC@[^@]+@/', $out, $y);
echo "dynamic fields kept: ", count($x[0]), " / ", count($y[0]), "\n";
echo "content between tags unchanged: ", (preg_replace('/\[[^\]]*\]/','',$src) === preg_replace('/\[[^\]]*\]/','',$out) ? 'yes':'NO'), "\n\n";
preg_match('/\[dnxte_dual_button[^\]]*\]/', $out, $m); echo $m[0], "\n\n";
preg_match_all('/\[et_pb_text[^\]]*\]/', $out, $m); echo $m[0][0], "\n\n", $m[0][2], "\n\n";
preg_match('/\[et_pb_accordion [^\]]*\]/', $out, $m); echo $m[0], "\n\n";
// Run twice: same result (no growth)
echo "idempotent: ", (SEOB_AP_Bake::divi($out,$d)===$out?'yes':'NO'), "\n";
// Elementor
$el = SEOB_AP_Design::elementor_source(); $els = $el['content'] ?? $el;
$o = SEOB_AP_Bake::elementor($els, $d);
$f=function($es) use (&$f){ foreach($es as $e){ if(($e['elType']??'')==='widget' && $e['widgetType']==='button'){ return $e['settings']; } if(!empty($e['elements'])){ $r=$f($e['elements']); if($r) return $r; } } return null; };
echo "\nelementor button: ", json_encode(array_intersect_key($f($o), array_flip(['typography_font_family','typography_font_size','typography_font_weight','typography_text_transform','background_color','button_text_color','button_background_hover_color','hover_color','border_radius','border_border','border_width','border_color','text_padding','custom_css']))), "\n";
