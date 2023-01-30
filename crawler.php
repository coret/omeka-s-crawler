#!/usr/bin/php
<?php

require_once realpath(__DIR__.'/..')."/vendor/autoload.php";

# Change to base URL of your Omeka S instance

$omeka_base='https://www.goudatijdmachine.nl/data';

if (!file_exists("nt/")) { mkdir("nt"); }
if (!file_exists("hashes/")) { mkdir("hashes"); }

$resources_seen=array();
$resources_todo=array();

$next=$omeka_base.'/api/items?per_page=100&sort_by=id&sort_order=asc&page=1';
while (!empty($next)) {
	$next=get_content_and_next($next);
}

while (!empty($resources_todo)) {
	$resource = array_shift($resources_todo);
	if (!isset($resources_seen[$resource])) {
		get_content($resource);
		$resources_seen[$resource]=1;
	}
}

function get_content($url) {

	error_log($url);

	list($headers,$body)=get_headers_body($url);

	if (preg_match("/HTTP\/2 404/",$headers)) {
		error_log("ERROR: 404 on $url");
	} elseif (preg_match("/HTTP\/2 500/",$headers)) {
		error_log("ERROR: 500 on $url");
		exit;
	} else {
		process_content($body);
	}
}

function get_content_and_next($url) {

	list($headers,$body)=get_headers_body($url);

	if (preg_match("/HTTP\/2 404/",$headers)) {
		error_log("ERROR: 404 on $url");
	} elseif (preg_match("/HTTP\/2 403/",$headers)) {
		error_log("ERROR: 403 on $url");
	} elseif (preg_match("/HTTP\/2 500/",$headers)) {
		error_log("ERROR: 500 on $url");
		exit;
	} else {
		process_contents($body);
		return get_next($headers);
	}
}

function process_jsonld($json) {
	global $resources_todo,$resources_seen, $omeka_base;

	if (isset($json["@id"])) {
		$id=$json["@id"];
		$resources_seen[$id]=1;
		
		$body=json_encode($json);
		$body=preg_replace('#\\\/#',"/",$body);
		preg_match_all('#'.$omeka_base.''\/[a-z_\-\/]+[0-9]+#',$body,$uris);
		
		foreach($uris[0] as $uri) {
			if (!isset($resources_seen[$uri])) {
				$resources_todo[]=$uri;
			}
		}
		
		#
		# de URI's van de resources worden gebaseerd op ARK (niet Omeka ID) als het kan
		#
		if (isset($json['dcterms:identifier'])) {
			$ark="https://n2t.net/".$json['dcterms:identifier'][0]['@value'];
		}

		if (isset($ark)) {
			$sameAs=array();;
			$sameAs['@id']=$id;
			$sameAs['http://www.w3.org/2002/07/owl#sameAs']=array('@id'=>$ark);
			$json['@id']=$ark;
			save_if_changed($ark,'['.json_encode($json).','.json_encode($sameAs).']');
		} else {
			save_if_changed($id,$body);
		}
	}
}

function save_converted_if_changed($uri,$jsonld_string) {
	$hash_of_uri=md5($uri);
	$hash_of_jsonld_string=md5($jsonld_string);	
			
	if (file_exists("hashes/$hash_of_uri.hash")) {
		$stored_hash_of_jsonld_string=file_get_contents("hashes/$hash_of_uri.hash");
		if ($stored_hash_of_jsonld_string==$hash_of_jsonld_string) {
			return;
		}
	}

	file_put_contents("nt/$hash_of_uri.nt",convert_jsonld_to_ntriples($uri,$jsonld_string));
	file_put_contents("hashes/$hash_of_uri.hash",$hash_of_jsonld_string);
}

function convert_jsonld_to_ntriples($uri,$jsonld_string) {
	$graph = new \EasyRdf\Graph($uri);
	$graph->parse($jsonld_string, "jsonld", $uri);
	return $graph->serialise("nt");
}

function process_content($jsonld_string) {
	$json=json_decode($jsonld_string,true);
	process_jsonld($json);
}

function process_contents($jsonld_string) {
	$json_array=json_decode($jsonld_string,true);
	foreach($json_array as $json) {
		process_jsonld($json);
	}
}

function get_next($headers) {
	if (preg_match("/\<([^\>\;]+)\>\; rel\=\"next\"/",$headers,$matches)) {
		return $matches[1];
	}
	return '';
}	
	
function get_headers_body($url) {
	error_log("INFO: Fetching $url");
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, true);

	$response = curl_exec($ch);
	if (curl_errno($ch)) {
		echo 'Error:' . curl_error($ch);
		exit();
	}

	$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
	$headers = substr($response, 0, $header_size);
	$body = substr($response, $header_size);

	curl_close($ch);

	return array($headers,$body);
}