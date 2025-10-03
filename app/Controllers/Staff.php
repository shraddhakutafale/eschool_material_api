<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\StaffModel;
use App\Models\TypeModel;
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

    // Fetch staff where not deleted
    $staffs = $staffModel
        ->where('staff_mst.isDeleted', 0)
        ->findAll();

    return $this->respond([
        "status" => true,
        "message" => "All Staff Data Fetched",
        "data" => $staffs
    ], 200);
}

     public function getAllStaff()
    {
        $tenantService = new TenantService();

        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load UserModel with the tenant database connection
        $staffmodel = new StaffModel($db);
        $response = [
            "status" => true,
            "message" => "All Data Fetched",
            "data" => $staffmodel->findAll(),
        ];
        return $this->respond($response, 200);
    }

   public function getStaffPaging()
{
    $input = $this->request->getJSON();

    if (!$input) {
        return $this->fail([
            'status'  => false,
            'message' => 'Invalid or missing JSON payload'
        ], 400);
    }

    $page       = !empty($input->page) ? (int) $input->page : 1;
    $perPage    = !empty($input->perPage) ? (int) $input->perPage : 10;
    $sortField  = !empty($input->sortField) ? $input->sortField : 'staffId';
    $sortOrder  = !empty($input->sortOrder) ? $input->sortOrder : 'asc';
    $search     = !empty($input->search) ? $input->search : '';
    $filter     = !empty($input->filter) ? json_decode(json_encode($input->filter), true) : [];

    // ✅ Ensure businessId is provided
    if (empty($input->businessId)) {
        return $this->fail([
            'status'  => false,
            'message' => 'businessId is required'
        ], 400);
    }

    // ✅ Tenant DB Connection
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    if (!$db) {
        return $this->fail([
            'status'  => false,
            'message' => 'Tenant DB connection failed'
        ], 500);
    }

    $staffModel = new StaffModel($db);

    // ✅ Start Query
    $query = $staffModel->where('isDeleted', 0)
                        ->where('businessId', $input->businessId);

    // ✅ Apply Filters
    if (!empty($filter)) {
        foreach ($filter as $key => $value) {
            if (in_array($key, ['empName', 'empCategory', 'empCode', 'empSal'])) {
                $query->like($key, $value);
            } elseif ($key === 'createdDate') {
                $query->where($key, $value);
            }
        }

        if (!empty($filter['startDate']) && !empty($filter['endDate'])) {
            $query->where('createdDate >=', $filter['startDate'])
                  ->where('createdDate <=', $filter['endDate']);
        }

        if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last7days') {
            $query->where('createdDate >=', date('Y-m-d', strtotime('-7 days')));
        }

        if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last30days') {
            $query->where('createdDate >=', date('Y-m-d', strtotime('-30 days')));
        }
    }

    // ✅ Sorting
    if (!empty($sortField) && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
        $query->orderBy($sortField, $sortOrder);
    }

    // ✅ Pagination
    $staffs = $query->paginate($perPage, 'default', $page);
    $pager  = $staffModel->pager;

    return $this->respond([
        "status"     => true,
        "message"    => "All Staff Data Fetched",
        "data"       => $staffs,
        "pagination" => [
            "currentPage" => $pager->getCurrentPage(),
            "totalPages"  => $pager->getPageCount(),
            "totalItems"  => $pager->getTotal(),
            "perPage"     => $perPage
        ]
    ], 200);
}




