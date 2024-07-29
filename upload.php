<?php

use Shuchkin\SimpleXLSX;
require_once('vendor/autoload.php');
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_FILES['file']) && $_FILES['file']['error'] == UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['file']['tmp_name'];
        $fileName = $_FILES['file']['name'];
        $fileSize = $_FILES['file']['size'];
        $fileType = $_FILES['file']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        $allowedfileExtensions = array('xlsx');

        if (in_array($fileExtension, $allowedfileExtensions)) {
            $uploadFileDir = './xlsx/';
            $dest_path = $uploadFileDir . 'discounts.xlsx';  // Save with specific name

            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                echo "File is successfully uploaded as discounts.xlsx.";
                if ( $xlsx = SimpleXLSX::parse($dest_path) ) {
                    echo $xlsx->toHTMLEx();
                }
            } else {
                echo "There was an error moving the uploaded file.";
            }
        } else {
            echo "Upload failed. Allowed file types: " . implode(',', $allowedfileExtensions);
        }
    } else {
        echo "There was an error uploading the file.";
    }
}
?>
