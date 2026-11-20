<?php
$_SERVER['HTTP_HOST']='127.0.0.1:8899';
require __DIR__.'/wp/wp-load.php';
// Divi adapt
$d = SEOB_AP_Design::divi_source();
echo "divi layout colors: ", implode(' ', SEOB_AP_Design::layout_colors($d)), "\n";
$a = SEOB_AP_Design::adapt($d, ['#947d65'=>'#503aa8']);
echo "divi old site links left: ", substr_count($a,'bayareapaversavers'), " | new link: ", preg_match('#http://127\.0\.0\.1:8899/about-us/#',$a), " | color swapped: ", substr_count(strtolower($a),'#503aa8'), " | dynamic tags intact: ", substr_count($a,'@ET-DC@'), "/", substr_count($d,'@ET-DC@'), "\n";
// Elementor
$src = SEOB_AP_Design::elementor_source();
$m = new ReflectionMethod('SEOB_AP_Design','link_buttons'); $m->setAccessible(true);
$content = $m->invoke(null, $src['content']);
$json = SEOB_AP_Design::adapt(wp_json_encode($content), ['#7c62ff'=>'#503aa8']);
$back = json_decode($json, true);
echo "elementor json valid: ", is_array($back)?'yes':'NO', " | rainbowlakes left: ", substr_count($json,'rainbowlakesac'), " | old phone left: ", substr_count($json,'3524896673'), "\n";
$walk=function($els) use (&$walk){ foreach($els as $e){ if(($e['widgetType']??'')==='button') echo "  btn ", $e['settings']['text'], " -> static=", $e['settings']['link']['url'], " dyn=", urldecode(preg_replace('/.*settings="([^"]+)".*/','$1',$e['settings']['__dynamic__']['link'] ?? '-')), "\n"; if(!empty($e['elements'])) $walk($e['elements']); } };
$walk($back);
