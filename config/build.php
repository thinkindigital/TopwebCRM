<?php

return [
    'commit_sha' => env('TOPWEBCRM_BUILD_SHA', 'unknown'),
    'timestamp' => env('TOPWEBCRM_BUILD_TIMESTAMP', 'unknown'),
    'branch' => env('TOPWEBCRM_BUILD_BRANCH', 'unknown'),
    'image_tag' => env('TOPWEBCRM_IMAGE_TAG', 'unknown'),
    'image_revision' => env('TOPWEBCRM_IMAGE_REVISION', 'unknown'),
];
