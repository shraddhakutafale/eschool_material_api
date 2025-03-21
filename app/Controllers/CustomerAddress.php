<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Libraries\TenantService;
use CodeIgniter\API\ResponseTrait;
use Config\Database;
use App\Models\CustomerAddressModel;
use App\Models\CustomerModel;
use App\Models\TenantUserModel;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

class CustomerAddress extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        //
    }

    public function loginWithMobileUid()
    {
        $input = $this->request->getJSON();
        log_message('error', 'mobileNumber: '.$input->mobileNumber);
        log_message('error', 'uid: '.$input->uid);
        $tenantService = new TenantService();
        
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $tenantUserModel = new TenantUserModel($db);
        
        $tenantUser = $tenantUserModel->where('mobileNo', $input->mobileNumber)->first();
        if(is_null($tenantUser)) {
            $tenantUserInput = [
                'uid' => $input->uid,
                'mobileNo' => $input->mobileNumber
            ];
            if($insertId = $tenantUserModel->insert($tenantUserInput)) {
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


    
    public function create()
    {
        $input = $this->request->getPost();
    
        // Validate Required Fields
        $rules = [
            'customerId'    => 'required',
            'fullName'      => 'required',
            'mobileNo'      => 'required',
            'pincode'       => 'required',
            'addressLine1'  => 'required',
            'city'          => 'required',
            'state'         => 'required',
            'country'       => 'required'
        ];
    
        if (!$this->validate($rules)) {
            return $this->fail(['status' => false, 'message' => 'Validation Failed', 'errors' => $this->validator->getErrors()], 400);
        }
    
        // Get Database Connection
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    
        if (!$db) {
            return $this->fail(['status' => false, 'message' => 'Database connection failed'], 500);
        }
    
        // Insert Address Data
        $customerAddressModel = new CustomerAddressModel($db);
        $data = [
            'customerId'          => $input['customerId'],
            'fullName'            => $input['fullName'],
            'mobileNo'            => $input['mobileNo'],
            'pincode'             => $input['pincode'],
            'addressLine1'        => $input['addressLine1'],
            'addressLine2'        => isset($input['addressLine2']) ? $input['addressLine2'] : null,
            'landmark'            => isset($input['landmark']) ? $input['landmark'] : null,
            'city'                => $input['city'],
            'state'               => $input['state'],
            'country'             => $input['country'],
            'deliveryInstruction' => isset($input['deliveryInstruction']) ? $input['deliveryInstruction'] : null,
            'isActive'            => 1,
            'isDeleted'           => 0,
            'createdDate'         => date('Y-m-d H:i:s'),
            'createdBy'           => $input['customerId']
        ];
    
        if (!$customerAddressModel->insert($data)) {
            return $this->fail(['status' => false, 'message' => 'Address insertion failed'], 500);
        }
    
        return $this->respond(['status' => true, 'message' => 'Address Added Successfully'], 200);
    }
    
    public function update()
    {
        $input = $this->request->getPost();
    
        // Validate User ID
        if (!isset($input['userId']) || empty($input['userId'])) {
            return $this->fail(['status' => false, 'message' => 'User ID is required'], 400);
        }
    
        // Define Validation Rules
        $rules = [
            'name'      => 'required',
            'email'     => 'required|valid_email',
            'mobileNo'  => 'required',
            'country'   => 'required',
            'location'  => 'required',
            'userType'  => 'required',
            'town'      => 'required',
            'postcode'  => 'required'
        ];
    
        // Validate Input
        if (!$this->validate($rules)) {
            return $this->fail(['status' => false, 'message' => 'Validation Failed', 'errors' => $this->validator->getErrors()], 400);
        }
    
        // Get Database Connection
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    
        if (!$db) {
            return $this->fail(['status' => false, 'message' => 'Database connection failed'], 500);
        }
    
        // Update User Record in tenant_user
        $tenantUserModel = new TenantUserModel($db);
        if (!$tenantUserModel->update($input['userId'], $input)) {
            return $this->fail(['status' => false, 'message' => 'Update failed'], 500);
        }
    
        // If userType is 'Customer', insert into customer_mst
        if ($input['userType'] === 'Customer') {
            $customerModel = new CustomerModel($db);
            $customerData = [
                'name'              => $input['name'],
                'mobileNo'          => $input['mobileNo'],
                'emailId'           => $input['email'],
                'gender'            => isset($input['gender']) ? $input['gender'] : null,
                'dateOfBirth'       => isset($input['dateOfBirth']) ? $input['dateOfBirth'] : null,
                'alternateMobileNo' => isset($input['alternateMobileNo']) ? $input['alternateMobileNo'] : null,
                'createdBy'         => $input['userId'],
                'createdDate'       => date('Y-m-d H:i:s'),
                'isActive'          => 1,
                'isDeleted'         => 0
            ];
    
            // Insert into customer_mst and get the inserted customerId
            $customerId = $customerModel->insert($customerData);
    
            if ($customerId) {
                // Update tenant_user with the customerId in userTypeSpeid
                $tenantUserModel->update($input['userId'], ['userTypeSpeid' => $customerId]);
            }
        }
    
        return $this->respond(['status' => true, 'message' => 'User Updated Successfully'], 200);
    }
    

    public function addAddress()
    {
        $input = $this->request->getPost();
    
        // Validate User ID
        if (!isset($input['userId']) || empty($input['userId'])) {
            return $this->fail(['status' => false, 'message' => 'User ID is required'], 400);
        }
    
        // Get Database Connection
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    
        if (!$db) {
            return $this->fail(['status' => false, 'message' => 'Database connection failed'], 500);
        }
    
        // Fetch customerId from tenant_user table using userId
        $tenantUserModel = new TenantUserModel($db);
        $tenantUser = $tenantUserModel->where('userId', $input['userId'])->first();
    
        if (!$tenantUser || empty($tenantUser['userTypeSpeid'])) {
            return $this->fail(['status' => false, 'message' => 'Customer ID not found'], 400);
        }
    
        $customerId = $tenantUser['userTypeSpeid']; // Customer ID from userTypeSpeid
    
        // Define Validation Rules
        $rules = [
            'fullName'       => 'required',
            'mobileNo'       => 'required',
            'pincode'        => 'required',
            'addressLine1'   => 'required',
            'city'           => 'required',
            'state'          => 'required',
            'country'        => 'required'
        ];
    
        if (!$this->validate($rules)) {
            return $this->fail(['status' => false, 'message' => 'Validation Failed', 'errors' => $this->validator->getErrors()], 400);
        }
    
        // Insert Address into customer_address table
        $customerAddressModel = new CustomerAddressModel($db);
        $addressData = [
            'customerId'          => $customerId, // Use fetched customerId
            'fullName'            => $input['fullName'],
            'mobileNo'            => $input['mobileNo'],
            'pincode'             => $input['pincode'],
            'addressLine1'        => $input['addressLine1'],
            'addressLine2'        => isset($input['addressLine2']) ? $input['addressLine2'] : null,
            'landmark'            => isset($input['landmark']) ? $input['landmark'] : null,
            'city'                => $input['city'],
            'state'               => $input['state'],
            'country'             => $input['country'],
            'deliveryInstruction' => isset($input['deliveryInstruction']) ? $input['deliveryInstruction'] : null,
            'isActive'            => 1,
            'isDeleted'           => 0,
            'createdBy'           => $customerId,
            'createdDate'         => date('Y-m-d H:i:s'),
        ];
    
        if (!$customerAddressModel->insert($addressData)) {
            return $this->fail(['status' => false, 'message' => 'Address addition failed'], 500);
        }
    
        return $this->respond(['status' => true, 'message' => 'Address Added Successfully'], 200);
    }
    
    

}


    