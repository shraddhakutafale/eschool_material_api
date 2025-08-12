<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\FormModel;
use App\Models\FormDataModel;
use App\Models\UserFormMapModel;
use App\Models\UserModel;
use App\Models\TransactionModel;
use App\Libraries\TenantService;
use Config\Database;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

class Form extends BaseController
{
    use ResponseTrait;
  

    public function index()
    {
        $tenantService = new TenantService();

        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load UserModel with the tenant database connection
        $formModel = new FormModel($db);
        $response = [
            "status" => true,
            "message" => "All Data Fetched",
            "data" => $formModel->findAll(),
        ];
        return $this->respond($response, 200);
    }
  
    public function getAllFormDataPaging()
    {
        $input = $this->request->getJSON();
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'formId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
        $formId = isset($input->formId) ? $input->formId : null;
        
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load FormDataModel with the tenant database connection
        $formDataModel = new FormDataModel($db);
        $query = $formDataModel;

        if (!empty($filter)) {
            $filter = json_decode(json_encode($filter), true);
            foreach ($filter as $key => $value) {
                if (in_array($key, ['formId', 'userId'])) {
                    $query->where($key, $value); // Exact match filter for specific fields
                } else if ($key === 'createdDate') {
                    $query->where($key, $value); // Exact match filter for createdDate
                }
            }
            // Apply Date Range Filter (startDate and endDate)
            if (!empty($filter['startDate']) && !empty($filter['endDate'])) {
                $query->where('createdDate >=', $filter['startDate'])
                      ->where('createdDate <=', $filter['endDate']);
            }
        }

        if (!empty($search)) {
            $query->groupStart()
                  ->like('formDataJson', $search)
                  ->orLike('userId', $search)
                  ->groupEnd();
        }

        $query->orderBy($sortField, $sortOrder);
        $query->limit($perPage, ($page - 1) * $perPage);
        $query->where('isDeleted', 0);
        if ($formId) {
            $query->where('formId', $formId); // Filter by formId if provided
        }

        $formData = $query->get()->getResultArray();
        $totalRows = $query->countAllResults();
        $pager = [
            'currentPage' => $page,
            'totalPages' => ceil($totalRows / $perPage),
            'totalItems' => $totalRows,
            'perPage' => $perPage
        ];
        $response = [
            "status" => true,
            "message" => "All Form Data Fetched",
            "data" => $formData,
            "pagination" => $pager
        ];
        return $this->respond($response, 200);    
    }

  
   public function getFormsPaging()
{
    $input = $this->request->getJSON();

    $page = isset($input->page) ? $input->page : 1;
    $perPage = isset($input->perPage) ? $input->perPage : 10;
    $sortField = isset($input->sortField) ? $input->sortField : 'formId';
    $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
    $search = isset($input->search) ? $input->search : '';
    $filter = $input->filter;

    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    $formModel = new FormModel($db);
    $userFormMapModel = new UserFormMapModel($db); // ✅ Load the map model

    $query = $formModel;

    if (!empty($filter)) {
        $filter = json_decode(json_encode($filter), true);

        foreach ($filter as $key => $value) {
            if (in_array($key, ['formHeader'])) {
                $query->like($key, $value);
            } else if ($key === 'createdDate') {
                $query->where($key, $value);
            }
        }

        if (!empty($filter['startDate']) && !empty($filter['endDate'])) {
            $query->where('createdDate >=', $filter['startDate'])
                  ->where('createdDate <=', $filter['endDate']);
        }

        if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last7days') {
            $last7DaysStart = date('Y-m-d', strtotime('-7 days'));
            $query->where('createdDate >=', $last7DaysStart);
        }

        if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last30days') {
            $last30DaysStart = date('Y-m-d', strtotime('-30 days'));
            $query->where('createdDate >=', $last30DaysStart);
        }
    }

    $query->where('isDeleted', 0)->where('businessId', $input->businessId);

    if (!empty($sortField) && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
        $query->orderBy($sortField, $sortOrder);
    }

    $forms = $query->paginate($perPage, 'default', $page);
    $pager = $formModel->pager;

    // ✅ Add assigned user count to each form
   foreach ($forms as &$form) {
    $form['assignedUserCount'] = $userFormMapModel
        ->where('formId', $form['formId'])
        ->where('isActive', 1)
        ->where('isDeleted', 0)
        ->countAllResults();
}


    $response = [
        "status" => true,
        "message" => "All Survey Data Fetched",
        "data" => $forms,
        "pagination" => [
            "currentPage" => $pager->getCurrentPage(),
            "totalPages" => $pager->getPageCount(),
            "totalItems" => $pager->getTotal(),
            "perPage" => $perPage
        ]
    ];

    return $this->respond($response, 200);
}
public function update()
{
    $input = $this->request->getJSON(true);

    // Validation rules
    $rules = [
        'formId' => ['rules' => 'required|numeric'],
        'formHeader' => ['rules' => 'required|string|max_length[255]'],
        'formStructureJson' => ['rules' => 'required|string'],
    ];

    if (!$this->validate($rules)) {
        return $this->failValidationErrors($this->validator->getErrors());
    }

    // Get tenant db connection
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    $FormModel = new \App\Models\FormModel($db);

    $formId = $input['formId'];
    $existingForm = $FormModel->find($formId);
    if (!$existingForm) {
        return $this->failNotFound('Form not found');
    }

    $updateData = [
        'formHeader' => $input['formHeader'],
        'formDescripton' => $input['formDescripton'] ?? $existingForm['formDescripton'],
        'footerMessage' => $input['footerMessage'] ?? $existingForm['footerMessage'],
        'businessId' => $input['businessId'] ?? $existingForm['businessId'],
        'formStructureJson' => $input['formStructureJson'], // This is your full JSON string with all fields
        'modifiedDate' => date('Y-m-d H:i:s'),
    ];

    if (!$FormModel->update($formId, $updateData)) {
        return $this->failServerError('Failed to update form');
    }

    return $this->respond([
        'status' => true,
        'message' => 'Form updated successfully',
        'formId' => $formId,
    ]);
}


