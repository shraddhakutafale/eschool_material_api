<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Libraries\TenantService;
use CodeIgniter\API\ResponseTrait;
use Config\Database;
use App\Models\TenantUserModel;

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

class TenantUser extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        //
    }

    public function loginWithMobileUid()
    {
        $input = $this->request->getJSON();

        $tenantService = new TenantService();
        
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $tenantUserModel = new TenantUserModel($db);
        
        $tenantUser = $tenantUserModel->where('mobileNo', $input->mobileNumber)->first();
        if(is_null($tenantUser)) {
            if($insertId = $tenantUserModel->insert($input)) {
                $key = "Exiaa@11";
                $iat = time(); // current timestamp value
                $exp = $iat + 3600;

                $payload = array(
                    "iss" => "Issuer of the JWT",
                    "aud" => "Audience that the JWT",
                    "sub" => "Subject of the JWT",
                    "iat" => $iat, //Time the JWT issued at
                    "exp" => $exp, // Expiration time of token
                    "uid" => $input->uid,
                    "mobileNo" => $input->mobileNumber,
                    "userId" => $insertId
                );
            } else {
                return $this->respond(['status' => false, 'message' => 'User creation failed'], 401);
            }
        }else{
            if($tenantUser['uid'] == $input->uid){
                $key = "Exiaa@11";
                $iat = time(); // current timestamp value
                $exp = $iat + 3600;

                $payload = array(
                    "iss" => "Issuer of the JWT",
                    "aud" => "Audience that the JWT",
                    "sub" => "Subject of the JWT",
                    "iat" => $iat, //Time the JWT issued at
                    "exp" => $exp, // Expiration time of token
                    "uid" => $tenantUser['uid'],
                    "mobileNo" => $tenantUser['mobileNo'],
                    "userId" => $tenantUser['userId']
                );
            }else{
                return $this->respond(['status' => false, 'message' => 'Mobile number and UID does not match'], 401);
            }
            
        }
         
        $token = JWT::encode($payload, $key, 'HS256');
        $refreshToken = bin2hex(random_bytes(32)); // Generate random token
 
        $response = [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $exp,
            'exp' => $exp,
            'refresh_token' => $refreshToken
        ];
         
        return $this->respond($response, 200);
    }
}
