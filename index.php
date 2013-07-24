<?php
session_start();

/**
 * start: Configuration
 */

// Max width and height in pixels of thumbnails.
$max = 256;

// Thumbnail quality in percentage. Thumbnails are images
// copied from the small images, so they have already been
// resized once and downgraded in quality. Setting to 100
// maintains the quality of the small image size.
$thumbQuality = 100;

// Small lightbox image size.
$maxsmall = 1024;

// Small lightbox image quality in percentage.
$smallQuality = 75;

// Max height in pixels of displayed images. All images displayed
// will be of this height.
$maxh = 192;

// Use lightbox.
$useLightbox = true;

// Path to mogrify executable.
$mogrify = "/usr/bin/mogrify";

/**
 * end: Configuration
 */

if (!file_exists("images")) {
    mkdir("images");
}
$imgdir = "images/photos";
if (!file_exists($imgdir)) {
    mkdir($imgdir);
}
$tmpzipdir = "images/tmpzip";
if (!file_exists($tmpzipdir)) {
    mkdir($tmpzipdir);
}

// Dependency check
if ($mogrify == "") {
    die("You must set \$mogrify to your executable.");
}
if (!file_exists($mogrify)) {
    die("\$mogrify does not exist.");
}
if (!is_executable($mogrify)) {
    die("\$mogrify({$mogrify}) is not executable.");
}

function mogrify($img, $size, $quality) {
    exec("{$GLOBALS['mogrify']} -resize {$size}x{$size} \"$img\"");
    exec("{$GLOBALS['mogrify']} -quality $quality \"$img\"");
}

function getmicrotime() {
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}

function glob_r($dir, $logfile) {
    $f = glob("$dir/*");
    if (is_array($f) && count($f) > 0) {
        foreach ($f as $k=>$file) {
            if (is_dir($file)) {
                glob_r($file, $logfile);
            } else {
                if (preg_match("/\.(png|gif|jpg|jpeg)$/i", $file)) {
                    file_put_contents($logfile, "$file\n", FILE_APPEND);
                }
            }
        }
    }
}

if ($_GET['action'] == "upload") {
    $imghtml = "<div class='failure'>Failure</div>";
    if (is_array($_FILES) && count($_FILES) > 0) {
        foreach($_FILES['newfiles']['name'] as $key => $value) {
            $filename = $_FILES['newfiles']['name'][$key];
            $name = $_FILES['newfiles']['name'][$key];
            $newname = date("U") . getmicrotime() . $_FILES['newfiles']['name'][$key];
            $type = $_FILES['newfiles']['type'][$key];
            $tmp_name = $_FILES['newfiles']['tmp_name'][$key];
            $error = $_FILES['newfiles']['error'][$key];
            $size = $_FILES['newfiles']['size'][$key];

            if ($error > 0) {
                continue;
            }

            if (preg_match("/\.zip$/i", $name)) {
                $imghtml = "<div class='success'>Success</div>";
                mkdir($tmpzipdir . "/" . $newname);
                move_uploaded_file($tmp_name, $tmpzipdir . "/" . $newname . "/" . $newname);
                // do work here on the zip file to move into $imgdir
                $curdir = getcwd();
                chdir($tmpzipdir . "/" . $newname);
                exec("unzip \"$newname\""); 
                unset($a_files);

                $logfile = $newname . ".list";
                file_put_contents($logfile, "");
                glob_r(".", $logfile);
                $a_list = file($logfile);
                chdir($curdir);

                foreach ($a_list as $k=>$img) {
                    $directory = trim(preg_replace("/^(.*)\/.*$/", "\${1}", $img));
                    $thefile = trim(preg_replace("/^.*\/(.*)$/", "\${1}", $img));
                    $newfile = date("U") . getmicrotime() . $thefile;
                    $oldfilepath = trim($img);
                    $newfilepath = trim($imgdir . "/" . $newfile);
                    $newfilepaththumb = trim($imgdir . "/thumb_" . $newfile);
                    copy($tmpzipdir . "/" . $newname . "/" . $oldfilepath, $newfilepath);
                    copy($tmpzipdir . "/" . $newname . "/" . $oldfilepath, $newfilepathsmall);
                    mogrify($newfilepathsmall, $maxsmall, $smallQuality);
                    copy($newfilepathsmall, $newfilepaththumb);
                    mogrify($newfilepaththumb, $max, $thumbQuality);
                }

            } else if (preg_match("/\.(png|gif|jpg|jpeg)$/i", $name)) {
                $imghtml = "<div class='success'>Success</div>";
                move_uploaded_file($tmp_name, $imgdir . "/" . $newname);
                copy($imgdir . "/" . $newname, $imgdir . "/small_" . $newname);
                mogrify($imgdir . "/small_" . $newname, $maxsmall, $smallQuality);
                copy($imgdir . "/small_" . $newname, $imgdir . "/thumb_" . $newname);
                mogrify($imgdir . "/thumb_" . $newname, $max, $thumbQuality);
            }
        }
    }

    $_SESSION['msg'] = $imghtml;
    header("Location:{$_SERVER['PHP_SELF']}");
    exit();
}