public function view($formId)
{
    // Validate $formId as needed (integer check etc.)
    if (!is_numeric($formId)) {
        return $this->failValidationErrors('Invalid form ID');
    }

    // Get tenant config (if multi-tenant)
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $model = new FormModel($db);

    $form = $model->find($formId);

    if (!$form) {
        return $this->failNotFound('Form not found');
    }

    return $this->respond([
        'status' => true,
        'data' => $form,
    ]);
}


    public function getAllFormByBusiness()
    {
        $input = $this->request->getJSON();
        // Decode token
        $key = "Exiaa@11";
        $header = $this->request->getHeader("Authorization");
        $token = null;
        if (!empty($header) && preg_match('/Bearer\s(\S+)/', $header->getValue(), $matches)) {
            $token = $matches[1];
        }
        $decoded = null;
        if ($token) {
            try {
                $decoded = JWT::decode($token, new Key($key, 'HS256'));
            } catch (\Exception $e) {
                return $this->failUnauthorized('Invalid or expired token');
            }
        }
        $businessId = $decoded->businessId ?? $input->businessId ?? null;
        // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 

        // Load UserModel with the tenant database connection
        $FormModel = new FormModel($db);
        $forms = $FormModel->where('businessId', $businessId)->orderBy('createdDate', 'DESC')->where('active', 1)->where('isDeleted', 0)->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $forms], 200);
    }
