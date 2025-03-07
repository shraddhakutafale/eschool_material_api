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

    public function getProfile()
    {
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $key = "Exiaa@11";
        $header = $this->request->getHeader("Authorization");
        $token = null;
  
        // extract the token from the header
        if(!empty($header)) {
            if (preg_match('/Bearer\s(\S+)/', $header, $matches)) {
                $token = $matches[1];
            }
        }
        $decoded = JWT::decode($token, new Key($key, 'HS256'));
        $tenantUserModel = new TenantUserModel($db);

        $user = $tenantUserModel->where('userId', $decoded->userId)->first();
        if(is_null($user)) {
            return $this->respond(['status' => false, 'message' => 'User not found'], 404);
        }
        return $this->respond(['status' => true, 'message' => 'User found', 'data' => $user], 200);
    }


    

//    public function saveToken()
// {
//     $input = $this->request->getJSON();

//     if (!isset($input->userId) || !isset($input->token)) {
//         return $this->respond(['status' => false, 'message' => 'Invalid input'], 400);
//     }

//     // 🔹 Verify JWT Token Before Storing
//     try {
//         $key = "Exiaa@11";
//         $decoded = JWT::decode($input->token, new Key($key, 'HS256'));

//         // 🔹 Connect to Tenant Database
//         $tenantService = new TenantService();
//         $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
//         $tenantUserModel = new TenantUserModel($db);

//         // 🔹 Check if User Exists
//         $userExists = $tenantUserModel->find($input->userId);
//         if (!$userExists) {
//             return $this->respond(['status' => false, 'message' => 'User not found'], 404);
//         }

//         // 🔹 Hash and Save Token
//         $hashedToken = hash('sha256', $input->token);

//         $updateData = [
//             'token' => $hashedToken,
//             'modifiedDate' => date('Y-m-d H:i:s'),
//             'modifiedBy' => $input->userId
//         ];

//         if ($tenantUserModel->update($input->userId, $updateData)) {
//             return $this->respond(['status' => true, 'message' => 'Token saved successfully']);
//         } else {
//             return $this->respond(['status' => false, 'message' => 'Failed to save token'], 500);
//         }

//     } catch (\Exception $e) {
//         return $this->respond(['status' => false, 'message' => 'Invalid token: ' . $e->getMessage()], 400);
//     }
// }

    
public function create()
{
    $input = $this->request->getPost();
    
    $rules = [
        'name' => ['rules' => 'required'],
        'email' => ['rules' => 'required|valid_email'],
        'mobileNo' => ['rules' => 'required'],
        'country' => ['rules' => 'required'],
        'location' => ['rules' => 'required'],
        'userType' => ['rules' => 'required'],
        'town' => ['rules' => 'required'],
        'postcode' => ['rules' => 'required'],
    ];

    if (!$this->validate($rules)) {
        return $this->fail(['status' => false, 'errors' => $this->validator->getErrors(), 'message' => 'Invalid Inputs'], 409);
    }

    $key = "Exiaa@11";
    $header = $this->request->getHeader("Authorization");
    $token = null;

    if (!empty($header) && preg_match('/Bearer\s(\S+)/', $header, $matches)) {
        $token = $matches[1];
    }

    try {
        $decoded = JWT::decode($token, new Key($key, 'HS256'));
    } catch (Exception $e) {
        return $this->fail(['status' => false, 'message' => 'Invalid Token'], 401);
    }

    // Handle Image Upload
    $photoUrl = $this->request->getFile('photoUrl');
    if ($photoUrl && $photoUrl->isValid() && !$photoUrl->hasMoved()) {
        $photoUrlPath = FCPATH . 'uploads/' . $decoded->tenantName . '/itemImages/';
        if (!is_dir($photoUrlPath)) mkdir($photoUrlPath, 0777, true);

        $photoUrlName = $photoUrl->getRandomName();
        $photoUrl->move($photoUrlPath, $photoUrlName);

        $input['photoUrl'] = $decoded->tenantName . '/itemImages/' . $photoUrlName;
    }

    // Insert Data
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $model = new TenantUserModel($db);
    $model->insert($input);

    return $this->respond(['status' => true, 'message' => 'User Added Successfully'], 200);
}
  
    
}





    