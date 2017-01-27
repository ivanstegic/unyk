<?php

date_default_timezone_set('UTC');
openlog("unyk", LOG_PID, LOG_SYSLOG);

// Command line use only please.
if (php_sapi_name() != "cli") {
	echo "Please use the command line to execute this script.";
	exit;
}

// At least PHP 5.5
if (version_compare(phpversion(), '5.5.0', '<')) {
    // php version isn't high enough
    echo "Requires at least PHP 5.5.0.\n";
    exit;
}

// Current path
$current_path = __DIR__;

// First argument is the directory of images to check
if (isset($argv[1]) && file_exists($argv[1])) {
	$input_path = realpath($argv[1]);
}
else {
	echo "Please specify a source directory.";
	exit;
}

// Second argument is the location of output
if (isset($argv[2]) && file_exists($argv[2])) {
	$output_path = realpath($argv[2]);
}
else {
	$output_path = $current_path . "/output";
	if (!file_exists($output_path)) {
		if (!mkdir($output_path, 0777, TRUE)) {
			echo "Unable to create $output_path";
			exit;
		}
		else {
			echo "Created $output_path\n";
		}		
	}
}

// Build unique, duplicate and similar folders
$unique_path = $output_path . "/unique";
if (!file_exists($unique_path)) {
	if (!mkdir($unique_path, 0777, TRUE)) {
		echo "Unable to create $unique_path";
		exit;
	}
	else {
		echo "Created $unique_path\n";
	}		
}
$duplicate_path = $output_path . "/duplicate";
if (!file_exists($duplicate_path)) {
	if (!mkdir($duplicate_path, 0777, TRUE)) {
		echo "Unable to create $duplicate_path";
		exit;
	}		
	else {
		echo "Created $duplicate_path\n";
	}		
}
$similar_path = $output_path . "/similar";
if (!file_exists($similar_path)) {
	if (!mkdir($similar_path, 0777, TRUE)) {
		echo "Unable to create $similar_path";
		exit;
	}		
	else {
		echo "Created $similar_path\n";
	}		
}

// Autoloader
require __DIR__ . '/vendor/autoload.php';

// Image comparison

// use Jenssegers\ImageHash\Implementations\DifferenceHash;
// use Jenssegers\ImageHash\ImageHash;
// $implementation = new DifferenceHash;
// $hasher = new ImageHash($implementation);

// use Jenssegers\ImageHash\Implementations\AverageHash;
// use Jenssegers\ImageHash\ImageHash;
// $implementation = new AverageHash;
// $hasher = new ImageHash($implementation);

use Jenssegers\ImageHash\Implementations\PerceptualHash;
use Jenssegers\ImageHash\ImageHash;
$implementation = new PerceptualHash;
$hasher = new ImageHash($implementation);

// Some variables
$getID3 = new getID3;
$num_uniq = 0;
$num_dupe = 0;
$num_sim = 0;
$num_comp = 0;
$num_tot = 0;
$num_ops = 0;
$logfile = $output_path . "/log.txt";

// Media files
$dir = new RecursiveDirectoryIterator($input_path);
$ite = new RecursiveIteratorIterator($dir);
$files = new RegexIterator($ite, "/(.*\.jpe?g)|(.*\.png)|(.*\.mov)|(.*\.mp4)/i", RegexIterator::GET_MATCH);

// Feedback bbzzzz
echo "----\n";
echo "Looking for copies...\n";
syslog(LOG_ERR, "Starting unyk...");


