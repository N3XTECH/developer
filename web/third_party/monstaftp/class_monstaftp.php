<?php

//##############################################
// MONSTA FTP v1.4.3 by MONSTA APPS
//##############################################
//
// Monsta FTP is proud to be open source.
//
// Please consider a donation and support this product's ongoing
// development: http://www.monstaapps.com/donations/
//
//##############################################
// COPYRIGHT NOTICE
//##############################################
//
// Copyright 2013 Internet Services Group Limited of New Zealand
//
// Monsta FTP is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// any later version.
//
// Monsta FTP is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// A copy of the GNU General Public License can be viewed at:
// < http://www.gnu.org/licenses/ >
//
//##############################################
// SUPPORT, BUG REPORTS, FEATURE REQUESTS
//##############################################
//
// Please visit http://www.monstaftp.com/support/
//
//##############################################
// INSTALL NOTES **IMPORTANT**
//##############################################
//
// 1. While this application is able to connect to FTP servers on both
//	  Windows and Linux, this script must run on a Linux server with PHP.
// 2. The server running this script must allow external FTP connections
//	  if you intend to allow connection to external servers.
// 3. The script can be uploaded anywhere on your website, and you can
//	  rename index.php to any name you prefer.
// 4. Please check the configurable variables below before running.
//
//##############################################
// Rewritten and adapted to easy-wi.com by Ulrich Block
// Contact ulrich.block@easy-wi.com

//##############################################
// SET UPLOAD LIMIT
//##############################################

class Monsta {

    private $upload_limit, $ftpConnection, $dateFormatUsa, $lang_size_kb, $lang_size_mb, $lang_size_gb, $ftpIP, $ftpPort, $ftpUser, $ftpPass;
    private $actionTarget = 'userpanel.php?w=gs&amp;d=wf&amp;id=', $platformTestCount = 0, $trCount = 0;
    public $loggedIn = false, $errorResponse = false;

    public function __construct ($ftpIP, $ftpPort, $ftpUser, $ftpPass, $language, $startDir = '') {

        $this->ftpIP = $ftpIP;
        $this->ftpPort = $ftpPort;
        $this->ftpUser = $ftpUser;
        $this->ftpPass = $ftpPass;

        $this->setDateFormatUsa($language);

        $this->setUploadLimit();

        $this->defineActionTarget();

        $this->errorResponse = $this->connectFTP();

        $this->getPlatform();

        $this->setInitialDir($startDir);
    }

    public function __destruct () {
        $upload_limit = null;
    }

    private function setDateFormatUsa ($language) {
        $this->dateFormatUsa = ($language == 'de') ? 0 : 1;
    }

    private function setUploadLimit() {

        $upload_limit = ini_get('memory_limit');

        $ll = substr($upload_limit,strlen($upload_limit)-1,1);

        if ($ll == "B") {
            $upload_limit = str_replace("B","",$upload_limit);
            $upload_limit = $upload_limit * 1;
        }
        if ($ll == "K") {
            $upload_limit = str_replace("K","",$upload_limit);
            $upload_limit = $upload_limit * 1024;
        }
        if ($ll == "M") {
            $upload_limit = str_replace("M","",$upload_limit);
            $upload_limit = $upload_limit * 1024 * 1024;
        }
        if ($ll == "G") {
            $upload_limit = str_replace("G","",$upload_limit);
            $upload_limit = $upload_limit * 1024 * 1024 * 1024;
        }
        if ($ll == "T") {
            $upload_limit = str_replace("T","",$upload_limit);
            $upload_limit = $upload_limit * 1024 * 1024 * 1024 * 1024;
        }

        $this->upload_limit = $upload_limit;
    }

    private function defineActionTarget () {

        global $ui;

        $this->actionTarget .= $ui->id('id', 10, 'get');

        foreach (array_keys($ui->get) as $k) {
            if (!in_array($k, array('w', 'd', 'id')) and $ui->w($k, 255, 'get')) {
                $this->actionTarget .= '&amp;' .$k . '=' . $ui->w($k, 255, 'get');
            }
        }
    }

###############################################
# CONNECT TO FTP
###############################################
    private function connectFTP() {


        $this->ftpConnection = @ftp_connect($this->ftpIP, $this->ftpPort, 3);

        if ($this->ftpConnection) {

            if (@ftp_login ($this->ftpConnection, $this->ftpUser, $this->ftpPass)) {

                $this->loggedIn = true;

                return true;

            } else {

                global $lang_cant_authenticate;
                return $lang_cant_authenticate;

            }
        }

        global $lang_cant_connect;
        return $lang_cant_connect;
    }

    private function setInitialDir ($ftpDir) {

        // Change dir if one set
        if ($ftpDir != "") {
            if (@ftp_chdir($this->ftpConnection, $ftpDir)) {
                $_SESSION["monstaftp"]["dir_current"] = $ftpDir;
            } else if (@ftp_chdir($this->ftpConnection, "~".$ftpDir)) {
                $_SESSION["monstaftp"]["dir_current"] = "~".$ftpDir;
            }
        } else {
            $_SESSION["monstaftp"]["dir_current"] = "";
        }
    }

    private function adjustButtonWidth($str) {
        return (strlen(utf8_decode($str)) > 12) ? "inputButtonNf" : "inputButton";
    }

