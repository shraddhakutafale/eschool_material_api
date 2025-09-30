<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\CommitteeModel;
use Config\Database;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;


class Committee extends BaseController
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

    $committeeModel = new CommitteeModel($db);

    $committees = $committeeModel
        ->where('committee_mst.isDeleted', 0)
        ->findAll();

    return $this->respond([
        "status" => true,
        "message" => "All Committee Data Fetched",
        "data" => $committees
    ], 200);
}

     public function getAllCommittee()
    {
        $tenantService = new TenantService();

        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load UserModel with the tenant database connection
        $committeemodel = new CommitteeModel($db);
        $response = [
            "status" => true,
            "message" => "All Data Fetched",
            "data" => $committeemodel->findAll(),
        ];
        return $this->respond($response, 200);
    }
public function getCommitteePaging()
{
    $input = $this->request->getJSON();

    if (!$input) {
        return $this->fail([
            'status'  => false,
            'message' => 'Invalid or missing JSON payload'
        ], 400);
    }

    $page      = !empty($input->page) ? (int) $input->page : 1;
    $perPage   = !empty($input->perPage) ? (int) $input->perPage : 10;
    $sortField = !empty($input->sortField) ? $input->sortField : 'committeeId';
    $sortOrder = !empty($input->sortOrder) ? $input->sortOrder : 'ASC';
    $search    = !empty($input->search) ? $input->search : '';
    $filter    = !empty($input->filter) ? json_decode(json_encode($input->filter), true) : [];

    if (empty($input->businessId)) {
        return $this->fail([
            'status'  => false,
            'message' => 'businessId is required'
        ], 400);
    }

    // ✅ Get DB connection and select database
    $db = \Config\Database::connect();
    $db->query("USE exiaa_ex0009");

    $builder = $db->table('committee_mst')
                  ->where('isDeleted', 0)
                  ->where('businessId', $input->businessId);

    // ✅ Filters
    if (!empty($filter)) {
        foreach ($filter as $key => $value) {
            if (in_array($key, ['committeeName', 'committeeCode', 'committeeType'])) {
                $builder->like($key, $value);
            } elseif ($key === 'createdDate') {
                $builder->where($key, $value);
            }
        }

        if (!empty($filter['startDate']) && !empty($filter['endDate'])) {
            $builder->where('createdDate >=', $filter['startDate'])
                    ->where('createdDate <=', $filter['endDate']);
        }

        if (!empty($filter['dateRange'])) {
            if ($filter['dateRange'] === 'last7days') {
                $builder->where('createdDate >=', date('Y-m-d', strtotime('-7 days')));
            } elseif ($filter['dateRange'] === 'last30days') {
                $builder->where('createdDate >=', date('Y-m-d', strtotime('-30 days')));
            }
        }
    }

    // ✅ Global search
    if (!empty($search)) {
        $builder->groupStart()
                ->like('committeeName', $search)
                ->orLike('committeeType', $search)
                ->orLike('committeeCode', $search)
                ->groupEnd();
    }

    // ✅ Sorting
    $builder->orderBy($sortField, $sortOrder);

    // ✅ Pagination
    $total = $builder->countAllResults(false); // false prevents reset
    $builder->limit($perPage, ($page - 1) * $perPage);
    $query = $builder->get();
    $committees = $query->getResult();

    return $this->respond([
        'status'     => true,
        'message'    => 'All Committee Data Fetched',
        'data'       => $committees,
        'pagination' => [
            'currentPage' => $page,
            'totalPages'  => ceil($total / $perPage),
            'totalItems'  => $total,
            'perPage'     => $perPage
        ]
    ], 200);
}

public function create()
{
    $input = $this->request->getPost();

    // Validation rules
    $rules = [
        'committeeMember' => ['rules' => 'required'],
    ];

    if (!$this->validate($rules)) {
        return $this->fail([
            'status'  => false,
            'errors'  => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ], 409);
    }

    // 🔑 Decode tenant from JWT
    $key = "Exiaa@11";
    $header = $this->request->getHeader("Authorization");
    $token = null;

    if (!empty($header) && preg_match('/Bearer\s(\S+)/', $header, $matches)) {
        $token = $matches[1];
    }

    if (!$token) {
        return $this->failUnauthorized('JWT token missing');
    }

    $decoded = JWT::decode($token, new Key($key, 'HS256'));

    // Handle profile picture upload
    $profilePic = $this->request->getFile('profilePic');
    if ($profilePic && $profilePic->isValid() && !$profilePic->hasMoved()) {
        $uploadPath = FCPATH . 'uploads/' . $decoded->tenantName . '/committeeImages/';
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }

        $newName = $profilePic->getRandomName();
        $profilePic->move($uploadPath, $newName);

        // Save relative path only
        $input['profilePic'] = $decoded->tenantName . '/committeeImages/' . $newName;
    }

    // Explicitly use exiaa_ex0009 database
  // ✅ Get DB connection and select database
    $db = \Config\Database::connect();
    $db->query("USE exiaa_ex0009");
    // Use the tenant-aware model with this DB
    $model = new CommitteeModel($db);
    $model->insert($input);

    return $this->respond([
        'status'  => true,
        'message' => 'Committee Added Successfully'
    ], 200);
}

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
