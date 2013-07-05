#!/usr/bin/php
<?php

function error_handler($errNo, $errStr, $errFile, $errLine)
{
	$msg = "$errStr in $errFile on line $errLine";
	throw new ErrorException($msg, $errNo);
}
set_error_handler('error_handler');

$deps = array("common.php", "pack.php");

foreach($deps as $dep)
{
	require_once dirname(__FILE__) . "/$dep";
}

$options = Common\getOpts();

if(!isset($options['tool']))
{
	echo "Please select a tool!\n";
	exit;
}

if($options['tool'] == 'diffpack')
{
	if(count($argv) < 3)
	{
		echo "Please provide 2 gzips to diff!\n";
		exit;
	}

	$from = Pack\readGZIP($argv[1]);
	$to   = Pack\readGZIP($argv[2]);

	if($from == false)
	{
		echo "Invalid GZIP {$argv[1]}!\n";
		exit;
	}

	if($to == false)
	{
		echo "Invalid GZIP {$argv[2]}!\n";
		exit;
	}

	if(isset($options['tpl']))
	{
		$out  = basename($options['tpl'],'.csv') . ".diff.csv";

		Common\CSVForEachIO($options['tpl'], $out, function ($row) use ($from, $to){

			$key = $row['Array Key'];
			if(!preg_match('/^mail_/', $key))
			{
				$first  = isset($from['strings'][$key]) ? $from['strings'][$key]['translation'] : '';
				$second = isset($to['strings'][$key])   ? $to['strings'][$key]['translation']   : '';
				$row    = array_merge($row, array($first, $second, ($first == $second ? 'yes' : 'no')));
				return $row;
			}
			else return false;
		}, array("First Version", "Second Version", "Same?"));
	}
	else
	{
		$outname = basename($argv[1]) . "_" . basename($argv[2]) . ".diff.csv";
		$out = fopen($outname, 'w');

		Common\fputcsv($out, array('Key', 'Same', "# words added", 'From ('.basename($argv[1]).')', 'To ('.basename($argv[2]).')'));

		$tot  = 0;	

		$keys = array_unique(array_merge(array_keys($from['strings']), array_keys($to['strings'])));
		foreach($keys as $key)
		{
			$f = isset($from['strings'][$key]) ? $from['strings'][$key]['translation'] : null;
			$t = isset($to['strings'][$key])   ? $to['strings'][$key]['translation']   : null;
			$same = ($f == $t) ? 'YES' : 'NO';
			
			$t_arr = array_map('strtolower', preg_split('/\s+/', $t ? $t : ''));
			$f_arr = array_map('strtolower', preg_split('/\s+/', $f ? $f : ''));

			$n = count(array_diff($t_arr, $f_arr));
			$tot += $n;

			Common\fputcsv($out, array($key, $same, $n, $f, $t));
		}

		fclose($out);

		echo "Words added: $tot\n";
	}

	
}
else if($options['tool'] == 'diffarray')
{
	$from = Pack\extractArray($argv[1]);
	$to   = Pack\extractArray($argv[2]);

	$keys = array_merge(array_keys($from), array_keys($to));

	$out  = basename($argv[1],'.php') . ".diff.csv";

	$f    = fopen($out, 'w');
	Common\fputcsv($f, array("Key", "From", "To", "Same?"));
	$n    = 0;
	foreach($keys as $key)
	{
		$fv = isset($from[$key])?$from[$key]:'';
		$tv = isset($to[$key])?$to[$key]:'';
		if($fv != $tv)
		{
			$n += 1;
		}
		Common\fputcsv($f, array($key, $fv, $tv, $fv == $tv ? 'YES' : 'NO'));
	}
	fclose($f);
	echo "$n differences!\n";
}
else if($options['tool'] == 'tabs2xml')
{
	if(!isset($options['tpl']))
	{
		die("Plese select an xml template!\n");
	}
	if(count($argv) < 2 or !file_exists($file = $argv[1]))
	{
		die("Please select a valid file!\n");
	}

	$class2id = array();
	$tpl = simplexml_load_file($options['tpl']);
	foreach($tpl->entities[0]->tab as $ent)
	{
		$class2id[(string)$ent->class_name] = (string)$ent['id'];
	}


	$pack = Pack\readGZIP($file, '/tabs.php$/');
	
	$xml = new SimpleXMLElement('<entity_tab/>');

	foreach($pack['strings'] as $key => $data)
	{
		$translation = $data['translation'];
		if(mb_strlen($translation, 'utf-8') > 32)
		{
			die("Translation for $key is too long: $translation");
		}
		if(!isset($class2id[$key]))
		{
			die("No ID found for class $key!\n");
		}
		$id  = $class2id[$key];
		$tab = $xml->addChild('tab');
		$tab->addAttribute('id', $id);
		$tab->addAttribute('name', $translation);
	}

	$dom = new DOMDocument();
	$dom->loadXML($xml->asXML());
	$dom->formatOutput = true;
	
	$outfile = basename($file, '.gzip') . '_tab.xml';

	file_put_contents($outfile, $dom->saveXML());

	die("Successfully wrote " . count($pack['strings']) . " tabs to $outfile!\n");
}