    private function getPlatform() {

        if ($this->loggedIn === true and $_SESSION["monstaftp"]["win_lin"] == "") {
            $ftp_rawlist = ftp_rawlist($this->ftpConnection, ".");

            // Check for content in array
            if (sizeof($ftp_rawlist) == 0) {

                $this->platformTestCount++;

                // Create a test folder
                if (@ftp_mkdir($this->ftpConnection, "test")) {

                    if ($this->platformTestCount < 2) {
                        $this->getPlatform();
                        @ftp_rmdir($this->ftpConnection, "test");
                    }
                }

            } else {

                $win_lin = '';

                // Get first item in array
                $ff = $ftp_rawlist[0];

                // Split up array into values
                $ff = preg_split("/[\s]+/",$ff,9);

                // First item in Linux rawlist is permissions. In Windows it's date.
                // If length of first item in array line is 8 chars, without a-z, it's a date.
                if (strlen($ff[0]) == 8 && !preg_match("/[a-z]/i", $ff[0], $matches)) {
                    $win_lin = "win";
                }

                if (strlen($ff[0]) == 10 && !preg_match("/[0-9]/i", $ff[0], $matches)) {
                    $win_lin = "lin";
                }

                $_SESSION["monstaftp"]["win_lin"] = $win_lin;
            }
        }
    }

    public function displayFormStart () {
        return '<form method="post" action="' . $this->actionTarget . '" enctype="multipart/form-data" name="ftpActionForm" id="ftpActionForm">';
    }

    public function displayFtpActions () {

        global $lang_btn_refresh, $lang_btn_cut, $lang_btn_copy, $lang_btn_paste, $lang_btn_rename, $lang_btn_delete, $lang_btn_chmod, $lang_btn_logout;

        $return = '<div id="ftpActionButtonsDiv">
            <input type="button" value="' . $lang_btn_refresh . '" onClick="refreshListing()" class="' . $this->adjustButtonWidth($lang_btn_refresh) . '">
            <input type="button" id="actionButtonCut" value="' . $lang_btn_cut . '" onClick="actionFunctionCut(\'\',\'\');" disabled class="' . $this->adjustButtonWidth($lang_btn_cut) . '">
            <input type="button" id="actionButtonCopy" value="' . $lang_btn_copy . '" onClick="actionFunctionCopy(\'\',\'\');" disabled class="' . $this->adjustButtonWidth($lang_btn_copy) . '">
            <input type="button" id="actionButtonPaste" value="' . $lang_btn_paste . '" onClick="actionFunctionPaste(\'\');" disabled class="' . $this->adjustButtonWidth($lang_btn_paste) . '">
            <input type="button" id="actionButtonRename" value="' . $lang_btn_rename . '" onClick="actionFunctionRename(\'\',\'\');" disabled class="' . $this->adjustButtonWidth($lang_btn_rename) . '">
            <input type="button" id="actionButtonDelete" value="' . $lang_btn_delete . '" onClick="actionFunctionDelete(\'\',\'\');" disabled class="' . $this->adjustButtonWidth($lang_btn_delete) . '">
            ';

        if ($_SESSION["monstaftp"]["win_lin"] == "lin") {
            $return .= '<input type="button" id="actionButtonChmod" value="' . $lang_btn_chmod . '" onClick="actionFunctionChmod(\'\',\'\');" disabled class="' . $this->adjustButtonWidth($lang_btn_chmod) . '">';
        }

        $return .= '</div>';

        return $return;
    }

    private function assignWinLinNum() {

        if ($_SESSION["monstaftp"]["win_lin"] == "lin") {
            return 1;
        }

        if ($_SESSION["monstaftp"]["win_lin"] == "win") {
            return 0;
        }

        return false;

    }

    public function displayAjaxDivOpen () {
        return '<div id="ajaxContentWindow" onContextMenu="displayContextMenu(event,\'\',\'\',' . $this->assignWinLinNum() . ')" onClick="unselectFiles()">';
    }

    private function sanitizeStr($str) {

        $str = trim($str);
        $str = str_replace("&","&amp;",$str);
        $str = str_replace('"','&quot;',$str);
        $str = str_replace("<","&lt;",$str);
        $str = str_replace(">","&gt;",$str);

        return $str;
    }

    private function replaceTilde($str) {

        $str = str_replace("~","/",$str);
        $str = str_replace("//","/",$str);

        return $str;
    }

    public function displayFtpHistory() {

        $return = '<select onChange="openThisFolder(this.options[this.selectedIndex].value,1)" id="ftpHistorySelect">';

        if (isset($_SESSION["monstaftp"]["dir_history"]) and is_array($_SESSION["monstaftp"]["dir_history"])) {

            foreach ($_SESSION["monstaftp"]["dir_history"] as $dir) {

                $dir_display = $this->sanitizeStr($dir);
                $dir_display = $this->replaceTilde($dir_display);

                $return .= "<option value=\"".rawurlencode($dir)."\"";

                // Check if this is current directory
                if ($_SESSION["monstaftp"]["dir_current"] == $dir) {
                    $return .= " selected";
                }

                $return .= ">" . $dir_display . "</option>";
            }
        }

        $return .= '</select>';

        return $return;
    }

    private function getFtpRawList($folder_path) {

        global $lang_folder_cant_access;


        if ($this->loggedIn === true) {

            $isError=0;

            if (!@ftp_chdir($this->ftpConnection, $folder_path)) {
                if ($this->checkFirstCharTilde($folder_path) == 1) {
                    if (!@ftp_chdir($this->ftpConnection, replaceTilde($folder_path))) {
                        $this->recordFileError("folder",$folder_path,$lang_folder_cant_access);
                        $isError=1;
                    }
                } else {
                    $this->recordFileError("folder",$folder_path,$lang_folder_cant_access);
                    $isError=1;
                }
            }

            if ($isError == 0) {
                return ftp_rawlist($this->ftpConnection, ".");
            }

        }

        return false;

    }

//##############################################
// CHECK FIRST CHAR IS TILDE
//##############################################

