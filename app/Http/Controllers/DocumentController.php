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
        return env("MFILES_BASEURL","https://raemulanlands.cloudvault.m-files.com");
    }

    public function getToken(){
        $authURL = $this->baseURL().'/REST/server/authenticationtokens';
        $credential = [
            'Username'  => config('mfiles.mfiles_credentials.username'),
            'Password'  => config('mfiles.mfiles_credentials.password'),
            'VaultGuid' => env('MFILES_VAULTID',"DF00F177-C725-4EB7-AC98-A557D1245361")
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
        $username = !$request->Credentials?env("MFILES_ADMIN_USER"):$request->Credentials['Username'];
        $password = !$request->Credentials?env("MFILES_ADMIN_PASS"):$request->Credentials['Password'];
        $credential = [
            'Username'  => $username,
            'Password'  => $password,
            'VaultGuid' => env('MFILES_VAULTID',"DF00F177-C725-4EB7-AC98-A557D1245361")
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
        $objectId = env("MFILES_STOREFRONT_OBJECT_ID",723368);

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
        $objectId = env("MFILES_STOREFRONT_OBJECT_ID",723368);
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
    $uploadData = is_array($request->data) ? $request->data : json_decode($request->data, true);

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
            ? htmlspecialchars(json_encode($value))
            : htmlspecialchars((string) ($value ?? ''))
        )
        ->toArray();

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

}
