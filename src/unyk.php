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
$files = new RegexIterator($ite, "/.*\.jpe?g/i", RegexIterator::GET_MATCH);
$fileList = array();

$total = 0;
$start = microtime();

foreach($files as $file) {
	unset($file_path_name, $picture_exif, $picture_md5, $picture_fileinfo, $picture_ext, $picture_epoch, $picture_year, $picture_month, $picture_day, $picture_filename);
	$file_path_name = $file[0];
	$picture_exif = exif_read_data($file_path_name);
	$picture_md5 = md5_file($file_path_name);
	$picture_fileinfo = pathinfo($file_path_name);
	$picture_ext = $picture_fileinfo['extension'];
	$picture_epoch = isset($picture_exif['FileDateTime']) ? $picture_exif['FileDateTime'] : NULL;
	$picture_year = date("Y", $picture_epoch);
	$picture_month = date("m", $picture_epoch);
	$picture_day = date("d", $picture_epoch);
	$picture_filename = isset($picture_exif['FileName']) ? $picture_exif['FileName'] : NULL;
	// Unique file check
	$unique_file_path = "$unique_path/$picture_year/$picture_month/$picture_day";
	$unique_file_name =	"$picture_md5.$picture_ext";
	$duplicate_file_path = "$duplicates_path/$picture_year/$picture_month/$picture_day";
	$duplicate_file_name = uniqid($picture_md5."_", TRUE)."_".$picture_filename;

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

print microtime() - $start;