    private function checkFirstCharTilde($str) {
        return (substr($str,0,1) == "~") ? 1 : 0;
    }

//##############################################
// RECORD FILE/FOLDER ERROR
//##############################################

    private function recordFileError($str,$file_name,$error) {

        $_SESSION["monstaftp"]["errors"][] = str_replace("[".$str."]","<strong>".$file_name."</strong>",$error);
    }

    private function getFtpColumnSpan($sort,$name) {

        global $ui;

        // Check current column
        $ord = ($ui->w('sort', 1, 'post') == $sort and $ui->w('ord', 4, 'post') == 'desc') ? 'asc' : 'desc';

        return "<span onclick=\"processForm('&amp;ftpAction=openFolder&amp;openFolder=".rawurlencode($_SESSION["monstaftp"]["dir_current"])."&amp;sort=".$sort."&amp;ord=".$ord."')\" class=\"cursorPointer\">".$name."</span>";
    }

    public function displayFiles() {

        global $lang_table_name, $lang_table_size, $lang_table_date, $lang_table_time;

        $ftp_rawlist = $this->getFtpRawList($_SESSION["monstaftp"]["dir_current"]);

        #~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        # FOLDER/FILES TABLE HEADER
        #~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        $return = "<table width=\"100%\" cellpadding=\"7\" cellspacing=\"0\" id=\"ftpTable\">";
        $return .= "<tr>"."\n";
        $return .= "<td width=\"16\" class=\"ftpTableHeadingNf\"><input type=\"checkbox\" id=\"checkboxSelector\" onClick=\"checkboxSelectAll()\"></td>"."\n";
        $return .= "<td width=\"16\" class=\"ftpTableHeadingNf\"></td>"."\n";
        $return .= "<td class=\"ftpTableHeading\">".$this->getFtpColumnSpan("n",$lang_table_name)."</td>"."\n";
        $return .= "<td width=\"10%\" class=\"ftpTableHeading\">".$this->getFtpColumnSpan("s",$lang_table_size)."</td>"."\n";
        $return .= "<td width=\"10%\" class=\"ftpTableHeading\">".$this->getFtpColumnSpan("d",$lang_table_date)."</td>"."\n";
        $return .= "<td width=\"10%\" class=\"ftpTableHeading\">".$this->getFtpColumnSpan("t",$lang_table_time)."</td>"."\n";

        $return .= "</tr>"."\n";

        #~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        # FOLDER UP BUTTON
        #~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        if ($_SESSION["monstaftp"]["dir_current"] != "/" && $_SESSION["monstaftp"]["dir_current"] != "~") {

            $return .= "<tr>"."\n";
            $return .= "<td width=\"16\"></td>"."\n";
            $return .= "<td width=\"16\"><i class='fa fa-folder-o'></i></td>"."\n";

            $return .= "<td colspan=\"7\">"."\n";

            // Get the parent directory
            $parent = $this->getParentDir();

            $return .= "<div class=\"width100pc\" onDragOver=\"dragFile(event); selectFile('folder0',0);\" onDragLeave=\"unselectFolder('folder0')\" onDrop=\"dropFile('".rawurlencode($parent)."')\"><a href=\"#\" id=\"folder0\" draggable=\"false\" onClick=\"openThisFolder('".rawurlencode($parent)."',1)\">...</a></div>";

            $return .= "</td>"."\n";
            $return .= "</tr>"."\n";
        }

        #~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        # FOLDERS & FILES
        #~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        if (sizeof($ftp_rawlist) > 0) {

            // Linux
            if ($_SESSION["monstaftp"]["win_lin"] == "lin") {
                $return .= $this->createFileFolderArrayLin($ftp_rawlist,"folders");
                $return .= $this->createFileFolderArrayLin($ftp_rawlist,"links");
                $return .= $this->createFileFolderArrayLin($ftp_rawlist,"files");
            }

            // Windows
            if ($_SESSION["monstaftp"]["win_lin"] == "win") {
                $return .= $this->createFileFolderArrayWin($ftp_rawlist,"folders");
                $return .= $this->createFileFolderArrayWin($ftp_rawlist,"files");
            }
        }

        #~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        # CLOSE TABLE
        #~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        $return .= "</table>";

        return $return;

    }


