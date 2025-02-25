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

    // Update an existing lead
    public function update()
    {
        $input = $this->request->getJSON();

        // Validation rules for the lead
        $rules = [
            'leadId' => ['rules' => 'required|numeric'], // Ensure leadId is provided and is numeric
        ];

        // Validate the input
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

            // Connect to the tenant's database
            $db = Database::connect($tenantConfig);
            $model = new LeadModel($db);  // Use LeadModel for lead-related operations

            // Retrieve the lead by leadId
            $leadId = $input->leadId;
            $lead = $model->find($leadId); // Assuming find method retrieves the lead

            if (!$lead) {
                return $this->fail(['status' => false, 'message' => 'Lead not found'], 404);
            }

            // Prepare the data to be updated (exclude leadId if it's included)
            $updateData = [
                'fName' => $input->fName,
                'lName' => $input->lName,
                'primaryMobileNo' => $input->primaryMobileNo,
                'secondaryMobileNo' => $input->secondaryMobileNo,
                'whatsAppNo' => $input->whatsAppNo,
                'email' => $input->email
            ];

            // Update the lead with new data
            $updated = $model->update($leadId, $updateData);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Lead Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update lead'], 500);
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

    // Delete a lead
    public function delete()
    {
        $input = $this->request->getJSON();

        // Validation rules for the lead
        $rules = [
            'leadId' => ['rules' => 'required'], // Ensure leadId is provided and is numeric
        ];

        // Validate the input
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

            // Connect to the tenant's database
            $db = Database::connect($tenantConfig);
            $model = new LeadModel($db);

            // Retrieve the lead by leadId
            $leadId = $input->leadId;
            $lead = $model->find($leadId); // Assuming find method retrieves the lead

            if (!$lead) {
                return $this->fail(['status' => false, 'message' => 'Lead not found'], 404);
            }

            // Proceed to delete the lead
            $deleted = $model->delete($leadId);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Lead Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete lead'], 500);
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

    // Get all lead sources
    public function getAllLeadSource()
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
        $leadSourceModel = new LeadSourceModel($db);
        return $this->respond(["status" => true, "message" => "All Lead Sources Fetched", "data" => $leadSourceModel->findAll()], 200);
    }

    // Get all lead interests
    public function getAllLeadInterested()
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
        $leadInterestedModel = new LeadInterestedModel($db);
        return $this->respond(["status" => true, "message" => "All Lead Interests Fetched", "data" => $leadInterestedModel->findAll()], 200);
    }
}
