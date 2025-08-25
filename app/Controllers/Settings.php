<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\SettingsModel;
use App\Models\VisionMissionModel;
use App\Models\FooterModel;
use App\Models\LinkModel;
use Config\Database;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;


class Settings extends BaseController
{
    use ResponseTrait;

 
    public function index()
    {
           // Insert the product data into the database
           $tenantService = new TenantService();
           // Connect to the tenant's database
           $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 
        $settingsModel = new SettingsModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $settingsModel->findAll()], 200);
    }

    public function getSettingsPaging() {
        $input = $this->request->getJSON();
    
        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'departmentId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
    
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load StaffModel with the tenant database connection
        $settingsModel = new SettingsModel($db);
    
        $query = $settingsModel;
    
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
        $query = $settingsModel->where('isDeleted', 0)->where('businessId', $input->businessId); // Apply the deleted check at the beginning
    
        // Apply Sorting
        if (!empty($sortField) && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
            $query->orderBy($sortField, $sortOrder);
        }
    
        // Get Paginated Results
        $settings = $query->paginate($perPage, 'default', $page);
        $pager = $settingsModel->pager;
    
        $response = [
            "status" => true,
            "message" => "All Staff Data Fetched",
            "data" => $settings,
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
        $input = $this->request->getJSON();
        
        // Define validation rules for required fields
        $rules = [
            'title' => ['rules' => 'required'],
        ];
    
        if ($this->validate($rules)) {
           
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new SettingsModel($db);
            $model->insert($input);
    
            return $this->respond(['status' => true, 'message' => 'Exam Added Successfully'], 200);
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




       public function getLinksPaging()
    {
        $input = $this->request->getJSON();
    
        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'linkId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
    
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load EventModel with the tenant database connection
        $linkModel = new LinkModel($db);
    
        $query = $linkModel;
    
        if (!empty($filter)) {
            $filter = json_decode(json_encode($filter), true);
    
            foreach ($filter as $key => $value) {
                if (in_array($key, ['labelName', 'link'])) {
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
        $links = $query->paginate($perPage, 'default', $page);
        $pager = $linkModel->pager;
    
        $response = [
            "status" => true,
            "message" => "All Event Data Fetched",
            "data" => $links,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]
        ];
    
        return $this->respond($response, 200);
    }


public function createLink()
{
    $input = $this->request->getPost();

    // Validation
    $rules = [
        'labelName' => ['rules' => 'required'],
    ];

    if (!$this->validate($rules)) {
        return $this->fail([
            'status'  => false,
            'errors'  => $this->validator->getErrors(),
            'message' => 'Invalid Inputs',
        ], 409);
    }

    try {
        // 🔑 Decode JWT
        $key    = "Exiaa@11";
        $header = $this->request->getHeader("Authorization");
        $token  = null;

        if (!empty($header) && preg_match('/Bearer\s(\S+)/', $header, $matches)) {
            $token = $matches[1];
        }

        $decoded = JWT::decode($token, new Key($key, 'HS256'));

        // ✅ Handle profilePic upload
        $profilePic = $this->request->getFile('profilePic');
        if ($profilePic && $profilePic->isValid() && !$profilePic->hasMoved()) {
            
            // 👉 Always save inside public/
            $uploadPath = FCPATH . 'exEducationTraining/linkImages/'; 
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0777, true);
            }

            $newName = $profilePic->getRandomName();
            $profilePic->move($uploadPath, $newName);

            // 👉 Save only relative path in DB (e.g. "exEducationTraining/linkImages/abc.png")
            $input['profilePic'] = 'exEducationTraining/linkImages/' . $newName;
        }

        // 🔗 DB insert
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new LinkModel($db);
        $model->insert($input);

        return $this->respond([
            'status'  => true,
            'message' => 'Link Added Successfully'
        ], 200);

    } catch (\Exception $e) {
        return $this->respond([
            'status'  => false,
            'message' => $e->getMessage()
        ], 500);
    }
}


    public function updateLink()
    {
        $input = $this->request->getPost();
        
        // Validation rules for the studentId
        $rules = [
            'linkId' => ['rules' => 'required|numeric'], // Ensure studentId is provided and is numeric

        ];

        // Validate the input
        if ($this->validate($rules)) {
             
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); $model = new LinkModel($db);

            // Retrieve the student by studentId
            $linkId = $input['linkId'];  // Corrected here
            $link = $model->find($linkId); // Assuming find method retrieves the student

            if (!$link) {
                return $this->fail(['status' => false, 'message' => 'link not found'], 404);
            }

            // Prepare the data to be updated (exclude studentId if it's included)
            $updateData = [

                'categoryName' => $input['categoryName'],  // Corrected here
                'labelName' => $input['labelName'],  // Corrected here
                'link' => $input['link'],  // Corrected here

            ];

            // Update the student with new data
            $updated = $model->update($linkId, $updateData);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'link Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update link '], 500);
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


    public function deleteLink()
    {
        $input = $this->request->getJSON();
        

        $rules = [
            'linkId' => ['rules' => 'required'], // Ensure vendorId is provided
        ];
    

        // Validate the input
        if ($this->validate($rules)) {
            $tenantService = new TenantService();
                // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new LinkModel($db);
    
            // Retrieve the vendor by vendorId
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));   $model = new LinkModel($db);

            // Retrieve the lead by leadId
            $linkId = $input->linkId;
            $link = $model->find($linkId); // Assuming the find method retrieves the vendor
    
            
            $link = $model->find($linkId); // Assuming find method retrieves the lead

            if (!$link) {
                return $this->fail(['status' => false, 'message' => 'link not found'], 404);
            }
    
            // Soft delete by marking 'isDeleted' as 1
            $updateData = [
                'isDeleted' => 1,
            ];
    

            // Proceed to delete the lead
            $deleted = $model->delete($linkId);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'link Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete link'], 500);
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

    


    
       public function getFooterPaging()
    {
        $input = $this->request->getJSON();
    
        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'footerId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
    
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load EventModel with the tenant database connection
        $footerModel = new FooterModel($db);
    
        $query = $footerModel;
    
        if (!empty($filter)) {
            $filter = json_decode(json_encode($filter), true);
    
            foreach ($filter as $key => $value) {
                if (in_array($key, ['labelName', 'link'])) {
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
        $footers = $query->paginate($perPage, 'default', $page);
        $pager = $footerModel->pager;
    
        $response = [
            "status" => true,
            "message" => "All Event Data Fetched",
            "data" => $footers,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]
        ];
    
        return $this->respond($response, 200);
    }


 public function createFooter()
{
$input = $this->request->getJSON(true); // true = returns array
    $file = $this->request->getFile('file');

    // ✅ Get tenant DB
    $tenantService = new \App\Libraries\TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $model = new \App\Models\FooterModel($db);

    // ✅ Common fields
    $data = [
        'businessId'     => isset($input['businessId']) ? (int)$input['businessId'] : 0,
        'parentFooterId' => isset($input['parentFooterId']) ? (int)$input['parentFooterId'] : 0,
        'isActive'       => 1,
        'isDeleted'      => 0,
        'modifiedBy'     => session()->get('userId') ?? 0,
        'modifiedDate'   => date('Y-m-d H:i:s'),
    ];

    // ✅ Add title only if provided
    if (!empty($input['title'])) {
        $data['title'] = trim($input['title']);
    }

    // ✅ Add URL only if provided
    if (!empty($input['url'])) {
        $data['url'] = trim($input['url']);
    }

    // ✅ Handle file upload if exists
    if ($file && $file->isValid() && !$file->hasMoved()) {
        $uploadPath = WRITEPATH . 'uploads/footer/';
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }
        $newFileName = $file->getRandomName();
        $file->move($uploadPath, $newFileName);
        $data['file'] = base_url('writable/uploads/footer/' . $newFileName);
    }

    try {
        // ✅ Update if footerId exists, otherwise insert
        if (!empty($input['footerId'])) {
            $footerId = (int)$input['footerId'];
            $model->update($footerId, $data);
            $message = 'Footer updated successfully.';
        } else {
            $data['createdBy']   = session()->get('userId') ?? 0;
            $data['createdDate'] = date('Y-m-d H:i:s');
            $model->insert($data);
            $footerId = $model->insertID();
            $message = 'Footer created successfully.';
        }

        return $this->respond([
            'status'   => true,
            'message'  => $message,
            'footerId' => $footerId
        ], 200);

    } catch (\Exception $e) {
        return $this->respond([
            'status'  => false,
            'message' => 'Failed to save footer.',
            'error'   => $e->getMessage()
        ], 500);
    }
}


  public function getAllFooter()
    {
           // Insert the product data into the database
           $tenantService = new TenantService();
           // Connect to the tenant's database
           $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 
        $footerModel = new FooterModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $footerModel->findAll()], 200);
    }



    public function getFooter()
{
    $tenantService = new \App\Libraries\TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $model = new \App\Models\FooterModel($db);

    // Get all active + non-deleted footers
    $footers = $model->where('isActive', 1)
                    ->where('isDeleted', 0)
                    ->orderBy('parentFooterId ASC, footerId ASC')
                    ->findAll();

    $result = [];

    // Step 1: collect parents
    foreach ($footers as $footer) {
        if ($footer['parentFooterId'] == 0) {
            $result[$footer['footerId']] = [
                'footerId' => $footer['footerId'],
                'title'    => $footer['title'],
                'children' => []
            ];
        }
    }

    // Step 2: attach children to their parents
    foreach ($footers as $footer) {
        if ($footer['parentFooterId'] != 0) {
            if (isset($result[$footer['parentFooterId']])) {
                $result[$footer['parentFooterId']]['children'][] = [
                    'footerId' => $footer['footerId'],
                    'title'    => $footer['title'],
                    'url'      => $footer['url']
                ];
            }
        }
    }

    // Step 3: return only values (reset keys)
    return $this->respond(array_values($result));
}