###############################################
# CREATE FILE/FOLDER ARRAY FOR LINUX
###############################################

    private function createFileFolderArrayLin($ftp_rawlist, $type) {

        global $ui;

        // set and correct to avoid php notice
        $foldAllAr = false;
        $linkAllAr = false;
        $fileAllAr = false;

        if (!is_array($ftp_rawlist)) {
            $ftp_rawlist = (array) $ftp_rawlist;
        }

        // Go through array of files/folders
        foreach($ftp_rawlist AS $ff) {

            // Reset values
            $time="";
            $year="";

            // Split up array into values
            $ff = preg_split("/[\s]+/",$ff,9);

            $perms = $ff[0];
            $user = $ff[2];
            $group = $ff[3];
            $size = $ff[4];
            $month = $ff[5];
            $day = $ff[6];
            $file = $ff[8];

            // Check if file starts with a dot
            $dot_prefix=0;
            if (preg_match("/^\.+/",$file) && $_SESSION["monstaftp"]["interface"] == "bas")
                $dot_prefix=1;

            if ($file != "." && $file != ".." && $dot_prefix == 0) {

                // Where the last mod date is the previous year, the year will be displayed in place of the time
                if (preg_match("/:/",$ff[7]))
                    $time = $ff[7];
                else
                    $year = $ff[7];

                // Set date
                $date = $this->formatFtpDate($day,$month,$year);

                // Reset user and group
                if ($user == "0")
                    $user = "-";
                if ($group == "0")
                    $group = "-";

                // Add folder to array
                if ($this->getFileType($perms) == "d") {
                    $foldAllAr[] = $file."|d|".$date."|".$time."|".$user."|".$group."|".$perms;
                    $foldNameAr[] = $file;
                    $foldDateAr[] = $date;
                    $foldTimeAr[] = $time;
                    $foldUserAr[] = $user;
                    $foldGroupAr[] = $group;
                    $foldPermsAr[] = $perms;
                }

                // Add link to array
                if ($this->getFileType($perms) == "l") {
                    $linkAllAr[] = $file."|l|".$date."|".$time."|".$user."|".$group."|".$perms;
                    $linkNameAr[] = $file;
                    $linkDateAr[] = $date;
                    $linkTimeAr[] = $time;
                    $linkUserAr[] = $user;
                    $linkGroupAr[] = $group;
                    $linkPermsAr[] = $perms;
                }

                // Add file to array
                if ($this->getFileType($perms) == "f") {
                    $fileAllAr[] = $file."|".$size."|".$date."|".$time."|".$user."|".$group."|".$perms;
                    $fileNameAr[] = $file;
                    $fileSizeAr[] = $size;
                    $fileDateAr[] = $date;
                    $fileTimeAr[] = $time;
                    $fileUserAr[] = $user;
                    $fileGroupAr[] = $group;
                    $filePermsAr[] = $perms;
                }
            }
        }

        // Check there are files and/or folders to display
        if (is_array($foldAllAr) || is_array($linkAllAr) || is_array($fileAllAr)) {

            // Set sorting order
            if ($ui->w('sort', 1, 'post') == "")
                $sort = "n";
            else
                $sort = $ui->w('sort', 1, 'post');

            if ($ui->w('ord', 4, 'post') == "")
                $ord = "asc";
            else
                $ord = $ui->w('ord', 4, 'post');

            // Return folders
            if ($type == "folders") {

                if (is_array($foldAllAr)) {

                    // Set the folder arrays to sort
                    if ($sort == "n") $sortAr = $foldNameAr;
                    if ($sort == "d") $sortAr = $foldDateAr;
                    if ($sort == "t") $sortAr = $foldTimeAr;
                    if ($sort == "u") $sortAr = $foldUserAr;
                    if ($sort == "g") $sortAr = $foldGroupAr;
                    if ($sort == "p") $sortAr = $foldPermsAr;

                    // Multisort array
                    if (is_array($sortAr)) {
                        if ($ord == "asc")
                            array_multisort($sortAr, SORT_ASC, $foldAllAr);
                        else
                            array_multisort($sortAr, SORT_DESC, $foldAllAr);
                    }

                    // Format and display folder content
                    return $this->getFileListHtml($foldAllAr, "<i class='fa fa-folder-o'></i>");
                }

            }

            // Return links
            if ($type == "links") {

                if (is_array($linkAllAr)) {

                    // Set the folder arrays to sort
                    if ($sort == "n") $sortAr = $linkNameAr;
                    if ($sort == "d") $sortAr = $linkDateAr;
                    if ($sort == "t") $sortAr = $linkTimeAr;
                    if ($sort == "u") $sortAr = $linkUserAr;
                    if ($sort == "g") $sortAr = $linkGroupAr;
                    if ($sort == "p") $sortAr = $linkPermsAr;

                    // Multisort array
                    if (is_array($sortAr)) {
                        if ($ord == "asc")
                            array_multisort($sortAr, SORT_ASC, $linkAllAr);
                        else
                            array_multisort($sortAr, SORT_DESC, $linkAllAr);
                    }

                    // Format and display folder content
                    return $this->getFileListHtml($linkAllAr, "<i class='fa fa-link'></i>");
                }

            }

            // Return files
            if ($type == "files") {

                if (is_array($fileAllAr)) {

                    // Set the folder arrays to sort
                    if ($sort == "n") $sortAr = $fileNameAr;
                    if ($sort == "s") $sortAr = $fileSizeAr;
                    if ($sort == "d") $sortAr = $fileDateAr;
                    if ($sort == "t") $sortAr = $fileTimeAr;
                    if ($sort == "u") $sortAr = $fileUserAr;
                    if ($sort == "g") $sortAr = $fileGroupAr;
                    if ($sort == "p") $sortAr = $filePermsAr;

                    // Multisort folders
                    if ($ord == "asc")
                        array_multisort($sortAr, SORT_ASC, $fileAllAr);
                    else
                        array_multisort($sortAr, SORT_DESC, $fileAllAr);

                    // Format and display file content
                    return $this->getFileListHtml($fileAllAr, "<i class='fa fa-file-text-o'></i>");
                }

            }
        }

        return '';
    }


