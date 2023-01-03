<?php

error_reporting(E_ERROR);

require_once(dirname(__FILE__).DIRECTORY_SEPARATOR."include/config.php");
require_once(dirname(__FILE__).DIRECTORY_SEPARATOR."include/mime_types_data.php");
require_once(dirname(__FILE__).DIRECTORY_SEPARATOR."include/mime_types.php");
require_once("include/utils.php");

$flags = ENT_COMPAT | ENT_HTML401;
if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
    $flags = ENT_DISALLOWED | ENT_XML1;
}

function new_file($dir, $file) {
    return preg_replace("/\/$/", "", preg_replace("/\/$/", "", $dir) . "/" . preg_replace("/^\//", "", $file));
}

function getDefaultThumbnailName($basename) {
   return preg_replace("/\.bmp$/", ".png", $basename);
}

function createListing($baseDir, $path, $jsonmode) {
    global $serviceId;
    echo $jsonmode ? "[" : "<DIR>";

    $parent = new_file($baseDir, $path);
    $dParent = new_file($serviceId, $path);

    $isFirst = True;

    if (is_dir($parent)) {
        if ($dh = opendir($parent)) {
            while (($file = readdir($dh)) !== false) {
                if ($file == "." || $file == "..") {
                    continue;
                }
                /* (PBT: #5967) mbstring is a non-default extension,
                 * we cannot rely on it being activated.
                 **/
                if (function_exists("mb_convert_encoding")) {
                    $fileUTF8 = mb_convert_encoding($file, "UTF-8", "UTF-8, ISO-8859-15");
                } else {
                /* best efford to deliver a listing. If encoding problems arise this may still fail. */
                    $fileUTF8 = $file;
                }
                $child = new_file($parent, $fileUTF8);
                if (strcmp($file, $fileUTF8) != 0) {
                    /* ensure UTF-8 filename encoding */
                    rename(new_file($parent, $file), $child);
                }
                $dChild = new_file($path, $fileUTF8);
                $type = is_dir($child) ? "DIR" : "CHILD";


                if (!$isFirst) {
                  echo $jsonmode ? "," : "";
                }
                echo $jsonmode ? "{" : "<$type";
                printFirstKeyValue("ID", $dChild, $jsonmode);
                printKeyValue("PATH", $dChild, $jsonmode);
                printKeyValue("PARENT_ID", $dParent, $jsonmode);
                preg_match ( '/[^\/]*$/', $dChild, $matches);
                printKeyValue("BASENAME", $matches[0], $jsonmode);
                printKeyValue("TYPE", ($type == "DIR" ? "DIR" : "FILE"), $jsonmode);
                printKeyValue("LASTMODIFIED", date("YmdHis" , filemtime($child)), $jsonmode);

                if ($type == "CHILD") {
                    $contentType = getContentType($child);
                    printKeyValue("PUBLIC_URL", "/.cm4all/iproc.php" . urlenc($dChild), $jsonmode);
                    printKeyValue("CONTENT_LENGTH", filesize($child), $jsonmode);
                    printKeyValue("CONTENT_TYPE", $contentType, $jsonmode);
                    if (preg_match("~^image/(jpeg|png|gif)~",$contentType) === 1) {
                        printKeyValue("THUMBNAIL_URL", "/.cm4all/iproc.php" . urlenc($dChild) . "/center_80_80_FFFFFF_80_80/" . getDefaultThumbnailName(urlenc(basename($dChild))), $jsonmode);
                        $image_size =  getimagesize($child);
                        printKeyValue("WIDTH", $image_size[0], $jsonmode);
                        printKeyValue("HEIGHT", $image_size[1], $jsonmode);
                    }else if ($contentType == "application/x-shockwave-flash"){
                        printKeyValue("THUMBNAIL_URL", "/.cm4all/vproc.php" . urlenc($dChild), $jsonmode);
                        $image_size = getflashsize($child);
                        if ($image_size) {
                            printKeyValue("WIDTH", $image_size[0], $jsonmode);
                            printKeyValue("HEIGHT", $image_size[1], $jsonmode);
                        }
                    }else if (strpos($contentType, "video/") === 0){
                        printKeyValue("THUMBNAIL_URL", "/.cm4all/vproc.php" . urlenc($dChild), $jsonmode);
                        printKeyValue("WIDTH", "", $jsonmode);
                        printKeyValue("HEIGHT", "", $jsonmode);
                    }else{
                        printKeyValue("THUMBNAIL_URL", "/.cm4all/widgetres.php/cm4all.com.widgets.VFS/res/file.png", $jsonmode);
                    }
                }

                echo $jsonmode ? "}" : "/>";
                $isFirst = False;
            }
            closedir($dh);
        }
    }
    echo $jsonmode ? "]" : "</DIR>";
}

function printFirstKeyValue($key, $value, $jsonmode) {
  printKV($key, $value, $jsonmode, True);
}

function printKeyValue($key, $value, $jsonmode) {
  printKV($key, $value, $jsonmode, False);
}

function printKV($key, $value, $jsonmode, $isFirstProperty) {
  if ($jsonmode) {
    // backwards compatible for names like "xxx\'"
    $value = str_replace("\\'", "\\\\'" , $value);
    $value = str_replace("\\?", "\\\\?" , $value);
    echo $isFirstProperty ? "" : ",";

    echo " \"$key\" : \"$value\"";
  } else {
    // since this is xml output we need to encode chars like &,< and >
    $value = htmlspecialchars($value, $flags, "UTF-8");
    echo " $key=\"$value\"";
  }
}



$jsonmode = isset($_GET["json"]);
if ($jsonmode) {
  header("content-type: application/json encoding=\"UTF-8\"");
} else {
  header("content-type: text/xml encoding=\"UTF-8\"");
}

$data = explode("/", $_SERVER["PATH_INFO"]);
array_shift($data);
$key = array_shift($data);
$serviceId = array_shift($data);
$path = "/" . implode("/", $data);

if (!isset($config["listingkey"]) || $config["listingkey"] == ""
|| $key != $config["listingkey"] || strpos($path, "..") !== FALSE
|| $serviceId != 0){
    header("HTTP/1.1 401 Authorization Required");
    echo "<ERROR>401 Authorization Required</ERROR>";
    exit;
}

$mediadb = (strpos($config["mediadb"], "/") != 0 ? "../" : "") . $config["mediadb"];

if (!$jsonmode) {
  echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
}
createListing($mediadb, $path, $jsonmode);
