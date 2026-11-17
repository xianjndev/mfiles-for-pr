<?php

namespace App\Http\Controllers;

use App\Jobs\PrepareMFilesInventorySync;
use App\Services\MFilesInventorySyncState;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Psr7\MultipartStream;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use App\Support\EncryptedRouteParameter;
use Illuminate\Support\Str;

class MfilesController extends Controller
{
    private function baseURL(){
        return config('mfiles.mfiles_baseurl');
    }
    public function get_datatype($value)
    {
        $datatypes = [
            'Boolean' => 8,
            'Date' => 5,
            'FILETIME' => 12,
            'Float' => 3,
            'Integer' => 2,
            'Integer64' => 11,
            'Lookup' => 9,
            'MultiLookup' => 10,
            'MultiText' => 13,
            'Text' => 1,
            'Time' => 6,
            'Timestamp' => 7,
        ];
        return $datatypes[$value] ?? null;

    }
    public function request_catcher(Request $request){
        dd($request);
    }
    public function process_property($array){
        $datatypes = [
            'Boolean' => 8,
            'Date' => 5,
            'FILETIME' => 12,
            'Float' => 3,
            'Integer' => 2,
            'Integer64' => 11,
            'Lookup' => 9,
            'MultiLookup' => 10,
            'MultiText' => 13,
            'Text' => 1,
            'Time' => 6,
            'Timestamp' => 7,
        ];
        $datatype = $array['DataType'] ?? null;

        switch ($array['DataType']) {
            case 'MultiLookup':
                $property = [
                    "PropertyDef" => $array['ID'],
                    "TypedValue" => [
                        "DataType" => $this->get_datatype($array['DataType']),
                        "Lookups" => [
                            "Item" => $array['ObjID'],
                            "Version" => -1
                        ]
                    ]
                ];
                break;
            case 'Lookup':
                $property = [
                    "PropertyDef" => $array['ID'],
                    "TypedValue" => [
                        "DataType" => $this->get_datatype($array['DataType']),
                        "Lookup" => [
                            "Item" => $array['ObjID'],
                            "Version" => -1
                        ]
                    ]
                ];
                break;
            default:
                $property = [
                    "PropertyDef" => $array['ID'],
                    "TypedValue" => [
                        "DataType" => $this->get_datatype($array['DataType']),
                        "Value" => $array['Value']
                    ]
                ];
                break;
        }

        return $property;

    }

    public function user_information(){
        $get_token = $this->get_token($request);
        $sessionURL =$this->baseURL()."/REST/session" ;
        // Get the uploaded file from the request
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];

        // dd($response_content);