###############################################
# CREATE FILE/FOLDER ARRAY FOR WINDOWS
###############################################

    private function createFileFolderArrayWin($ftp_rawlist,$type) {

        global $ui;

        $foldAllAr = false;
        $fileAllAr = false;

        if (!is_array($ftp_rawlist)) {
            $ftp_rawlist = (array) $ftp_rawlist;
        }

        // Go through array of files/folders
        foreach($ftp_rawlist AS $ff) {

            // Split up array into values
            $ff = preg_split("/[\s]+/",$ff,4);

            $date = $ff[0];
            $time = $ff[1];
            $size = $ff[2];
            $file = $ff[3];

            if ($size == "<DIR>") $size = "d";

            // Format date
            $day = substr($date,3,2);
            $month = substr($date,0,2);
            $year = substr($date,6,2);
            $date = $this->formatFtpDate($day,$month,$year);

            // Format time
            $time = $this->formatWinFtpTime($time);

            // Add folder to array
            if ($size == "d") {
                $foldAllAr[] = $file."|d|".$date."|".$time."|||";
                $foldNameAr[] = $file;
                $foldDateAr[] = $date;
                $foldTimeAr[] = $time;
            }

            // Add file to array
            if ($size != "d") {
                $fileAllAr[] = $file."|".$size."|".$date."|".$time."|||";
                $fileNameAr[] = $file;
                $fileSizeAr[] = $size;
                $fileDateAr[] = $date;
                $fileTimeAr[] = $time;
            }
        }

        // Check there are files and/or folders to display
        if (is_array($foldAllAr) || is_array($fileAllAr)) {

            // Set sorting order
            if ($ui->w('sort', 1, 'post') == "")
                $sort = "n";
            else
                $sort = $ui->w('sort', 1, 'post');

            if ($ui->w('ord', 4, 'post') == "")
                $ord = "asc";
            else
                $ord = $ui->w('ord', 4, 'post');

            // Return folders
            if ($type == "folders") {

                if (is_array($foldAllAr)) {

                    // Set the folder arrays to sort
                    if ($sort == "n") $sortAr = $foldNameAr;
                    if ($sort == "d") $sortAr = $foldDateAr;
                    if ($sort == "t") $sortAr = $foldTimeAr;

                    // Multisort array
                    if (is_array($sortAr)) {
                        if ($ord == "asc")
                            array_multisort($sortAr, SORT_ASC, $foldAllAr);
                        else
                            array_multisort($sortAr, SORT_DESC, $foldAllAr);
                    }

                    // Format and display folder content
                    return $this->getFileListHtml($foldAllAr, "<i class='fa fa-folder-o'></i>");
                }

            }

            // Return files
            if ($type == "files") {

                if (is_array($fileAllAr)) {

                    // Set the folder arrays to sort
                    if ($sort == "n") $sortAr = $fileNameAr;
                    if ($sort == "s") $sortAr = $fileSizeAr;
                    if ($sort == "d") $sortAr = $fileDateAr;
                    if ($sort == "t") $sortAr = $fileTimeAr;

                    // Multisort folders
                    if ($ord == "asc")
                        array_multisort($sortAr, SORT_ASC, $fileAllAr);
                    else
                        array_multisort($sortAr, SORT_DESC, $fileAllAr);

                    // Format and display file content
                    return $this->getFileListHtml($fileAllAr, "<i class='fa fa-file-text-o'></i>");
                }
            }
        }

        return '';
    }


###############################################
# FORMAT FTP DATE
###############################################

    private function formatFtpDate($day,$month,$year) {

        if (strlen($day) == 1)
            $day = "0".$day;

        if ($year == "")
            $year = date("Y");

        if (strlen($year) == 2) {

            // To avoid a future Y2K problem, check the first two digits of year on Windows
            if ($year > 00 && $year < 99)
                $year = substr(date("Y"),0,2).$year;
            else
                $year = (substr(date("Y"),0,2)-1).$year;
        }

        if ($month == "Jan") $month = "01";
        if ($month == "Feb") $month = "02";
        if ($month == "Mar") $month = "03";
        if ($month == "Apr") $month = "04";
        if ($month == "May") $month = "05";
        if ($month == "Jun") $month = "06";
        if ($month == "Jul") $month = "07";
        if ($month == "Aug") $month = "08";
        if ($month == "Sep") $month = "09";
        if ($month == "Oct") $month = "10";
        if ($month == "Nov") $month = "11";
        if ($month == "Dec") $month = "12";

        $date = $year.$month.$day;

        return $date;
    }

###############################################
# FORMAT WINDOWS FTP TIME
###############################################

    private function formatWinFtpTime($time) {

        $h = substr($time,0,2);
        $m = substr($time,3,2);
        $am_pm = substr($time,5,2);

        if ($am_pm == "PM")
            $h = $h + 12;

        $time = $h.":".$m;

        return $time;
    }

###############################################
# GET FILE TYPE
###############################################

    function getFileType($perms) {

        if (substr($perms,0,1) == "d")
            return "d"; // directory
        if (substr($perms,0,1) == "l")
            return "l"; // link
        if (substr($perms,0,1) == "-")
            return "f"; // file

        return '';
    }