public function getAssignedBusinessUsers()
{
    try {
        $input = $this->request->getJSON();
        $tenantService = new TenantService();

        // Get tenant DB for user-form mappings
        $tenantDb = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

        // Use EXIAA main DB (default DB group in CodeIgniter)
        $mainDb = \Config\Database::connect(); // this connects to default DB, i.e., `exiaa_db`

        // Models
        $UserFormMapModel = new UserFormMapModel($tenantDb);
        $UserModel = new UserModel($mainDb); // fetch from central DB (`user_mst`)

        // Step 1: Get assigned users for the form
        $assignedUserMaps = $UserFormMapModel
            ->where('formId', $input->formId)
            ->where('isActive', 1)
            ->where('isDeleted', 0)
            ->findAll();

        if (empty($assignedUserMaps)) {
            return $this->respond([
                'status' => true,
                'message' => 'No assigned users',
                'data' => []
            ], 200);
        }

        // Step 2: Extract user IDs from map
        $assignedUserIds = array_column($assignedUserMaps, 'userId');

        // Maintain mapping of userId -> userFormMapId
        $assignedUserMapById = [];
        foreach ($assignedUserMaps as $map) {
            $assignedUserMapById[$map['userId']] = $map['userFormMapId'];
        }

        // Step 3: Fetch users from exiaa_db
        $assignedUsers = $UserModel
            ->whereIn('userId', $assignedUserIds)
            ->where('isActive', 1)
            ->where('isDeleted', 0)
            ->findAll();

        // Step 4: Add assignment metadata
        $result = [];
        foreach ($assignedUsers as $user) {
            $userData = $user;
            $userData['isAssigned'] = true;
            $userData['userFormMapId'] = $assignedUserMapById[$user['userId']] ?? null;
            $result[] = $userData;
        }

        return $this->respond([
            'status' => true,
            'message' => 'Only assigned users',
            'data' => $result
        ], 200);

    } catch (\Exception $e) {
        return $this->respond([
            'status' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}


public function getAssignedFormsToUser()
{
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    $input = $this->request->getJSON();
    
    // Decode JWT token to get userId and businessId
    $key = "Exiaa@11";
    $header = $this->request->getHeader("Authorization");
    $token = null;
    if (!empty($header) && preg_match('/Bearer\s(\S+)/', $header->getValue(), $matches)) {
        $token = $matches[1];
    }
    $decoded = null;
    if ($token) {
        try {
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
        } catch (\Exception $e) {
            return $this->failUnauthorized('Invalid or expired token');
        }
    }

    $userId = $decoded->userId ?? null;
    $businessId = $decoded->businessId ?? null;

    if (!$userId || !$businessId) {
        return $this->fail('Missing user or business ID');
    }

    $formModel = new FormModel($db);
    $userFormMapModel = new UserFormMapModel($db);

    // Get all assigned form IDs for this user
    $assignedForms = $userFormMapModel
        ->select('formId')
        ->where('userId', $userId)
        ->where('isDeleted', 0)
        ->where('isActive', 1)
        ->findAll();

    $formIds = array_column($assignedForms, 'formId');

    if (empty($formIds)) {
        return $this->respond(["status" => true, "message" => "No assigned forms", "data" => []], 200);
    }

    // Get the actual forms
    $forms = $formModel
        ->whereIn('formId', $formIds)
        ->where('businessId', $businessId)
        ->where('isDeleted', 0)
        ->where('active', 1)
        ->orderBy('createdDate', 'DESC')
        ->findAll();

    return $this->respond(["status" => true, "message" => "Assigned Forms Fetched", "data" => $forms], 200);
}

public function assignUser()
{
    $input = $this->request->getJSON();
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    $UserFormMapModel = new \App\Models\UserFormMapModel($db);

    if (is_array($input)) {
        foreach ($input as $userFormMap) {
            $formId = $userFormMap->formId;
            $userId = $userFormMap->userId;

            // Check if mapping already exists (even if soft-deleted)
            $existing = $UserFormMapModel
                ->where('formId', $formId)
                ->where('userId', $userId)
                ->first();

            $data = [
                'formId'    => $formId,
                'userId'    => $userId,
                'isDeleted' => $userFormMap->isDeleted,
                'isActive'  => ($userFormMap->isDeleted == 1) ? 0 : 1,
            ];

            if ($existing) {
                // Update existing record
                $data['modifiedBy'] = $userFormMap->modifiedBy ?? null;
                $data['modifiedDate'] = date('Y-m-d H:i:s');
                $UserFormMapModel->update($existing['userFormMapId'], $data);
            } else {
                // Insert new record
                $data['createdBy'] = $userFormMap->createdBy ?? null;
                $data['createdDate'] = date('Y-m-d H:i:s');
                $UserFormMapModel->insert($data);
            }
        }
    }

    return $this->respond(['status' => true, 'message' => 'Users assigned successfully'], 200);
}


 

    public function getFormsWebsite()
    {
        $input = $this->request->getJSON();
        // Decode token
        $key = "Exiaa@11";
        $header = $this->request->getHeader("Authorization");
        $token = null;
        if (!empty($header) && preg_match('/Bearer\s(\S+)/', $header->getValue(), $matches)) {
            $token = $matches[1];
        }
        $decoded = null;
        if ($token) {
            try {
                $decoded = JWT::decode($token, new Key($key, 'HS256'));
            } catch (\Exception $e) {
                return $this->failUnauthorized('Invalid or expired token');
            }
        }
        $businessId = $decoded->businessId ?? $input->businessId ?? null;
        // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 

        // Load UserModel with the tenant database connection
        $FormModel = new FormModel($db);
        $forms = $FormModel->orderBy('createdDate', 'DESC')->where('isActive', 1)->where('isDeleted', 0)->where('businessId', $this->request->getHeaderLine('X-Business-Id'))->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $forms], 200);
    }


    public function create()
    {
        $input = $this->request->getJSON(true); // Get JSON input as assoc array

        // Validation (formUrl not required)
        $rules = [
            'formHeader'        => ['rules' => 'required'],
            'formStructureJson' => ['rules' => 'required'],
            'formDescripton'    => ['rules' => 'required'],
            'businessId'        => ['rules' => 'required|integer'],
        ];

        if ($this->validate($rules)) {
            // Decode token
            $key = "Exiaa@11";
            $header = $this->request->getHeader("Authorization");
            $token = null;

            if (!empty($header) && preg_match('/Bearer\s(\S+)/', $header->getValue(), $matches)) {
                $token = $matches[1];
            }

            if ($token) {
                try {
                    $decoded = JWT::decode($token, new Key($key, 'HS256'));
                    $input['businessId'] = $decoded->businessId ?? $input['businessId'] ?? null;
                } catch (\Exception $e) {
                    return $this->failUnauthorized('Invalid or expired token');
                }
            }

            // Connect to tenant DB
            $tenantService = new TenantService();
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new FormModel($db);

            // Generate a unique 15-character alphanumeric formUrl
            do {
                $randomUrl = substr(bin2hex(random_bytes(10)), 0, 15); // 15 chars
            } while ($model->where('formUrl', $randomUrl)->first());

            $input['formUrl'] = $randomUrl;

            // Insert into database
            $model->insert($input);

            return $this->respond([
                'status' => true,
                'message' => 'Form Added Successfully',
                'formUrl' => $randomUrl
            ], 200);
        } else {
            return $this->fail([
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs',
            ], 409);
        }
    }

    public function addFormData()
    {
        $input = $this->request->getJSON(true); // Get JSON input as assoc array

        $rules = [
            'formId'        => ['rules' => 'required|integer'],
            'formDataJson'  => ['rules' => 'required'],
        ];

        if (!$this->validate($rules)) {
            return $this->fail([
                'status' => false,
                'message' => 'Validation Failed',
                'errors' => $this->validator->getErrors()
            ]);
        }

        // Get userId from token
        $key = "Exiaa@11";
        $header = $this->request->getHeader("Authorization");
        $token = null;

        if (!empty($header) && preg_match('/Bearer\s(\S+)/', $header->getValue(), $matches)) {
            $token = $matches[1];
        }

        $userId = null;
        if ($token) {
            try {
                $decoded = JWT::decode($token, new \Firebase\JWT\Key($key, 'HS256'));
                $userId = $decoded->userId ?? null;
            } catch (\Exception $e) {
                return $this->failUnauthorized('Invalid or expired token');
            }
        }

        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $formDataModel = new \App\Models\FormDataModel($db);

        $saveData = [
            'formId'        => $input['formId'],
            'formDataJson'  => json_encode($input['formDataJson']), // array of form field structure
            'userId'        => $userId,
            'isActive'      => 1,
            'isDeleted'     => 0,
            'createdDate'   => date('Y-m-d H:i:s')
        ];

        if ($formDataModel->insert($saveData)) {
            return $this->respond([
                'status' => true,
                'message' => 'Form structure saved successfully'
            ]);
        } else {
            return $this->failServerError('Failed to save form structure');
        }
    }

    public function delete()
    {
        $input = $this->request->getJSON();

        $rules = [
            'formId' => ['rules' => 'required|integer']
        ];

        if ($this->validate($rules)) {
            $tenantService = new TenantService();
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

            $model = new FormModel($db);

            $formId = $input->formId;
            $form = $model->find($formId);

            if (!$form) {
                return $this->fail(['status' => false, 'message' => 'Form not found'], 404);
            }

            // Soft delete by setting isDeleted = 1
            $updated = $model->update($formId, ['isDeleted' => 1]);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Form deleted successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete form'], 500);
            }
        } else {
            return $this->fail([
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ], 409);
        }
    }

