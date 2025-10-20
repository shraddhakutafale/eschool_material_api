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
    $input = $this->request->getPost();


    // Normalize all other fields: convert null, undefined, or "null" to empty string
    $optionalFields = ['content', 'buttonText', 'buttonUrl', 'profilePic'];
    foreach ($optionalFields as $field) {
        if (!isset($input[$field]) || $input[$field] === null || $input[$field] === 'null') {
            $input[$field] = '';
        }
    }

    // Validation rules: only title is required
    $rules = [
        'title' => ['rules' => 'required|string']
    ];

    if ($this->validate($rules)) {
        $key = "Exiaa@11";
        $header = $this->request->getHeader("Authorization");
        $token = null;

        if (!empty($header) && preg_match('/Bearer\s(\S+)/', $header, $matches)) {
            $token = $matches[1];
        }

        $decoded = JWT::decode($token, new Key($key, 'HS256'));
        $input['businessId'] = $decoded->businessId;

        // Handle image upload
        $profilePic = $this->request->getFile('profilePic');
        if ($profilePic && $profilePic->isValid() && !$profilePic->hasMoved()) {
            $profilePicPath = FCPATH . 'uploads/' . $decoded->tenantName . '/itemCategoryImages/';
            if (!is_dir($profilePicPath)) mkdir($profilePicPath, 0777, true);
            $profilePicName = $profilePic->getRandomName();
            $profilePic->move($profilePicPath, $profilePicName);
            $input['profilePic'] = $decoded->tenantName . '/itemCategoryImages/' . $profilePicName;
        }

        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new SlideModel($db);
        $model->insert($input);

        return $this->respond(['status' => true, 'message' => 'Slide Created Successfully'], 200);
    } else {
        return $this->fail([
            'status' => false,
            'errors' => $this->validator->getErrors(),
            'message' => 'Invalid Inputs',
        ], 409);
    }
}



public function update()
{
    $input = $this->request->getPost();


    $rules = [
        'slideId' => ['rules' => 'required|numeric'],
        'title' => ['rules' => 'required|string'],
        ];

    if (!$this->validate($rules)) {
        return $this->fail([
            'status' => false,
            'errors' => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ], 409);
    }

    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $model = new SlideModel($db);

    $slideId = $input['slideId'];
    $slide = $model->find($slideId);
    if (!$slide) return $this->fail(['status' => false, 'message' => 'Slide not found'], 404);

    // Preserve buttonText and buttonUrl even if showButton = 0
    $updateData = [
        'title' => $input['title'],
        'content' => $input['content'],
        'buttonText' => $input['buttonText'] ?? $slide['buttonText'],
        'buttonUrl' => $input['buttonUrl'] ?? $slide['buttonUrl'],
        'profilePic' => $input['profilePic'] ?? $slide['profilePic'],
    ];

    $updated = $model->update($slideId, $updateData);

    return $updated
        ? $this->respond([
            'status' => true,
            'message' => 'Slide Updated Successfully',
            'data' => $model->find($slideId) // Return slide data to frontend
        ], 200)
        : $this->fail(['status' => false, 'message' => 'Failed to update Slide'], 500);
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


    
    public function getAllSlide()
    {
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $slideModel = new SlideModel($db);
        
        $slides = $slideModel->findAll();
        return $this->respond(["status" => true, "message" => "All Slides Fetched", "data" => $slides], 200);
    }

}
