<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Psr7\MultipartStream;
use Carbon\Carbon;

class BuyerController extends Controller
{

    public function get_object_information(request $request, string $classID)
    {
        $get_token = $this->get_token($request);
        $dataset = $this->getDataSet($classID);
        // dd($dataset);
        $client = new Client();
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];

        $client = new Client();

        $objectURL = $this->baseURL() . "/REST/objects/" . $objID . "/" . $classID;

        // dd($objectURL);
        $request = new GuzzleRequest('GET', $objectURL, $headers, null);
        $res = $client->sendAsync($request)->wait();
        dd($res);
        $responseBody = json_decode($res->getBody());//->getContents();
        // dd($responseJSON);
        // return $responseJSON->DisplayID;
        // return $res->getBody();
        return $responseBody;
    }

    public function get_token(Request $request)
    {
        $authURL = $this->baseURL() . '/REST/server/authenticationtokens';
        $username = !$request->Credentials ? env("MFILES_ADMIN_USER") : $request->Credentials['Username'];
        $password = !$request->Credentials ? env("MFILES_ADMIN_PASS") : $request->Credentials['Password'];
        $credential = [
            'Username' => $username,
            'Password' => $password,
            'VaultGuid' => env('MFILES_VAULTID', "DF00F177-C725-4EB7-AC98-A557D1245361") // $request->input('vaultid') -- change to Param if multiple vault
        ];

        // dd($credential);
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
    private function baseURL()
    {
        return env("MFILES_BASEURL", "https://raemulanlands.cloudvault.m-files.com");
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

    private static function getDataSet($classType)
    {
        //for enhancement gawan ng maintenance pag nagkatime
        $dataset = [];

        if ($classType == "buyer") {

            $dataset["ClassDetails"] = [
                "ObjectID" => 160,
                "ClassID" => 201
            ];

            $dataset["Properties"] = [
                [
                    "ID" => 0,
                    "DataType" => "Text",
                    "label" => "Name Title",
                    "requestField" => "name"
                ],
                [
                    "ID" => 1331,
                    "DataType" => "Text",
                    "label" => "StorefrontID",
                    "requestField" => "id"
                ],
                [
                    "ID" => 1330,
                    "DataType" => "Text",
                    "label" => "NOAH ID",
                    "requestField" => "noah_id"
                ],
            ];

        } elseif ($classType == "transactions") {

            $dataset["ClassDetails"] = [
                "ObjectID" => 122,
                "ClassID" => 199
            ];

            $dataset["Properties"] = [
                [
                    "ID" => 0,
                    "DataType" => "Text",
                    "label" => "Name Title",
                    "requestField" => "name"
                ],
                [
                    "ID" => 1323,
                    "DataType" => "MultiLookup",
                    "label" => "Buyer Name",
                    "requestField" => "buyer"
                ],
                [
                    "ID" => 1325,
                    "DataType" => "MultiLookup",
                    "label" => "CoBuyer",
                    "requestField" => "coborrowers"
                ],
                [
                    "ID" => 1104,
                    "DataType" => "MultiLookup",
                    "label" => "Inventory Unit",
                    "requestField" => "inventoryUnit"
                ],
                [
                    "ID" => 1288,
                    "DataType" => "Text",
                    "label" => "Reference Code",
                    "requestField" => "referenceCode"
                ],
                [
                    "ID" => 1330,
                    "DataType" => "Text",
                    "label" => "NOAH ID",
                    "requestField" => "noah_id"
                ],
            ];
        }

        return $dataset;
    }
    public function create_buyers(Request $request)
    {
        // Authenticate once
        $get_token = $this->get_token($request);

        if (isset($get_token['error'])) {
            return $get_token;
        }
        //get inventory
        $inventory = $this->getInventory($request->input('pbl'), $get_token);
        // dd($inventory);
        if (!isset($inventory)) {
            return json_decode(
                "No Inventory Found",
                false
            );
        }
        // Load datasets
        $buyerDataset = $this->getDataSet('buyer');
        $transactionDataset = $this->getDataSet('transactions');

        $primaryResponse = $this->createBuyer(
            $request->all(),
            $buyerDataset,
            $get_token
        );

        $primaryMfilesID = $primaryResponse['DisplayID'] ?? null;

        $coborrowerResults = [];
        $coborrowerMfilesIDs = [];

        $coborrowers = $request->input('coborrowers', []);

        if (is_array($coborrowers)) {

            foreach ($coborrowers as $coborrower) {

                $coborrowerResponse = $this->createBuyer(
                    $coborrower,
                    $buyerDataset,
                    $get_token
                );

                $coborrowerMfilesID =
                    $coborrowerResponse['DisplayID'] ?? null;

                $coborrower['mfilesID'] = $coborrowerMfilesID;

                $coborrowerResults[] = $coborrower;

                if ($coborrowerMfilesID !== null) {
                    $coborrowerMfilesIDs[] = $coborrowerMfilesID;
                }
            }
        }

        $transactionData = [
            'name' => $request->input('pbl'),
            'buyer' => $primaryMfilesID,
            'coborrowers' => $coborrowerMfilesIDs,
            'referenceCode' => $request->input('referenceCode'),
            "inventoryUnit" => $inventory
        ];

        // $transactionData = [
        //     "name" => "PVNE-01-010-002",
        //     "buyer" => "34",
        //     "coborrowers" => [
        //         0 => "35",
        //         1 => "36",
        //         2 => "37"
        //     ],
        //     "referenceCode" => "jn-1231231",
        //     "inventoryUnit" => $inventory
        // ];

        // dd([$transactionData,$transactionDataNew]);
        $transactionResponse = $this->create_transaction(
            $transactionData,
            $transactionDataset,
            $get_token
        );

        $result = $request->except('coborrowers');

        $result['mfilesID'] = $primaryMfilesID;

        $result['coborrowers'] = $coborrowerResults;

        $result['transaction'] = $transactionResponse;

        return response()->json($result);
    }

    private function createBuyer(array $data, array $dataset, array $get_token)
    {
        $setProperties = [];
        foreach ($dataset['Properties'] ?? [] as $property) {

            if (
                !isset($property['ID']) ||
                !isset($property['DataType']) ||
                !isset($property['requestField'])
            ) {
                continue;
            }

            $requestField = $property['requestField'];
            if (!array_key_exists($requestField, $data)) {
                continue;
            }

            $value = $data[$requestField];

            // Skip null values
            if ($value === null) {
                continue;
            }

            $setProperties[] = [
                "PropertyDef" => $property['ID'],
                "TypedValue" => [
                    "DataType" => $this->get_datatype(
                        $property['DataType']
                    ),
                    "Value" => $value
                ]
            ];
        }


        $setProperties[] = [
            "PropertyDef" => 22,
            "TypedValue" => [
                "DataType" => 8,
                "Value" => false
            ]
        ];


        $setProperties[] = [
            "PropertyDef" => 100,
            "TypedValue" => [
                "DataType" => 9,
                "Lookup" => [
                    "Item" => $dataset['ClassDetails']['ClassID'],
                    "Version" => -1
                ]
            ]
        ];

        $bodyJson = [
            "PropertyValues" => $setProperties,
            "Files" => []
        ];

        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];

        $client = new Client();

        $objectURL = $this->baseURL()
            . "/REST/objects/"
            . $dataset['ClassDetails']['ObjectID']
            . "?checkIn=true";

        $response = $client->post($objectURL, [
            'headers' => $headers,
            'json' => $bodyJson
        ]);
        // $transaction = $this->create_transaction()
        return json_decode(
            $response->getBody()->getContents(),
            true
        );
    }

    public function create_transaction(array $data, array $dataset, array $get_token)
    {
        $setProperties = [];
        foreach ($dataset['Properties'] ?? [] as $property) {

            if (
                !isset($property['ID']) ||
                !isset($property['DataType']) ||
                !isset($property['requestField'])
            ) {
                continue;
            }

            $requestField = $property['requestField'];

            if (!array_key_exists($requestField, $data)) {
                continue;
            }

            $value = $data[$requestField];

            if ($value === null) {
                continue;
            }
            if ($property['DataType'] === 'MultiLookup') {

                if (!is_array($value)) {
                    $value = [$value];
                }

                $lookups = [];

                foreach ($value as $mfilesID) {

                    if ($mfilesID === null) {
                        continue;
                    }

                    $lookups[] = [
                        "Item" => $mfilesID,
                        "Version" => -1
                    ];
                }

                if (!empty($lookups)) {

                    $setProperties[] = [
                        "PropertyDef" => $property['ID'],
                        "TypedValue" => [
                            "DataType" => $this->get_datatype(
                                $property['DataType']
                            ),
                            "Lookups" => $lookups
                        ]
                    ];
                }

                continue;
            }

            $setProperties[] = [
                "PropertyDef" => $property['ID'],
                "TypedValue" => [
                    "DataType" => $this->get_datatype(
                        $property['DataType']
                    ),
                    "Value" => $value
                ]
            ];
        }

        $setProperties[] = [
            "PropertyDef" => 100,
            "TypedValue" => [
                "DataType" => 9,
                "Lookup" => [
                    "Item" => $dataset['ClassDetails']['ClassID'],
                    "Version" => -1
                ]
            ]
        ];
        // dd($setProperties);
        $bodyJson = [
            "PropertyValues" => $setProperties,
            "Files" => []
        ];
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];

        $client = new Client();

        $objectURL = $this->baseURL()
            . "/REST/objects/"
            . $dataset['ClassDetails']['ObjectID']
            . "?checkIn=true";

        $response = $client->post($objectURL, [
            'headers' => $headers,
            'json' => $bodyJson
        ]);

        return json_decode(
            $response->getBody()->getContents(),
            true
        );
    }
    public function checkBuyer(int $buyerID, array $get_token)
    {

        try {
            $client = new Client();

            $headers = [
                'x-authentication' => $get_token['token'],
                'Content-Type' => 'application/json',
                'Cookie' => $get_token['setCookie']
            ];
            $objID = env("MFilesBuyerID", 160);
            $objectURL = $this->baseURL()
                . "/REST/objects/"
                . $objID . "/"
                . $buyerID;
            $response = $client->get($objectURL, [
                'headers' => $headers
            ]);

            $res_data = json_decode(
                $response->getBody()->getContents(),
                true
            );
            // dd($res_data['DisplayID']?true:false);
            return $res_data['DisplayID'] ? ['status' => 'true'] : ['error' => 'Buyer not found. ', 'status' => false];

        } catch (\Exception $e) {
            return ['error' => 'Buyer not found.', 'status' => false];
        }
    }
    public function getInventory(string $pbl, array $get_token)
    {
        try {
            $client = new Client();

            $headers = [
                'x-authentication' => $get_token['token'],
                'Content-Type' => 'application/json',
                'Cookie' => $get_token['setCookie']
            ];
            $objID = env("MFilesInventoryID", 119);
            $objectURL = $this->baseURL()
                . "/REST/objects/"
                . $objID
                . "?p0=" . $pbl;
            $response = $client->get($objectURL, [
                'headers' => $headers
            ]);
            $res_data = json_decode(
                $response->getBody()->getContents(),
                true
            );
            return $res_data['Items'][0]['DisplayID'] ?? ['error' => 'Inventory not found. ', 'status' => false];

        } catch (\Exception $e) {
            return response()->json(['error' => 'Inventory not found: ' . $e->getMessage()], 500);
        }
    }
    //upload file
    public function upload_document(Request $request, $buyerID, $documentCode)
    {
        $get_token = $this->get_token($request);

        $documentName = $request->name;
        $fileName = $request->uploadFile->getClientOriginalName();
        $ext = $request->uploadFile->getClientOriginalExtension();
        $classId = env('MFILES_BUYER_DOCS_ID', 194);

        // dd($classId);
        $client = new Client();
        $objectURL = $this->baseURL() . "/REST/files";
        // Get the uploaded file from the request
        $uploadFile = $request->file('uploadFile');
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
            ],

        ];
        $buyerClassID = env("MFILES_BUYER_CLASS_ID", 1323);

        $setProperties[] =
            [
                "PropertyDef" => $buyerClassID,
                "TypedValue" => [
                    "DataType" => 10,
                    "Lookups" => [
                        [
                            "Item" => (int) $buyerID,
                            "Version" => -1
                        ]
                    ]
                ]
            ];

        $setProperties[] = [
            "PropertyDef" => 1326,//document code
            "TypedValue" => [
                "DataType" => 1,
                "Value" => $documentCode
            ],

        ];
        $setProperties[] = [
            "PropertyDef" => 0,//document name
            "TypedValue" => [
                "DataType" => 1,
                "Value" => $documentName
            ],

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
        // dd($body);
        $bodyJson = [
            "PropertyValues" => $setProperties,
            "Files" => $upload_response
        ];

        // dd($bodyJson);
        // dd($get_token);
        $client = new Client();
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];
        // dd($headers);


        try {
            $objectURL = $this->baseURL() . "/REST/objects/0?checkIn=true";
            $request = new GuzzleRequest('POST', $objectURL, $headers, $body);
            $res = $client->sendAsync($request)->wait();
            $responseBody = $res->getBody()->getContents();
            // dd($responseBody);
            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                // 'fileId' => $responseBody['AddedFiles'][0]['ID']
                // 'fileId' => $responseBody['DisplayID']
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
    public function get_document_old(Request $request, $buyerID, $documentCode)
    {
        $get_token = $this->get_token($request);
        // $objectId = env("MFilesBuyerID", 160);
        $client = new Client();
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];
        //get fileId
        $Ids = $this->getFileId($buyerID, $documentCode, $get_token);
        // dd($Ids);
        try {
            $objectURL = $this->baseURL() . "/REST/objects/0/" . $Ids['objectId'] . "/files/" . $Ids['fileId'] . "/content.aspx?format=pdf";
            // dd($objectURL);
            $request = new GuzzleRequest('GET', $objectURL, $headers);

            dd($request);
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
    public function get_document(Request $request, $buyerID, $documentCode)
    {
        $get_token = $this->get_token($request);
        $Ids = $this->getFileId($buyerID, $documentCode, $get_token);
        $objectId = $Ids['objectId'];
        $fileId = $Ids['fileId'];
        $client = new Client();
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];
        try {
            $objectURL = $this->baseURL() . "/REST/objects/0/" . $objectId . "/files/" . $fileId . "/content.aspx?format=pdf";
            //    dd($objectURL);
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

    public function getFileId($buyerId, $documentCode, $get_token)
    {
        try {
            $client = new Client();

            $headers = [
                'x-authentication' => $get_token['token'],
                'Content-Type' => 'application/json',
                'Cookie' => $get_token['setCookie']
            ];
            // $objID = env("MFilesInventoryID", 119);
            $objectURL = $this->baseURL()
                . "/REST/objects/0"
                . "?p1326=" . $documentCode
                . "&p1323=" . $buyerId;
            $response = $client->get($objectURL, [
                'headers' => $headers
            ]);
            $res_data = json_decode(
                $response->getBody()->getContents(),
                true
            );
            // dd($res_data,$documentCode,$buyerId);
            // return ['objectId' => (int) $res_data['Items'][0]['DisplayID'], 'fileId' => $res_data['Items'][0]['Files'][0]['ID']] ?? ['error' => 'Document not found. ', 'status' => false];
            $objId = $res_data['Items'][0]['DisplayID']??[];
            $fileId = $res_data['Items'][0]['Files'][0]['ID']??[];
            // dd($res_data,$documentCode,$buyerId, $objId);
            return ['objectId' => $objId , 'fileId' =>  $fileId] ?? ['error' => 'Document not found. ', 'status' => false];
        } catch (\Exception $e) {
            return response()->json(['error' => 'Document not found: ' . $e->getMessage()], 500);
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
    public function get_documents(Request $request, $buyerID)
    {
        $get_token = $this->get_token($request);
        try {
            $client = new Client();

            $headers = [
                'x-authentication' => $get_token['token'],
                'Content-Type' => 'application/json',
                'Cookie' => $get_token['setCookie']
            ];
            // $objID = env("MFilesInventoryID", 119);
            $objectURL = $this->baseURL()
                . "/REST/objects/0"
                . "?p1323=" . $buyerID;
            $response = $client->get($objectURL, [
                'headers' => $headers
            ]);
            $file_arr = json_decode(
                $response->getBody()->getContents(),
                true
            );
            // dd($file_arr);

            $res_data=[];
            foreach ($file_arr['Items'] as $file) {

                $documentCode = $this->get_property_value($headers, "0/" . $file['DisplayID'] . "/latest/properties/1326");

                // dd($file);
                // $fileId = $this->getFileId();
                $res_data[] = [
                    'Name' => $file['Title'],
                    'Document Code' => $documentCode,
                    'link' => url('/api/mfiles/' . $buyerID . '/' . $documentCode)
                ];

            }
            // dd($res_data);
            // return $res_data;
            return response()->json([
                'success' => true,
                'documents' => $res_data,
            ], 200) ?? ['error' => 'Document not found. ', 'status' => false];

        } catch (\Exception $e) {
            return response()->json(['error' => 'Document not found: ' . $e->getMessage()], 500);
        }

    }
    //{{MFWSUrl}}/objects/{{ObjectType}}/{{ObjectID}}/{{ObjectVersion}}/files/{{FileID}}/content?_method=PUT
    public function checkInOutDocument($documentID, $get_token, int $method)
    {
        try {
            $client = new Client();

            $headers = [
                'x-authentication' => $get_token['token'],
                'Content-Type' => 'application/json',
                'Cookie' => $get_token['setCookie']
            ];
            $checkoutURL = $this->baseURL() . "/REST/objects/0/{$documentID}/latest/checkedout?_method=PUT";
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
            return response()->json(['error' => 'Document not checked out: ' . $e->getMessage()], 500);
        }

    }
    public function update_document(Request $request, $buyerID, $documentCode)
    {
        $get_token = $this->get_token($request);
        $ids = $this->getFileId($buyerID, $documentCode, $get_token);
        // dd($ids);
        $objectId = $ids['objectId'];
        $fileId = $ids['fileId'];
        $file = $request->file('uploadFile');

        $client = new Client();

        $checkoutDocument = $this->checkInOutDocument($objectId, $get_token, 1);
        if ($checkoutDocument) {
            try {
                $url = $this->baseURL() . "/REST/objects/0/{$objectId}/latest/files/{$fileId}/content?_method=PUT";
                $response = $client->request('POST', $url, [
                    'headers' => [
                        'X-Authentication' => $get_token['token'],
                        'Cookie' => $get_token['setCookie'],
                        'Content-Type' => $file->getMimeType()
                    ],
                    'body' => fopen($file->getRealPath(), 'rb'),
                ]);
                $checkoutDocument = $this->checkInOutDocument($objectId, $get_token, 0);
                return response()->json([
                    'status' => true,
                    'message' => 'Document updated successfully.',
                    // 'response' => $response->getBody()->getContents(),
                ]);

            } catch (\Throwable $e) {
                // dd(
                //     $e->getResponse()->getStatusCode(),
                //     $e->getResponse()->getBody()->getContents()
                // );
                return response()->json([
                    'status' => false,
                    'error' => $e->getMessage(),
                ], 500);
            }
        }
    }
    public function delete_document(Request $request, $buyerID, $documentCode)
    {

        $get_token = $this->get_token($request);

        $ids = $this->getFileId($buyerID, $documentCode, $get_token);
        // dd($ids);
        $objectId = $ids['objectId'];
        $fileId = $ids['fileId'];
        $file = $request->file('uploadFile');

        // $checkoutDocument = $this->checkInOutDocument($objectId, $get_token, 1);
        $client = new Client();
        try {
            $client = new Client();

            $headers = [
                'x-authentication' => $get_token['token'],
                'Content-Type' => 'application/json',
                'Cookie' => $get_token['setCookie']
            ];
            // $checkoutURL = $this->baseURL() . "/REST/objects/0/{$objectId}/latest?_method=DELETE";
            $checkoutURL = $this->baseURL() . "/REST/objects/0/{$objectId}/deleted?_method=PUT";
            $response = $client->post($checkoutURL, [
            'headers' => $headers,
            'json' => ['Value' => 1]
            ]);
            // dd($response->getBody()->getContents());
            // $res_data = json_decode(
            //     $response->getBody()->getContents(),
            //     true
            // );

            // $checkoutDocument = $this->checkInOutDocument($objectId, $get_token, 0);
            // return $res_data ?? false;
            return response()->json([
                    'status' => true,
                    'message' => 'Document deleted successfully.',
                    // 'response' => $response->getBody()->getContents(),
            ]);

        } catch (\Throwable $e) {
            // dd(
            //     $e->getResponse()->getStatusCode(),
            //     $e->getResponse()->getBody()->getContents()
            // );
            // return response()->json([
            //     'status' => false,
            //     'error' => $e->getMessage(),
            // ], 500);
            return response()->json([
                    'status' => true,
                    'message' => 'Document updated successfully.',
                    // 'response' => $response->getBody()->getContents(),
                ]);
        }

    }
}
