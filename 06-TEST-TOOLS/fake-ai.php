<?php
// Test only: answers Anthropic calls with rule-following JSON. First answer per page is deliberately broken once.
add_filter('pre_http_request', function($pre, $args, $url){
	if (strpos($url, 'api.anthropic.com') === false) return $pre;
	$body = json_decode($args['body'], true);
	$msg  = $body['messages'][0]['content'];
	if (strpos($msg, 'Return {"ok":true}') !== false) $text = '{"ok":true}';
	else {
		preg_match('/"service": "([^"]+)"/', $msg, $s); preg_match('/"city": "([^"]+)"/', $msg, $c); preg_match('/"location": "([^"]+)"/', $msg, $l);
		$svc=$s[1]; $city=$c[1]; $loc=$l[1];
		$w = function($n,$seed) use($svc,$city){ $words=explode(' ',"our team delivers careful {$svc} work for homes in {$city} with clear pricing honest advice tidy crews modern tools and lasting results that families trust every single season across the area"); $o=[]; for($i=0;$i<$n;$i++){ $o[]=$words[($i*7+$seed)%count($words)]; } return ucfirst(implode(' ',$o)).' '.$seed.'.'; };
		$retry = strpos($msg, 'Fix ONLY these problems') !== false;
		$d = ['primary_keyword'=>'', 'seo_title'=>"{$svc} in {$city} | Test Co", 'meta_desc'=>"Test Co offers {$svc} in {$loc}. Fast booking and friendly local crews."];
		$d['section_2_heading']="Dependable High Quality {$svc} In {$city}"; $d['section_2_description']=$retry? $w(120,21):$w(40,21);
		$d['section_2_left_image_alt_tag']="Technician finishing {$svc} job in {$city} home";
		$d['section_3_heading']="Long Lasting {$svc} Results in {$city}"; $d['section_3_description']=$w(120,31); $d['section_3_right_image_alt_tag']="Clean {$svc} setup for a {$city} family";
		$d['section_4_heading']="Certified {$svc} Specialists Serving {$city}"; $d['section_4_description']=$w(120,41); $d['section_4_right_image_alt_tag']="Certified crew completing {$svc} in {$city}";
		$d['section_5_main_heading']="Complete {$svc} Options For Every Home Across {$city} Today"; $d['section_5_description']=$w(60,51);
		for($i=1;$i<=6;$i++){ $d["section_5_box_{$i}_heading"]="Box {$i} {$svc} Benefit Option"; $d["section_5_box_{$i}_description"]=$w(45,60+$i); }
		$d['section_6_main_heading']="Answers To Common {$svc} Questions From People In {$city}";
		for($i=1;$i<=5;$i++){ $d["faq_{$i}_heading"]="How often do {$city} homes need {$svc} help {$i}?"; $d["faq_{$i}_description"]=$w(65,70+$i); }
		$d['section_7_heading']="Book Your {$svc} Visit With Our {$city} Team Today"; $d['section_7_description']=$w(50,90);
		$text = "```json\n".json_encode($d)."\n```";
	}
	$GLOBALS['fake_ai_calls'] = ($GLOBALS['fake_ai_calls'] ?? 0) + 1;
	update_option('fake_ai_calls', (int)get_option('fake_ai_calls')+1);
	return ['headers'=>[], 'body'=>json_encode(['content'=>[['type'=>'text','text'=>$text]]]), 'response'=>['code'=>200,'message'=>'OK'], 'cookies'=>[], 'filename'=>null];
}, 10, 3);
