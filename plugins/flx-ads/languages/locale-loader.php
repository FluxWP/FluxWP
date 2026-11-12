<?php
defined('ABSPATH') || exit;
add_filter('load_textdomain_mofile', static function($file,$domain){
 if($domain!=='flx-ads' || is_readable($file) || is_readable(str_replace('.mo','.l10n.php',$file)))return $file;
 $locale=preg_replace('/^flx-ads-/', '', basename($file,'.mo'));
 $base=strtolower(explode('_',str_replace('-','_',$locale))[0]);
 $locales=array('en'=>'en_US','tr'=>'tr_TR','de'=>'de_DE','es'=>'es_ES','fr'=>'fr_FR','pt'=>'pt_BR','ja'=>'ja','zh'=>'zh_CN','ar'=>'ar','ru'=>'ru_RU');
 if(!isset($locales[$base]))return $file;
 $path=__DIR__.'/'.str_replace('{locale}',$locales[$base],'flx-ads-{locale}').'.mo';
 return is_readable($path)?$path:$file;
},20,2);
