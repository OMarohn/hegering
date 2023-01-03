<?php
ob_start("ob_gzhandler");

error_reporting(E_ERROR);

require_once(dirname(__FILE__).DIRECTORY_SEPARATOR."include/config.php");
require_once(dirname(__FILE__).DIRECTORY_SEPARATOR."include/mime_types_data.php");
require_once(dirname(__FILE__).DIRECTORY_SEPARATOR."include/mime_types.php");
require_once("include/utils.php");

$flags = ENT_COMPAT | ENT_HTML401;
if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
  $flags = ENT_DISALLOWED | ENT_XML1;
}

// --------------------------------------------------------------------------------

function new_file($dir, $file) {
  return preg_replace("/\/$/", "", preg_replace("/\/$/", "", $dir) . "/" . preg_replace("/^\//", "", $file));
}

// --------------------------------------------------------------------------------

function print_keyValue($key, $value, $isFirstProperty) {
  // backwards compatible for names like "xxx\'"
  $value = str_replace("\\'", "\\\\'" , $value);
  $value = str_replace("\\?", "\\\\?" , $value);

  echo $isFirstProperty ? "" : ",";
  echo "\"$key\":\"$value\"";
}

// --------------------------------------------------------------------------------

function createListing($baseDir, $path) {
  global $serviceId;
  global $page_start;
  global $page_end;
  $count = 0;
  echo  "[";

  $parent = new_file($baseDir, $path);
  $dParent = new_file($serviceId, $path);

  $isFirst = True;

  if (is_dir($parent)) {
    if ($dh = opendir($parent)) {
      while (($file = readdir($dh)) !== false) {
        if ($file == "." || $file == "..") {
          continue;
        }

        $count++;

        if ($page_end != 0) { # if the request should be paged
          if ($count <= $page_start) {
            continue;
          }
          if ($page_end < $count) {
            break;
          }
        }

        // (PBT: #5967) mbstring is a non-default extension, we cannot rely on it being activated.
        if (function_exists("mb_convert_encoding")) {
          $fileUTF8 = mb_convert_encoding($file, "UTF-8", "UTF-8, ISO-8859-15");
        } else {
          // best efford to deliver a listing. If encoding problems arise this may still fail.
          $fileUTF8 = $file;
        }

        $child = new_file($parent, $fileUTF8);

        if (strcmp($file, $fileUTF8) != 0) {
          // ensure UTF-8 filename encoding
          rename(new_file($parent, $file), $child);
        }

        $dChild = new_file($path, $fileUTF8);
        $type = is_dir($child) ? "DIR" : "FILE";

        if (!$isFirst) {
          echo ",";
        }

        echo "{";
        print_keyValue("PATH", $dChild, true);
        print_keyValue("TYPE", $type, false);
        print_keyValue("LASTMODIFIED", date("YmdHis" , filemtime($child)), false);

        if ($type == "FILE") {
          $contentType = getContentType($child);
          print_keyValue("CONTENT_LENGTH", filesize($child), false);
          print_keyValue("CONTENT_TYPE", $contentType, false);

          if (preg_match("~^image/(jpeg|png|svg|gif)~",$contentType) === 1) {
            $image_size = getimagesize($child);
            print_keyValue("WIDTH", $image_size[0], false);
            print_keyValue("HEIGHT", $image_size[1], false);
          }
        }

        echo "}\n";
        $isFirst = False;
      }

      closedir($dh);
    }
  }

  echo "]";
}

// --------------------------------------------------------------------------------

$pagenumber = intval($_GET["pn"]); // pn === pagenumber
$pagesize = intval($_GET["ps"]); // ps === pagesize
$page_start = $pagesize * ($pagenumber - 1);
$page_end = $pagesize * $pagenumber;

$data = explode("/", $_SERVER["PATH_INFO"]);
array_shift($data);
$key = array_shift($data);
$serviceId = array_shift($data);
$path = "/" . implode("/", $data);

if (!isset($config["listingkey"]) || $config["listingkey"] == ""
|| $key != $config["listingkey"] || strpos($path, "..") !== FALSE
|| $serviceId != 0){
  header("HTTP/1.1 401 Authorization Required");
  header("content-type: text/plain encoding=\"UTF-8\"");
  echo "401 Authorization Required";
  exit;
}

$mediadb = (strpos($config["mediadb"], "/") != 0 ? "../" : "") . $config["mediadb"];

header("content-type: application/json encoding=\"UTF-8\"");
createListing($mediadb, $path);

// --------------------------------------------------------------------------------

ob_end_flush();
?>