public function create()
{
    $input = $this->request->getPost();

    $rules = [
        'empName' => ['rules' => 'required'],
        'empCode' => ['rules' => 'required'],
    ];

    if ($this->validate($rules)) {

        // Decode JWT token
        $key = "Exiaa@11";
        $header = $this->request->getHeader("Authorization");
        $token = null;

        if (!empty($header) && preg_match('/Bearer\s(\S+)/', $header, $matches)) {
            $token = $matches[1];
        }

        $decoded = JWT::decode($token, new Key($key, 'HS256'));

        // Add businessId
        $input['businessId'] = $decoded->businessId;

        // Handle profilePic upload
        $profilePic = $this->request->getFile('profilePic');
        if ($profilePic && $profilePic->isValid() && !$profilePic->hasMoved()) {
            $uploadPath = FCPATH . 'uploads/' . $decoded->tenantName . '/staffImages/';
            if (!is_dir($uploadPath)) mkdir($uploadPath, 0777, true);

            $fileName = $profilePic->getRandomName();
            $profilePic->move($uploadPath, $fileName);
            $input['profilePic'] = $decoded->tenantName . '/staffImages/' . $fileName;
        }

            // Handle resume PDF upload
        $resume = $this->request->getFile('resumeFile');
        if ($resume && $resume->isValid() && !$resume->hasMoved()) {
            $resumeUploadPath = FCPATH . 'uploads/' . $decoded->tenantName . '/resume/';
            if (!is_dir($resumeUploadPath)) mkdir($resumeUploadPath, 0777, true);

            $resumeName = $resume->getRandomName();
            $resume->move($resumeUploadPath, $resumeName);

            // Save relative path for Angular
            $input['resumeUrl'] = $decoded->tenantName . '/resume/' . $resumeName;
        }

        // Remove empty optional fields so DB doesn't store '-'
        $optionalFields = ['photoUrl','linkedinUrl','virtualCardUrl','qualification','email','panNumber','uanNumber','ipNumber','fatherName','empSal','empDoj','empDol'];
        foreach ($optionalFields as $field) {
            if(empty($input[$field])) unset($input[$field]);
        }

        // Database setup
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

        $staffModel = new StaffModel($db);
        $attendanceModel = new StaffAttendanceModel($db);

        $db->transStart();

        // Save staff with businessId
        $staffModel->insert($input);
        $staffId = $staffModel->getInsertID();

        // Save today's attendance
        $attendanceModel->insert([
            'staffId'        => $staffId,
            'businessId'     => $decoded->businessId,
            'attendanceDate' => date('Y-m-d'),
            'inTime'         => date('H:i:s'),
            'outTime'        => null,
            'deviceId'       => null,
            'status'         => '',
            'present'        => 0
        ]);

        $db->transComplete();

        return $this->respond(['status' => true, 'message' => 'Staff Added Successfully'], 200);

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
        'staffId' => ['rules' => 'required|numeric'],
    ];

    if (!$this->validate($rules)) {
        return $this->fail([
            'status' => false,
            'errors' => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ], 409);
    }

    $key = "Exiaa@11";
    $header = $this->request->getHeader("Authorization");
    $token = null;

    if (!empty($header) && preg_match('/Bearer\s(\S+)/', $header, $matches)) {
        $token = $matches[1];
    }

    $decoded = JWT::decode($token, new Key($key, 'HS256'));

    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $model = new StaffModel($db);

    $staffId = $input['staffId'];
    $staff = $model->find($staffId);

    if (!$staff) {
        return $this->fail(['status' => false, 'message' => 'Staff not found'], 404);
    }

    // Handle profilePic upload
    $profilePic = $this->request->getFile('profilePic');
    if ($profilePic && $profilePic->isValid() && !$profilePic->hasMoved()) {
        $uploadPath = FCPATH . 'uploads/' . $decoded->tenantName . '/staffImages/';
        if (!is_dir($uploadPath)) mkdir($uploadPath, 0777, true);

        $fileName = $profilePic->getRandomName();
        $profilePic->move($uploadPath, $fileName);
        $input['profilePic'] = $decoded->tenantName . '/staffImages/' . $fileName;
    }

    // Handle resume upload
    $resume = $this->request->getFile('resumeFile');
    if ($resume && $resume->isValid() && !$resume->hasMoved()) {
        $resumeUploadPath = FCPATH . 'writable/uploads/' . $decoded->tenantName . '/resume/';
        if (!is_dir($resumeUploadPath)) mkdir($resumeUploadPath, 0777, true);

        $resumeName = $resume->getRandomName();
        $resume->move($resumeUploadPath, $resumeName);

        $input['resumeUrl'] = $decoded->tenantName . '/resume/' . $resumeName; // relative path only
    }

    // Prepare all update fields (only the fields you have in the table)
    $updateData = [
        'businessId'     => $decoded->businessId,
        'empName'        => $input['empName'] ?? $staff['empName'],
        'empCategory'    => $input['empCategory'] ?? $staff['empCategory'],
        'empCode'        => $input['empCode'] ?? $staff['empCode'],
        'type'           => $input['type'] ?? $staff['type'],
        'department'     => $input['department'] ?? $staff['department'],
        'qualification'  => $input['qualification'] ?? $staff['qualification'],
        'email'          => $input['email'] ?? $staff['email'],
        'aadharNumber'   => $input['aadharNumber'] ?? $staff['aadharNumber'],
        'profilePic'     => $input['profilePic'] ?? $staff['profilePic'],
        'photoUrl'       => $input['photoUrl'] ?? $staff['photoUrl'],
        'resumeUrl'      => $input['resumeUrl'] ?? $staff['resumeUrl'],
        'linkedinUrl'    => $input['linkedinUrl'] ?? $staff['linkedinUrl'],
        'virtualCardUrl' => $input['virtualCardUrl'] ?? $staff['virtualCardUrl'],
        'panNumber'      => $input['panNumber'] ?? $staff['panNumber'],
        'uanNumber'      => $input['uanNumber'] ?? $staff['uanNumber'],
        'ipNumber'       => $input['ipNumber'] ?? $staff['ipNumber'],
        'fatherName'     => $input['fatherName'] ?? $staff['fatherName'],
        'empSal'         => $input['empSal'] ?? $staff['empSal'],
        'empDoj'         => $input['empDoj'] ?? $staff['empDoj'],
        'empDol'         => $input['empDol'] ?? $staff['empDol'],
    ];

    $updated = $model->update($staffId, $updateData);

    if ($updated) {
        return $this->respond(['status' => true, 'message' => 'Staff Updated Successfully'], 200);
    } else {
        return $this->fail(['status' => false, 'message' => 'Failed to update staff'], 500);
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


      public function getAllType()
    {
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $typeModel = new TypeModel($db);
        $types = $typeModel->where('isDeleted', 0)->findAll();
        return $this->respond(['status' => true, 'message' => 'Subjects fetched successfully', 'data' => $types], 200);
    }



      public function createType()
    {
        $input = $this->request->getJSON();
        $rules = [
            'title' => ['rules' => 'required'],

        ];

        if ($this->validate($rules)) {
            // Insert the product data into the database
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 
            $model = new TypeModel($db);

            // Insert the lead data into the database
            $model->insert($input);

            // Return a success response
            return $this->respond(['status' => true, 'message' => 'Type Created Successfully'], 200);
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