// Loop through the media files and do stuff.
foreach($files as $file) {

	unset($file_path_name, $file_name, $file_newname, $file_epoch, $file_info, $file_ext, $file_width, $file_imagehash, $file_year, $file_month, $file_day);
	
	$file_path_name = $file[0];
	$file_name = basename($file_path_name);
	$file_newname = md5_file($file_path_name);
	$file_epoch = filemtime($file_path_name);
	$file_info = $getID3->analyze($file_path_name);
	$file_ext = $file_info['fileformat'];
	$file_imagehash = NULL;
	//echo $file_path_name;

	// Get better creation date time if we know the format of the metadata
	switch ($file_ext) {
		case 'jpg':
			// jpg -> $file_info['jpg']['exif']['FILE']['FileDateTime']
			$file_width = NULL;
			if (isset($file_info['jpg']['exif']['COMPUTED']['Width'])) {
				$file_width = $file_info['jpg']['exif']['COMPUTED']['Width'];
			}
			// if ($file_width>9999 || $file_width==0) {
			// 	echo "\nSkip width $file_width: $file_path_name\n";
			// 	continue;
			// }
			if (isset($file_info['jpg']['exif']['FILE']['FileDateTime'])) {
				$file_epoch = $file_info['jpg']['exif']['FILE']['FileDateTime'];
			}
			$file_imagehash = $hasher->hash($file_path_name);				
			if ($file_imagehash) {
				$file_newname = "im_$file_imagehash";
			}
			break;
		
		case 'mp4':
			// mov,mp4 -> $file_info['quicktime']['moov']['subatoms'][0]['creation_time']
			//	or
			// mov,mp4 -> $file_info['quicktime']['moov']['subatoms'][0]['creation_time_unix']
			if (isset($file_info['quicktime']['moov']['subatoms'][0]['creation_time'])) {
				$file_epoch = $file_info['quicktime']['moov']['subatoms'][0]['creation_time'];
			}
			if ($file_epoch < 0 || $file_epoch > time()) {
				if (isset($file_info['quicktime']['moov']['subatoms'][0]['creation_time_unix'])) {
					$file_epoch = $file_info['quicktime']['moov']['subatoms'][0]['creation_time_unix'];
				}
			}
			break;

		default:
			break;
	}

	// Extract human readable info please.
	$file_year = date("Y", $file_epoch);
	$file_month = date("m", $file_epoch);
	$file_day = date("d", $file_epoch);

	// Unique and duplicate file names
	$unique_file_path = "$unique_path/$file_year/$file_month/$file_day";
	$unique_file_name = "$file_newname.$file_ext";
	$duplicate_file_path = "$duplicate_path/$file_year/$file_month/$file_day";
	$duplicate_file_name = uniqid($file_newname."_", TRUE)."_".$file_name;


	// echo $file_info['filenamepath'].":".$file_info['fileformat'].":".$file_epoch.":".$file_year.":".$file_month.":".$file_day.":".$file_newname."\n";
	
	if (file_exists("$unique_file_path/$unique_file_name")) {
		// Move to dupes location
		if (!file_exists($duplicate_file_path)) {
			mkdir($duplicate_file_path, 0777, TRUE);
		}
		rename($file_path_name, "$duplicate_file_path/$duplicate_file_name");
		syslog(LOG_ERR, "Duplicate: $file_path_name --> $duplicate_file_path/$duplicate_file_name");
		$num_dupe++;
		echo "D";
	}
	else {
		// Move to uniques location
		if (!file_exists($unique_file_path)) {
			mkdir($unique_file_path, 0777, TRUE);
		}
		rename($file_path_name, "$unique_file_path/$unique_file_name");
		syslog(LOG_ERR, "Unique: $file_path_name --> $unique_file_path/$unique_file_name");
		$num_uniq++;
		echo "U";

	}

}



// How similar do they have to be?
$hamming_distance = 5;

// JPGs in unique location
$jpgdir = new RecursiveDirectoryIterator($unique_path);
$jpgite = new RecursiveIteratorIterator($jpgdir);
$jpgs = new RegexIterator($jpgite, "/(.*\.jpe?g)/i", RegexIterator::GET_MATCH);

echo "\n----";
echo "\nComputing image hashes...\n";

// Arrays
$image_hashes = array();
$image_hashes_copy = array();
$ih=0;

