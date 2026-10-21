<?php

namespace App\Http\Controllers;

use App\Helpers\MfilesApiLogHelper;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Psr7\MultipartStream;
use Illuminate\Support\Facades\Crypt;
use App\Support\EncryptedRouteParameter;


use Carbon\Carbon;

class ProjectController extends Controller
{
    private function baseURL()
    {
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
    public function request_catcher(Request $request)
    {
        dd($request);
    }
    public function process_property($array)
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
        $datatype = $array['DataType'] ?? null;

        switch ($array['DataType']) {
            case 'MultiLookup':
                $property = [
                    "PropertyDef" => $array['ID'],
                    "TypedValue" => [
                        "DataType" => $this->get_datatype($array['DataType']),
                        "Lookups" => [
                            "Item" => $array['ObjID'] ?? $array['Value'],
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
                            "Item" => $array['ObjID'] ?? $array['Value'],
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

    public function user_information()
    {
        $get_token = $this->get_token($request);
        $sessionURL = $this->baseURL() . "/REST/session";
        // Get the uploaded file from the request
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];

        // dd($response_content);

        $client = new Client();
        try {
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
        $authURL = $this->baseURL() . '/REST/server/authenticationtokens';
        $username = !$request->Credentials ? config("mfiles.mfiles_credentials.username") : $request->Credentials['Username'];
        $password = !$request->Credentials ? config("mfiles.mfiles_credentials.password") : $request->Credentials['Password'];
        $credential = [
            'Username' => $username,
            'Password' => $password,
            'VaultGuid' => config("mfiles.mfiles_vaultid") // $request->input('vaultid') -- change to Param if multiple vault
        ];

        $client = new Client();
        try {
            $response = $client->post($authURL, [
                'json' => $credential,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]);

            $setCookies = null;
            $setCookie = $response->getHeader('Set-Cookie');
            if ($setCookie) {
                foreach ($setCookie as $cookie) {
                    $array = explode(';', $cookie);
                    $setCookies = $setCookies == NULL ? $array[0] : $setCookies . ";" . $array[0];
                }
            }
            $response_message = json_decode($response->getBody()->getContents());
            return ["token" => $response_message->Value, "setCookie" => $setCookies];

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error in generating Token: ' . $e->getMessage()], 500);
        }
    }

    public function checkProjectCode($get_token, $project_code)
    {
        
        $objectID = 101;// $objectID ?? $request->objectID;
        // $propertyID = 1050;// $propertyID ?? $request->propertyID;
        $client = new Client();
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];
        try {
            $objProjectURL = $this->baseURL() . "/REST/objects/" . $objectID . "?limit=99999";
            $objProjectURL = $objProjectURL . "&p1050=" . $project_code;
            // dd( $objPropertyURL);
            $requestProject = new GuzzleRequest('GET', $objProjectURL, $headers);
            $resProject = $client->sendAsync($requestProject)->wait();
            $responseBodyProject = json_decode($resProject->getBody()->getContents());
            $response = $responseBodyProject->Items[0]->DisplayID??null;
            if($response){
            $objPropertyURL = $this->baseURL() . "/REST/objects/". $objectID ."/". $response ."/latest/properties";
            // dd( $objPropertyURL);
            $requestProp = new GuzzleRequest('GET', $objPropertyURL, $headers);
            $resProp = $client->sendAsync($requestProp)->wait();
            $responseBodyProp = json_decode($resProp->getBody()->getContents());
            
            foreach ($responseBodyProp as $property) {
                if ($property->PropertyDef==1050){
                    // dd($property->Value->Value);
                    if ($property->Value->Value==$project_code) {
                        return true;
                    } else {
                        return false;
                    }
                };
            }
            // dd( $responseBodyProp);
            // $response = $responseBodyProject->Items[0]->DisplayID??null;
            // if ($response==$project_code) {
            //     return true;
            // } else {
            //     return false;
            // }
            }
        } catch (\Exception $e) {
            dd($e);
            return response()->json(['error' => 'Error in getting project: ' . $e->getMessage()], 500);
        }
    }
    public function create_project(Request $request)
    {
        $startTime = microtime(true);
        $get_token = $this->get_token($request);
        $developers = $this->get_value_list($request, 156);
        $checkProjectCode = $this->checkProjectCode($get_token, $request->project_code);
        if ($checkProjectCode) {
            $durationMs = round( (microtime(true) - $startTime) * 1000 );
            MfilesApiLogHelper::log( 
                apiCalled: config('app.url').'/api/mfiles/create/project',
                httpMethod: 'POST',
                requestBody: $request->input(), 
                response: [
                    'message' => "Project Code already existing",
                ], 
                statusCode: 200, 
                durationMs: $durationMs 
            );
            return response()->json([
                'message' => "Project Code already existing",
            ], 200);
        }
        foreach ($developers->Items as $developer) {
            // dd($developer );
            if ($developer->Name == $request->project_developer) {
                $devId = $developer->ID;
            }
        }
        $properties = [
            [
                "ID" => 1049,
                "DataType" => "Text",
                "Value" => $request->project_name
            ],
            [
                "ID" => 1050,
                "DataType" => "Text",
                "Value" => $request->project_code
            ],
            [
                "ID" => 1293,
                "DataType" => "Lookup",
                "Value" => $devId
            ],
            [
                "ID" => 1294,
                "DataType" => "Text",
                "Value" => $request->deed_of_registry
            ],
            [
                "ID" => 1067,
                "DataType" => "Boolean",
                "Value" => 0
            ]
        ];
        $setProperties = [];
        // Set project properties
        foreach ($properties as $property) {
            $setProperties[] = $this->process_property($property);
        }


        // dd($setProperties);
        // Set class
        $setProperties[] = [
            "PropertyDef" => 100,
            "TypedValue" => [
                "DataType" => 9,
                "Lookup" => [
                    "Item" => 2,
                    "Version" => -1
                ]
            ]
        ];

        // Create fileless Land Project object
        $bodyJson = [
            "PropertyValues" => $setProperties,
            "Files" => []
        ];
        // dd($bodyJson);
        $client = new Client();

        $objectURL = $this->baseURL() . "/REST/objects/101?checkIn=true";

        try {

            $res = $client->post($objectURL, [
                'headers' => [
                    'x-authentication' => $get_token['token'],
                    'Content-Type' => 'application/json',
                    'Cookie' => $get_token['setCookie'],
                ],
                'json' => $bodyJson,
            ]);
            $responseBody = json_decode($res->getBody()->getContents(),true);
            // $responseBuild = [
            //     "message"=>"Project Created Successfully",
            //     "projectMfilesID" => $responseBody->DisplayID

            // ];

            $durationMs = round( (microtime(true) - $startTime) * 1000 );
            MfilesApiLogHelper::log( 
                apiCalled: config('app.url').'/api/mfiles/create/project',
                httpMethod: 'POST',
                requestBody: $request->input(), 
                response: [
                    "message"=>"Project Created Successfully",
                    "projectMfilesID" => $responseBody['DisplayID'],
                    "response" => $responseBody
                ], 
                statusCode: 200, 
                durationMs: $durationMs 
            );
            return response()->json([
                "message"=>"Project Created Successfully",
                "projectMfilesID" => $responseBody['DisplayID'],
                "response" => $responseBody
            ], 200);
            // return $responseBody;

        } catch (\GuzzleHttp\Exception\ClientException $e) {

            $response = $e->getResponse();

            $errorBody = $response
                ? $response->getBody()->getContents()
                : null;

            $durationMs = round( (microtime(true) - $startTime) * 1000 );
            MfilesApiLogHelper::log( 
                apiCalled: config('app.url').'/api/mfiles/create/project',
                httpMethod: 'POST',
                requestBody: $request->input(), 
                response: [
                    'status' => $response?->getStatusCode(),
                    'error' => json_decode($errorBody, true),
                    'raw_error' => $errorBody,
                ], 
                statusCode: $response?->getStatusCode() ?? 500, 
                durationMs: $durationMs 
            );

            return response()->json([
                'status' => $response?->getStatusCode(),
                'error' => json_decode($errorBody, true),
                'raw_error' => $errorBody,
            ], $response?->getStatusCode() ?? 500);
        }
    }

    public function update_project(Request $request, $projectID)
    {
        $startTime = microtime(true);
        $get_token = $this->get_token($request);
        $developers = $this->get_value_list($request, 156);
        // $checkProjectCode = $this->checkProjectCode($get_token, $request->project_code);
        // if ($checkProjectCode==false) {
        //     return response()->json([
        //         'message' => "Project Code not existing",
        //     ], 200);
        // }
        // dd($checkProjectCode);
        foreach ($developers->Items as $developer) {
            if ($developer->Name == $request->project_developer) {
                $devId = $developer->ID;
            }
        }
        $properties = [
            [
                "ID" => 1049,
                "DataType" => "Text",
                "Value" => $request->project_name
            ],
            [
                "ID" => 1050,
                "DataType" => "Text",
                "Value" => $request->project_code
            ],
            [
                "ID" => 1293,
                "DataType" => "Lookup",
                "Value" => $devId
            ],
            [
                "ID" => 1294,
                "DataType" => "Text",
                "Value" => $request->deed_of_registry
            ]
        ];
        $setProperties = [];
        // Set project properties
        foreach ($properties as $property) {
            $setProperties[] = $this->process_property($property);
        }

        //Set if document accept multiple files
        $setProperties[] = [
            "PropertyDef" => 22,
            "TypedValue" => [
                "DataType" => 8,
                "Value" => false
            ]
        ];
        // dd($setProperties);
        // Set class
        $setProperties[] = [
            "PropertyDef" => 100,
            "TypedValue" => [
                "DataType" => 9,
                "Lookup" => [
                    "Item" => 2,
                    "Version" => -1
                ]
            ]
        ];

        // Create fileless Land Project object
        $bodyJson = [
            "PropertyValues" => $setProperties,
            "Files" => []
        ];
        // dd(json_encode($setProperties));
        $client = new Client();

        // $objectURL = $this->baseURL() . "/REST/objects/101?checkIn=true";

        $checkoutProject = $this->checkInOutDocument($projectID, $get_token, 1);
        $objectURL = $this->baseURL() . "/REST/objects/101/".$projectID."/latest/properties?_method=PUT";
        try {

            $res = $client->post($objectURL, [
                'headers' => [
                    'x-authentication' => $get_token['token'],
                    'Content-Type' => 'application/json',
                    'Cookie' => $get_token['setCookie'],
                ],
                'json' => $setProperties,
            ]);

            $responseBody = json_decode(
                $res->getBody()->getContents(),
                true
            );
            $checkoutProject = $this->checkInOutDocument($projectID, $get_token, 0);
            // return $responseBody;

            $durationMs = round( (microtime(true) - $startTime) * 1000 );
            MfilesApiLogHelper::log( 
                apiCalled: config('app.url').'/api/mfiles/update/project/'.$projectID,
                httpMethod: 'POST',
                requestBody: $request->input(), 
                response: [
                    "message"=>"Project Updated Successfully",
                    "projectMfilesID" => $responseBody['DisplayID'],
                    "response" => $responseBody
                ], 
                statusCode: 200, 
                durationMs: $durationMs 
            );
             return response()->json([
                "message"=>"Project Updated Successfully",
                "projectMfilesID" => $responseBody['DisplayID'],
                "response" => $responseBody
            ], 200);

        } catch (\GuzzleHttp\Exception\ClientException $e) {

            $response = $e->getResponse();

            $errorBody = $response
                ? $response->getBody()->getContents()
                : null;

            $durationMs = round( (microtime(true) - $startTime) * 1000 );
            MfilesApiLogHelper::log( 
                apiCalled: config('app.url').'/api/mfiles/update/project/'.$projectID,
                httpMethod: 'POST',
                requestBody: $request->input(), 
                response: [
                    'status' => $response?->getStatusCode(),
                    'error' => json_decode($errorBody, true),
                    'raw_error' => $errorBody,
                ], 
                statusCode: $response?->getStatusCode() ?? 500, 
                durationMs: $durationMs 
            );
            return response()->json([
                'status' => $response?->getStatusCode(),
                'error' => json_decode($errorBody, true),
                'raw_error' => $errorBody,
            ], $response?->getStatusCode() ?? 500);
        }
    }
    public function checkInOutDocument($documentID, $get_token, int $method)
    {
        try {
            $client = new Client();

            $headers = [
                'x-authentication' => $get_token['token'],
                'Content-Type' => 'application/json',
                'Cookie' => $get_token['setCookie']
            ];
            $checkoutURL = $this->baseURL() . "/REST/objects/101/{$documentID}/latest/checkedout?_method=PUT";
            $response = $client->post($checkoutURL, [
                'headers' => $headers,
                'json' => ['Value' => $method]
            ]);

            $res_data = json_decode(
                $response->getBody()->getContents(),
                true
            );
            // dd($res_data);
            return $res_data['ObjectCheckedOut'] ?? false;

        } catch (\Exception $e) {
            return response()->json(['error' => 'Project not checked out: ' . $e->getMessage()], 500);
        }

    }
    public function upload_file_url(Request $request)
    {
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
        } else {
            return "File not found in " . $request->filePath;
        }
        $classId = (int) $request->classID;
        $objId = $request->objectId;
        $client = new Client();
        $objectURL = $this->baseURL() . "/REST/files";
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
            "Size" => $response_content->Size,
            "Title" => $fileName,
            "Extension" => $ext
        ];
        // dd($upload_response);
        //create object 
        $setProperties;
        // dd($properties);
        if ($properties) {
            foreach ($properties as $property) {
                $currProperty = $this->process_property($property);
                $setProperties[] = $currProperty;
            }
        }
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
                "Lookup" => [
                    "Item" => $classId,
                    "Version" => -1
                ]
            ]
        ];
        // dd($setProperties);
        $body = '{"PropertyValues":' . json_encode($setProperties) . ',"Files": [' . json_encode($upload_response) . ']}';
        $bodyJson = [
            "PropertyValues" => $setProperties,
            "Files" => $upload_response
        ];


        // dd($bodyJson);
        $client = new Client();
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];
        // dd($headers);


