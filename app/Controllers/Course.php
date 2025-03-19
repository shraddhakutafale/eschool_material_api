<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\FeeModel;
use App\Models\ShiftModel;
use App\Models\SubjectModel;


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
        $sortField = isset($input->sortField) ? $input->sortField : 'feeId';
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
                if (in_array($key, ['particularName', 'amount'])) {
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
        $fees = $query->paginate($perPage, 'default', $page);
        $pager = $feeModel->pager;
    
        $response = [
            "status" => true,
            "message" => "All Customer Data Fetched",
            "data" => $fees,
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
    
            return $this->respond(['status' => true, 'message' => 'Fee Added Successfully'], 200);
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


    public function updateFee()
    {
        $input = $this->request->getJSON();
        
        // Validation rules for the vendor
        $rules = [
            'feeId' => ['rules' => 'required|numeric'], // Ensure vendorId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
            $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new FeeModel($db);

            // Retrieve the vendor by vendorId
            // $feeId = $input ->$feeId;
            // $fee = $model->find($feeId); 

            $fee = $model->find($input->feeId);




        if (!$fee) {
            return $this->fail(['status' => false, 'message' => 'Fee not found'], 404);
         }

            
         $updateData = [
            'perticularName' => $input -> perticularName,  // Corrected here
            'amount' => $input -> amount,  // Corrected here
        
        ];     

            // Update the vendor with new data
         $updated = $model->update($fee, $updateData);


         if ($updated) {
             return $this->respond(['status' => true, 'message' => 'Fee Updated Successfully'], 200);
        } else {
            return $this->fail(['status' => false, 'message' => 'Failed to update Fee'], 500);
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


    public function deleteFee()
    {
        $input = $this->request->getJSON();

        // Validation rules for the customer
        $rules = [
            'feeId' => ['rules' => 'required'], // Ensure customerId is provided
        ];

        // Validate the input
        if ($this->validate($rules)) {
            // Connect to the tenant's database
            $tenantService = new TenantService();
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));   
            $model = new FeeModel($db);

            // Retrieve the customer by customerId
            $feeId = $input->feeId;
            $fee = $model->where('feeId', $feeId)->where('isDeleted', 0)->first(); // Only find active customers

            if (!$fee) {
                return $this->fail(['status' => false, 'message' => 'Customer not found or already deleted'], 404);
            }

            // Perform a soft delete (mark as deleted instead of removing the record)
            $updateData = [
                'isDeleted' => 1,
            ];
            $deleted = $model->update($feeId, $updateData);
            

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Fee marked as deleted'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete fee'], 500);
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


    public function getShiftPaging()
    {
        $input = $this->request->getJSON();
    
        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'shiftId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
    
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load CustomerModel with the tenant database connection
        $shiftModel = new ShiftModel($db);
    
        $query = $shiftModel;
    
        if (!empty($filter)) {
            $filter = json_decode(json_encode($filter), true);
    
            foreach ($filter as $key => $value) {
                if (in_array($key, ['shiftName', 'startTime', 'endTime'])) {
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
        $shifts = $query->paginate($perPage, 'default', $page);
        $pager = $shiftModel->pager;
    
        $response = [
            "status" => true,
            "message" => "All Customer Data Fetched",
            "data" => $shifts,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]
        ];
    
        return $this->respond($response, 200);
    }
    

    public function createShift()
    {
        // Retrieve the input data from the request
        $input = $this->request->getJSON();
        
        // Define validation rules for required fields
        $rules = [
            'shiftName' => ['rules' => 'required'],
            'startTime' => ['rules' => 'required'],
            'endTime' => ['rules' => 'required'],
            'emailTime' => ['rules' => 'required']

        ];
    
        if ($this->validate($rules)) {
           
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new ShiftModel($db);
            $model->insert($input);
    
            return $this->respond(['status' => true, 'message' => 'Shift Added Successfully'], 200);
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


    

    public function updateShift()
    {
        $input = $this->request->getJSON();
        
        // Validation rules for the vendor
        $rules = [
            'shiftId' => ['rules' => 'required|numeric'], // Ensure vendorId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
            $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new ShiftModel($db);

            // Retrieve the vendor by vendorId
            // $feeId = $input ->$feeId;
            // $fee = $model->find($feeId); 

            $shift = $model->find($input->shiftId);




        if (!$shift) {
            return $this->fail(['status' => false, 'message' => 'Shift not found'], 404);
         }

            
         $updateData = [
            'shiftName' => $input -> shiftName,  
            'startTime' => $input -> startTime,  
            'endTime' => $input -> endTime, 
            'emailTime' => $input -> emailTime 
        ];     

            // Update the vendor with new data
         $updated = $model->update($shift, $updateData);


         if ($updated) {
             return $this->respond(['status' => true, 'message' => 'Shift Updated Successfully'], 200);
        } else {
            return $this->fail(['status' => false, 'message' => 'Failed to update Shift'], 500);
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


    public function deleteShift()
    {
        $input = $this->request->getJSON();

        // Validation rules for the customer
        $rules = [
            'shiftId' => ['rules' => 'required'], // Ensure customerId is provided
        ];

        // Validate the input
        if ($this->validate($rules)) {
            // Connect to the tenant's database
            $tenantService = new TenantService();
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));   
            $model = new ShiftModel($db);

            // Retrieve the customer by customerId
            $shiftId = $input->shiftId;
            $shift = $model->where('shiftId', $shiftId)->where('isDeleted', 0)->first(); // Only find active customers

            if (!$shift) {
                return $this->fail(['status' => false, 'message' => 'Shift not found or already deleted'], 404);
            }

            // Perform a soft delete (mark as deleted instead of removing the record)
            $updateData = [
                'isDeleted' => 1,
            ];
            $deleted = $model->update($shiftId, $updateData);
            

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Shift marked as deleted'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete shift'], 500);
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


    
    public function getSubjectPaging()
    {
        $input = $this->request->getJSON();
    
        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'subjectId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
    
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load CustomerModel with the tenant database connection
        $subjectModel = new SubjectModel($db);
    
        $query = $subjectModel;
    
        if (!empty($filter)) {
            $filter = json_decode(json_encode($filter), true);
    
            foreach ($filter as $key => $value) {
                if (in_array($key, ['subjectName'])) {
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
        $subjects = $query->paginate($perPage, 'default', $page);
        $pager = $subjectModel->pager;
    
        $response = [
            "status" => true,
            "message" => "All Customer Data Fetched",
            "data" => $subjects,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]
        ];
    
        return $this->respond($response, 200);
    }
    

    public function createSubject()
    {
        // Retrieve the input data from the request
        $input = $this->request->getJSON();
        
        // Define validation rules for required fields
        $rules = [
            'subjectName' => ['rules' => 'required'],
            'subjectDesc' => ['rules' => 'required']
          

        ];
    
        if ($this->validate($rules)) {
           
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new SubjectModel($db);
            $model->insert($input);
    
            return $this->respond(['status' => true, 'message' => 'Subject Added Successfully'], 200);
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


    

    public function updateSubject()
    {
        $input = $this->request->getJSON();
        
        // Validation rules for the vendor
        $rules = [
            'subjectId' => ['rules' => 'required|numeric'], // Ensure vendorId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
            $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new SubjectModel($db);

            // Retrieve the vendor by vendorId
            // $feeId = $input ->$feeId;
            // $fee = $model->find($feeId); 

            $subject = $model->find($input->subjectId);




        if (!$subject) {
            return $this->fail(['status' => false, 'message' => 'Subject not found'], 404);
         }

            
         $updateData = [
            'subjectName' => $input -> subjectName,  
            'subjectDesc' => $input -> subjectDesc 
           
        ];     

            // Update the vendor with new data
         $updated = $model->update($subject, $updateData);


         if ($updated) {
             return $this->respond(['status' => true, 'message' => 'Subject Updated Successfully'], 200);
        } else {
            return $this->fail(['status' => false, 'message' => 'Failed to update Subject'], 500);
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


    public function deleteSubject()
    {
        $input = $this->request->getJSON();

        // Validation rules for the customer
        $rules = [
            'subjectId' => ['rules' => 'required'], // Ensure customerId is provided
        ];

        // Validate the input
        if ($this->validate($rules)) {
            // Connect to the tenant's database
            $tenantService = new TenantService();
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));   
            $model = new SubjectModel($db);

            // Retrieve the customer by customerId
            $subjectId = $input->subjectId;
            $subject = $model->where('subjectId', $subjectId)->where('isDeleted', 0)->first(); // Only find active customers

            if (!$subject) {
                return $this->fail(['status' => false, 'message' => 'Subject not found or already deleted'], 404);
            }

            // Perform a soft delete (mark as deleted instead of removing the record)
            $updateData = [
                'isDeleted' => 1,
            ];
            $deleted = $model->update($subjectId, $updateData);
            

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Subject marked as deleted'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete subject'], 500);
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