// Compute image hashes
foreach ($jpgs as $jpg) {
	# code...
	unset($file_imagehash, $fj);
	$fj = $jpg[0];
	$pi = pathinfo($fj);
	$ih++;

	syslog(LOG_ERR, "Prepping $fj");

	if (substr($pi['filename'], 0, 3) == 'im_') {
		$file_imagehash = substr($pi['filename'], 3);
		$image_hashes[$file_imagehash] = $fj;
		syslog(LOG_ERR, "$file_imagehash for $fj");
	}
	else {

		$fj_info = $getID3->analyze($fj);
		if (isset($fj_info['jpg']['exif']['COMPUTED']['Width'])) {
			$j_width = $fj_info['jpg']['exif']['COMPUTED']['Width'];
		}
		// else {
		// 	echo "0";
		// 	continue;
		// }
		// if ($j_width > 9000) {
		// 	echo "W";
		// 	continue;
		// }
		$file_imagehash = $hasher->hash($fj);
		$fr = $pi['dirname'].'/im_'.$file_imagehash.'.jpg';
		rename($fj, $fr);
		syslog(LOG_ERR, "Renamed $fj --> $fr");
		$image_hashes[$file_imagehash] = $fr;
		syslog(LOG_ERR, "$file_imagehash for $fr");
	}
	echo ".";

}
$image_hashes_copy = $image_hashes;

echo "\n".number_format($ih)." image hashes extracted";

// Arithmetic series formula for num of possible comparisons
$combinations = $ih*($ih-1)*0.5;
$pctupdate = $combinations*0.002;

echo "\n----";
echo "\nComparing ".number_format($combinations)." combinations for similarity...\n";

$i=0;

// Loops through and compares similarity
foreach ($image_hashes as $image_hash => $image_filepath) {
	unset($image_hashes_copy[$image_hash]);
	foreach ($image_hashes_copy as $image_hash_copy => $image_filepath_copy) {
		$i++;
		// $image_filepath = trim($image_filepath, "'");
		// $image_filepath_copy = trim($image_filepath_copy, "'");
		if (!file_exists($image_filepath) || !file_exists($image_filepath_copy)) {
			continue;
		}
		//$distance = $hasher->compare($image_filepath, $image_filepath_copy);
		$distance = $hasher->distance($image_hash, $image_hash_copy);
		$num_comp++;
		if ($distance <= $hamming_distance) {
			unset($small_file, $big_file, $small_file_epoch, $small_file_year, $small_file_month, $small_file_day, $small_file_path, $aksf, $sf, $akbf, $bf, $small_file_name );
			// Move the smaller file
			$filesize_image = filesize($image_filepath);
			$filesize_image_copy = filesize($image_filepath_copy);

			if ($filesize_image > $filesize_image_copy) {
				$small_file[$image_hash_copy] = $image_filepath_copy;
				$big_file[$image_hash] = $image_filepath;
			}
			else {
				$small_file[$image_hash] = $image_filepath;
				$big_file[$image_hash_copy] = $image_filepath_copy;
			}

			$small_file_epoch = filemtime(array_values($small_file)[0]);
			$small_file_year = date("Y", $small_file_epoch);
			$small_file_month = date("m", $small_file_epoch);
			$small_file_day = date("d", $small_file_epoch);
			$small_file_path = "$similar_path/$small_file_year/$small_file_month/$small_file_day";
			$aksf = array_keys($small_file);
			$sf = array_shift($aksf);
			$akbf = array_keys($big_file);
			$bf = array_shift($akbf);
			$small_file_name = $sf."_similar_to_".$bf."_".uniqid().".jpg";

			// Move to similar location
			if (!file_exists($small_file_path)) {
				mkdir($small_file_path, 0777, TRUE);
			}
			rename(array_values($small_file)[0], "$small_file_path/$small_file_name");
			syslog(LOG_ERR, "Similar $distance: ".array_values($small_file)[0]." --> $small_file_path/$small_file_name");
			$num_sim++;
			echo "S";

		}

		// @TODO
		// Compute estimated time remaining here
		if ($i % $pctupdate ==0) {
			$pct = round(100*($i / $combinations), 1);
			echo "\n$pct% complete ";
		}
	}
}

// @TODO
// Compare existence of the same imagehash in different parts of the unique directory


$num_tot = $num_uniq + $num_dupe;
$num_ops = $num_tot + $num_sim;
$results = "Results: $num_uniq uniques, $num_dupe duplicates, $num_tot total files, $num_sim visually similar in $num_comp comparisons, $num_ops file operations total.";
syslog(LOG_ERR, "$results");
echo $results;
echo "Done.\n";


