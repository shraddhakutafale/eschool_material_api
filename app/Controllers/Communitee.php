<?php
 
namespace App\Controllers;
 
use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\CommuniteeModel;
use App\Models\CandidateModel;
use App\Libraries\TenantService;



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

public function createCandidate()
{
    $input = $this->request->getJSON(true); // JSON RECEIVE

    /** JWT decode same logic */
    $key = "Exiaa@11";
    $header = $this->request->getHeaderLine("Authorization");
    $token = null;

    if ($header && preg_match('/Bearer\s(\S+)/', $header, $matches)) {
        $token = $matches[1];
    }

    $decoded = $token ? JWT::decode($token, new Key($key, 'HS256')) : null;
    $tenantName = $decoded->tenantName ?? 'default';

    /** No File Upload here because JSON use kar rahe ho */
$data = [
    'businessId'       => $decoded->businessId ?? 0,
    'name'             => $input['name'] ?? null,
    'about'            => $input['about'] ?? null,
    'mobileNo'         => $input['mobileNo'] ?? null,
    'email'            => $input['email'] ?? null,
    'age'              => $input['age'] ?? null,
    'height'           => $input['height'] ?? null,
    'weight'           => $input['weight'] ?? null,
    'maritalStatus'    => $input['maritalStatus'] ?? null,
    'motherTongue'     => $input['motherTongue'] ?? null,
    'physicalStatus'   => $input['physicalStatus'] ?? null,
    'bodyType'         => $input['bodyType'] ?? null,
    'profileCreatedBy' => $input['profileCreatedBy'] ?? null,
    'eatingHabits'     => $input['eatingHabits'] ?? null,
    'drinkingHabits'   => $input['drinkingHabits'] ?? null,
    'smokingHabits'    => $input['smokingHabits'] ?? null,

    // 🔹 Add religion-related fields
    'religion'         => $input['religion'] ?? null,
    'cast'             => $input['caste'] ?? null,
    'community'        => $input['community'] ?? null,
    'stars'            => $input['stars'] ?? null,
    'rashi'            => $input['rashi'] ?? null,
    'zodiac'           => $input['zodiac'] ?? null,
    'dosh'             => $input['havingDosh'] ?? null,
    'otherCommunities' => $input['otherCommunities'] ?? 0,

    'createdDate'      => date('Y-m-d H:i:s'),
    'modifiedDate'     => date('Y-m-d H:i:s'),
    'createdBy'        => $decoded->userId ?? null,
    'modifiedBy'       => $decoded->userId ?? null,
    'isActive'         => 1,
    'isDeleted'        => 0,
];

    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $model = new CandidateModel($db);

    $id = $model->insert($data);

    return $this->respond([
        'status' => true,
        'message' => 'Candidate created successfully',
        'data' => $id
    ], 200);
}



}
