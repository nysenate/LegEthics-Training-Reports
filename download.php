<?php
require_once 'utils.php';

if (!isset($_GET['file'])) {
  die("File to download must be specified");
}

// Replace all directory separators with underscore.  Paths are not allowed.
$filename = str_replace(DIRECTORY_SEPARATOR, '_', $_GET['file']);

$cfg = get_local_config('legethics.ini');
$report_dir = $cfg['general']['report.dir'];
$full_path = $report_dir.'/'.$filename;

if (!file_exists($full_path)) {
  die("$filename: File not found");
}

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename='.$filename);
header('Content-Length: '.filesize($full_path));
readfile($full_path);
exit(0);
?>
