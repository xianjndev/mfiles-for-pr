<?php

return [
    'mfile_sf_obj_id' => env('MFILES_STOREFRONT_OBJECT_ID'),
    'mfiles_credentials' =>[
        'username' => env('MFILES_USERNAME'),
        'password'=> env('MFILES_PASSWORD')
    ],
    'mfiles_baseurl' => env("MFILES_BASEURL", "https://raemulanlands.cloudvault.m-files.com"),
    'mfiles_vaultid' => env('MFILES_VAULTID', "DF00F177-C725-4EB7-AC98-A557D1245361"),
    'match_api_log' => [
        'url' => env('MATCH_URL'),
        'token' => env('MATCH_API_TOKEN'),
    ]
];