public function updateStatus()
{
    $input = $this->request->getJSON(true); // ✅ Get JSON input

    log_message('error', 'updateStatus INPUT: ' . json_encode($input)); // ✅ Debug line

    // ✅ Validation
    $rules = [
        'formDataId' => ['rules' => 'required|numeric'],
        'status'     => ['rules' => 'required']
    ];

    if (!$this->validate($rules)) {
        return $this->failValidationErrors($this->validator->getErrors());
    }

    $formDataId = $input['formDataId'];
    $status = $input['status'];

    // ✅ Get userId from token
    $key = "Exiaa@11";
    $header = $this->request->getHeader("Authorization");
    $token = null;

    if (!empty($header) && preg_match('/Bearer\s(\S+)/', $header->getValue(), $matches)) {
        $token = $matches[1];
    }

    $userId = null;
    if ($token) {
        try {
            $decoded = JWT::decode($token, new \Firebase\JWT\Key($key, 'HS256'));
            $userId = $decoded->userId ?? null;
        } catch (\Exception $e) {
            return $this->failUnauthorized('Invalid or expired token');
        }
    }

    // ✅ Get DB connection
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    try {
        $updated = $db->table('form_data')
            ->where('formDataId', $formDataId)
            ->update([
                'status'       => $status,
                'modifiedDate' => date('Y-m-d H:i:s'),
                'modifiedBy'   => $userId,
            ]);

        if ($updated) {
            return $this->respond([
                'status' => 'success',
                'message' => 'Status updated successfully'
            ]);
        } else {
            return $this->fail('Failed to update status or no changes made.');
        }
    } catch (\Exception $e) {
        log_message('error', 'Status Update Error: ' . $e->getMessage());
        return $this->failServerError('Something went wrong while updating the status.');
    }
}

}