###############################################
# GET FTP COLUMN SPAN
###############################################

    private function getFileListHtml($array,$image) {

        global $ui;

        $html = '';

        if ($this->trCount == 1)
            $this->trCount=1;
        else
            $this->trCount=0;

        $i=1;
        foreach ($array AS $file) {

            list($file,$size,$date,$time,$user,$group,$perms) = explode("|",$file);

            $action = '';

            // Folder check (lin/win)
            if ($size == "d")
                $action = "folderAction";
            // Link check (lin/win)
            if ( $size == "l")
                $action = "linkAction";
            // File check (lin/win)
            if ($size != "d" && $size != "l")
                $action = "fileAction";

            // Set file path
            if ($size == "l") {

                $file_path = $this->getPathFromLink($file);
                $file = preg_replace("/ -> .*/","",$file);

            } else {

                if ($_SESSION["monstaftp"]["dir_current"] == "/")
                    $file_path = "/".$file;
                else
                    $file_path = $_SESSION["monstaftp"]["dir_current"]."/".$file;
            }

            if ($this->trCount == 0) {
                $trClass = "trBg0";
                $this->trCount=1;
            } else {
                $trClass = "trBg1";
                $this->trCount=0;
            }

            // Check for checkbox check (only if action button clicked"
            if ($ui->w('ftpAction', 255, 'post') != "") {
                if (
                    (sizeof($_SESSION["monstaftp"]["clipboard_rename"]) > 1 && in_array($file,$_SESSION["monstaftp"]["clipboard_rename"]))
                    ||
                    (sizeof($_SESSION["monstaftp"]["clipboard_chmod"]) > 1 && in_array($file_path,$_SESSION["monstaftp"]["clipboard_chmod"])))
                    $checked = "checked";
                else
                    $checked = "";

            } else {
                $checked = "";
            }

            // Set the date
            if ($this->dateFormatUsa == 1)
                $date = substr($date,4,2)."/".substr($date,6,2)."/".substr($date,2,2);
            else
                $date = substr($date,6,2)."/".substr($date,4,2)."/".substr($date,2,2);

            $html .= "<tr class=\"".$trClass."\">"."\n";
            $html .= "<td>"."\n";

            if ($action != "linkAction")
                $html .= "<input type=\"checkbox\" name=\"".$action."[]\" value=\"".rawurlencode($file_path)."\" onclick=\"checkFileChecked()\" ".$checked.">"."\n";

            $html .= "</td>"."\n";
            $html .= "<td>".$image."</td>"."\n";
            $html .= "<td>"."\n";

            // Display Folders
            if ($action == "folderAction")
                $html .= "<div class=\"width100pc\" onDragOver=\"dragFile(event); selectFile('folder".$i."',0);\" onDragLeave=\"unselectFolder('folder".$i."')\" onDrop=\"dropFile('".rawurlencode($file_path)."')\"><a href=\"#\" id=\"folder".$i."\" onClick=\"openThisFolder('".rawurlencode($file_path)."',1)\" onContextMenu=\"selectFile(this.id,1); displayContextMenu(event,'','".rawurlencode($file_path)."',".$this->assignWinLinNum().")\" draggable=\"true\" onDragStart=\"selectFile(this.id,1); setDragFile('','".rawurlencode($file_path)."')\">".$this->sanitizeStr($file)."</a></div>"."\n";

            // Display Links
            if ($action == "linkAction")
                $html .= "<div class=\"width100pc\"><a href=\"#\" id=\"link".$i."\" onContextMenu=\"\" draggable=\"false\">".$this->sanitizeStr($file)."</a></div>"."\n";

            // Display files
            if ($action == "fileAction")
                $html .= "<a href=\"".$this->actionTarget."&amp;dl=".rawurlencode($file_path)."\" id=\"file".$i."\" target=\"ajaxIframe\" onContextMenu=\"selectFile(this.id,1); displayContextMenu(event,'".rawurlencode($file_path)."','',".$this->assignWinLinNum().")\" draggable=\"true\" onDragStart=\"selectFile(this.id,1); setDragFile('".rawurlencode($file_path)."','')\">".$this->sanitizeStr($file)."</a>"."\n";

            $html .= "</td>"."\n";
            $html .= "<td>".$this->formatFileSize($size)."</td>"."\n";
            $html .= "<td>".$date."</td>"."\n";
            $html .= "<td>".$time."</td>"."\n";

            $html .= "</tr>"."\n";

            $i++;
        }

        return $html;
    }


###############################################
# GET PATH FROM LINK
###############################################

    private function getPathFromLink($file) {

        $file_path = preg_replace("/.* -> /","",$file);

        // Check if path is not absolute
        if (substr($file_path,0,1) != "/") {

            // Count occurances of ../
            $i=0;
            while (substr($file_path,0,3) == "../") {
                $i++;
                $file_path = substr($file_path,3,strlen($file_path));
            }

            $dir_current = $_SESSION["monstaftp"]["dir_current"];

            // Get the real parent
            for ($j=0;$j<$i;$j++) {

                $path_parts = pathinfo($dir_current);
                $dir_current = $path_parts['dirname'];
            }

            // Set the path
            if ($dir_current == "/")
                $file_path = "/".$file_path;
            else
                $file_path = $dir_current."/".$file_path;
        }

        if ($file_path == "~/")
            $file_path = "~";

        return $file_path;
    }

//##############################################
// GET PARENT DIRECTORY
//##############################################

    private function getParentDir() {

        if ($_SESSION["monstaftp"]["dir_current"] == "/") {

            $parent = "/";

        } else {

            $path_parts = pathinfo($_SESSION["monstaftp"]["dir_current"]);
            $parent = $path_parts['dirname'];
        }

        return $parent;
    }

###############################################
# FORMAT FILE SIZES
###############################################

    private function formatFileSize($size) {

        if ($size == "d" || $size == "l") {

            $size="";

        } else {

            if ($size < 1024) {
                $size = round($size,2);
                //$size = round($size,2).$lang_size_b;
            } elseif ($size < (1024*1024)) {
                $size = round(($size/1024),0).$this->lang_size_kb;
            } elseif ($size < (1024*1024*1024)) {
                $size = round((($size/1024)/1024),0).$this->lang_size_mb;
            } elseif ($size < (1024*1024*1024*1024)) {
                $size = round(((($size/1024)/1024)/1024),0).$this->lang_size_gb;
            }
        }

        return $size;
    }

