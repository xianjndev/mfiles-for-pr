<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Utils;
use Carbon\Carbon;
use App\Models\UploadAndConvert;
use PhpOffice\PhpWord\TemplateProcessor;

class DocumentController extends Controller
{
    private function baseURL(){
        return config('mfiles.mfiles_baseurl');
    }

    public function getToken(){
        $authURL = $this->baseURL().'/REST/server/authenticationtokens';
        $credential = [
            'Username'  => config('mfiles.mfiles_credentials.username'),
            'Password'  => config('mfiles.mfiles_credentials.password'),
            'VaultGuid' => config('mfiles.mfiles_vaultid')
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

    public function get_token(Request $request)
    {
        $authURL = $this->baseURL().'/REST/server/authenticationtokens';
        $username = !$request->Credentials?config('mfiles.mfiles_credentials.username'):$request->Credentials['Username'];
        $password = !$request->Credentials?config('mfiles.mfiles_credentials.password'):$request->Credentials['Password'];
        $credential = [
            'Username'  => $username,
            'Password'  => $password,
            'VaultGuid' => config('mfiles.mfiles_vaultid')
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
        $objectId = config('mfile_sf_obj_id');
        $client = new Client();
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie']
        ];
        try{
            // $objectURL =$this->baseURL()."/REST/objects/0/723368/files/".$fileId."/content.aspx?filePreview=true&format=pdf" ;
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

    public function generate_document_object(Request $request)
    {
        $get_token = $this->get_token($request);

        $client = new Client();
        $username = "rae.admin";
        $password = "Jng@2024";
        $headers = [
            'X-Username'=> $username,
            'X-Password'=> $password,
            'X-Vault'=>"DF00F177-C725-4EB7-AC98-A557D1245361"
        ];

        $body = [
            "PropertyValues" => [
                [
                    "PropertyDef" => 0,
                    "TypedValue" => [
                        "DataType" => 1,
                        "Value" => Carbon::now()->timestamp."_document_generated"
                    ]
                ],
                [
                    "PropertyDef" => 22,
                    "TypedValue" => [
                        "DataType" => 8,
                        "Value" => false
                    ]
                ],
                [
                    "PropertyDef" => 100,
                    "TypedValue" => [
                        "DataType" => 9,
                        "Lookup" => [
                            "Item" => 0,
                            "Version" => -1
                        ]
                    ]
                ]
            ],
            "Files" => []
        ];

        try {
            $url = $this->baseURL() . '/REST/objects/0?checkIn=true';
            $guzzleRequest = new GuzzleRequest('POST', $url, $headers, json_encode($body));
            $res = $client->sendAsync($guzzleRequest)->wait();
            $response = json_decode($res->getBody()->getContents(), true);

            return response()->json([
                'success'    => true,
                'displayID'  => $response['DisplayID'] ?? null,
            ]);
        } catch (\Exception $e) {

            logger()->error('M-Files Upload Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $error = $e->getMessage();

            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                $error = $e->getResponse()->getBody()->getContents();
            }
            return response()->json([
                'error'   => 'Document object creation failed',
                'details' => $error
            ], 500);
        }
    }

    public function generate_document_object(Request $request)
    {
        $get_token = $this->get_token($request);

        $client = new Client();
        $username = "rae.admin";
        $password = "Jng@2024";
        $headers = [
            'X-Username'=> $username,
            'X-Password'=> $password,
            'X-Vault'=>"DF00F177-C725-4EB7-AC98-A557D1245361"
        ];

        $body = [
            "PropertyValues" => [
                [
                    "PropertyDef" => 0,
                    "TypedValue" => [
                        "DataType" => 1,
                        "Value" => Carbon::now()->timestamp."_document_generated"
                    ]
                ],
                [
                    "PropertyDef" => 22,
                    "TypedValue" => [
                        "DataType" => 8,
                        "Value" => false
                    ]
                ],
                [
                    "PropertyDef" => 100,
                    "TypedValue" => [
                        "DataType" => 9,
                        "Lookup" => [
                            "Item" => 0,
                            "Version" => -1
                        ]
                    ]
                ]
            ],
            "Files" => []
        ];

        try {
            $url = $this->baseURL() . '/REST/objects/0?checkIn=true';
            $guzzleRequest = new GuzzleRequest('POST', $url, $headers, json_encode($body));
            $res = $client->sendAsync($guzzleRequest)->wait();
            $response = json_decode($res->getBody()->getContents(), true);

            return response()->json([
                'success'    => true,
                'displayID'  => $response['DisplayID'] ?? null,
            ]);
        } catch (\Exception $e) {

            logger()->error('M-Files Upload Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $error = $e->getMessage();

            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                $error = $e->getResponse()->getBody()->getContents();
            }
            return response()->json([
                'error'   => 'Document object creation failed',
                'details' => $error
            ], 500);
        }
    }

    public function upload_and_view_storefront_file(Request $request)
{
    $client = new \GuzzleHttp\Client();

    $username = "rae.admin";
    $password = "Jng@2024";

    $headers = [
        'X-Username'=> $username,
        'X-Password'=> $password,
        'X-Vault'=>"DF00F177-C725-4EB7-AC98-A557D1245361"
    ];



    // $objectId = env('MFILES_STOREFRONT_OBJECT_ID', 723368);

        $uploadFile = $request->file('uploadFile');
        $uploadData = is_array($request->data)
                ? $request->data
                : json_decode($request->data, true);
        if (!is_array($uploadData)) {
            throw new \Exception('Invalid request data.');
        }

    try {

        // Step 0
        $body = [
            "PropertyValues" => [
                [
                    "PropertyDef" => 0,
                    "TypedValue" => [
                        "DataType" => 1,
                        "Value" => Carbon::now()->timestamp."_document_generated"
                    ]
                ],
                [
                    "PropertyDef" => 22,
                    "TypedValue" => [
                        "DataType" => 8,
                        "Value" => false
                    ]
                ],
                [
                    "PropertyDef" => 100,
                    "TypedValue" => [
                        "DataType" => 9,
                        "Lookup" => [
                            "Item" => 0,
                            "Version" => -1
                        ]
                    ]
                ]
            ],
            "Files" => []
        ];
        $url = $this->baseURL() . '/REST/objects/0?checkIn=true';
        $guzzleRequest = new GuzzleRequest('POST', $url, $headers, json_encode($body));
        $res = $client->sendAsync($guzzleRequest)->wait();
            $response = json_decode($res->getBody()->getContents(), true);
        $objectId=   $response['DisplayID'] ?? null;
        /*
        |--------------------------------------------------------------------------
        | STEP 1: Process DOCX Template
        |--------------------------------------------------------------------------
        */

            $templateProcessor = new TemplateProcessor($uploadFile->getPathname());
            $sanitizedData = collect($uploadData)
                ->map(fn ($value) => is_array($value)
                    ? json_encode($value)
                    : (string) ($value ?? '')
                )
                ->toArray();

            if(in_array($sanitizedData['project_code'], config('mfiles.new_project'))){
                $sanitizedData['new_technical_description'] = $sanitizedData['technical_description'] ?? [];
                $technicalDescription = $sanitizedData['new_technical_description'] ?? [];
                
                if (is_string($technicalDescription)) {
                    $technicalDescription = json_decode($technicalDescription, true);
                }
                
                unset($sanitizedData['technical_description']);
                unset($sanitizedData['new_technical_description']);

                $phpWord = new \PhpOffice\PhpWord\PhpWord();
        
                $indent = new \PhpOffice\PhpWord\ComplexType\TblWidth(
                    720,
                    \PhpOffice\PhpWord\SimpleType\TblWidth::TWIP
                );
            
                $table = new \PhpOffice\PhpWord\Element\Table([
                    'borderColor' => 'FFFFFF',
                    'borderSize'  => 0,
                    'cellMargin'  => 0,
                    'cellSpacing' => 0,
                    'indent'      => $indent,
                ]);
            
                $fontStyle = [
                    'name' => 'Courier New',
                    'size' => 9,
                ];
            
                // Table widths
                $labelWidth = 3000;
                $colonWidth = 300;
                $valueWidth = 5700;
            
                /*
                |--------------------------------------------------------------------------
                | Technical Description Values
                |--------------------------------------------------------------------------
                */
                
                $surveyPlanNo = $this->sanitizeText($technicalDescription['survey_plan_no'] ?? '');
                $blockNo = $this->sanitizeText($technicalDescription['block_no'] ?? '');
                $lotNo = $this->sanitizeText($technicalDescription['lot_no'] ?? '');
                $portionOfLot = $this->sanitizeText($technicalDescription['portion_of_lot'] ?? '');
                $lrcRecordNo = $this->sanitizeText($technicalDescription['lrc_record_no'] ?? '');
                $landOwnerClaimant = $this->sanitizeText($technicalDescription['land_owner_claimant'] ?? '');
                $location = $this->sanitizeText($technicalDescription['location'] ?? '');
                $area = $this->sanitizeText($technicalDescription['area'] ?? '');
                $descriptionOfCorners = $this->sanitizeText($technicalDescription['description_of_corners'] ?? '');
                $originalDateOfSurvey = $this->sanitizeText($technicalDescription['original_date_of_survey'] ?? '');
                $dateOfSurvey = $this->sanitizeText($technicalDescription['date_of_survey'] ?? '');
                $dateApproved = $this->sanitizeText($technicalDescription['date_approved'] ?? '');
                $geodeticEngineer = $this->sanitizeText($technicalDescription['geodetic_engineer'] ?? '');

                $bearings = $this->sanitizeText($technicalDescription['bearings'] ?? '');

                if($bearings===1 || $bearings==1 || $bearings=='1'){
                    $bearings='True';
                }

                // $surveyPlanNo = 'Pcs-04-036420';
                // $blockNo = '21';
                // $lotNo = '7';
            
                // $portionOfLot = 'CONS.-SUBD. of Lot 2278, Naic Estate (Frs-04-006884); Lot 1687-B, Psd-04-296359; Lot 7 (Reserved Area), Block 21, Lot 1 (Reserved Area), Block 22 and Lot 6, Block 29, all of Psd-04-289489';
            
                // $lrcRecordNo = '8340';
            
                // $landOwnerClaimant = 'RAEMULAN LANDS, INC.';
            
                // $location = "Barangay of MUZON";
            
                // $area = 'FIFTY SEVEN (57) SQUARE METERS more or less.';
            
                // $descriptionOfCorners = 'Corners 1 and 5 by P.S. cyl. conc. mons. 15x40 cms. and the rest are OLD P.S. cyl. conc. mons. 15x40 cms.';
            
                // $bearings = 'True';
            
                // $originalDateOfSurvey = 'AUG. 1906 - NOV. 1907';
            
                // $dateOfSurvey = 'APRIL 22-23, 2026';
            
                // $dateApproved = 'MAY 15, 2026';
            
                // $geodeticEngineer = 'JOEL J. HUBAC';
            
            
                /*
                |--------------------------------------------------------------------------
                | Survey Plan No.
                |--------------------------------------------------------------------------
                */
                $table->addRow();
            
                $table->addCell($labelWidth, [
                    'valign' => 'top',
                ])->addText(
                    'Survey Plan No.',
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
                $table->addCell($colonWidth, [
                    'valign' => 'top',
                ])->addText(
                    ':',
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
                $table->addCell($valueWidth, [
                    'valign' => 'top',
                ])->addText(
                    $surveyPlanNo,
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
            
                /*
                |--------------------------------------------------------------------------
                | Block No.
                |--------------------------------------------------------------------------
                */
                $table->addRow();
            
                $table->addCell($labelWidth, [
                    'valign' => 'top',
                ])->addText(
                    'Block No.',
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
                $table->addCell($colonWidth, [
                    'valign' => 'top',
                ])->addText(
                    ':',
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
                $table->addCell($valueWidth, [
                    'valign' => 'top',
                ])->addText(
                    $blockNo,
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
            
                /*
                |--------------------------------------------------------------------------
                | Lot No.
                |--------------------------------------------------------------------------
                */
                $table->addRow();
            
                $table->addCell($labelWidth, [
                    'valign' => 'top',
                ])->addText(
                    'Lot No.',
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
                $table->addCell($colonWidth, [
                    'valign' => 'top',
                ])->addText(
                    ':',
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
                $table->addCell($valueWidth, [
                    'valign' => 'top',
                ])->addText(
                    $lotNo,
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
            
                /*
                |--------------------------------------------------------------------------
                | Portion of Lot
                |--------------------------------------------------------------------------
                */
                $table->addRow();
            
                $table->addCell($labelWidth, [
                    'valign' => 'top',
                ])->addText(
                    'Portion of Lot',
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
                $table->addCell($colonWidth, [
                    'valign' => 'top',
                ])->addText(
                    ':',
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
                $table->addCell($valueWidth, [
                    'valign' => 'top',
                ])->addText(
                    $portionOfLot,
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
            
                /*
                |--------------------------------------------------------------------------
                | LRC Record No.
                |--------------------------------------------------------------------------
                */
                $table->addRow();
            
                $table->addCell($labelWidth, [
                    'valign' => 'top',
                ])->addText(
                    'LRC Record No.',
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
                $table->addCell($colonWidth, [
                    'valign' => 'top',
                ])->addText(
                    ':',
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
                $table->addCell($valueWidth, [
                    'valign' => 'top',
                ])->addText(
                    $lrcRecordNo,
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
            
                /*
                |--------------------------------------------------------------------------
                | Land Owner/Claimant
                |--------------------------------------------------------------------------
                */
                $table->addRow();
            
                $table->addCell($labelWidth, [
                    'valign' => 'top',
                ])->addText(
                    'Land Owner/Claimant',
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
                $table->addCell($colonWidth, [
                    'valign' => 'top',
                ])->addText(
                    ':',
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
                $table->addCell($valueWidth, [
                    'valign' => 'top',
                ])->addText(
                    $landOwnerClaimant,
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
            
                /*
                |--------------------------------------------------------------------------
                | Location
                |--------------------------------------------------------------------------
                */
                $table->addRow();
            
                $table->addCell($labelWidth, [
                    'valign' => 'top',
                ])->addText(
                    'Location',
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
                $table->addCell($colonWidth, [
                    'valign' => 'top',
                ])->addText(
                    ':',
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
                // $locationCell = $table->addCell($valueWidth, [
                //     'valign' => 'top',
                // ]);
            
                // foreach ($location as $locationLine) {
                //     $locationCell->addText(
                //         $locationLine,
                //         $fontStyle,
                //         ['spaceAfter' => 0]
                //     );
                // }
                $table->addCell($valueWidth, [
                    'valign' => 'top',
                ])->addText(
                    $location,
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
            
                /*
                |--------------------------------------------------------------------------
                | Area
                |--------------------------------------------------------------------------
                */
                $table->addRow();
            
                $table->addCell($labelWidth, [
                    'valign' => 'top',
                ])->addText(
                    'Area',
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
                $table->addCell($colonWidth, [
                    'valign' => 'top',
                ])->addText(
                    ':',
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
                $table->addCell($valueWidth, [
                    'valign' => 'top',
                ])->addText(
                    $area,
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
            
                /*
                |--------------------------------------------------------------------------
                | Description of Corners
                |--------------------------------------------------------------------------
                */
                $table->addRow();
            
                $table->addCell($labelWidth, [
                    'valign' => 'top',
                ])->addText(
                    'Description of Corners',
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
                $table->addCell($colonWidth, [
                    'valign' => 'top',
                ])->addText(
                    ':',
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
                $table->addCell($valueWidth, [
                    'valign' => 'top',
                ])->addText(
                    $descriptionOfCorners,
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
            
                /*
                |--------------------------------------------------------------------------
                | Bearings
                |--------------------------------------------------------------------------
                */
                $table->addRow();
            
                $table->addCell($labelWidth, [
                    'valign' => 'top',
                ])->addText(
                    'Bearings',
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
                $table->addCell($colonWidth, [
                    'valign' => 'top',
                ])->addText(
                    ':',
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
                $table->addCell($valueWidth, [
                    'valign' => 'top',
                ])->addText(
                    $bearings,
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
            
                /*
                |--------------------------------------------------------------------------
                | Original Date of Survey
                |--------------------------------------------------------------------------
                */
                $table->addRow();
            
                $table->addCell($labelWidth, [
                    'valign' => 'top',
                ])->addText(
                    'Original Date of Survey',
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
                $table->addCell($colonWidth, [
                    'valign' => 'top',
                ])->addText(
                    ':',
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
                $table->addCell($valueWidth, [
                    'valign' => 'top',
                ])->addText(
                    $originalDateOfSurvey,
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
            
                /*
                |--------------------------------------------------------------------------
                | Date of Survey
                |--------------------------------------------------------------------------
                */
                $table->addRow();
            
                $table->addCell($labelWidth, [
                    'valign' => 'top',
                ])->addText(
                    'Date of Survey',
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
                $table->addCell($colonWidth, [
                    'valign' => 'top',
                ])->addText(
                    ':',
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
                $table->addCell($valueWidth, [
                    'valign' => 'top',
                ])->addText(
                    $dateOfSurvey,
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
            
                /*
                |--------------------------------------------------------------------------
                | Date Approved
                |--------------------------------------------------------------------------
                */
                $table->addRow();
            
                $table->addCell($labelWidth, [
                    'valign' => 'top',
                ])->addText(
                    'Date approved',
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
                $table->addCell($colonWidth, [
                    'valign' => 'top',
                ])->addText(
                    ':',
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
                $table->addCell($valueWidth, [
                    'valign' => 'top',
                ])->addText(
                    $dateApproved,
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
            
                /*
                |--------------------------------------------------------------------------
                | Geodetic Engineer
                |--------------------------------------------------------------------------
                */
                $table->addRow();
            
                $table->addCell($labelWidth, [
                    'valign' => 'top',
                ])->addText(
                    'Geodetic Engineer',
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
                $table->addCell($colonWidth, [
                    'valign' => 'top',
                ])->addText(
                    ':',
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
                $table->addCell($valueWidth, [
                    'valign' => 'top',
                ])->addText(
                    $geodeticEngineer,
                    $fontStyle,
                    ['spaceAfter' => 0]
                );
            
            
                /*
                |--------------------------------------------------------------------------
                | Insert into template
                |--------------------------------------------------------------------------
                */
                $templateProcessor->setComplexBlock(
                    'technical_description',
                    $table
                );

            }

            $sanitizedData = array_map(function ($value) {
                return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
            }, $sanitizedData);
           
            $templateProcessor->setValues($sanitizedData);

        $generatedFileName = now()->timestamp . '_' . $uploadFile->getClientOriginalName();
        $generatedPath = storage_path('app/temp/' . $generatedFileName);

        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $templateProcessor->saveAs($generatedPath);

        /*
        |--------------------------------------------------------------------------
        | STEP 2: Upload File to M-Files
        |--------------------------------------------------------------------------
        */

        $uploadUrl = $this->baseURL() . "/REST/objects/0/{$objectId}/files"."?checkIn=true";

        $multipart = [[
            'name'     => 'file',
            'contents' => Utils::tryFopen($generatedPath, 'r'),
            'filename' => $generatedFileName,
        ]];

        $uploadReq  = new GuzzleRequest('POST', $uploadUrl, $headers);
        $uploadRes  = $client->sendAsync($uploadReq, ['multipart' => $multipart])->wait();
        $uploadBody = json_decode($uploadRes->getBody()->getContents(), true);

        $fileId = $uploadBody['AddedFiles'][0]['ID'] ?? null;
  

        if (!$fileId) {
            return response()->json([
                'error'   => 'Upload failed — no file ID returned',
                'details' => $uploadBody,
            ], 500);
        }

        /*
        |--------------------------------------------------------------------------
        | STEP 3: Get File Metadata
        |--------------------------------------------------------------------------
        */

        $fileInfoUrl = $this->baseURL() . "/REST/objects/0/{$objectId}/files/{$fileId}";

        $fileInfoReq = new GuzzleRequest('GET', $fileInfoUrl, $headers);
        $fileInfoRes = $client->sendAsync($fileInfoReq)->wait();

        $fileInfoBody = json_decode($fileInfoRes->getBody()->getContents(), true);

        $fileSize = $fileInfoBody['Size'] ?? filesize($generatedPath);
        /*
        |--------------------------------------------------------------------------
        | STEP 4: Convert DOCX → PDF View
        |--------------------------------------------------------------------------
        */

        $viewUrl = $this->baseURL() . "/REST/objects/0/{$objectId}/files/{$fileId}/content.aspx?format=pdf";

        /*
        |--------------------------------------------------------------------------
        | STEP 5: Log Upload
        |--------------------------------------------------------------------------
        */

        $uac = \App\Models\UploadAndConvert::create([
            'request_from_ip'      => $request->ip(),
            'request_from_website' => $request->from ?? '',
            'user_id'              => $request->from_user_id ?? '',
            'user_name'            => $request->from_user_name ?? '',
            'object_id'            => $objectId,
            'file_id'              => $fileId,
            'data'                 => $request->data ?? '',
            'file_name'            => $generatedFileName,
            'link'                 => $viewUrl,
            'mfiles_view_link'     => $viewUrl,
            'file_size'            => number_format($fileSize / 1024, 2) . ' KB',
            'expires_at'           => now()->addDays(7),
        ]);

            $uac->link = config('app.url') . "/api/mfiles/storefront/{$uac->id}/view/{$generatedFileName}.pdf";
            $uac->save();

        /*
        |--------------------------------------------------------------------------
        | STEP 6: Return Link
        |--------------------------------------------------------------------------
        */

        return $uac->link;

    } catch (\Exception $e) {

        logger()->error('M-Files Upload Error', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        $error = $e->getMessage();

        if (method_exists($e, 'getResponse') && $e->getResponse()) {
            $error = $e->getResponse()->getBody()->getContents();
        }

        return response()->json([
            'error'   => 'Upload and view failed',
            'details' => $error,
        ], 500);
    }
}

    public function view(String $id,String $filename){
        $get_token = $this->getToken();
        // $objectId = env("MFILES_STOREFRONT_OBJECT_ID",723368);
        $username = "rae.admin";
        $password = "Jng@2024";
        $headers = [
            // 'x-authentication' => $get_token['token'],
            'X-Username'=> $username,
            'X-Password'=>$password,
            // 'Cookie'           => $get_token['setCookie'],
            'X-Vault'=>"DF00F177-C725-4EB7-AC98-A557D1245361"
        ];
        $client = new Client();
        $headers = [
            // 'x-authentication' => $get_token['token'],
            'X-Username'=> $username,
            'X-Password'=>$password,
            // 'Cookie'           => $get_token['setCookie'],
            'X-Vault'=>"DF00F177-C725-4EB7-AC98-A557D1245361"
        ];
        $uac = UploadAndConvert::findOrFail($id);
        try{
            // $objectURL =$this->baseURL()."/REST/objects/0/723368/files/".$fileId."/content.aspx?filePreview=true&format=pdf" ;
            // $objectURL =$this->baseURL()."/REST/objects/0/".$objectId."/files/".$uac->object_id."/content.aspx?format=pdf&checkIn=true" ;
            $objectURL = $uac->mfiles_view_link;
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

    public function convert(Request $request){

        $get_token = $this->get_token($request);
        $client = new Client();

        $headers = [
            'x-authentication' => $get_token['token'],
            'Cookie' => $get_token['setCookie'],
        ];
//        $objectId = env("MFILES_STOREFRONT_OBJECT_ID",723368);
        $objectId=config('mfiles.mfile_sf_obj_id');

        $uploadFile = $request->file('uploadFile');
        $fileName = $uploadFile->getClientOriginalName();
        $objectURL = $this->baseURL() . "/REST/objects/0/{$objectId}/files";
        $fileId = '';
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

            $fileId = $resBodyJson['AddedFiles'][0]['ID'];

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

    function sanitizeText($value): string
    {
        if ($value === null) {
            return '';
        }

        $value = (string) $value;

        // Remove invalid XML/control characters
        $value = preg_replace(
            '/[^\x09\x0A\x0D\x20-\x7F\x{A0}-\x{10FFFF}]/u',
            '',
            $value
        );

        return htmlspecialchars(trim($value));
    }

}
