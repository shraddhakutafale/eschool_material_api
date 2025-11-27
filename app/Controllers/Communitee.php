<?php
 
namespace App\Controllers;
 
use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\CommuniteeModel;

use App\Models\RoleModel;



use App\Models\TenantModel;


class Communitee extends BaseController
{
    use ResponseTrait;
     
    public function index()
    {
        $communitees = new CommuniteeModel;
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $communitees->findAll()], 200);
    }


public function registerCommunitee()
{
    $input = $this->request->getJSON();

    if(!$input || !isset($input->email) || !isset($input->password) || !isset($input->userName)) {
        return $this->respond([
            'status' => false,
            'message' => 'Name, email and password required'
        ], 400);
    }

    $communiteeModel = new CommuniteeModel();

    // Check email
    $existing = $communiteeModel->where('email', $input->email)->first();
    if($existing){
        return $this->respond(['status' => false, 'message' => 'Email already exists'], 409);
    }

    // Insert only fields that exist in table
    $data = [
        'userName'   => $input->userName,   // ✅ use actual userName
        'email'      => $input->email,
        'password'   => password_hash($input->password, PASSWORD_DEFAULT),
        'roleId'     => 3,
        'businessId' => 0,
        'isActive'   => 1,
        'isDeleted'  => 0
    ];

    $communiteeModel->insert($data);

    return $this->respond([
        'status' => true,
        'message' => 'Registered successfully'
    ], 200);
}



public function communiteeLogin()
{
    $input = $this->request->getJSON();

    if(!$input || !isset($input->email) || !isset($input->password)) {
        return $this->respond([
            'status' => false,
            'message' => 'email and password are required'
        ], 400);
    }

    // Use CommuniteeModel (correct DB group exiaa_ex0018)
    $communiteeModel = new \App\Models\CommuniteeModel();

    // Find user
    $user = $communiteeModel->where('email', $input->email)->first();
    
    if(!$user){
        return $this->respond(['status' => false, 'message' => 'Invalid email'], 401);
    }

    // Verify password
    if(!password_verify($input->password, $user['password'])){
        return $this->respond(['status' => false, 'message' => 'Incorrect password'], 401);
    }

    // JWT
    $key = "Exiaa@11";
    $iat = time();
    $exp = $iat + 3600;

    $payload = [
        "iat" => $iat,
        "exp" => $exp,
        "email" => $user['email'],
        "userId" => $user['userId'],
        "roleId" => $user['roleId'],
        "businessId" => $user['businessId']
    ];

    $token = \Firebase\JWT\JWT::encode($payload, $key, 'HS256');

    return $this->respond([
        'status' => true,
        'message' => 'Login successful',
        'access_token' => $token,
        'expires_in'   => $exp,
        'user' => [
            "userId" => $user['userId'],
            "email"  => $user['email'],
            "roleId" => $user['roleId'],
            "businessId" => $user['businessId']
        ]
    ], 200);
}

}