###############################################
# DISPLAY ERRORS
###############################################

    public function displayErrors() {

        global $lang_title_errors;

        $sizeAr = sizeof($_SESSION["monstaftp"]["errors"]);

        $return = '';

        if ($sizeAr > 0) {

            $width = (getMaxStrLen($_SESSION["monstaftp"]["errors"]) * 10) + 30;
            $height = sizeof($_SESSION["monstaftp"]["errors"]) * 25;

            $title = $lang_title_errors;

            // Display pop-up
            $return .= $this->displayPopupOpen(1,$width,$height,1,$title);

            $errors = array_reverse($_SESSION["monstaftp"]["errors"]);

            foreach($errors AS $error) {
                $return .= $error."<br>";
            }

            $vars = "&amp;ftpAction=openFolder&amp;resetErrorArray=1";

            $return .=$this->displayPopupClose(1,$vars,0);
        }

        return $return;

    }

//##############################################
// DISPLAY POP-UP FRAME OPEN
//##############################################

    private function displayPopupOpen($resize,$width,$height,$isError,$title) {

        global $ui;

        // Set default sizes of exceeded
        if ($resize == 1) {

            if ($width < 400)
                $width = 400;

            if ($height > 400)
                $height = 400;
        }

        // Center window
        if ($ui->id('windowWidth', 255, 'post') > 0)
            $left = round(($ui->id('windowWidth', 255, 'post') - $width) / 2 - 15); // -15 for H padding
        else
            $left = 250;

        if ($ui->id('windowHeight', 255, 'post') > 0)
            $top = round(($ui->id('windowHeight', 255, 'post') - $height) / 2 - 50);
        else
            $top = 250;

        $return = "<div id=\"blackOutDiv\">";
        $return .= "<div id=\"popupFrame\" style=\"left: ".$left."px; top: ".$top."px; width: ".$width."px;\">";

        if ($isError == 1)
            $divId = "popupHeaderError";
        else
            $divId = "popupHeaderAction";

        $return .= "<div id=\"".$divId."\">";
        $return .= $title;
        $return .= "</div>";

        if ($isError == 1)
            $divId = "popupBodyError";
        else
            $divId = "popupBodyAction";

        $return .= "<div id=\"".$divId."\" style=\"height: ".$height."px;\">";

        return $return;

    }

//##############################################
// DISPLAY POP-UP FRAME CLOSE
//##############################################

    function displayPopupClose($isError,$vars,$btnCancel) {

        global $lang_btn_ok;
        global $lang_btn_cancel;

        $return = "</div>";

        if ($isError == 1)
            $divId = "popupFooterError";
        else
            $divId = "popupFooterAction";

        $return .= "<div id=\"".$divId."\">";

        // OK button
        if ($vars != "")
            $return .= "<input type=\"button\" class=\"popUpBtn\" value=\"".$lang_btn_ok."\" onClick=\"processForm('".$vars."'); activateActionButtons(0,0);\"> ";

        // Cancel button
        if ($btnCancel == 1)
            $return .= "<input type=\"button\" class=\"popUpBtn\" value=\"".$lang_btn_cancel."\" onClick=\"processForm('&amp;ftpAction=openFolder');\"> ";

        $return .= "</div>";

        $return .= "</div>";
        $return .= "</div>";

        return $return;

    }

    public function divClose() {
        return '</div>';
    }

###############################################
# DISPLAY IFRAME
###############################################

    public function displayAjaxIframe() {
        return '<iframe name="ajaxIframe" id="ajaxIframe" width="0" height="0" frameborder="0" style="visibility: hidden; display: none;"></iframe>';
    }

###############################################
# DISPLAY UPLOAD PROGRESS
###############################################

    public function displayUploadProgress() {

        global $lang_xfer_file;
        global $lang_xfer_size;
        global $lang_xfer_progress;
        global $lang_xfer_elapsed;
        global $lang_xfer_uploaded;
        global $lang_xfer_rate;
        global $lang_xfer_remain;
        return '<div id="uploadProgressDiv" style="visibility:hidden; display:none">
            <table width="100%" cellpadding="7" cellspacing="0" id="uploadProgressTable">
                <tr>
                    <td class="ftpTableHeadingNf" width="1%"></td>
                    <td class="ftpTableHeading" size="35%">' . $lang_xfer_file . '</td>
                    <td class="ftpTableHeading" width="7%">' . $lang_xfer_size . '</td>
                    <td class="ftpTableHeading" width="21%">' . $lang_xfer_progress . '</td>
                    <td class="ftpTableHeading" width="9%">' . $lang_xfer_elapsed . '</td>
                    <td class="ftpTableHeading" width="7%">' . $lang_xfer_uploaded . '</td>
                    <td class="ftpTableHeading" width="9%">' . $lang_xfer_rate . '</td>
                    <td class="ftpTableHeading" width="10%">' . $lang_xfer_remain . '</td>
                    <td class="ftpTableHeading" width="1%"></td>
                </tr>
            </table>
        </div>';
    }

