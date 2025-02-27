<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use Config\Database;
use App\Models\SlideModel;


class Slide extends BaseController
{
    use ResponseTrait;

    // Get all leads
    public function index()
    {
        $tenantConfigHeader = $this->request->getHeaderLine('X-Tenant-Config');
        if (!$tenantConfigHeader) {
            throw new \Exception('Tenant configuration not found.');
        }

        // Decode the tenantConfig JSON
        $tenantConfig = json_decode($tenantConfigHeader, true);
        if (!$tenantConfig) {
            throw new \Exception('Invalid tenant configuration.');
        }

        // Connect to the tenant's database
        $db = Database::connect($tenantConfig);
        $slideModel = new SlideModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $slideModel->findAll()], 200);
    }

  // Create a new slide
public function create()
{
    $input = $this->request->getPost();
    $rules = [
        'title' => ['rules' => 'required|string'],
        'content' => ['rules' => 'required|string'],
        'buttonUrl' => ['rules' => 'required|valid_url'],
        'buttonText' => ['rules' => 'required']

    ];

    if ($this->validate($rules)) {
        // Retrieve tenantConfig from the headers
        $tenantConfigHeader = $this->request->getHeaderLine('X-Tenant-Config');
        if (!$tenantConfigHeader) {
            throw new \Exception('Tenant configuration not found.');
        }

        // Decode the tenantConfig JSON
        $tenantConfig = json_decode($tenantConfigHeader, true);
        if (!$tenantConfig) {
            throw new \Exception('Invalid tenant configuration.');
        }

         // Handle image upload for the cover image
         $coverImage = $this->request->getFile('coverImageUrl');
         $coverImageName = null;
 
         if ($coverImage && $coverImage->isValid() && !$coverImage->hasMoved()) {
             // Define the upload path for the cover image
             $coverImagePath = FCPATH . 'uploads/slideImage/';
             if (!is_dir($coverImagePath)) {
                 mkdir($coverImagePath, 0777, true); // Create directory if it doesn't exist
             }
 
             // Move the file to the desired directory with a unique name
             $coverImageName = $coverImage->getRandomName();
             $coverImage->move($coverImagePath, $coverImageName);
 
             // Get the URL of the uploaded cover image and remove the 'uploads/slideImages/' prefix
             $coverImageUrl = 'uploads/slideImage/' . $coverImageName;
             $coverImageUrl = str_replace('uploads/slideImage/', '', $coverImageUrl);
 
             // Add the cover image URL to the input data
             $input['coverImageUrl'] = $coverImageUrl; 
         }

        // Connect to the tenant's database
        $db = Database::connect($tenantConfig);
        $model = new SlideModel($db);

        $model->insert($input);


        // Return a success response
        return $this->respond(['status' => true, 'message' => 'Slide Created Successfully'], 200);
    } else {
        // Return validation errors if the rules are not satisfied
        $response = [
            'status' => false,
            'errors' => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ];
        return $this->fail($response, 409);
    }
}

}
