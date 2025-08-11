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
            $input['businessId'] = $decoded->businessId;
            
            // Handle image upload for the slide image
            $profilePic = $this->request->getFile('profilePic');
            $profilePicName = null;
    
            if ($profilePic && $profilePic->isValid() && !$profilePic->hasMoved()) {
                // Define the upload path for slide images
                $profilePicPath = FCPATH . 'uploads/' . $decoded->tenantName . '/itemCategoryImages/';
                if (!is_dir($profilePicPath)) {
                    mkdir($profilePicPath, 0777, true); // Create directory if it doesn't exist
                }
    
                // Move file with a unique name
                $profilePicName = $profilePic->getRandomName();
                $profilePic->move($profilePicPath, $profilePicName);
    
                // Get the URL of the uploaded image
                $profilePicUrl = 'uploads/itemCategoryImages/' . $profilePicName;
                $profilePicUrl = str_replace('uploads/itemCategoryImages/', '', $profilePicUrl);
    
                // Add the image URL to the input data
                $input['profilePic'] = $decoded->tenantName . '/itemCategoryImages/' . $profilePicUrl;
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

    public function update()
    {
        $input = $this->request->getPost();

        // Validation rules for the slide
        $rules = [
            'slideId' => ['rules' => 'required|numeric'], // Ensure slideId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new SlideModel($db);

            // Retrieve the Slide by slideId
            $slideId = $input['slideId'];  // Corrected here
            $slide = $model->find($slideId);

            if (!$slide) {
                return $this->fail(['status' => false, 'message' => 'Slide not found'], 404);
            }

            // Prepare the data to be updated (exclude slideId if it's included)
            $updateData = [
                'title' => $input['title'],
                'content' => $input['content'],
                'buttonUrl' => $input['buttonUrl'],
                'buttonText' => $input['buttonText'],
                'profilePic' => $input['profilePic'],
               
            ];

            // Update the slide with new data
            $updated = $model->update($slideId, $updateData);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Slide Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update Slide'], 500);
            }
        } else {
            // Validation failed
            $response = [
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ];
            return $this->fail($response, 409);
        }
    }

    public function delete()
    {
        $input = $this->request->getJSON();

        // Validation rules for the Slide
        $rules = [
            'slideId' => ['rules' => 'required'], // Ensure slideId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
                // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));   $model = new SlideModel($db);

            // Retrieve the slide by slideId
            $slideId = $input->slideId;
            $slide = $model->find($slideId); // Assuming find method retrieves the slide

            if (!$slide) {
                return $this->fail(['status' => false, 'message' => 'slide not found'], 404);
            }

            // Proceed to delete the slide
            $deleted = $model->delete($slideId);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'slide Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete slide'], 500);
            }
        } else {
            // Validation failed
            $response = [
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ];
            return $this->fail($response, 409);
        }
    }


    
    public function getall()
    {
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $slideModel = new SlideModel($db);
        
        $slides = $slideModel->findAll();
        return $this->respond(["status" => true, "message" => "All Slides Fetched", "data" => $slides], 200);
    }

}