public function getAllLink()
{
    try {
        $tenantService = new \App\Libraries\TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

        $businessId = $this->request->getVar('businessId');

        if (!$businessId) {
            return $this->respond([
                'status' => false,
                'message' => 'Business ID is required.'
            ], 400);
        }

        $model = new \App\Models\LinkModel($db);

        $links = $model->where('businessId', $businessId)
                       ->where('isActive', 1)
                       ->where('isDeleted', 0)
                       ->orderBy('linkId ASC')
                       ->findAll();

        // ✅ prepend base URL to profilePic
        $baseUrl = base_url(); // gives you domain + project base
        foreach ($links as &$link) {
            if (!empty($link['profilePic'])) {
                $link['profilePic'] = $baseUrl . $link['profilePic'];
            }
        }

        return $this->respond([
            'status' => true,
            'message' => 'Links fetched successfully.',
            'data'    => $links
        ], 200);

    } catch (\Exception $e) {
        return $this->respond([
            'status' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}


 
       public function getVisionMissionPaging()
    {
        $input = $this->request->getJSON();
    
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'visionMissionId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
    
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $visionMissionModel = new VisionMissionModel($db);
    
        $query = $visionMissionModel;
    
        if (!empty($filter)) {
            $filter = json_decode(json_encode($filter), true);
    
            foreach ($filter as $key => $value) {
                if (in_array($key, ['labelName', 'link'])) {
                    $query->like($key, $value); 
                } else if ($key === 'createdDate') {
                    $query->where($key, $value); 
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
    
        $query->where('isDeleted', 0);
    
        // Apply Sorting
        if (!empty($sortField) && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
            $query->orderBy($sortField, $sortOrder);
        }
    
        // Get Paginated Results
        $visions = $query->paginate($perPage, 'default', $page);
        $pager = $visionMissionModel->pager;
    
        $response = [
            "status" => true,
            "message" => "All Data Fetched",
            "data" => $visions,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]
        ];
    
        return $this->respond($response, 200);
    }

public function createVisionMission()
{
    $input = $this->request->getPost();
    if (empty($input)) {
        $input = (array) $this->request->getJSON(true);
    }

    $rules = [
        'title'       => 'required',
        'description' => 'required',
    ];

    if ($this->validate($rules)) {
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

        $model = new VisionMissionModel($db);

        $model->insert([
            'title'       => $input['title'],
            'description' => $input['description'],
            'type'        => $input['type'] ?? 'vision_mission',
            'businessId'  => $input['businessId'] ?? null,
        ]);

        return $this->respond(['status' => true, 'message' => 'Vision & Mission Added Successfully'], 200);
    } else {
        return $this->fail([
            'status' => false,
            'errors' => $this->validator->getErrors(),
            'message' => 'Invalid Inputs',
        ], 409);
    }
}


}
