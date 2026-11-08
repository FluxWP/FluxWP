<?php
/** Static gettext inventory. Only quoted literal tokens are decoded; application code is never run. */
$functions=['__'=>[1,null,null], '_e'=>[1,null,null], 'esc_html__'=>[1,null,null], 'esc_attr__'=>[1,null,null], 'esc_html_e'=>[1,null,null], 'esc_attr_e'=>[1,null,null], '_x'=>[2,null,1], '_ex'=>[2,null,1], 'esc_html_x'=>[2,null,1], 'esc_attr_x'=>[2,null,1], '_n'=>[3,1,null], '_nx'=>[4,1,3], '_n_noop'=>[2,1,null], '_nx_noop'=>[3,1,2]];
$result=[];
$qa_input=json_decode(file_get_contents('php://stdin'),true);
$rewrite=isset($qa_input['packages']);
$qa_roots=$rewrite?array_keys($qa_input['packages']):$qa_input;
function literal($tokens){$clean=array_values(array_filter($tokens,fn($t)=>!(is_array($t)&&in_array($t[0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT]))));return count($clean)===1&&is_array($clean[0])&&$clean[0][0]===T_CONSTANT_ENCAPSED_STRING?eval('return '.$clean[0][1].';'):null;}
foreach($qa_roots as $root){
 foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)) as $file){
  $path=$file->getPathname();if($file->getExtension()!=='php'||preg_match('~/node_modules/|/languages/|/tests/~',$path) || (strpos($path,'/vendor/')!==false && !preg_match('/^(class-Emsfb-|class-flx-)/',$file->getBasename())))continue;
  $code=file_get_contents($path);$tokens=token_get_all($code);$count=count($tokens);$offsets=[];$offset=0;$edits=[];foreach($tokens as $index=>$token){$offsets[$index]=$offset;$offset+=strlen(is_array($token)?$token[1]:$token);}
  for($i=0;$i<$count;$i++){
   if(!is_array($tokens[$i])||$tokens[$i][0]!==T_STRING||!isset($functions[$tokens[$i][1]]))continue;
   $function=$tokens[$i][1];$j=$i+1;while(isset($tokens[$j])&&is_array($tokens[$j])&&$tokens[$j][0]===T_WHITESPACE)$j++;
   if(($tokens[$j]??'')!=='(')continue;$args=[[]];$positions=[[]];$depth=0;
   for($k=$j+1;$k<$count;$k++){$t=$tokens[$k];if(!is_array($t)){if(in_array($t,['(','[','{']))$depth++;if(in_array($t,[')',']','}'])){if($depth===0)break;$depth--;}if($t===','&&$depth===0){$args[]=[];$positions[]=[];continue;}}$args[count($args)-1][]=$t;$positions[count($positions)-1][]=$k;}
   [$domainArg,$pluralArg,$contextArg]=$functions[$function];$domain=literal($args[$domainArg]??[]);$single=literal($args[0]);if(!$domain||$single===null)continue;
   $plural=$pluralArg!==null?literal($args[$pluralArg]??[]):null;$context=$contextArg!==null?literal($args[$contextArg]??[]):null;
   $key=($context!==null?$context.chr(4):'').$single.($plural!==null?chr(0).$plural:'');$result[$root][$domain][$key]=['file'=>substr($path,strlen($root)+1),'line'=>$tokens[$i][2]];
   if($rewrite&&$domain===$qa_input['packages'][$root]['domain']&&isset($qa_input['packages'][$root]['aliases'][$key])){
    $canonical=$qa_input['packages'][$root]['aliases'][$key];$ctx=strpos($canonical,chr(4))!==false?explode(chr(4),$canonical)[0]:null;$body=explode(chr(4),$canonical);$forms=explode(chr(0),end($body));
    $replace=[0=>$forms[0]];if($pluralArg!==null)$replace[$pluralArg]=$forms[1]??$forms[0];if($contextArg!==null&&$ctx!==null)$replace[$contextArg]=$ctx;
    foreach($replace as $arg=>$text){foreach($positions[$arg]??[] as $index){$t=$tokens[$index];if(is_array($t)&&$t[0]===T_CONSTANT_ENCAPSED_STRING&&literal([$t])!==$text){$edits[$offsets[$index]]=[strlen($t[1]),var_export($text,true)];break;}}}
   }
  }
  if($rewrite&&$edits){krsort($edits);foreach($edits as $start=>[$length,$text])$code=substr_replace($code,$text,$start,$length);file_put_contents($path,$code);}
 }
}
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
