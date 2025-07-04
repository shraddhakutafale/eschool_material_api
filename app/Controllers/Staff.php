<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\StaffModel;
use App\Models\StaffAttendanceModel;
use Config\Database;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;


class Staff extends BaseController
{
    use ResponseTrait;

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
        // Load StaffModel with the tenant database connection
        $staffModel = new StaffModel($db);
         $staffs = $staffModel
            ->where('staff_mst.staffId', $input->staffId)
            ->where('staff_mst.businessId', $input->businessId)
            ->where('staff_mst.isDeleted', 0)
            ->findAll();

        return $this->respond(["status" => true, "message" => "All Staff Data Fetched", "data" => $staffModel->findAll()], 200);
    }

    public function getStaffPaging() {
        $input = $this->request->getJSON();
    
        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'staffId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
    
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load StaffModel with the tenant database connection
        $staffModel = new StaffModel($db);
    
        $query = $staffModel;
    
        if (!empty($filter)) {
            $filter = json_decode(json_encode($filter), true);
    
            foreach ($filter as $key => $value) {
                if (in_array($key, ['empName', 'empCategory', 'empCode', 'empSal'])) {
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
        $query = $staffModel->where('isDeleted', 0)->where('businessId', $input->businessId); // Apply the deleted check at the beginning
    
        // Apply Sorting
        if (!empty($sortField) && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
            $query->orderBy($sortField, $sortOrder);
        }
    
        // Get Paginated Results
        $staffs = $query->paginate($perPage, 'default', $page);
        $pager = $staffModel->pager;
    
        $response = [
            "status" => true,
            "message" => "All Staff Data Fetched",
            "data" => $staffs,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]
        ];
    
        return $this->respond($response, 200);
    }
    

    // public function getStaffPaging() {
    //     $input = $this->request->getJSON();
    
    //     // Get the page number from the input, default to 1 if not provided
    //     $page = isset($input->page) ? $input->page : 1;
    //     $perPage = isset($input->perPage) ? $input->perPage : 10;
    //     $sortField = isset($input->sortField) ? $input->sortField : 'staffId';
    //     $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
    //     $search = isset($input->search) ? $input->search : '';
    //     $filter = $input->filter;
    
    //     $tenantService = new TenantService();
    //     $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    //     // Load StaffModel with the tenant database connection
    //     $staffModel = new StaffModel($db);
    
    //     $query = $staffModel;
    
    //     if (!empty($filter)) {
    //         $filter = json_decode(json_encode($filter), true);
    
    //         foreach ($filter as $key => $value) {
    //             if (in_array($key, ['empName', 'empCategory', 'empCode', 'empSal'])) {
    //                 $query->like($key, $value); // LIKE filter for specific fields
    //             } else if ($key === 'createdDate') {
    //                 $query->where($key, $value); // Exact match filter for createdDate
    //             }
    //         }
    
    //         // Apply Date Range Filter (startDate and endDate)
    //         if (!empty($filter['startDate']) && !empty($filter['endDate'])) {
    //             $query->where('createdDate >=', $filter['startDate'])
    //                 ->where('createdDate <=', $filter['endDate']);
    //         }
    
    //         // Apply Last 7 Days Filter if requested
    //         if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last7days') {
    //             $last7DaysStart = date('Y-m-d', strtotime('-7 days'));  // 7 days ago from today
    //             $query->where('createdDate >=', $last7DaysStart);
    //         }
    
    //         // Apply Last 30 Days Filter if requested
    //         if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last30days') {
    //             $last30DaysStart = date('Y-m-d', strtotime('-30 days'));  // 30 days ago from today
    //             $query->where('createdDate >=', $last30DaysStart);
    //         }
    //     }
    
    //     // Ensure that the "deleted" status is 0 (active records)
    //     $query->where('isDeleted', 0);
    
    //     // Apply Sorting
    //     if (!empty($sortField) && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
    //         $query->orderBy($sortField, $sortOrder);
    //     }
    
    //     // Get Paginated Results
    //     $staffs = $query->paginate($perPage, 'default', $page);
    //     $pager = $staffModel->pager;
    
    //     $response = [
    //         "status" => true,
    //         "message" => "All Staff Data Fetched",
    //         "data" => $staffs,
    //         "pagination" => [
    //             "currentPage" => $pager->getCurrentPage(),
    //             "totalPages" => $pager->getPageCount(),
    //             "totalItems" => $pager->getTotal(),
    //             "perPage" => $perPage
    //         ]
    //     ];
    
    //     return $this->respond($response, 200);
    // }
    


    public function create()
    {
        // Retrieve the input data from the request
        $input = $this->request->getPost();
        
        // Define validation rules for required fields
        $rules = [
            'empName' => ['rules' => 'required'],
            'empCode' => ['rules' => 'required'],
        ];
    
        if ($this->validate($rules)) {
            $key = "Exiaa@11";
            $header = $this->request->getHeader("Authorization");
            $token = null;
    
            // extract the token from the header
            if(!empty($header)) {
                if (preg_match('/Bearer\s(\S+)/', $header, $matches)) {
                    $token = $matches[1];
                }
            }
            
            $decoded = JWT::decode($token, new Key($key, 'HS256')); $key = "Exiaa@11";
            $header = $this->request->getHeader("Authorization");
            $token = null;
    
            // extract the token from the header
            if(!empty($header)) {
                if (preg_match('/Bearer\s(\S+)/', $header, $matches)) {
                    $token = $matches[1];
                }
            }
            
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
           
            // Handle image upload for the cover image
            $profilePic = $this->request->getFile('profilePic');
            $profilePicName = null;
    
            if ($profilePic && $profilePic->isValid() && !$profilePic->hasMoved()) {
                // Define the upload path for the cover image
                $profilePicPath = FCPATH . 'uploads/'. $decoded->tenantName .'/staffImages/';
                if (!is_dir($profilePicPath)) {
                    mkdir($profilePicPath, 0777, true); // Create directory if it doesn't exist
                }
    
                // Move the file to the desired directory with a unique name
                $profilePicName = $profilePic->getRandomName();
                $profilePic->move($profilePicPath, $profilePicName);
    
                // Get the URL of the uploaded cover image and remove the 'uploads/profilePics/' prefix
                $profilePicUrl = 'uploads/staffImages/' . $profilePicName;
                $profilePicUrl = str_replace('uploads/staffImages/', '', $profilePicUrl);
    
                // Add the cover image URL to the input data
                // $input['profilePic'] = $profilePicUrl; 
                $input['profilePic'] = $decoded->tenantName . '/staffImages/' .$profilePicUrl;
            }
    
           
    
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            // $model = new StaffModel($db);
            // $model->insert($input);
            //     // Start DB transaction
                // $db->transStart();

                // Save staff
                $staffModel = new StaffModel($db);
                $staffModel->insert($input);
                $staffId = $staffModel->getInsertID();

                // Save attendance
                $attendanceModel = new StaffAttendanceModel($db);
                $attendanceData = [
                    'staffId'        => $staffId,
                    'attendanceDate' => date('Y-m-d'),
                    'inTime'         => date('H:i:s'),
                    'outTime'        => null,
                    'deviceId'       => null,
                    'status'         => '',
                    'present' => 0
                ];
                $attendanceModel->insert($attendanceData);

                $db->transComplete();
    
            return $this->respond(['status' => true, 'message' => 'Staff Added Successfully'], 200);
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

    //     public function create()
    // {
    //     $input = $this->request->getPost();

    //     $rules = [
    //         'empName' => ['rules' => 'required'],
    //         'empCode' => ['rules' => 'required'],
    //     ];

    //     if (!$this->validate($rules)) {
    //         return $this->fail([
    //             'status' => false,
    //             'errors' => $this->validator->getErrors(),
    //             'message' => 'Invalid Inputs',
    //         ], 409);
    //     }

    //     // Decode JWT token
    //     $key = "Exiaa@11";
    //     $header = $this->request->getHeader("Authorization");
    //     $token = null;

    //     if (!empty($header) && preg_match('/Bearer\s(\S+)/', $header, $matches)) {
    //         $token = $matches[1];
    //     }

    //     $decoded = JWT::decode($token, new Key($key, 'HS256'));

    //     // Handle profilePic upload
    //     $profilePic = $this->request->getFile('profilePic');
    //     if ($profilePic && $profilePic->isValid() && !$profilePic->hasMoved()) {
    //         $profilePicPath = FCPATH . 'uploads/' . $decoded->tenantName . '/staffImages/';
    //         if (!is_dir($profilePicPath)) {
    //             mkdir($profilePicPath, 0777, true);
    //         }
    //         $profilePicName = $profilePic->getRandomName();
    //         $profilePic->move($profilePicPath, $profilePicName);
    //         $input['profilePic'] = $decoded->tenantName . '/staffImages/' . $profilePicName;
    //     }

    //     // Connect to tenant DB
    //     $tenantService = new TenantService();
    //     $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    //     // Start DB transaction
    //     $db->transStart();

    //     // Save staff
    //     $staffModel = new StaffModel($db);
    //     $staffModel->insert($input);
    //     $staffId = $staffModel->getInsertID();

    //     // Save attendance
    //     $attendanceModel = new StaffAttendanceModel($db);
    //     $attendanceData = [
    //         'staffId'        => $staffId,
    //         'attendanceDate' => date('Y-m-d'),
    //         'inTime'         => date('H:i:s'),
    //         'outTime'        => null,
    //         'deviceId'       => null,
    //         'status'         => 'Present',
    //         'present'        => 1
    //     ];
    //     $attendanceModel->insert($attendanceData);

    //     $db->transComplete();

    //     if ($db->transStatus() === false) {
    //         return $this->failServerError('Failed to add staff or attendance.');
    //     }

    //     return $this->respond(['status' => true, 'message' => 'Staff and attendance added successfully'], 200);
    // }


    public function update()
    {
        $input = $this->request->getPost();

        // Validation rules for the staff
        $rules = [
            'staffId' => ['rules' => 'required|numeric'], // Ensure staffId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new StaffModel($db);

            // Retrieve the staff by staffId
            $staffId = $input['staffId'];  // Corrected here
            $staff = $model->find($staffId);

            if (!$staff) {
                return $this->fail(['status' => false, 'message' => 'Staff not found'], 404);
            }

            // Prepare the data to be updated (exclude staffId if it's included)
            $updateData = [
                'empName'=> $input['empName'],
                'empCategory'=> $input['empCategory'],
                'empCode'=> $input['empCode'],
                'aadharNumber'=> $input['aadharNumber'],
                'panNumber'=> $input['panNumber'],
                'uanNumber'=> $input['uanNumber'],
                'ipNumber'=> $input['ipNumber'],
                'fatherName'=> $input['fatherName'],
                'empSal'=> $input['empSal'],
                'empDoj'=> $input['empDoj'],
                'empDol'=> $input['empDol'],

                
               
            ];

            // Update the staff with new data
            $updated = $model->update($staffId, $updateData);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Staff Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update staff'], 500);
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

        // Validation rules for the staff
        $rules = [
            'staffId' => ['rules' => 'required'], // Ensure staffID is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
                // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));   $model = new StaffModel($db);

            // Retrieve the staff by staffId
            $staffId = $input->staffId;
            $staff = $model->find($staffId); // Assuming find method retrieves the staff

            if (!$staff) {
                return $this->fail(['status' => false, 'message' => 'staff not found'], 404);
            }

            // Proceed to delete the staff
            $deleted = $model->delete($staffId);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'staff Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete staff'], 500);
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
