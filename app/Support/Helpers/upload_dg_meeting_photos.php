<?php

function upload_dg_meeting_photos(array $fileInput, string &$errorCode): array {
    return upload_managed_files($fileInput, $errorCode, 'upload_dg_meeting_photo');
}
