<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use Config\Database;


class Lead extends BaseController
{

    use ResponseTrait;


    public function create()
    {
        // Get the input data (assumed to be in JSON format)
        $input = $this->request->getJSON();
    
        // Define validation rules for lead fields
        $rules = [
            'fName' => ['rules' => 'required|string'],
            'lName' => ['rules' => 'required|string'],
            'primaryMobileNo' => ['rules' => 'required|numeric|min_length[10]|max_length[15]'],
            'secondaryMobileNo' => ['rules' => 'permit_empty|numeric|min_length[10]|max_length[15]'],
            'whatsAppNo' => ['rules' => 'permit_empty|numeric|min_length[10]|max_length[15]'],
            'email' => ['rules' => 'required|valid_email'],
            'interestedCategory' => ['rules' => 'required'],
            'interestedCategoryId' => ['rules' => 'required'],
            'leadSourceCategoryId' => ['rules' => 'required'],
            'leadSourceValue' => ['rules' => 'permit_empty'],
        ];
    
        // Validate the input data using the rules
        if($this->validate($rules)) {
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
    
            // Connect to the tenant's database
            $db = Database::connect($tenantConfig);
            
            // Assuming there's a LeadModel to handle lead-related operations
            $model = new leadModel($db);
    
            // Insert the lead data into the database
            $model->insert($input);
    
            // Return a success response
            return $this->respond(['status' => true, 'message' => 'Lead Created Successfully'], 200);
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
    
    
    public function index()
    {
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
 
         // Connect to the tenant's database
         $db = Database::connect($tenantConfig);
         // Load UserModel with the tenant database connection
         $leadModel = new \App\Models\LeadModel($db);
         return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $leadModel->findAll()], 200);
        
    }

    public function getAllLeadSource(){
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
        $leadSourceModel = new \App\Models\LeadSourceModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $leadSourceModel->findAll()], 200);
    }

    public function getAllLeadInterested(){
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
        $leadInterestedModel = new \App\Models\LeadInterestedModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $leadInterestedModel->findAll()], 200);
    }
}
