<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use Config\Database;
use App\Models\LeadModel;
use App\Models\LeadSource;
use App\Models\LeadInterested;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;


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

    public function getLeadsPaging()
    {
        $input = $this->request->getJSON();

        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'leadId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
        

        $tenantService = new TenantService();
        
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load leadModel with the tenant database connection
        $leadModel = new LeadModel($db);

        $query = $leadModel;

        if (!empty($filter)) {
            $filter = json_decode(json_encode($filter), true);

            foreach ($filter as $key => $value) {
                if (in_array($key, ['fName','lName','email', 'primaryMobileNo'])) {
                    $query->like($key, $value); // LIKE filter for specific fields
                }  else if ($key === 'createdDate') {
                    $query->where($key, $value); // Exact match filter for createdDate
                }
            }

            // Apply Date Range Filter (startDate and endDate)
            if (!empty($filter['startDate']) && !empty($filter['endDate'])) {
                $query->where('createdDate >=', $filter['startDate'])
                      ->where('createdDate <=', $filter['endDate']);
            }
    
            // Apply Last 7 Days Filter if requested
            if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last7days') {
                $last7DaysStart = date('Y-m-d', strtotime('-7 days'));  // 7 days ago from today
                $query->where('createdDate >=', $last7DaysStart);
            }
    
            // Apply Last 30 Days Filter if requested
            if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last30days') {
                $last30DaysStart = date('Y-m-d', strtotime('-30 days'));  // 30 days ago from today
                $query->where('createdDate >=', $last30DaysStart);
            }
        }
        
        $query->where('isDeleted',0);
        // Apply Sorting
        if (!empty($sortField) && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
            $query->orderBy($sortField, $sortOrder);
        }

        // Get Paginated Results
        $leads = $query->paginate($perPage, 'default', $page);
        $pager = $leadModel->pager;

        $response = [
            "status" => true,
            "message" => "All Lead Data Fetched",
            "data" => $leads,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]
        ];

        return $this->respond($response, 200);
    }


    // Create a new lead
    public function create()
    {
        $input = $this->request->getJSON();
        $rules = [
            'fName' => ['rules' => 'required'],
            'lName' => ['rules' => 'required'],

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
            $leadId = $input->leadId;  // Corrected here
            $lead = $model->find($leadId); // Assuming find method retrieves the lead

            if (!$lead) {
                return $this->fail(['status' => false, 'message' => 'Lead not found'], 404);
            }

           // List of fields you allow to update
            $allowedFields = [
                'fName', 'lName', 'primaryMobileNo', 'secondaryMobileNo',
                'whatsAppNo', 'email', 'leadInterestedId', 'leadInterestedIdValue',
                'leadSourceId', 'leadSourceValue', 'status', 'followUpDate', 'remark'
            ];

            $updateData = [];

            // Build updateData only from existing input fields
            foreach ($allowedFields as $field) {
                if (isset($input->$field)) {
                    $updateData[$field] = $input->$field;
                }
            }

            // Update only if there's something to update
            if (!empty($updateData)) {
                $updated = $model->update($leadId, $updateData);

                if ($updated) {
                    return $this->respond(['status' => true, 'message' => 'Lead updated successfully'], 200);
                } else {
                    return $this->fail(['status' => false, 'message' => 'Failed to update lead'], 500);
                }
            } else {
                return $this->fail(['status' => false, 'message' => 'No data provided to update'], 400);
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
         $leadSourceModel = new LeadSource($db);
        return $this->respond(["status" => true, "message" => "All Lead Sources Fetched", "data" => $leadSourceModel->findAll()], 200);
    }

    // Get all lead interests
    public function getAllLeadInterested()
    {
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $leadInterestedModel = new LeadInterested($db);
        return $this->respond(["status" => true, "message" => "All Lead Interests Fetched", "data" => $leadInterestedModel->findAll()], 200);
    }

    public function createWeb()
    {
        $input = $this->request->getJSON();
        $rules = [
            'fName' => ['rules' => 'required'],
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

}


