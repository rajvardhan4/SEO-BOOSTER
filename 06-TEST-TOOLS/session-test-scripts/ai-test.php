<?php
$_SERVER['HTTP_HOST']='127.0.0.1:8899';
require __DIR__.'/wp/wp-load.php'; wp_set_current_user(1);
$orig = get_option('seob_ap_ai');
// 1. Migration: old option with single key.
update_option('seob_ap_ai',['provider'=>'anthropic','model'=>'','key'=>SEOB_AP_AI::encrypt('sk-ant-OLD1234'),'last4'=>'1234','speed'=>'fast']);
$s=SEOB_AP_AI::settings(); echo "migrate: has_key=",(int)SEOB_AP_AI::has_key()," last4=",$s['last4']," keys=",implode(',',array_keys($s['keys']))," model=",SEOB_AP_AI::model(),"\n";
echo "anthropic test (fake ai): "; var_dump(SEOB_AP_AI::test());

$log=[]; $mode=[];
add_filter('pre_http_request', function($pre,$a,$url) use (&$log,&$mode){
  if (strpos($url,'anthropic')!==false) return $pre;
  $b = isset($a['body']) && is_string($a['body']) ? json_decode($a['body'],true) : null;
  $log[] = (isset($a['method'])?$a['method']:'').' '.$url.' auth='.substr($a['headers']['Authorization']??'-',0,12).' body='.($b?implode(',',array_keys($b)).' model='.$b['model'].(isset($b['max_tokens'])?' mt='.$b['max_tokens']:''):'');
  $R = fn($c,$d)=>['headers'=>[],'body'=>json_encode($d),'response'=>['code'=>$c,'message'=>''],'cookies'=>[],'filename'=>null];
  if (preg_match('#/models$#',$url)) return $R(200,['data'=>[['id'=>'grok-4'],['id'=>'grok-4-fast'],['id'=>'grok-2-image'],['id'=>'grok-3-mini']]]);
  if (($mode['nomodel']??0) && $b['model']!=='grok-4-fast') return $R(404,['error'=>['message'=>'The model does not exist']]);
  if (($mode['norf']??0) && isset($b['response_format'])) return $R(400,['error'=>['message'=>'response_format json_object is not supported']]);
  if (($mode['cap']??0) && ($b['max_tokens']??0)>8192) return $R(400,['error'=>['message'=>'max_tokens must be in range [1, 8192]']]);
  return $R(200,['choices'=>[['message'=>['content'=>'<think>hmm</think>{"ok":true}'],'finish_reason'=>'stop']]]);
},10,3);

foreach (['openai','gemini','deepseek','openrouter','groq','mistral'] as $p) {
  SEOB_AP_AI::save($p,'','key-'.$p.'-ABCD','fast'); $log=[];
  $r=SEOB_AP_AI::test(); echo str_pad($p,11), ($r===true?'OK ':'FAIL '.$r->get_error_message()), ' ', $log[0],"\n";
}
echo "anthropic key kept: ", (int)isset(SEOB_AP_AI::saved_keys()['anthropic']), " saved=",implode(',',array_keys(SEOB_AP_AI::saved_keys())),"\n";
// xai: no default model -> auto pick from list (fast)
SEOB_AP_AI::save('xai','','xai-key-1','fast'); $log=[]; $r=SEOB_AP_AI::test(); echo "xai auto: ",($r===true?'OK':'FAIL'),' model=',SEOB_AP_AI::model(),"\n  ",implode("\n  ",$log),"\n";
// model not found -> repick
$s=get_option('seob_ap_ai'); $s['auto_model']['xai']='grok-old'; update_option('seob_ap_ai',$s); $mode=['nomodel'=>1]; $log=[];
$r=SEOB_AP_AI::test(); echo "xai repick: ",($r===true?'OK':'FAIL '.$r->get_error_message()),' model=',SEOB_AP_AI::model(),"\n";
// response_format unsupported + token cap (custom)
SEOB_AP_AI::save('custom','my-model','cust-key','fast','https://api.example.com/v1/chat/completions'); $mode=['norf'=>1,'cap'=>1]; $log=[];
$r=SEOB_AP_AI::complete('sys','Return {"ok":true}',16000); echo "custom: ",(is_wp_error($r)?'FAIL '.$r->get_error_message():$r),"\n  ",implode("\n  ",$log),"\n";
// custom pointing to local network blocked
SEOB_AP_AI::save('custom','m','k','fast','https://127.0.0.1/v1'); remove_all_filters('pre_http_request'); $r=SEOB_AP_AI::test(); echo "custom local: ",is_wp_error($r)?'blocked: '.$r->get_error_message():'NOT BLOCKED',"\n";
SEOB_AP_AI::forget_key(); echo "after forget custom: ",implode(',',array_keys(SEOB_AP_AI::saved_keys())),"\n";
update_option('seob_ap_ai',$orig);