$imghtml = "";
$curdir = getcwd();
chdir($imgdir);
$imgstmp = glob("thumb_*.{png,PNG,gif,GIF,jpg,JPG,jpeg,JPEG}", GLOB_BRACE);
natcasesort($imgstmp);
$imgs = array_reverse($imgstmp);
chdir($curdir);
if (is_array($imgs) && count($imgs) > 0) {
    $lightbox = "";
    if ($useLightbox) {
        $lightbox = " data-lightbox=\"frenchlick2013\"";
    }
    foreach ($imgs as $k=>$v) {
        $realimg = preg_replace("/^thumb_/", "", $v);
        $size = getimagesize("{$imgdir}/{$v}");
        $w = $size[0];
        $h = $size[1];
        $newh = $maxh;
        $neww = floor(($maxh * $w) / $h);
        $imghtml .= "<div class=\"imgcont\"><a target=\"_blank\" href=\"{$imgdir}/small_{$realimg}\"{$lightbox}><img class=\"lazy\" src=\"images/white.png\" data-original=\"{$imgdir}/{$v}\" alt=\"{$imgdir}/{$v}\" width=\"{$neww}\" height=\"{$newh}\" /></a></div> ";
    }
}

if (isset($_SESSION) && $_SESSION != "") {
    $msg = $_SESSION['msg'];
    unset($_SESSION['msg']);
}

$html = <<<eof
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8" />
        <title>Simple Image Share</title>
        <script type="text/javascript" src="js/lightbox/js/jquery-1.10.2.min.js"></script>
        <script type="text/javascript" src="js/lightbox/js/lightbox-2.6.min.js"></script>
        <script type="text/javascript" src="js/jquery.lazyload.min.js"></script>
        <link type="text/css" rel="stylesheet" href="js/lightbox/css/lightbox.css" />
        <style type="text/css">
            body {
                font-size:100%;
                font-family:sans-serif;
                margin:0px;
                padding:0px;
            }

            #container {
                margin:16px;
                padding:16px;
            }

            .imgcont a {
                float:left;
                margin-right:4px;
                display:relative;
            }

            .fade-in {
                /*
                opacity:0.5;
                -webkit-transition:opacity 0.2s ease-out;
                -moz-transition:opacity 0.2s ease-out;
                -o-transition:opacity 0.2s ease-out;
                transition:opacity 0.2s ease-out;
                */
            }

            .fade-in:hover {
                /*
                opacity:1;
                -webkit-transition:opacity 0.8s ease-in;
                -moz-transition:opacity 0.8s ease-in;
                -o-transition:opacity 0.8s ease-in;
                transition:opacity 0.8s ease-in;
                */
            }

            .clear {
                margin:0px;
                padding:0px;
                width:0px;
                height:0px;
                visibility:hidden;
                clear:both;
            }

            h1 {
                font-size:1.6em;
                margin:0.5em;
            }

            h2 {
                font-size:1.5em;
                margin:0.5em;
            }
        
            h3 {
                font-size:1.4em;
                margin:0.5em;
            }

            h4 {
                font-size:1.3em;
                margin:0.5em;
            }

            h5 {
                font-size:1.2em;
                margin:0.5em;
            }
        
            h6 {
                font-size:1.1em;
                margin:0.5em;
            }

            fieldset {
                margin:0;
                padding:0;
                border:0;
            }

            legend {
                margin:0;
                padding:0;
                font-weight:bold;
            }

            img {
                border:1px solid #dfdfdf;
                padding:1px;
            }

            .success {
                margin:0px 0px 8px 0px;
                padding:8px;
                background-color:#D4C45A;
                color:#404040;
                border-radius:1px;
                -moz-border-radius:1px;
                -webkit-border-radius:1px;
                width:25%;
                text-align:center;
                font-weight:bold;
            }

            .failure {
                margin:0px 0px 8px 0px;
                padding:8px;
                background-color:red;
                color:white;
                border-radius:1px;
                -moz-border-radius:1px;
                -webkit-border-radius:1px;
                width:25%;
                text-align:center;
                font-weight:bold;
            }
        </style>
        <script type="text/javascript">
            $(document).ready(function(){
                $(".lazy").lazyload();
                $(document).on("click", ".afile", function() {
                    $(this).after('<br /><input class="afile" type="file" name="newfiles[]" />');
                });
            });
        </script>
    </head>
    <body>
        <div id="container">
            <div id="formy">
                {$msg}
                <form id="formAttachments" action="{$_SERVER['PHP_SELF']}?action=upload" method="post" enctype="multipart/form-data">
                    <fieldset>
                        <legend>Choose an image or zip of images and click Submit</legend>
                        <input type="hidden" name="MAX_FILE_SIZE" value="2000000000">
                        <input class="afile" type="file" name="newfiles[]" />
                        <input class="button" type="submit" value="Submit"  />
                    </fieldset>
                </form>
                <br /><br />
                {$imghtml}
                <div class="clear"></div>
            </div>
        </div><!--div#container-->
    </body>
</html>
eof;

print($html);
