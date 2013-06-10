<?php

namespace Common;

//create a temporary directory
//warning, subject to tiny race condition, couldn't find any better
function tempdir()
{
    $tempfile=tempnam(sys_get_temp_dir(),'');
    if(file_exists($tempfile))
    { 
    	unlink($tempfile);
    	mkdir($tempfile);
    }

    if(is_dir($tempfile))return $tempfile;
    else return false;
}

//recursively delete directory
function rrmdir($dir) 
{ 
   if(is_dir($dir)) 
   { 
	     $objects = scandir($dir); 
	     foreach ($objects as $object)
	     { 
	       	if ($object != "." && $object != "..")
	       	{ 
	         	if (filetype($dir."/".$object) == "dir")rrmdir($dir."/".$object); 
	         	else unlink($dir."/".$object); 
	       	} 
    	} 
    	reset($objects); 
    	rmdir($dir); 
   } 
 }

 //file put contents with directory structure creation
function file_put_contents_with_parents($path, $data)
{
	$dir = dirname($path);
	if(!is_dir($dir))
	{
		mkdir($dir, 0777, true);
	}
	file_put_contents($path, $data);
}

//return an array with the (recursive) list of files in a directory with full path
function file_list($dir)
{
	$files = array();
	//lol, php
	foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $path)
	{
		if(is_file($path) and !is_dir($path))$files[] = $path;
	}
	return $files;
}

//like addslashes but removes superfluous backslashes before adding them!
function slashify($str)
{
	return preg_replace('/\\\\*([\'])/', "\\\\$1", $str);
}

function getOpts()
{
	global $argv;
	$options = array();
	$n       = count($argv);

	for($i=1; $i < $n; $i+=1)
	{
		$m = array();
		if(preg_match('/^--(.*)$/', $argv[$i], $m))
		{
			unset($argv[$i]);
			$i+=1;
			if($i < $n)
			{
				$options[$m[1]] = $argv[$i];
				unset($argv[$i]);
			}
			else 
			{
				$options[$m[1]] = null;
			}
		}
	}

	$argv = array_values($argv);

	return $options;
}

function getCSVHeaders($file, &$separator=null)
{
	$f = fopen($file, 'r');
	$first_line = fgets($f);
	rewind($f);

	//guess separator
	if(substr_count($first_line, ";") > substr_count($first_line, ","))
	{
		$separator=";";
	}
	else
	{
		$separator=",";
	}

	$headers = fgetcsv($f, 0, $separator);

	fclose($f);

	return $headers;
}

function CSVForEach($file, $func, $customQuote=true)
{
	$f = fopen($file, 'r');

	fgets($f);
	
	$separator = '';
	$headers   = getCSVHeaders($file, $separator);

	if($customQuote)
	{
		$pos = ftell($f);
		while($row = fgetcsv($f, 0, $separator, '"','\\'))
		{
			if(count($row) != count($headers))
			{
				fseek($f, $pos);
				$row = fgetcsv($f, 0, $separator, '"','"');
				print_r($row);
			}
			$row = array_combine($headers, $row);
			$func($row);
			$pos = ftell($f);
		}
	}
	else
	{
		while($row = fgetcsv($f, 0, $separator))
		{
			$row = array_combine($headers, $row);
			$func($row);
		}
	}

	fclose($f);
}

function fputcsv($handle, $array, $delim=";", $quote="\"")
{
	fputs($handle, implode($delim, array_map(function($item) use ($delim, $quote){
			
			if(false !== strpos($item, $delim) or false !== strpos($item, $quote) or false !== strpos($item, "\n"))
			{
				$item = $quote.preg_replace("/(?:$quote)+/", $quote.$quote, $item).$quote;
			}

			return $item;}, 
		$array))."\n");
}

function CSVForEachIO($infile, $outfile, $row_handler, $additional_headers=array(), $delim=";", $quote="\"")
{
	$f = fopen($outfile, 'w');

	$headers = array_merge(array_values(getCSVHeaders($infile)), array_values($additional_headers));
	fputcsv($f, $headers, $delim, $quote);

	CSVForEach($infile, function($row) use ($f, $row_handler, $headers, $delim, $quote){
		$data = $row_handler($row);
		if($data !== false)
		{
			while(count($data) < count($headers))
			{
				$data[] = null;
			}
			fputcsv($f, $data, $delim, $quote);
		}
	});

	fclose($f);
}