        $client = new Client();
        try{
            $request = new GuzzleRequest('GET', $sessionURL, $headers);
            $response = $client->sendAsync($request)->wait();
            $response_content = json_decode($response->getBody()->getContents());
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error in getting user information: ' . $e->getMessage()], 500);
        }
        return $response_content;
    }
    public function get_token(Request $request)
    {
        $authURL = $this->baseURL().'/REST/server/authenticationtokens';
        $username = !$request->Credentials?config('mfiles.mfiles_credentials.username'):$request->Credentials['Username'];
        $password = !$request->Credentials?config('mfiles.mfiles_credentials.password'):$request->Credentials['Password'];
        $credential = [
            'Username'  => $username,
            'Password'  => $password,
            'VaultGuid' => config('mfiles.mfiles_vaultid') // $request->input('vaultid') -- change to Param if multiple vault
        ];


        $client = new Client();

        try {
            $response = $client->post($authURL,[
                'json' => $credential ,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]);

            $setCookies = null;
            $setCookie = $response->getHeader('Set-Cookie');
            if($setCookie){
                foreach ($setCookie as $cookie) {
                    $array = explode(';',$cookie);
                    $setCookies = $setCookies == NULL ? $array[0] : $setCookies.";".$array[0];
                }
            }
            $response_message = json_decode($response->getBody()->getContents());
            return ["token"=>$response_message->Value, "setCookie" => $setCookies];

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error in generating Token: ' . $e->getMessage()], 500);
        }
    }

    public function create_object(Request $request){
        $get_token = $this->get_token($request);
        $properties = $request->Properties;
        $setProperties;
        //set document properties
        if($properties)
        {
            foreach ($properties as $property)
            {
                $currProperty = $this->process_property($property);
                $setProperties[] = $currProperty;


            }
        }
        //set if document accept multiple files
        $setProperties[] = [
            "PropertyDef" => 22,
            "TypedValue" => [
                "DataType" => 8,
                "Value" => false
            ]
        ];
        //set document class
        $setProperties[] = [
            "PropertyDef" => 100,
            "TypedValue" => [
                "DataType" => 9,
                "Lookup"=>[
                    "Item" => $request->classId,
                    "Version" => -1
                ]
            ]
        ];
        $body = '{"PropertyValues":'.json_encode($setProperties).',"Files": []}';
        $bodyJson = [
            "PropertyValues" => $setProperties,
            "Files" => [] ];

        $client = new Client();
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];

        $client = new Client();

        $objectURL =$this->baseURL()."/REST/objects/".$request->input('objectId')."?checkIn=true" ;
        // dd($objectURL);
        $request = new GuzzleRequest('POST', $objectURL, $headers, $body);
        $res = $client->sendAsync($request)->wait();
        $responseBody = json_decode($res->getBody());//->getContents();
        // dd($responseJSON);
        // return $responseJSON->DisplayID;
        // return $res->getBody();
        return $responseBody;

    }

    public function upload_file_url(Request $request){
        //Set document properties
        dd($request->body);
        $properties = $request->Properties;
        // if (file_exists(storage_path($request->filePath))) {
        //     $filePath = storage_path($request->filePath);
        // }
        // else
        // {
        // return "File not found in ".storage_path($request->filePath);
        // }
        $filePath = $request->filePath;
        if (file_exists($filePath)) {
            $filePath = $filePath;
        }
        else
        {
            return "File not found in ".$request->filePath;
        }
        $classId = (int)$request->classID;
        $objId = $request->objectId;
        $client = new Client();
        $objectURL =$this->baseURL()."/REST/files" ;
        $bodyFile = Utils::streamFor(fopen($filePath, 'r'));
        // dd( $request->filename );
        $get_token = $this->get_token($request);
        $fileName = $request->filename;
        $referenceCode = $request->referenceCode;
        $ext = $request->ext;

        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];
        $request = new GuzzleRequest('POST', $objectURL, $headers, $bodyFile);
        $response = $client->sendAsync($request)->wait();
        $response_content = json_decode($response->getBody()->getContents());
        $upload_response = [
            "UploadID" => $response_content->UploadID,
            "Size" => $response_content->Size ,
            "Title" => $fileName,
            "Extension" => $ext
        ];
        // dd($upload_response);
        //create object
        $setProperties;
        // dd($properties);
        if($properties)
        {
            foreach ($properties as $property)
            {
                $currProperty = $this->process_property($property);
                $setProperties[] = $currProperty;
            }
        }
        // $setProperties[] =  [
        //     "PropertyDef" => 1068,
        //     "TypedValue" => [
        //         "DataType" => 1,
        //         "Value" => $fileName
        //     ]
        // ];
        // $setProperties[] =  [
        //     "PropertyDef" => 1115,
        //     "TypedValue" => [
        //         "DataType" => 1,
        //         "Value" => $referenceCode
        //     ]
        // ];
        //Set if document accept multiple files
        $setProperties[] = [
            "PropertyDef" => 22,
            "TypedValue" => [
                "DataType" => 8,
                "Value" => false
            ]
        ];

        //Set document class
        $setProperties[] = [
            "PropertyDef" => 100,
            "TypedValue" => [
                "DataType" => 9,
                "Lookup"=>[
                    "Item" => $classId,
                    "Version" => -1
                ]
            ]
        ];
        // dd($setProperties);
        $body = '{"PropertyValues":'.json_encode($setProperties).',"Files": ['.json_encode($upload_response).']}';
        $bodyJson = [
            "PropertyValues" => $setProperties,
            "Files" => $upload_response ];


        // dd($bodyJson);
        $client = new Client();
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];
        // dd($headers);


        try{
            $objectURL =$this->baseURL()."/REST/objects/".$objId."?checkIn=true" ;
            $request = new GuzzleRequest('POST', $objectURL, $headers, $body);
            $res = $client->sendAsync($request)->wait();
            $responseBody = $res->getBody()->getContents();
            dd($responseBody);
        } catch (\Exception $e) {
            dd($e->getResponse()->getBody()->getContents());
            return response()->json(['error' => 'Error in uploading file: ' . $e->getMessage()], 500);
        }
        return $res->getBody();

    }
    public function upload_file(Request $request){
        $get_token = $this->get_token($request);
        $fileName = $request->upload->getClientOriginalName();
        $ext = $request->upload->getClientOriginalExtension();
        $classId = (int)$request->classID;
        // dd( $classId);
        $client = new Client();
        $objectURL =$this->baseURL()."/REST/files" ;
        // Get the uploaded file from the request
        $uploadFile = $request->file('upload');
        $bodyFile = Utils::streamFor(fopen($uploadFile->getPathname(), 'r'));
        // dd($bodyFile);



        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];
        $request = new GuzzleRequest('POST', $objectURL, $headers, $bodyFile);
        $response = $client->sendAsync($request)->wait();
        $response_content = json_decode($response->getBody()->getContents());
        // dd($response_content);
        $upload_response = [
            "UploadID" => $response_content->UploadID,
            "Size" => $response_content->Size ,
            "Title" => $fileName,
            "Extension" => $ext
        ];

        //create object
        //Set document properties
        $setProperties[] =  [
            "PropertyDef" => 1157,
            "TypedValue" => [
                "DataType" => 1,
                "Value" => $fileName
            ]
        ];
        //Set if document accept multiple files
        $setProperties[] = [
            "PropertyDef" => 22,
            "TypedValue" => [
                "DataType" => 8,
                "Value" => false
            ]
        ];

        //Set document class
        $setProperties[] = [
            "PropertyDef" => 100,
            "TypedValue" => [
                "DataType" => 9,
                "Lookup"=>[
                    "Item" => $classId,
                    "Version" => -1
                ]
            ]
        ];
        $body = '{"PropertyValues":'.json_encode($setProperties).',"Files": ['.json_encode($upload_response).']}';
        $bodyJson = [
            "PropertyValues" => $setProperties,
            "Files" => $upload_response ];

        // dd($body);
        // dd($get_token);
        $client = new Client();
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];
        // dd($headers);


        try{
            $objectURL =$this->baseURL()."/REST/objects/104?checkIn=true" ;
            $request = new GuzzleRequest('POST', $objectURL, $headers, $body);
            $res = $client->sendAsync($request)->wait();
            $responseBody = $res->getBody()->getContents();
            dd($responseBody);
        } catch (\Exception $e) {
            // dd($e->getResponse()->getBody()->getContents());
            return response()->json(['error' => 'Error in generating Token: ' . $e->getMessage()], 500);
        }
        return $res->getBody();
        // return $upload_response;
        // {{MFWSUrl}}/objects/{{ObjectType}}/{{ObjectID}}/{{ObjectVersion}}/properties?_method=PUT
        // dd($responseBody);
        // return $res->getBody();

    }

    public function upload_storefront_file(Request $request)
    {

        $get_token = $this->get_token($request);
        $client = new Client();

        $headers = [
            'x-authentication' => $get_token['token'],
            'Cookie' => $get_token['setCookie'],
        ];
        $objectId = config('mfiles.mfile_sf_obj_id');

        $uploadFile = $request->file('uploadFile');
        $fileName = $uploadFile->getClientOriginalName();
        $objectURL = $this->baseURL() . "/REST/objects/0/{$objectId}/files";

        try {
            $multipart = [
                [
                    'name'     => 'file',
                    'contents' => Utils::tryFopen($uploadFile->getPathname(), 'r'),
                    'filename' => Carbon::now()->timestamp."_".$fileName,
                ],
            ];
            $request = new GuzzleRequest('POST', $objectURL, $headers);
            $res = $client->sendAsync($request, ['multipart' => $multipart])->wait();

            $responseBody = $res->getBody()->getContents();
            $resBodyJson = json_decode($responseBody, true);

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'fileId' => $resBodyJson['AddedFiles'][0]['ID']
            ], 200);

        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                $errorMessage = $e->getResponse()->getBody()->getContents();
            }

            return response()->json([
                'error' => 'Upload failed',
                'details' => $errorMessage
            ], 500);
        }
    }
    public function view_storefront_document(Request $request, $fileId){
        $get_token = $this->get_token($request);
        $objectId = config('mfiles.mfile_sf_obj_id');
        $client = new Client();
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];
        try{
            $objectURL =$this->baseURL()."/REST/objects/0/".$objectId."/files/".$fileId."/content.aspx?format=pdf" ;
            $request = new GuzzleRequest('GET', $objectURL, $headers);
            $res = $client->sendAsync($request)->wait();
            $responseBody = $res->getBody()->getContents();
            

            return response($responseBody, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="document.pdf"'); // inline = view in
            // dd($responseBody);
        } catch (\Exception $e) {
            // dd($e->getResponse()->getBody()->getContents());
            return response()->json(['error' => 'Document not found'], 500);
        }

    }
    // public function view_storefront_video(Request $request, $fileId)
    // {
    //     $get_token = $this->get_token($request);
    //     $objectId = env("MFILES_STOREFRONT_OBJECT_ID", 723368);
    //     $client = new Client();

    //     $headers = [
    //         'x-authentication' => $get_token['token'],
    //         'Content-Type' => 'application/json',
    //         'Cookie' => $get_token['setCookie']
    //     ];

    //     try {
    //         $objectURL = $this->baseURL() . "/REST/objects/0/{$objectId}/files/{$fileId}/content.aspx";
    //         $rangeHeader = $request->header('Range', 'bytes=0-');

    //         $response = $client->get($objectURL, [
    //             'headers' => array_merge($headers, [
    //                 'Range' => $rangeHeader,
    //             ]),
    //             'stream' => true,
    //         ]);

    //         $statusCode = $response->getStatusCode();
    //         $body = $response->getBody();

    //         return response()->stream(function () use ($body) {
    //             while (!$body->eof()) {
    //                 echo $body->read(1024 * 8); 
    //                 ob_flush();
    //                 flush();
    //             }
    //         }, $statusCode, [
    //             'Content-Type' => 'video/mp4',
    //             'Content-Disposition' => 'inline; filename="video.mp4"',
    //             'Accept-Ranges' => 'bytes',
    //             'Cache-Control' => 'no-cache',
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json(['error' => 'Video not found or cannot be streamed'], 500);
    //     }
    // }

    public function get_document_property(Request $request, $ObjectID = null, $propertyID = null){
         //added only for CS lookup
         if(config("cs.property_lookup")){
            // $pbl;
            try {

                // Example API endpoint
                if($request->propertyID===1050)
                {
                    $baseURL = $this->getBaseURL($request->name);
                    $url = $baseURL."api_storefront_seller/get_project_detail/".$request->name;
                }
                if($request->propertyID===1105)
                {
                    $pbl=explode("-",$request->name);
                    $baseURL = $this->getBaseURL($pbl[0]);

                    $url = $baseURL."api_storefront_seller/get_property_detail/".$request->name;
                }
                $client = new Client();
                $response = $client->get($url);
                $response_message = json_decode($response->getBody(), true);

                return response()->json($response_message);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Request error: ' . $e->getMessage()
                ], 500);
            }


        }
        //end CS lookup
        
        $objectID = $objectID ?? $request->objectID;
        $propertyID = $propertyID ?? $request->propertyID;
    
        $get_token = $this->get_token($request);
    
        $objectURL = $this->baseURL()
            . "/REST/objects/"
            . $objectID
            . "?p"
            . $propertyID
            . "="
            . $request->name;
    
        $client = new Client();
    
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];
    
        $customPropIDs = [];
    
        if ($objectID == 119) {
    
            $customPropIDs = [
                1332 => "portion_of_lot",
                1333 => "lrc_record_no",
                1334 => "land_owner_claimant",
                1335 => "location",
                1336 => "area",
                1337 => "description_of_corners",
                1338 => "bearings",
                1339 => "original_date_of_survey",
                1340 => "date_of_survey",
                1341 => "date_approved",
                1342 => "geodetic_engineer"
            ];
    
            $propery_ids = array_unique(array_merge(
                (array) $request->property_ids,
                array_keys($customPropIDs)
            ));
    
        } else {
    
            $propery_ids = (array) $request->property_ids;
        }
    
        try {
    
            $requestObj = new GuzzleRequest(
                'GET',
                $objectURL,
                $headers
            );
    
            $res = $client
                ->sendAsync($requestObj)
                ->wait();
    
            $responseBody = $res
                ->getBody()
                ->getContents();
    
            $res = json_decode($responseBody);
    
            /*
             * Validate response
             */
            if (
                !$res ||
                !isset($res->Items) ||
                count($res->Items) === 0
            ) {
                return [
                    '_error' => 'Object not found'
                ];
            }
    
            $objID = $res->Items[0]->DisplayID;
    
            $result = [];
    
            foreach ($propery_ids as $prop_id) {
    
                if (isset($customPropIDs[$prop_id])) {
    
                    $prop_name = $customPropIDs[$prop_id];
    
                } else {
    
                    $prop_def = $this->get_property_definition(
                        $headers,
                        $prop_id
                    );
    
                    $prop_name =
                        $prop_def['name']
                        ?? 'property_' . $prop_id;
                }
    
                $prop_value = $this->get_property_value(
                    $headers,
                    $objectID
                    . "/"
                    . $objID
                    . "/latest/properties/"
                    . $prop_id
                );
               
    
                /*
                 * Technical Description
                 */
                if ($prop_id == 1285 && $prop_value) {
    
                    $short_start =
                        explode(
                            'Bounded on the',
                            $prop_value
                        );
    
                    $short_end =
                        explode(
                            'point of beginning',
                            $prop_value
                        );
    
                    if (
                        isset($short_start[0]) &&
                        isset($short_end[1])
                    ) {
    
                        $prop_value =
                            $short_start[0]
                            . " Bounded on the XXXX to the point of beginning"
                            . $short_end[1];
    
                        $result['old_technical_desc'] =
                            $prop_value;
                    }
                }
    
                $result[$prop_name] = $prop_value;
            }
    
            return $result;
    
        } catch (\Throwable $e) {
    
            return [
                '_error' =>
                    'Error in searching object: '
                    . $e->getMessage()
            ];
        }
    }

    public function get_document_property_multi(Request $request, $ObjectID = null, $propertyID = null){

        //added only for CS lookup
        // if(config("cs.property_lookup")){
        //     // $pbl;
        //     try {

        //         // Example API endpoint
        //         if($request->propertyID===1050)
        //         {
        //             $baseURL = $this->getBaseURL($request->name);
        //             $url = "https://everyhome.enclavewrx.io/developers/api_storefront_seller/get_project_detail/".$request->name;
        //         }
        //         if($request->propertyID===1105)
        //         {
        //             $pbl=explode("-",$request->name);
        //             $baseURL = $this->getBaseURL($pbl[0]);

        //             $url = $baseURL."api_storefront_seller/get_property_detail/".$request->name;
        //         }
        //         $client = new Client();
        //         $response = $client->get($url);
        //         $response_message = json_decode($response->getBody(), true);

        //         return response()->json($response_message);
        //     } catch (\Exception $e) {
        //         return response()->json([
        //             'status' => 'error',
        //             'message' => 'Request error: ' . $e->getMessage()
        //         ], 500);
        //     }


        // }
        //end CS lookup
        $objectID = $objectID ?? $request->objectID;
        $propertyID = $propertyID ?? $request->propertyID;
        // dd($request->Documents[0]);
        $get_token = $this->get_token($request);
        $client = new Client();
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];
        $results=[];
        foreach($request->Documents as $document){
            $result = null;
            $objectURL =$this->baseURL()."/REST/objects/".$document['objectID']."?p".$document['propertyID']."=".$document['name'] ;
            $requestObj = new GuzzleRequest('GET', $objectURL, $headers);
            $res = $client->sendAsync($requestObj)->wait();
            $responseBody = $res->getBody()->getContents();
            $res = json_decode($responseBody);
            if(count($res->Items) == 0){
                return response()->json(['error' => 'Object not found'], 404);
            }
            $objID = $res->Items[0]->DisplayID;
            foreach ($document['property_ids'] as $index =>$prop_id){
                if(isset($document['mask_field'])&& $document['mask_field'][$index]){
                    $prop_def['name'] = $document['mask_field'][$index];
                }
                else{
                    $prop_def = $this->get_property_definition($headers,$prop_id);
                }
                $prop_value = $this->get_property_value($headers,$document['objectID']."/".$objID."/latest/properties/".$prop_id);
                if($prop_id == '1285'){
                    $short_start = explode('Bounded on the', $prop_value);
                    $short_end = explode('point of beginning', $prop_value);
                    $prop_value = ($short_start[0] ." Bounded on the XXXX to the point of beginning".$short_end[1]);
                }

                $result[$prop_def['name']] = $prop_value ;

            }
            // dd($result);
            $json_name = $document['objectID']===119?"inventory":"project";
            $results[$document['json_name']?$document['json_name']:$json_name] = $result;

            //  return response()->json($result);
            // $results[]= response()->json($result);

            // dd($headers);

        }
        return $results;
    }
    public function get_document_property_single(Request $request, $ObjectID = null, $propertyID = null, $propertyValue = null, $getPropertyID = null){

        $get_token = $this->get_token($request);
        $objectURL =$this->baseURL()."/REST/objects/".$ObjectID."?p".$propertyID."=".$propertyValue ;
        $client = new Client();
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];
        // dd($headers);
        try{
            $requestObj = new GuzzleRequest('GET', $objectURL, $headers);
            $res = $client->sendAsync($requestObj)->wait();
            $responseBody = $res->getBody()->getContents();
            $res = json_decode($responseBody);
            if(count($res->Items) == 0){
                return response()->json(['error' => 'Object not found'], 404);
            }
            $objID = $res->Items[0]->DisplayID;
            $prop_def = $this->get_property_definition($headers,$getPropertyID);
            $prop_value = $this->get_property_value($headers,$ObjectID."/".$objID."/latest/properties/".$getPropertyID);
            if($prop_value == null){
                return response()->json(['error' => 'property value is empty'], 404);
            }

            $result[$prop_def['name']] = $prop_value ;
            // return $result;
            return response()->json($prop_value);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error in searching object: ' . $e->getMessage()], 404);
        }
    }

    public function update_inventory_status(Request $request, $property_unit = null, $status = null){
        $objectID = 119; //unit inventory
        $propertyID = 1109; //os status
        $get_token = $this->get_token($request);
        $objectURL =$this->baseURL()."/REST/objects/".$objectID."?p1105=".$property_unit ;
        $client = new Client();
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];
        try{
            $requestObj = new GuzzleRequest('GET', $objectURL, $headers);
            $res = $client->sendAsync($requestObj)->wait();
            $responseBody = $res->getBody()->getContents();
            $res = json_decode($responseBody);
            if(count($res->Items) == 0){
                return response()->json(['error' => 'Object not found'], 404)->setStatusCode(404);
            }
            $objID = $res->Items[0]->DisplayID;

            $setProperties[] = [

                "PropertyDef" => $propertyID,
                "TypedValue" => [
                    "DataType" => 10,
                    "Lookups"=> [
                        [
                            "Item" => $status,
                            "Version" => -1
                        ]
                    ]
                ]
            ];
            $body = json_encode($setProperties);
            // dd($body);
            $objectURL =$this->baseURL()."/REST/objects/".$objectID."/".$objID."/latest/properties";
            // dd($objectURL);
            $request = new GuzzleRequest('POST', $objectURL, $headers, $body);
            $res = $client->sendAsync($request)->wait();
            $responseBody = $res->getBody()->getContents();
            return $responseBody;

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error in searching object: ' . $e->getMessage()], 500);
        }
        $get_token = $this->get_token($request);
        $objectURL =$this->baseURL()."/REST/objects/".$objectID."?p".$propertyID."=".$request->name ;
        $client = new Client();
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];
        // dd($headers);
        try{
            $requestObj = new GuzzleRequest('GET', $objectURL, $headers);
            $res = $client->sendAsync($requestObj)->wait();
            $responseBody = $res->getBody()->getContents();
            $res = json_decode($responseBody);
            $objID = $res->Items[0]->DisplayID;
            foreach ($request->property_ids as $prop_id){
                $prop_def = $this->get_property_definition($headers,$prop_id);
                $prop_value = $this->get_property_value($headers,$objectID."/".$objID."/latest/properties/".$prop_id);
                $result[$prop_def['name']] = $prop_value ;

            }
            return $result;
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error in searching object: ' . $e->getMessage()], 500);
        }
    }
    public function get_value_list(Request $request, $ID){

        $get_token = $this->get_token($request);
        $client = new Client();
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];

        $client = new Client();

        $objectURL =$this->baseURL()."/REST/valuelists/".$ID."/items" ;
        $request = new GuzzleRequest('GET', $objectURL, $headers);
        $res = $client->sendAsync($request)->wait();
        // $responseBody = $res->getBody()->getContents();
        $responseBody = json_decode($res->getBody()->getContents());
        return $responseBody;
    }
    public function download_file(Request $request){
        $get_token = $this->get_token($request);
        $properties = $request->Properties;
        $setProperties;
        //set document properties
        if($properties)
        {
            foreach ($properties as $property)
            {
                $currProperty = [
                    "PropertyDef" => $property['ID'],
                    "TypedValue" => [
                        "DataType" => 1,
                        "Value" => $property['Value']
                    ]
                ];
                $setProperties[] = $currProperty;
            }
        }

        //set true if document accept multiple files
        $setProperties[] = [
            "PropertyDef" => 22,
            "TypedValue" => [
                "DataType" => 8,
                "Value" => false
            ]
        ];


        //set document class
        $setProperties[] = [
            "PropertyDef" => 100,
            "TypedValue" => [
                "DataType" => 9,
                "Lookup"=>[
                    "Item" => $request->classId,
                    "Version" => -1
                ]
            ]
        ];
        $body = '{"PropertyValues":'.json_encode($setProperties).',"Files": []}';
        $bodyJson = [
            "PropertyValues" => $setProperties,
            "Files" => [] ];

        $client = new Client();
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];

        $client = new Client();

        $objectURL =$this->baseURL()."/REST/objects/".$request->input('objectId')."?checkIn=true" ;
        // dd($objectURL);
        $request = new GuzzleRequest('POST', $objectURL, $headers, $body);
        $res = $client->sendAsync($request)->wait();
        $responseBody = $res->getBody()->getContents();
        return $responseBody;

    }
    public function get_object(Request $request){

        $get_token = $this->get_token($request);
        $objectURL =$this->baseURL()."/REST/objects/".$request->objectID."?p".$request->propertyID."=".$request->name ;
        $client = new Client();
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];
        try{
            $requestObj = new GuzzleRequest('GET', $objectURL, $headers);
            $res = $client->sendAsync($requestObj)->wait();
            $responseBody = $res->getBody()->getContents();
            $res = json_decode($responseBody);
            $objID = $res->Items[0]->DisplayID;
            $objPropertyURL =$this->baseURL()."/REST/objects/".$request->objectID."/".$objID."/latest?include=properties";
            $requestProp = new GuzzleRequest('GET', $objPropertyURL, $headers);
            $resProp = $client->sendAsync($requestProp)->wait();
            $responseBodyProp = json_decode($resProp->getBody()->getContents());
            return $responseBodyProp;
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error in searching object: ' . $e->getMessage()], 500);
        }
    }
    public function get_property_definition($headers,$propId){
        $client = new Client();
        try{
            $objectURL =$this->baseURL()."/REST/structure/properties/".$propId;
            $requestProp = new GuzzleRequest('GET', $objectURL, $headers);
            $resProp = $client->sendAsync($requestProp)->wait();
            $responseBodyProp = json_decode($resProp->getBody()->getContents());
            return ["dataType" => $responseBodyProp->DataType , "name" =>$responseBodyProp->Name];

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error in getting property name: ' . $e->getMessage()], 500);
        }
    }
    public function get_property_value($headers,$paramProperty)
    {
        $client = new Client();
        try{
            $objPropertyURL =$this->baseURL()."/REST/objects/".$paramProperty;
            $requestProp = new GuzzleRequest('GET', $objPropertyURL, $headers);
            $resProp = $client->sendAsync($requestProp)->wait();
            $responseBodyProp = json_decode($resProp->getBody()->getContents());
            return $responseBodyProp->TypedValue->DisplayValue;
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error in getting property value: ' . $e->getMessage()], 500);
        }
    }
    public function get_technical_description(Request $request, $propertyValue = null){
        //added only for CS lookup
       
        // if(config("cs.property_lookup")){
        //     try {
        //         // Example API endpoint
        //         $project_code = explode( "-",$propertyValue);
        //         $baseURL = $this->getBaseURL($project_code[0]);
        //         $url = $baseURL.'api_storefront_seller/get_technical_description/'.$propertyValue;
        //         $client = new Client();
        //         $response = $client->get($url);
        //         $response_message = (string) $response->getBody();

        //         dd($url);
        //         return response()->json($response_message);

        //     } catch (\Exception $e) {
        //         return response()->json([
        //             'status' => 'error',
        //             'message' => 'Request error: ' . $e->getMessage()
        //         ], 500);
        //     }
        // }
        //end CS lookup
        $get_token = $this->get_token($request);
        $objectURL =$this->baseURL()."/REST/objects/119?p1105=".$propertyValue ;
        $client = new Client();
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];
        // dd($headers);
        try{
            $requestObj = new GuzzleRequest('GET', $objectURL, $headers);
            $res = $client->sendAsync($requestObj)->wait();
            $responseBody = $res->getBody()->getContents();
            $res = json_decode($responseBody);
            if(count($res->Items) == 0){
                return response()->json(['error' => 'Object not found'], 404);
            }
            $objID = $res->Items[0]->DisplayID;
            $prop_def = $this->get_property_definition($headers,'1285');
            $prop_value = $this->get_property_value($headers,"119/".$objID."/latest/properties/1285");
            if($prop_value == null){
                return response()->json(['error' => 'property value is empty'], 404);
            }

            $short_start = explode('Bounded on the', $prop_value);
            $short_end = explode('point of beginning', $prop_value);
            $short_str = ($short_start[0] ." Bounded on the XXXX to the point of beginning".$short_end[1]);

            // $result[$prop_def['name']] = trim($short_str) ;
            // return $result;
            return response()->json(trim($short_str));
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error in searching object: ' . $e->getMessage()], 404);
        }
    }
    public function getBaseURL($project_code){
        $rdg = json_decode(config('cs.rdg_code', '[]'), true);
        $ehdg = json_decode(config('cs.ehdg_code', '[]'), true);
        $shdg = json_decode(config('cs.shdg_code', '[]'), true);

        if (in_array($project_code, $rdg)) {
            $baseURL = "https://elanvital.enclavewrx.io/developers/";
        }
        else if (in_array($project_code, $ehdg)) {
            $baseURL = "https://everyhome.enclavewrx.io/developers/";
        }
        else if (in_array($project_code, $shdg)) {
            $baseURL = "https://extraordinary.enclavewrx.io/developers/";
        }
        else {
            $baseURL = "https://elanvital.enclavewrx.io/developers/";
        }

        return $baseURL;
    }

    public function view_storefront_video(Request $request, $fileId)
    {
        $get_token = $this->get_token($request);
        $objectId = config("mfiles.mfile_sf_obj_id");
        $client = new Client();

        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];

        try {
            $objectURL = $this->baseURL() . "/REST/objects/0/{$objectId}/files/{$fileId}/content.aspx";
            $rangeHeader = $request->header('Range', 'bytes=0-');

            $response = $client->get($objectURL, [
                'headers' => array_merge($headers, [
                    'Range' => $rangeHeader,
                ]),
                'stream' => true,
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody();

            return response()->stream(function () use ($body) {
                while (!$body->eof()) {
                    echo $body->read(1024 * 8);
                    ob_flush();
                    flush();
                }
            }, $statusCode, [
                'Content-Type' => 'video/mp4',
                'Content-Disposition' => 'inline; filename="video.mp4"',
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'no-cache',
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Video not found or cannot be streamed'], 500);
        }
    }

    public function get_inventory_list($headers, $project = null,$propertyCode = null)
    {
        $objectID = 119;// $objectID ?? $request->objectID;
        $propertyID = 1105;// $propertyID ?? $request->propertyID;
        $client = new Client();
        $inventory_list = [];

        try {
            $objPropertyURL = $this->baseURL() . "/REST/objects/" . $objectID . "?limit=99999";
            if($project){
                $objPropertyURL = $objPropertyURL."&p1050=".$project ;
            }
            if($propertyCode){
                $objPropertyURL = $objPropertyURL."&p1105=".$propertyCode ;
            }
            
            $requestProp = new GuzzleRequest('GET', $objPropertyURL, $headers);
            $resProp = $client->sendAsync($requestProp)->wait();
            $responseBodyProp = json_decode($resProp->getBody()->getContents());
            $arr_inventory = $responseBodyProp->Items ?? [];
            if (empty($arr_inventory)) {
                return [];
            }
            foreach ($arr_inventory as $inv_item) {
                // $inventory_list[]=
                $inventory_list[] = $inv_item->Title;
            }
            $inventory_list = array_values(
                array_unique($inventory_list)
            );
            // dd($inventory_list);
            return $inventory_list;
        } catch (\Throwable $exception) {
            \Log::error('Unable to retrieve the M-Files inventory list.', [
                'project_code' => $project,
                'property_code' => $propertyCode,
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function sync_properties(Request $request, MFilesInventorySyncState $state)
    {
        $projectCode = $request->projectCode ?? null;
        $propertyCode = $request->propertyCode ?? null;
        $propertyCodes = $request->input('propertyCodes', []);
        $syncRequestId = $request->header('X-Sync-Request-ID');

        if (! is_string($syncRequestId) || ! Str::isUuid($syncRequestId)) {
            return response()->json([
                'message' => 'A valid X-Sync-Request-ID header is required.',
            ], 422);
        }

        $validator = validator([
            'property_codes' => $propertyCodes,
        ], [
            'property_codes' => ['array', 'max:5000'],
            'property_codes.*' => ['required', 'string', 'max:255', 'distinct'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $propertyCodes = array_values(array_unique(array_map(
            fn (mixed $code): string => trim((string) $code),
            $propertyCodes,
        )));
        $filterCount = (filled($projectCode) ? 1 : 0)
            + (filled($propertyCode) ? 1 : 0)
            + ($propertyCodes !== [] ? 1 : 0);

        if ($filterCount !== 1) {
            return response()->json([
                'message' => 'Provide exactly one of projectCode, propertyCode, or propertyCodes.',
            ], 422);
        }

        if (! $state->accept($syncRequestId, $projectCode, $propertyCode, count($propertyCodes))) {
            $snapshot = $state->snapshot($syncRequestId);

            if (in_array($snapshot['status'] ?? null, ['queued', 'running'], true)) {
                return response()->json([
                    'message' => 'M-Files synchronization was already accepted.',
                    'status' => 'accepted',
                    'sync_request_id' => $syncRequestId,
                ], 202);
            }

            return response()->json([
                'message' => 'This sync request ID has already finished and cannot be reused.',
                'status' => $snapshot['status'] ?? 'unknown',
            ], 409);
        }

        try {
            PrepareMFilesInventorySync::dispatch($syncRequestId, $projectCode, $propertyCode, $propertyCodes)
                ->onConnection('database-mfiles')
                ->onQueue('mfiles-sync');
        } catch (\Throwable $exception) {
            $state->forget($syncRequestId);

            throw $exception;
        }

        return response()->json([
            'message' => 'M-Files synchronization was accepted for background processing.',
            'status' => 'accepted',
            'sync_request_id' => $syncRequestId,
        ], 202);
    }

    public function sync_properties_legacy(Request $request)
    {
        set_time_limit(0);
        ini_set('max_execution_time', '0');

        $objectID = 119;
        $propertyID = 1105;
        $projectCode = $request->projectCode ?? null;
        $propertyCode = $request->propertyCode ?? null;

        $get_token = $this->get_token($request);

        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];

        $inventory_list = $this->get_inventory_list(
            $headers,
            $projectCode,
            $propertyCode
        );
        
        if (empty($inventory_list)) {
            return response()->json([
                'message' => 'No Inventory Found',
                'success' => 0,
                'failed' => 0,
            ], 200);
        }
        // dd($inventory_list[76]);
        $result = [
            'success' => 0,
            'failed' => 0,
        ];
        foreach ($inventory_list as $index => $inventory) {

            $propertyCode = EncryptedRouteParameter::fromConfig()
                ->encrypt($inventory);

            $request->name = $inventory;
            $request->property_ids = [
                1105, 1024, 1290, 1050, 1029,
                1107, 1109, 1203, 1204, 1202, 1285
            ];

            $propertyValue = $this->get_document_property(
                $request,
                $objectID,
                $propertyID
            );

            $propData = $propertyValue;

            // $request->name = $propData['Project Code'] ?? null;
            $request->name = $projectCode;
            $request->property_ids = [1293, 1294];

            $projectValue = $this->get_document_property(
                $request,
                101,
                1050
            );
            $projData = $projectValue;
            // dd($projData['Project Developer']);
            $body = [
                "company_name" => !empty($projData['Project Developer'])
                    ? $projData['Project Developer'] : "-",

                "registry_of_deeds" => !empty($projData['Deed of Registry'])
                    ? $projData['Deed of Registry'] : "-",

                "tct" => !empty($propData['TCT No.'])
                    ? $propData['TCT No.'] : "-",

                "vsr" => !empty($propData['VSR'])
                    ? $propData['VSR'] : "-",

                "old_technical_desc" => !empty($propData['Technical Description'])
                    ? $propData['Technical Description'] : "-",

                "survey_plan_no" => !empty($propData['VSR'])
                    ? $propData['VSR'] : "-",

                "block_no" => !empty($propData['Block No.'])
                    ? $propData['Block No.'] : "-",

                "lot_no" => !empty($propData['Lot'])
                    ? $propData['Lot'] : "-",

                "portion_of_lot" => !empty($propData['portion_of_lot'])
                    ? $propData['portion_of_lot'] : "-",

                "lrc_record_no" => !empty($propData['lrc_record_no'])
                    ? $propData['lrc_record_no'] : "-",

                "land_owner_claimant" => !empty($propData['land_owner_claimant'])
                    ? $propData['land_owner_claimant'] : "-",

                "location" => !empty($propData['location'])
                    ? $propData['location'] : "-",

                "area" => !empty($propData['area'])
                    ? $propData['area'] : "-",

                "description_of_corners" => !empty($propData['description_of_corners'])
                    ? $propData['description_of_corners'] : "-",

                "bearings" => !empty($propData['bearings'])
                    ? $propData['bearings'] : "-",

                "original_date_of_survey" => !empty($propData['original_date_of_survey'])
                    ? $propData['original_date_of_survey'] : "-",

                "date_of_survey" => !empty($propData['date_of_survey'])
                    ? $propData['date_of_survey'] : "-",

                "date_approved" => !empty($propData['date_approved'])
                    ? $propData['date_approved'] : "-",

                "geodetic_engineer" => !empty($propData['geodetic_engineer'])
                    ? $propData['geodetic_engineer'] : "-"
            ];

            // Convert bearings
            // if ($body['bearings'] === "TRUE") {
            //     $body['bearings'] = 1;
            // } elseif ($body['bearings'] === "") {
            //     $body['bearings'] = 0;
            // }

            $propUpdateRes = $this->update_properties(
                json_encode($body),
                $propertyCode,
                'TEST-UUID',
            );

            $propContent = $propUpdateRes;
            // Safely get message
            $message = $propContent['data']['message'] ?? null;
            // dd($message);

            if ($message === "Technical description updated successfully.") {
                $result['success']++;
            } else {
                $result['failed']++;
                $result['failed reason'] = $inventory. ":" . $message;
            }
        }

        return response()->json([
            'message' => 'Property sync completed.',
            'success' => $result['success'],
            'failed' => $result['failed'],
            'total' => $result['success'] + $result['failed'],
        ], 200);
    }

    public function update_properties($body, $propertyCode, $syncRequestId)
    {
        $client = new Client([
            'timeout' => 60,
            'connect_timeout' => 15,
            'http_errors' => false,
        ]);

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . config('storefront.property_api_key'),
            'X-Sync-Request-ID' => $syncRequestId,
        ];

        $propertyURL = rtrim(
            config('storefront.property_url'),
            '/'
        ) . '/api/technical-descriptions/' . $propertyCode;
        $maxRetries = 3;
        $retryCount = 0;

        while (true) {

            try {
                $response = $client->post($propertyURL, [
                    'headers' => $headers,
                    'body' => $body,
                ]);

                $statusCode = $response->getStatusCode();

                $responseBody = $response
                    ->getBody()
                    ->getContents();

                $responseData = json_decode(
                    $responseBody,
                    true
                );
                if (
                    $statusCode >= 200 &&
                    $statusCode < 300
                ) {
                    return [
                        'status' => $statusCode,
                        'data' => $responseData,
                        'retry_count' => $retryCount,
                    ];
                }

                if ($statusCode === 429) {

                    if ($retryCount >= $maxRetries) {

                        return [
                            'status' => 429,
                            'data' => $responseData,
                            'error' =>
                                $responseData['message']
                                ?? 'Maximum retry attempts reached due to rate limiting.',
                            'retry_count' => $retryCount,
                        ];
                    }

                    $retryCount++;
                    $retryAfter = trim(
                        $response->getHeaderLine('Retry-After')
                    );

                    if (
                        $retryAfter !== '' &&
                        is_numeric($retryAfter)
                    ) {

                        $sleepSeconds = max(
                            1,
                            (int) $retryAfter
                        );

                    } else {

                        $sleepSeconds = min(
                            2 ** $retryCount,
                            60
                        );
                    }

                    \Log::warning(
                        'Properties API rate limited.',
                        [
                            'property_code' => $propertyCode,
                            'retry' => $retryCount,
                            'max_retries' => $maxRetries,
                            'sleep_seconds' => $sleepSeconds,
                        ]
                    );

                    sleep($sleepSeconds);

                    continue;
                }

                return [
                    'status' => $statusCode,
                    'data' => $responseData,
                    'error' =>
                        $responseData['message']
                        ?? 'Property update failed.',
                    'retry_count' => $retryCount,
                ];

            } catch (RequestException $e) {

                $statusCode = null;
                $responseData = null;

                if ($e->hasResponse()) {

                    $errorResponse = $e->getResponse();

                    $statusCode =
                        $errorResponse->getStatusCode();

                    $responseBody =
                        $errorResponse
                            ->getBody()
                            ->getContents();

                    $responseData = json_decode(
                        $responseBody,
                        true
                    );

                    if ($statusCode === 429) {

                        if ($retryCount >= $maxRetries) {

                            return [
                                'status' => 429,
                                'data' => $responseData,
                                'error' =>
                                    $responseData['message']
                                    ?? 'Maximum retry attempts reached due to rate limiting.',
                                'retry_count' => $retryCount,
                            ];
                        }

                        $retryCount++;

                        $retryAfter = trim(
                            $errorResponse
                                ->getHeaderLine('Retry-After')
                        );

                        if (
                            $retryAfter !== '' &&
                            is_numeric($retryAfter)
                        ) {

                            $sleepSeconds = max(
                                1,
                                (int) $retryAfter
                            );

                        } else {

                            $sleepSeconds = min(
                                2 ** $retryCount,
                                60
                            );
                        }

                        \Log::warning(
                            'Properties API rate limited.',
                            [
                                'property_code' => $propertyCode,
                                'retry' => $retryCount,
                                'max_retries' => $maxRetries,
                                'sleep_seconds' => $sleepSeconds,
                            ]
                        );

                        sleep($sleepSeconds);

                        continue;
                    }
                }

                return [
                    'status' => $statusCode ?? 500,
                    'data' => $responseData,
                    'error' => $e->getMessage(),
                    'retry_count' => $retryCount,
                ];

            } catch (\Throwable $e) {

                return [
                    'status' => 500,
                    'data' => null,
                    'error' => $e->getMessage(),
                    'retry_count' => $retryCount,
                ];
            }
        }
    }

    private function report_sync_progress($syncRequestId, $propertyCode, $status, $message = null, $total = null, $requireSuccess = false)
    {
        if (empty($syncRequestId)) {
            return;
        }

        $client = new Client([
            'timeout' => 60,
            'connect_timeout' => 15,
            'http_errors' => false,
        ]);

        try {
            $response = $client->post(rtrim(config('storefront.property_url'), '/') . '/api/technical-description-sync-progress', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . config('storefront.property_api_key'),
                    'X-Sync-Request-ID' => $syncRequestId,
                ],
                'json' => [
                    'property_code' => $propertyCode,
                    'status' => $status,
                    'message' => $message,
                    'total' => $total,
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode < 200 || $statusCode >= 300) {
                \Log::warning('Properties rejected sync progress.', [
                    'sync_request_id' => $syncRequestId,
                    'property_code' => $propertyCode,
                    'progress_status' => $status,
                    'http_status' => $statusCode,
                    'response' => Str::limit((string) $response->getBody(), 1000),
                ]);

                if (in_array($statusCode, [401, 403, 404, 409], true)) {
                    \Log::warning('Stopping M-Files property sync because Properties rejected the active run.', [
                        'sync_request_id' => $syncRequestId,
                        'property_code' => $propertyCode,
                        'http_status' => $statusCode,
                    ]);

                    return false;
                }

                if ($requireSuccess) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable $exception) {
            \Log::warning('Unable to report technical description sync progress.', [
                'sync_request_id' => $syncRequestId,
                'property_code' => $propertyCode,
                'status' => $status,
                'message' => $exception->getMessage(),
            ]);

            return $requireSuccess ? false : null;
        }
    }
}