        try {
            $objectURL = $this->baseURL() . "/REST/objects/" . $objId . "?checkIn=true";
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
    public function upload_file(Request $request)
    {
        $get_token = $this->get_token($request);
        $fileName = $request->upload->getClientOriginalName();
        $ext = $request->upload->getClientOriginalExtension();
        $classId = (int) $request->classID;
        // dd( $classId);
        $client = new Client();
        $objectURL = $this->baseURL() . "/REST/files";
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
            "Size" => $response_content->Size,
            "Title" => $fileName,
            "Extension" => $ext
        ];

        //create object 
        //Set document properties
        $setProperties[] = [
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
                "Lookup" => [
                    "Item" => $classId,
                    "Version" => -1
                ]
            ]
        ];
        $body = '{"PropertyValues":' . json_encode($setProperties) . ',"Files": [' . json_encode($upload_response) . ']}';
        $bodyJson = [
            "PropertyValues" => $setProperties,
            "Files" => $upload_response
        ];

        // dd($body);
        // dd($get_token);
        $client = new Client();
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];
        // dd($headers);


        try {
            $objectURL = $this->baseURL() . "/REST/objects/104?checkIn=true";
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
        $objectId = config("mfiles.mfile_sf_obj_id");

        $uploadFile = $request->file('uploadFile');
        $fileName = $uploadFile->getClientOriginalName();
        $objectURL = $this->baseURL() . "/REST/objects/0/{$objectId}/files";

        try {
            $multipart = [
                [
                    'name' => 'file',
                    'contents' => Utils::tryFopen($uploadFile->getPathname(), 'r'),
                    'filename' => Carbon::now()->timestamp . "_" . $fileName,
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
    public function view_storefront_document(Request $request, $fileId)
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
            $objectURL = $this->baseURL() . "/REST/objects/0/" . $objectId . "/files/" . $fileId . "/content.aspx?format=pdf";
            $request = new GuzzleRequest('GET', $objectURL, $headers);
            $res = $client->sendAsync($request)->wait();
            $responseBody = $res->getBody()->getContents();


            return response($responseBody, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="document.pdf"');
            // dd($responseBody);
        } catch (\Exception $e) {
            // dd($e->getResponse()->getBody()->getContents());
            return response()->json(['error' => 'Document not found'], 500);
        }

    }
    public function get_document_property_multi(Request $request, $ObjectID = null, $propertyID = null)
    {
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
        $results = [];
        foreach ($request->Documents as $document) {
            $result = null;
            $objectURL = $this->baseURL() . "/REST/objects/" . $document['objectID'] . "?p" . $document['propertyID'] . "=" . $document['name'];
            $requestObj = new GuzzleRequest('GET', $objectURL, $headers);
            $res = $client->sendAsync($requestObj)->wait();
            $responseBody = $res->getBody()->getContents();
            $res = json_decode($responseBody);
            if (count($res->Items) == 0) {
                return response()->json(['error' => 'Object not found'], 404);
            }
            $objID = $res->Items[0]->DisplayID;
            foreach ($document['property_ids'] as $index => $prop_id) {
                if (isset($document['mask_field']) && $document['mask_field'][$index]) {
                    $prop_def['name'] = $document['mask_field'][$index];
                } else {
                    $prop_def = $this->get_property_definition($headers, $prop_id);
                }
                $prop_value = $this->get_property_value($headers, $document['objectID'] . "/" . $objID . "/latest/properties/" . $prop_id);
                if ($prop_id == '1285') {
                    $short_start = explode('Bounded on the', $prop_value);
                    $short_end = explode('point of beginning', $prop_value);
                    $prop_value = ($short_start[0] . " Bounded on the XXXX to the point of beginning" . $short_end[1]);
                }

                $result[$prop_def['name']] = $prop_value;

            }
            // dd($result);
            $json_name = $document['objectID'] === 119 ? "inventory" : "project";
            $results[$document['json_name'] ? $document['json_name'] : $json_name] = $result;

            //  return response()->json($result);
            // $results[]= response()->json($result);

            // dd($headers);

        }
        return $results;
    }
    public function get_document_property_single(Request $request, $ObjectID = null, $propertyID = null, $propertyValue = null, $getPropertyID = null)
    {

        $get_token = $this->get_token($request);
        $objectURL = $this->baseURL() . "/REST/objects/" . $ObjectID . "?p" . $propertyID . "=" . $propertyValue;
        $client = new Client();
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];
        // dd($headers);
        try {
            $requestObj = new GuzzleRequest('GET', $objectURL, $headers);
            $res = $client->sendAsync($requestObj)->wait();
            $responseBody = $res->getBody()->getContents();
            $res = json_decode($responseBody);
            if (count($res->Items) == 0) {
                return response()->json(['error' => 'Object not found'], 404);
            }
            $objID = $res->Items[0]->DisplayID;
            $prop_def = $this->get_property_definition($headers, $getPropertyID);
            $prop_value = $this->get_property_value($headers, $ObjectID . "/" . $objID . "/latest/properties/" . $getPropertyID);
            if ($prop_value == null) {
                return response()->json(['error' => 'property value is empty'], 404);
            }

            $result[$prop_def['name']] = $prop_value;
            // return $result;
            return response()->json($prop_value);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error in searching object: ' . $e->getMessage()], 404);
        }
    }

    public function update_inventory_status(Request $request, $property_unit = null, $status = null)
    {
        $objectID = 119; //unit inventory
        $propertyID = 1109; //os status
        $get_token = $this->get_token($request);
        $objectURL = $this->baseURL() . "/REST/objects/" . $objectID . "?p1105=" . $property_unit;
        $client = new Client();
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];
        try {
            $requestObj = new GuzzleRequest('GET', $objectURL, $headers);
            $res = $client->sendAsync($requestObj)->wait();
            $responseBody = $res->getBody()->getContents();
            $res = json_decode($responseBody);
            if (count($res->Items) == 0) {
                return response()->json(['error' => 'Object not found'], 404)->setStatusCode(404);
            }
            $objID = $res->Items[0]->DisplayID;

            $setProperties[] = [

                "PropertyDef" => $propertyID,
                "TypedValue" => [
                    "DataType" => 10,
                    "Lookups" => [
                        [
                            "Item" => $status,
                            "Version" => -1
                        ]
                    ]
                ]
            ];
            $body = json_encode($setProperties);
            // dd($body);
            $objectURL = $this->baseURL() . "/REST/objects/" . $objectID . "/" . $objID . "/latest/properties";
            // dd($objectURL);
            $request = new GuzzleRequest('POST', $objectURL, $headers, $body);
            $res = $client->sendAsync($request)->wait();
            $responseBody = $res->getBody()->getContents();
            return $responseBody;

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error in searching object: ' . $e->getMessage()], 500);
        }
        $get_token = $this->get_token($request);
        $objectURL = $this->baseURL() . "/REST/objects/" . $objectID . "?p" . $propertyID . "=" . $request->name;
        $client = new Client();
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];
        // dd($headers);
        try {
            $requestObj = new GuzzleRequest('GET', $objectURL, $headers);
            $res = $client->sendAsync($requestObj)->wait();
            $responseBody = $res->getBody()->getContents();
            $res = json_decode($responseBody);
            $objID = $res->Items[0]->DisplayID;
            foreach ($request->property_ids as $prop_id) {
                $prop_def = $this->get_property_definition($headers, $prop_id);
                $prop_value = $this->get_property_value($headers, $objectID . "/" . $objID . "/latest/properties/" . $prop_id);
                $result[$prop_def['name']] = $prop_value;

            }
            return $result;
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error in searching object: ' . $e->getMessage()], 500);
        }
    }
    public function get_value_list(Request $request, $ID)
    {

        $get_token = $this->get_token($request);
        $client = new Client();
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];

        $client = new Client();

        $objectURL = $this->baseURL() . "/REST/valuelists/" . $ID . "/items";
        $request = new GuzzleRequest('GET', $objectURL, $headers);
        $res = $client->sendAsync($request)->wait();
        // $responseBody = $res->getBody()->getContents();
        $responseBody = json_decode($res->getBody()->getContents());
        return $responseBody;
    }
    
    public function get_object(Request $request)
    {

        $get_token = $this->get_token($request);
        $objectURL = $this->baseURL() . "/REST/objects/" . $request->objectID . "?p" . $request->propertyID . "=" . $request->name;
        $client = new Client();
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];
        try {
            $requestObj = new GuzzleRequest('GET', $objectURL, $headers);
            $res = $client->sendAsync($requestObj)->wait();
            $responseBody = $res->getBody()->getContents();
            $res = json_decode($responseBody);
            $objID = $res->Items[0]->DisplayID;
            $objPropertyURL = $this->baseURL() . "/REST/objects/" . $request->objectID . "/" . $objID . "/latest?include=properties";
            $requestProp = new GuzzleRequest('GET', $objPropertyURL, $headers);
            $resProp = $client->sendAsync($requestProp)->wait();
            $responseBodyProp = json_decode($resProp->getBody()->getContents());
            return $responseBodyProp;
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error in searching object: ' . $e->getMessage()], 500);
        }
    }
    public function get_property_definition($headers, $propId)
    {
        $client = new Client();
        try {
            $objectURL = $this->baseURL() . "/REST/structure/properties/" . $propId;
            $requestProp = new GuzzleRequest('GET', $objectURL, $headers);
            $resProp = $client->sendAsync($requestProp)->wait();
            $responseBodyProp = json_decode($resProp->getBody()->getContents());
            return ["dataType" => $responseBodyProp->DataType, "name" => $responseBodyProp->Name];

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error in getting property name: ' . $e->getMessage()], 500);
        }
    }
    public function get_property_value($headers, $paramProperty)
    {
        $client = new Client();
        try {

            $objPropertyURL = $this->baseURL() . "/REST/objects/" . $paramProperty;

            $requestProp = new GuzzleRequest('GET', $objPropertyURL, $headers);
            $resProp = $client->sendAsync($requestProp)->wait();
            $responseBodyProp = json_decode($resProp->getBody()->getContents());
            return $responseBodyProp->TypedValue->DisplayValue;
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error in getting property value: ' . $e->getMessage()], 500);
        }
    }
    
}
