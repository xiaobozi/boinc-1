<?php

require_once("docutil.php");
require_once("help_db.php");
require_once("help_funcs.php");

$lang = $_GET["lang"];

function vol_info($vol) {
    $id = $vol->id;
    $x = "<a href=help_vol.php?volid=$id>$vol->name</a>";
    return $x;
}

function vol_modes($vol) {
    $x = "";
    if ($vol->voice_ok && $vol->text_ok) {
        return "Either";
    }
    if ($vol->text_ok) {
        return "Text only";
    }
    if ($vol->voice_ok) {
        return "Voice only";
    }
}

function rating_info($vol) {
    if ($vol->nratings == 0) {
        return "<font size=-2>(no ratings)</font>";
    }
    $x = $vol->rating_sum/$vol->nratings;
    if ($x > 4.5) $img = "stars-5-0.gif";
    else if ($x > 4.0) $img = "stars-4-5.gif";
    else if ($x > 3.5) $img = "stars-4-0.gif";
    else if ($x > 3.0) $img = "stars-3-5.gif";
    else if ($x > 2.5) $img = "stars-3-0.gif";
    else if ($x > 2.0) $img = "stars-2-5.gif";
    else if ($x > 1.5) $img = "stars-2-0.gif";
    else if ($x > 1.0) $img = "stars-1-5.gif";
    else if ($x > 0.5) $img = "stars-1-0.gif";
    else if ($x > 0.0) $img = "stars-0-5.gif";
    else $img = "stars-0-0.gif";
    return "
        <a href=help_ratings.php?volid=$vol->id>
        <img border=0 src=images/help/$img>
        <font size=-2>
        $vol->nratings ratings</font></a>
    ";
}

function info($vol) {
    $x = "<font size=-2> Country: $vol->country\n";
    if ($vol->availability) {
        $x .= "<br>Usual hours: $vol->availability";
    }
    if ($vol->specialities) {
        $x .= "<br>Specialties: $vol->specialties";
    }
    if ($vol->projects) {
        $x .= "<br>Projects: $vol->projects";
    }
    $x .= "</font>";
    return $x;
}

function show_vol($vol) {
    $status = skype_status($vol->skypeid);
    $image = button_image($status);
    list_item_array(array(
        vol_info($vol),
        status_string($status),
        vol_modes($vol),
        info($vol),
        rating_info($vol)
    ));
}

function show_vols($vols) {
    list_start("border=0");
    list_heading_array(array(
        "Volunteer name<br><font size=2>click to contact</font>",
        "Status",
        "Voice/Text",
        "Info",
        "Feedback <br><font size=-2>Click to see comments</font>",
    ));
    foreach ($vols as $vol) {
        show_vol($vol);
    }
    list_end();
}

page_head("Online Help in $lang");
$vols = get_vols($lang);
show_vols($vols);
page_tail();
?>
