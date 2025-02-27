<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use Config\Database;
use App\Models\LeadModel;
use App\Models\LeadSourceModel;
use App\Models\LeadInterestedModel;
use App\Libraries\TenantService;


class Lead extends BaseController
{
    use ResponseTrait;

    // Get all leads
    public function index()
    {
           // Insert the product data into the database
           $tenantService = new TenantService();
           // Connect to the tenant's database
           $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 
        $leadModel = new LeadModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $leadModel->findAll()], 200);
    }

    // Create a new lead
    public function create()
    {
        $input = $this->request->getJSON();
        $rules = [
            'fName' => ['rules' => 'required|string'],
            'lName' => ['rules' => 'required|string'],
            'primaryMobileNo' => ['rules' => 'required|numeric|min_length[10]|max_length[15]'],
            'secondaryMobileNo' => ['rules' => 'permit_empty|numeric|min_length[10]|max_length[15]'],
            'whatsAppNo' => ['rules' => 'permit_empty|numeric|min_length[10]|max_length[15]'],
            'email' => ['rules' => 'required|valid_email'],
        ];

        if ($this->validate($rules)) {
                // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 
            $model = new LeadModel($db);

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
                // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 
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
                // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));   $model = new LeadModel($db);

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
    {     // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 
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
