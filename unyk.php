<?php

date_default_timezone_set('UTC');
$hamming_distance = 25;

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

$dir = new RecursiveDirectoryIterator($input_path);
$ite = new RecursiveIteratorIterator($dir);
$files = new RegexIterator($ite, "/(.*\.jpe?g)|(.*\.png)|(.*\.mov)|(.*\.mp4)/i", RegexIterator::GET_MATCH);
$fileList = array();
require __DIR__ . '/vendor/autoload.php';
$getID3 = new getID3;
use Jenssegers\ImageHash\Implementations\DifferenceHash;
use Jenssegers\ImageHash\ImageHash;
$implementation = new DifferenceHash;
$hasher = new ImageHash($implementation);
$hashfile = $output_path . "/imagehashes.csv";
$logfile = $output_path . "/log.txt";
$num_uniq = 0;
$num_dupe = 0;
$num_sim = 0;
$num_comp = 0;
$num_tot = 0;
$num_ops = 0;

echo "----\n";
echo "Looking for copies...\n";


// Loop through the files and do stuff.
foreach($files as $file) {
	// unset($file_path_name, $picture_exif, $picture_md5, $picture_fileinfo, $picture_ext, $picture_epoch, $picture_year, $picture_month, $picture_day, $picture_filename);

	unset($file_path_name, $file_name, $file_newname, $file_epoch, $file_info, $file_ext, $file_imagehash, $file_year, $file_month, $file_day);
	
	$file_path_name = $file[0];
	$file_name = basename($file_path_name);
	$file_newname = md5_file($file_path_name);
	$file_epoch = filemtime($file_path_name);
	$file_info = $getID3->analyze($file_path_name);
	$file_ext = $file_info['fileformat'];
	$file_imagehash = NULL;

	// Get better creation date time if we know the format of the metadata
	switch ($file_ext) {
		case 'jpg':
			// jpg -> $file_info['jpg']['exif']['FILE']['FileDateTime']
			$file_imagehash = $file_newname = $hasher->hash($file_path_name);
			if (isset($file_info['jpg']['exif']['FILE']['FileDateTime'])) {
				$file_epoch = $file_info['jpg']['exif']['FILE']['FileDateTime'];
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
	$unique_file_name =	"$file_newname.$file_ext";
	$duplicate_file_path = "$duplicate_path/$file_year/$file_month/$file_day";
	$duplicate_file_name = uniqid($file_newname."_", TRUE)."_".$file_name;


	// echo $file_info['filenamepath'].":".$file_info['fileformat'].":".$file_epoch.":".$file_year.":".$file_month.":".$file_day.":".$file_newname."\n";
	
	if (file_exists("$unique_file_path/$unique_file_name")) {
		// Move to dupes location
		if (!file_exists($duplicate_file_path)) {
			mkdir($duplicate_file_path, 0777, TRUE);
		}
		rename($file_path_name, "$duplicate_file_path/$duplicate_file_name");
		file_put_contents($logfile, "Duplicate: $file_path_name --> $duplicate_file_path/$duplicate_file_name\n", FILE_APPEND);
		$num_dupe++;
		echo "D";
	}
	else {
		// Move to uniques location
		if (!file_exists($unique_file_path)) {
			mkdir($unique_file_path, 0777, TRUE);
		}
		rename($file_path_name, "$unique_file_path/$unique_file_name");
		if ($file_imagehash) {
			file_put_contents($hashfile, $file_imagehash.",'$unique_file_path/$unique_file_name'\n", FILE_APPEND);
		}
		file_put_contents($logfile, "Unique: $file_path_name --> $unique_file_path/$unique_file_name\n", FILE_APPEND);
		$num_uniq++;
		echo "U";

	}

}


// Loop through image hashes to extract visually similar, keeping the largest image
$image_hashes = $image_hashes_copy = array_column(array_map('str_getcsv', file($hashfile)), 1, 0);
echo "\n----";
echo "\nComparing similarity...\n";


clearstatcache(TRUE);

foreach ($image_hashes as $image_hash => $image_filepath) {
	unset($image_hashes_copy[$image_hash]);
	foreach ($image_hashes_copy as $image_hash_copy => $image_filepath_copy) {
		$image_filepath = trim($image_filepath, "'");
		$image_filepath_copy = trim($image_filepath_copy, "'");
		$distance = $hasher->compare($image_filepath, $image_filepath_copy);
		$num_comp++;
		if ($distance <= $hamming_distance) {
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
			$small_file_name = $sf."_similar_to_".$bf.".jpg";

			// Move to similar location
			if (!file_exists($small_file_path)) {
				mkdir($small_file_path, 0777, TRUE);
			}
			rename(array_values($small_file)[0], "$small_file_path/$small_file_name");
			file_put_contents($logfile, "Similar $distance: ".array_values($small_file)[0]." --> $small_file_path/$small_file_name\n", FILE_APPEND);
			$num_sim++;
			echo "S";
			unset($image_hashes_copy[$sf]);
			break;

		}
	}
}

$num_tot = $num_uniq + $num_dupe;
$num_ops = $num_tot + $num_sim;
$results = "\n----\nResults:\n $num_uniq uniques\n $num_dupe duplicates\n $num_tot total files\n $num_sim visually similar in $num_comp comparisons\n $num_ops file operations total\n";
file_put_contents($logfile, $results, FILE_APPEND);
echo $results;
echo "Done.\n";


