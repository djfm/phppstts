<?php

namespace Pack;

require_once dirname(__FILE__) . "/common.php";
require_once 'Archive/Tar.php'; 

function readGZIP($src, $filter=null)
{
	if(!file_exists($src))return false;
	//if(!preg_match('/\.gzip|\.tar\.gz$/', $src))return false;


	$dictionary = array('mails' => array(), 'strings' => array());


	$dir   = \Common\tempdir(); 
	$gzip  = new \Archive_Tar($src);
	$gzip->extract($dir);
	foreach($gzip->listContent() as $desc)
	{
		$filename = $desc['filename'];
		$path     = "$dir/$filename";
		$source   = file_get_contents($path);
		$m = array();
		if(null === $filter or preg_match($filter, $filename))
		{
			if(preg_match('/\.php$/', $filename) and basename($filename) != 'index.php' and preg_match('/\$(_\w+)\s*=\s*array\s*\(\s*\)\s*;/', $source, $m))
			{
				$arr = $m[1];
				include_once($path);
				foreach($$arr as $key => $value)
				{
					$dictionary['strings'][$key] = array('array' => '$'.$arr, 'file' => $filename, 'translation' => $value);
				}
			}
			else if(preg_match('/(\/|$)mails\//', $filename))
			{
				$dictionary['mails'][$filename] = $source;
			}
		}
	}
	\Common\rrmdir($dir);

	return $dictionary;
}

function extractArray($file)
{
	$contents = file_get_contents($file);
	$m        = array();
	if(preg_match('/\$(\w+)\s*=\s*array\(\);/', $contents, $m))
	{
		$arr = $m[1];
		include($file);
		return $$arr;
	}
	else return array();
}