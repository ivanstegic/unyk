<?php


// Command line use only please.
if (php_sapi_name() != "cli") {
	echo "Please use the command line to execute this script.";
	exit;
}

// Current path
$current_path = __DIR__;

// First argument is the directory of images to check
if (isset($argv[1]) && file_exists($argv[1])) {
	$current_path = realpath($argv[1]);
}

// Second argument is the location of unique images
if (isset($argv[2]) && file_exists($argv[2])) {
	$unique_path = realpath($argv[2]);
}
else {
	$unique_path = $current_path . "/../uniques";
	if (!file_exists($unique_path)) {
		if (!mkdir($unique_path, 0777, TRUE)) {
			echo "Unable to create $unique_path";
			exit;
		}
		else {
			echo "Created $unique_path\n";
		}		
	}
}

// Third argument is the location of duplicate images
if (isset($argv[3]) && file_exists($argv[3])) {
	$duplicates_path = realpath($argv[3]);
}
else {
	$duplicates_path = $current_path . "/../duplicates";
	if (!file_exists($duplicates_path)) {
		if (!mkdir($duplicates_path, 0777, TRUE)) {
			echo "Unable to create $duplicates_path";
			exit;
		}		
		else {
			echo "Created $duplicates_path\n";
		}		
	}
}

$dir = new RecursiveDirectoryIterator($current_path);
$ite = new RecursiveIteratorIterator($dir);
$files = new RegexIterator($ite, "/(.*\.jpe?g)|(.*\.png)|(.*\.mov)|(.*\.mp4)/i", RegexIterator::GET_MATCH);
$fileList = array();
require_once('getid3/getid3.php');
$getID3 = new getID3;

// Loop through the files and do stuff.
foreach($files as $file) {
	// unset($file_path_name, $picture_exif, $picture_md5, $picture_fileinfo, $picture_ext, $picture_epoch, $picture_year, $picture_month, $picture_day, $picture_filename);

	unset($file_path_name, $file_name, $file_md5, $file_epoch, $file_info, $file_ext, $file_year, $file_month, $file_day);
	
	$file_path_name = $file[0];
	$file_name = basename($file_path_name);
	$file_md5 = md5_file($file_path_name);
	$file_epoch = filemtime($file_path_name);
	$file_info = $getID3->analyze($file_path_name);
	$file_ext = $file_info['fileformat'];

	// Get better creation date time if we know the format of the metadata
	switch ($file_ext) {
		case 'jpg':
			// jpg -> $file_info['jpg']['exif']['FILE']['FileDateTime']
			$file_epoch = $file_info['jpg']['exif']['FILE']['FileDateTime'];
			break;
		
		case 'mp4':
			// mov,mp4 -> $file_info['quicktime']['moov']['subatoms'][0]['creation_time']
			//	or
			// mov,mp4 -> $file_info['quicktime']['moov']['subatoms'][0]['creation_time_unix']
			$file_epoch = $file_info['quicktime']['moov']['subatoms'][0]['creation_time'];
			if ($file_epoch < 0 || $file_epoch > time()) {
				$file_epoch = $file_info['quicktime']['moov']['subatoms'][0]['creation_time_unix'];
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
	$unique_file_name =	"$file_md5.$file_ext";
	$duplicate_file_path = "$duplicates_path/$file_year/$file_month/$file_day";
	$duplicate_file_name = uniqid($file_md5."_", TRUE)."_".$file_name;


	// echo $file_info['filenamepath'].":".$file_info['fileformat'].":".$file_epoch.":".$file_year.":".$file_month.":".$file_day.":".$file_md5."\n";
	
	if (file_exists("$unique_file_path/$unique_file_name")) {
		// Move to dupes location
		if (!file_exists($duplicate_file_path)) {
			mkdir($duplicate_file_path, 0777, TRUE);
		}
		rename($file_path_name, "$duplicate_file_path/$duplicate_file_name");
		echo "D $file_path_name --> $duplicate_file_path/$duplicate_file_name\n";
	}
	else {
		// Move to uniques location
		if (!file_exists($unique_file_path)) {
			mkdir($unique_file_path, 0777, TRUE);
		}
		rename($file_path_name, "$unique_file_path/$unique_file_name");
		echo "U $file_path_name --> $unique_file_path/$unique_file_name\n";
	}



}
