# unyk
Simple PHP deduplicator for media files:
* Supports JPG, PNG, MOV, MP4 file types
* First pass use of MD5 hash to check for identical files
* Does not delete files
* Rearranges location of files by creation time, and duplicate status
* Compares all unique files using visual hashes to determine similar images