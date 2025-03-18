<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\FeeModel;
use Config\Database;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

class Course extends BaseController
{
    use ResponseTrait;

    public function getFeePaging()
    {
        $input = $this->request->getJSON();
    
        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'customerId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
    
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load CustomerModel with the tenant database connection
        $feeModel = new FeeModel($db);
    
        $query = $feeModel;
    
        if (!empty($filter)) {
            $filter = json_decode(json_encode($filter), true);
    
            foreach ($filter as $key => $value) {
                if (in_array($key, ['name', 'mobileNo', 'email'])) {
                    $query->like($key, $value); // LIKE filter for specific fields
                } else if ($key === 'createdDate') {
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
    
        // Ensure that the "deleted" status is 0 (active records)
        $query->where('isDeleted', 0);
    
        // Apply Sorting
        if (!empty($sortField) && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
            $query->orderBy($sortField, $sortOrder);
        }
    
        // Get Paginated Results
        $customers = $query->paginate($perPage, 'default', $page);
        $pager = $customerModel->pager;
    
        $response = [
            "status" => true,
            "message" => "All Customer Data Fetched",
            "data" => $customers,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]
        ];
    
        return $this->respond($response, 200);
    }
    

    public function createFee()
    {
        // Retrieve the input data from the request
        $input = $this->request->getJSON();
        
        // Define validation rules for required fields
        $rules = [
            'perticularName' => ['rules' => 'required'],
            'amount' => ['rules' => 'required']
        ];
    
        if ($this->validate($rules)) {
           
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new FeeModel($db);
            $model->insert($input);
    
            return $this->respond(['status' => true, 'message' => 'Customer Added Successfully'], 200);
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
        
        // Validation rules for the vendor
        $rules = [
            'customerId' => ['rules' => 'required|numeric'], // Ensure vendorId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
            $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new CustomerModel($db);

            // Retrieve the vendor by vendorId
            $customerId = $input['customerId'];
            $customer = $model->find($customerId); // Assuming find method retrieves the vendor
            



            if (!$customer) {
                return $this->fail(['status' => false, 'message' => 'Customer not found'], 404);
            }

            
            $updateData = [
                'name' => $input['name'],  // Corrected here
                'customerCode' => $input['customerCode'],  // Corrected here
                'mobileNo' => $input['mobileNo'],  // Corrected here
                'alternateMobileNo' => $input['alternateMobileNo'],  // Corrected here
                'emailId' => $input['emailId'],  // Corrected here
                'dateOfBirth' => $input['dateOfBirth'],  // Corrected here
                'gender' => $input['gender'],  // Corrected here

                
    
            ];     

            // Update the vendor with new data
            $updated = $model->update($customerId, $updateData);


            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Vendor Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update vendor'], 500);
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

        // Validation rules for the customer
        $rules = [
            'customerId' => ['rules' => 'required'], // Ensure customerId is provided
        ];

        // Validate the input
        if ($this->validate($rules)) {
            // Connect to the tenant's database
            $tenantService = new TenantService();
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));   
            $model = new CustomerModel($db);

            // Retrieve the customer by customerId
            $customerId = $input->customerId;
            $customer = $model->where('customerId', $customerId)->where('isDeleted', 0)->first(); // Only find active customers

            if (!$customer) {
                return $this->fail(['status' => false, 'message' => 'Customer not found or already deleted'], 404);
            }

            // Perform a soft delete (mark as deleted instead of removing the record)
            $updateData = [
                'isDeleted' => 1,
            ];
            $deleted = $model->update($customerId, $updateData);
            

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Customer marked as deleted'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete customer'], 500);
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

}
