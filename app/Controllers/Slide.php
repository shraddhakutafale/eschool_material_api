<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use Config\Database;
use App\Models\SlideModel;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;



class Slide extends BaseController
{
    use ResponseTrait;

    // Get all leads
    public function index()
    {
       
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 
        $slideModel = new SlideModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $slideModel->findAll()], 200);
    }

    public function create()
    {
        // Retrieve input data from the request
        $input = $this->request->getPost();
        
        // Define validation rules for required fields
        $rules = [
            'title' => ['rules' => 'required|string'],
            'content' => ['rules' => 'required|string'],
            'buttonUrl' => ['rules' => 'required|valid_url'],
            'buttonText' => ['rules' => 'required']
        ];
    
        if ($this->validate($rules)) {
            $key = "Exiaa@11";
            $header = $this->request->getHeader("Authorization");
            $token = null;
    
            // Extract the token from the header
            if (!empty($header)) {
                if (preg_match('/Bearer\s(\S+)/', $header, $matches)) {
                    $token = $matches[1];
                }
            }
            
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
            
            // Handle image upload for the slide image
            $profilePic = $this->request->getFile('profilePic');
            $profilePicName = null;
    
            if ($profilePic && $profilePic->isValid() && !$profilePic->hasMoved()) {
                // Define the upload path for slide images
                $profilePicPath = FCPATH . 'uploads/' . $decoded->tenantName . '/slideImages/';
                if (!is_dir($profilePicPath)) {
                    mkdir($profilePicPath, 0777, true); // Create directory if it doesn't exist
                }
    
                // Move file with a unique name
                $profilePicName = $profilePic->getRandomName();
                $profilePic->move($profilePicPath, $profilePicName);
    
                // Get the URL of the uploaded image
                $profilePicUrl = 'uploads/slideImages/' . $profilePicName;
                $profilePicUrl = str_replace('uploads/slideImages/', '', $profilePicUrl);
    
                // Add the image URL to the input data
                $input['profilePic'] = $decoded->tenantName . '/slideImages/' . $profilePicUrl;
            }
    
            // Connect to the tenant's database
            $tenantService = new TenantService();
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            
            $model = new SlideModel($db);
            $model->insert($input);
    
            return $this->respond(['status' => true, 'message' => 'Slide Created Successfully'], 200);
        } else {
            // If validation fails, return the error messages
            $response = [
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs',
            ];
            return $this->fail($response, 409);
        }
    }
    

}
