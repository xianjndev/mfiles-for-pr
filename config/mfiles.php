<?php

return [
    'mfile_sf_obj_id' => env('MFILES_STOREFRONT_OBJECT_ID'),
    'mfiles_credentials' =>[
        'username' => env('MFILES_USERNAME'),
        'password'=> env('MFILES_PASSWORD')
    ],
    'mfiles_baseurl' => env("MFILES_BASEURL"),
    'mfiles_vaultid' => env('MFILES_VAULTID'),
    'storefront' => [
        'username' => env('STOREFRONT_USERNAME'),
        'password' => env('STOREFRONT_PASSWORD'),
    ],
];