###############################################
# WINDOW FOOTER
###############################################

    public function displayAjaxFooter() {

        global $lang_btn_new_folder;
        global $lang_btn_new_file;
        global $lang_info_host;
        global $lang_info_user;
        global $lang_info_upload_limit;
        global $lang_info_drag_drop;

        return '<div id="footerDiv">
        <div id="hostInfoDiv">
            <span>' . $lang_info_host . ':</span> ' . $this->ftpIP . '
            <span>' . $lang_info_user . ':</span> ' . $this->ftpUser .'
            <span>' . $lang_info_upload_limit . ':</span> ' . round(($this->upload_limit /(1024 * 1024) ) * 0.9) . ' MB' . '
            <!-- <span>' . $lang_info_drag_drop . ':</span> <div id="dropFilesCheckDiv"></div> --> <!-- Drag & Drop check commented out as considered redundant -->
        </div>
        <div class="floatLeft10">
            <input type="button" value="' . $lang_btn_new_folder . '" onClick="processForm(\'&amp;ftpAction=newFolder\')" class="' . $this->adjustButtonWidth($lang_btn_new_folder) . '">
        </div>

        <div class="floatLeft10">
            <input type="button" value="' .  $lang_btn_new_file . '" onClick="processForm(\'&amp;ftpAction=newFile\')" class="' . $this->adjustButtonWidth($lang_btn_new_file) . '">
        </div>

        <div id="uploadButtonsDiv"></div>';
    }

//##############################################
// LOAD JAVASCRIPT LANGUAGE VARS
//##############################################

    public function loadJsLangVars() {

        global $lang_no_xmlhttp;
        global $lang_support_drop;
        global $lang_no_support_drop;
        global $lang_transfer_pending;
        global $lang_transferring_to_ftp;
        global $lang_no_file_selected;
        global $lang_none_selected;
        global $lang_context_open;
        global $lang_context_download;
        global $lang_context_edit;
        global $lang_context_cut;
        global $lang_context_copy;
        global $lang_context_paste;
        global $lang_context_rename;
        global $lang_context_delete;
        global $lang_context_chmod;
        global $lang_size_b;
        global $lang_size_kb;
        global $lang_size_mb;
        global $lang_size_gb;
        global $lang_btn_upload_file;
        global $lang_btn_upload_files;
        global $lang_btn_upload_repeat;
        global $lang_btn_upload_folder;
        global $lang_file_size_error;

        return "<script type=\"text/javascript\">
        var lang_no_xmlhttp = '" . $this->quotesEscape($lang_no_xmlhttp,"s") . "';
        var lang_support_drop = '" . $this->quotesEscape($lang_support_drop,"s") . "';
        var lang_no_support_drop = '" . $this->quotesEscape($lang_no_support_drop,"s") . "';
        var lang_transfer_pending = '" . $this->quotesEscape($lang_transfer_pending,"s") . "';
        var lang_transferring_to_ftp = '" . $this->quotesEscape($lang_transferring_to_ftp,"s") . "';
        var lang_no_file_selected = '" . $this->quotesEscape($lang_no_file_selected,"s") . "';
        var lang_none_selected = '" . $this->quotesEscape($lang_none_selected,"s") . "';
        var lang_context_open = '" . $this->quotesEscape($lang_context_open,"s") . "';
        var lang_context_download = '" . $this->quotesEscape($lang_context_download,"s") . "';
        var lang_context_edit = '" . $this->quotesEscape($lang_context_edit,"s") . "';
        var lang_context_cut = '" . $this->quotesEscape($lang_context_cut,"s") . "';
        var lang_context_copy = '" . $this->quotesEscape($lang_context_copy,"s") . "';
        var lang_context_paste = '" . $this->quotesEscape($lang_context_paste,"s") . "';
        var lang_context_rename = '" . $this->quotesEscape($lang_context_rename,"s") . "';
        var lang_context_delete = '" . $this->quotesEscape($lang_context_delete,"s") . "';
        var lang_context_chmod = '" . $this->quotesEscape($lang_context_chmod,"s") . "';
        var lang_size_b = '" . $this->quotesEscape($lang_size_b,"s") . "';
        var lang_size_kb = '" . $this->quotesEscape($lang_size_kb,"s") . "';
        var lang_size_mb = '" . $this->quotesEscape($lang_size_mb,"s") . "';
        var lang_size_gb = '" . $this->quotesEscape($lang_size_gb,"s") . "';
        var lang_btn_upload_file = '" . $this->quotesEscape($lang_btn_upload_file,"s") . "';
        var lang_btn_upload_files = '" . $this->quotesEscape($lang_btn_upload_files,"s") . "';
        var lang_btn_upload_repeat = '" . $this->quotesEscape($lang_btn_upload_repeat,"s") . "';
        var lang_btn_upload_folder = '" . $this->quotesEscape($lang_btn_upload_folder,"s") . "';
        var lang_file_size_error = '" . $this->quotesEscape($lang_file_size_error,"s") . "';

        var upload_limit = '" . $this->upload_limit . "';
    </script>";
    }

###############################################
# ESCAPE QUOTES
###############################################

    private function quotesEscape($str,$type) {

        if ($type == "s" || $type == "")
            $str = str_replace("'","\'",$str);
        if ($type == "d" || $type == "")
            $str = str_replace('"','\"',$str);

        return $str;
    }

###############################################
# LOAD AJAX
###############################################

    public function loadAjax() {

        global $template_to_use;

        $javascript =  (is_file(EASYWIDIR . '/js/' . $template_to_use . '/monstaftp_ajax.js')) ? 'js/' . $template_to_use . '/monstaftp_ajax.js' : 'js/default/monstaftp_ajax.js';

        return '<script type="text/javascript" src="' . $javascript . '"></script>';

    }

###############################################
# WRITE HIDDEN DIVS
###############################################

    public function writeHiddenDivs() {
        return '<div id="contextMenu" style="visibility: hidden; display: none;"></div>
        <div id="indicatorDiv" style="z-index: 1; visibility: hidden; display: none"><i class="fa fa-spinner fa-spin fa-5x"></i></div>';
    }

###############################################
# END FORM
###############################################

    function displayFormEnd() {
        return '</form>';
    }
}