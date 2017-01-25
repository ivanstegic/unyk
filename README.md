# unyk
Simple, non-destructive image and video re-arranger that intelligently de-duplicates your media.

## Methodology
1. `Unyk` makes a list recursively of all JPG, PNG, MOV and MP4 files from an input folder on your computer.
2. Creates three folders in your output location: `duplicate`, `unique` and `similar`.
3. Processes each file, determining MD5 hash and creation time for each using EXIF or MOV/MP4 Metadata. Falls back to file time if unavailable.
4. Attempts to move the original file to `unique/file_created_year/file_created_month/file_created_day/md5hashoffile.ext` and if there is already a file there and as a result it is unable to, moves it instead to `duplicate/file_created_year/file_created_month/file_created_day/md5hashoffile_unique_id_original_name.ext`. This naturally puts all files that are exactly the same in the `duplicate/` folder tree, while still connecting them to the unique file names.
5. Once completed, a list of all JPGs in the `unique/` folder tree is made.
6. A Perceptual Image Hash is computed for each JPG in the list, and each file is renamed to have the convention `im_xxxxxxxxxxxxxxxx.jpg` where `xxxxxxxxxxxxxxxx` is the computed PerceptualHash.
7. JPGs are compared to one another, and if they are visually similar (hamming distance less than or equal to 5) then the smaller of the similar images is moved to `similar/file_created_year/file_created_month/file_created_day/img_hash_small_similar_to_img_hash_big.jpg`

## Requirements
PHP 5.5 or higher and composer.

## Usage
1. Clone the repo
2. Run `composer install` in the root of the repo
3. Run `php unyk.php input/ output/` in the root where `input` is the path to your source of media, and `output` is an existing folder where you want the media to be sorted into.