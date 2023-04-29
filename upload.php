<?php

// Require the ST_FileUploadUtils class
require_once "src/ST_FileUploadUtils.php";

// Delete old temporary files
ST_FileUploadUtils::deleteOldTemporaryFiles();

// Handle login
ST_FileUploadUtils::handleLogin();

// Display HTML
echo ST_FileUploadUtils::getHTML();

