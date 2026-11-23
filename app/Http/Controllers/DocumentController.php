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

            $sanitizedData = $this->prepareTechnicalDescription($sanitizedData);

            if ($this->isNewVerticalProject($sanitizedData)) {
                $templateProcessor->setComplexBlock(
                    'technical_description',
                    $this->buildVerticalTechnicalDescriptionTable($sanitizedData)
                );

                unset($sanitizedData['technical_description']);
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

    protected function prepareTechnicalDescription(array $data): array
    {
        if (! $this->isNewVerticalProject($data)) {
            return $data;
        }

        $technicalDescription = $data['technical_description'] ?? [];

        if (is_string($technicalDescription)) {
            $decodedTechnicalDescription = json_decode($technicalDescription, true);

            if (! is_array($decodedTechnicalDescription)) {
                return $data;
            }

            $technicalDescription = $decodedTechnicalDescription;
        }

        if (! is_array($technicalDescription)) {
            return $data;
        }

        $unitArea = trim((string) ($technicalDescription['unit_area'] ?? ''));

        if ($unitArea !== '' && $unitArea !== '-' && ! str_contains(strtoupper($unitArea), 'SQ')) {
            $unitArea .= ' SQ.M.';
        }

        $verticalFields = [
            'tower_building' => $technicalDescription['tower_building'] ?? '',
            'floor_level' => $technicalDescription['floor_level'] ?? '',
            'unit_type' => ($technicalDescription['unit_type'] ?? '') ?: ($data['unit_type'] ?? ''),
            'unit_id' => $technicalDescription['unit_id'] ?? '',
            'unit_number' => $technicalDescription['unit_number'] ?? '',
            'unit_area' => $unitArea,
        ];

        foreach ($verticalFields as $field => $value) {
            $data[$field] = trim((string) $value);
        }

        $data['technical_description'] = collect([
            'TOWER/BUILDING' => $verticalFields['tower_building'],
            'FLOOR LEVEL' => $verticalFields['floor_level'],
            'UNIT TYPE' => $verticalFields['unit_type'],
            'UNIT ID' => $verticalFields['unit_id'],
            'UNIT NUMBER' => $verticalFields['unit_number'],
            'UNIT AREA' => $verticalFields['unit_area'],
        ])->map(
            fn ($value, $label): string => sprintf('%-15s : %s', $label, trim((string) $value))
        )->implode("\n");

        return $data;
    }

    protected function isNewVerticalProject(array $data): bool
    {
        return strtolower(trim((string) ($data['project_classification'] ?? ''))) === 'new'
            && strtolower(trim((string) ($data['project_house_type'] ?? ''))) === 'vertical';
    }

    protected function buildVerticalTechnicalDescriptionTable(array $data): \PhpOffice\PhpWord\Element\Table
    {
        $indent = new \PhpOffice\PhpWord\ComplexType\TblWidth(
            720,
            \PhpOffice\PhpWord\SimpleType\TblWidth::TWIP
        );

        $table = new \PhpOffice\PhpWord\Element\Table([
            'borderColor' => 'FFFFFF',
            'borderSize' => 0,
            'cellMargin' => 0,
            'cellSpacing' => 0,
            'indent' => $indent,
        ]);

        $fontStyle = [
            'name' => 'Courier New',
            'size' => 8,
        ];

        $fields = [
            'Tower/Building' => $data['tower_building'] ?? '',
            'Floor Level' => $data['floor_level'] ?? '',
            'Unit Type' => $data['unit_type'] ?? '',
            'Unit ID' => $data['unit_id'] ?? '',
            'Unit Number' => $data['unit_number'] ?? '',
            'Unit Area' => $data['unit_area'] ?? '',
        ];

        foreach ($fields as $label => $value) {
            $table->addRow();
            $table->addCell(3000, ['valign' => 'top'])
                ->addText($label, $fontStyle, ['spaceAfter' => 0]);
            $table->addCell(300, ['valign' => 'top'])
                ->addText(':', $fontStyle, ['spaceAfter' => 0]);
            $table->addCell(5700, ['valign' => 'top'])
                ->addText($this->sanitizeText($value), $fontStyle, ['spaceAfter' => 0]);
        }

        return $table;
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
