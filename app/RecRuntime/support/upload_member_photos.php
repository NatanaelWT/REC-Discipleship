<?php

function upload_member_photos(array $fileInput, string &$errorCode): array {
    return upload_managed_files($fileInput, $errorCode, 'upload_member_photo');
}
