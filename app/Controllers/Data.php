<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\DataModel;
use Config\Database;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;


class Data extends BaseController
{
    use ResponseTrait;

     public function index()
    {
       // Insert the product data into the database
       $tenantService = new TenantService();
       // Connect to the tenant's database
       $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); // Load UserModel with the tenant database connection
        $DataModel = new DataModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $DataModel->findAll()], 200);
    }

   
  public function getAllDataPaging()
{
    $input = $this->request->getJSON();

    $page = isset($input->page) ? (int)$input->page : 1;
    $perPage = isset($input->perPage) ? (int)$input->perPage : 10;
    $sortField = isset($input->sortField) ? $input->sortField : 'id';
    $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
    $search = isset($input->search) ? trim($input->search) : '';
    $filter = isset($input->filter) ? $input->filter : null;
    $businessId = isset($input->businessId) ? $input->businessId : null;

    // 🔗 Tenant DB connect
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    $dataModel = new DataModel($db);

    // Base query
    $query = $dataModel
        ->where('isActive', 1)
        ->where('isDeleted', 0)
        ->orderBy($sortField, $sortOrder);

    if ($businessId) {
        $query->where('businessId', $businessId);
    }

    // 🔍 Search
    if (!empty($search)) {
        $query->groupStart()
              ->like('fullName', $search)
              ->orLike('mobileNo', $search)
              ->orLike('email', $search)
              ->groupEnd();
    }

    // 🎯 Filter
    if ($filter) {
        $filter = json_decode(json_encode($filter), true);

        foreach ($filter as $key => $value) {
            if ($value === '' || $value === null) continue;
            
            if (in_array($key, ['fullName', 'mobileNo', 'district', 'state'])) {
                $query->like($key, $value);
            } elseif ($key === 'createdDate') {
                $query->where($key, $value);
            }
        }

        // Date range filter
        if (!empty($filter['startDate']) && !empty($filter['endDate'])) {
            $query->where('createdDate >=', $filter['startDate'])
                  ->where('createdDate <=', $filter['endDate']);
        }

        // Date range shortcuts
        if (!empty($filter['dateRange'])) {
            if ($filter['dateRange'] === 'last7days') {
                $query->where('createdDate >=', date('Y-m-d', strtotime('-7 days')));
            } elseif ($filter['dateRange'] === 'last30days') {
                $query->where('createdDate >=', date('Y-m-d', strtotime('-30 days')));
            }
        }
    }

    // 🧾 Pagination
    $records = $query->paginate($perPage, 'default', $page);
    $pager = $dataModel->pager;

    return $this->respond([
        "status" => true,
        "message" => "All Data Fetched Successfully",
        "data" => $records,
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
        'fullName' => ['rules' => 'required'],
        'mobileNo' => ['rules' => 'required'],
        'gender'   => ['rules' => 'permit_empty']
    ];

    if (!$this->validate($rules)) {
        return $this->fail([
            'status' => false,
            'errors' => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ], 409);
    }

    $key = "Exiaa@11";
    $header = $this->request->getHeaderLine("Authorization");
    $token = null;
    if ($header && preg_match('/Bearer\s(\S+)/', $header, $matches)) $token = $matches[1];
    $decoded = $token ? JWT::decode($token, new Key($key, 'HS256')) : null;
    $tenantName = $decoded->tenantName ?? 'default';

    // ✅ Profile Image Upload
    $profileImage = $this->request->getFile('profilePic');
    if ($profileImage && $profileImage->isValid() && !$profileImage->hasMoved()) {
        $uploadPath = FCPATH . "uploads/{$tenantName}/dataImages/";
        if (!is_dir($uploadPath)) mkdir($uploadPath, 0777, true);
        $newName = $profileImage->getRandomName();
        $profileImage->move($uploadPath, $newName);
        $input['profilePic'] = "{$tenantName}/dataImages/{$newName}";
    } else {
        $input['profilePic'] = $input['profilePicOld'] ?? null;
    }

    $data = [
        'fullName'   => $input['fullName'],
        'mobileNo'   => $input['mobileNo'],
        'gender'     => $input['gender'] ?? null,
        'dob'        => $input['dob'] ?? null,
        'age'        => $input['age'] ?? null,
        'email'      => $input['email'] ?? null,
        'profilePic' => $input['profilePic'],
        'businessId' => $decoded->businessId ?? $input['businessId'],
        'createdBy'  => $decoded->userId ?? null,
        'createdDate'=> date('Y-m-d H:i:s'),
        'isDeleted'  => 0
    ];

    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $model = new DataModel($db);
    $id = $model->insert($data);

    return $this->respond([
        'status' => true,
        'message' => 'Data added successfully',
        'data' => $id
    ], 200);
}



public function update()
{
    $input = $this->request->getPost();

    // ----- Validate dataId -----
    if (!$this->validate(['dataId' => 'required|numeric'])) {
        return $this->fail([
            'status' => false,
            'errors' => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ], 409);
    }

    $dataId = $input['dataId'];

    // ----- Tenant determination -----
    $tenantName = $this->request->getHeaderLine('X-Tenant-Config');
    if (empty($tenantName)) {
        return $this->fail(['status' => false, 'message' => 'Tenant database not specified'], 400);
    }

    // ----- Get tenant DB config -----
    $tenantService = new \App\Libraries\TenantService();
    try {
        $dbConfig = $tenantService->getTenantConfig($tenantName);
    } catch (\Exception $e) {
        return $this->fail(['status' => false, 'message' => 'Invalid tenant configuration'], 400);
    }

    // Use safe folder name for uploads
    $tenantFolder = is_array($dbConfig) && isset($dbConfig['database']) ? $dbConfig['database'] : preg_replace('/[^a-zA-Z0-9_-]/', '', $tenantName);

    // ----- Initialize model with tenant DB -----
    $model = new \App\Models\DataModel($dbConfig);

    // ----- Check if data exists -----
    $existing = $model->find($dataId);
    if (!$existing) {
        return $this->fail(['status' => false, 'message' => 'Data not found'], 404);
    }

    // ----- Handle profile image upload -----
   // Change this line
$profileImage = $this->request->getFile('profilePic'); // <- match Angular key

if ($profileImage && $profileImage->isValid() && !$profileImage->hasMoved()) {

    $uploadBase = FCPATH . 'uploads/';
$uploadPath = $uploadBase . $tenantFolder . '/profileImage/';

if (!is_dir($uploadBase)) mkdir($uploadBase, 0777, true);
if (!is_dir($uploadPath)) mkdir($uploadPath, 0777, true);

$imageName = $profileImage->getRandomName();
$profileImage->move($uploadPath, $imageName);

$input['profilePic'] = $tenantFolder . '/profileImage/' . $imageName;

}

    $input['modifiedDate'] = date('Y-m-d H:i:s');

    // ----- Remove any invalid columns -----
    // Avoid unknown column errors like 'modifiedBy'
    $allowedColumns = $model->allowedFields;
    $input = array_intersect_key($input, array_flip($allowedColumns));

    // ----- Update data -----
    try {
        $updated = $model->update($dataId, $input);
        if ($updated) {
            return $this->respond(['status' => true, 'message' => 'Data updated successfully'], 200);
        }
    } catch (\Exception $e) {
        return $this->fail(['status' => false, 'message' => 'Failed to update data: ' . $e->getMessage()], 500);
    }

    return $this->fail(['status' => false, 'message' => 'Unknown error'], 500);
}




    // public function delete()
    // {
    //     $input = $this->request->getJSON();

    //     $rules = [
    //         'dataId' => ['rules' => 'required|numeric']
    //     ];

    //     if (!$this->validate($rules)) {
    //         return $this->fail([
    //             'status' => false,
    //             'errors' => $this->validator->getErrors(),
    //             'message' => 'Invalid Inputs'
    //         ], 409);
    //     }

    //     $model = new DataModel();
    //     $dataId = $input->dataId;
    //     $existing = $model->find($dataId);

    //     if (!$existing) {
    //         return $this->fail(['status' => false, 'message' => 'Data not found'], 404);
    //     }

    //     $deleted = $model->update($dataId, ['isDeleted' => 1]);
    //     if ($deleted) {
    //         return $this->respond(['status' => true, 'message' => 'Data deleted successfully'], 200);
    //     } else {
    //         return $this->fail(['status' => false, 'message' => 'Failed to delete data'], 500);
    //     }
    // }
    
      public function delete()
    {
        $input = $this->request->getJSON();
        
        // Validation rules for the course
        $rules = [
            'id' => ['rules' => 'required'], 
        ];

        // Validate the input
        if ($this->validate($rules)) {

            // Insert the product data into the database
             $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 
            $model = new DataModel($db);

            // Retrieve the course by eventId
            $id = $input->id;
            $data = $model->find($id); // Assuming find method retrieves the course

            if (!$data) {
                return $this->fail(['status' => false, 'message' => 'data not found'], 404);
            }

            $updateData = [
                'isDeleted' => 1,
            ];
            $deleted = $model->update($id, $updateData);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'data Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete data'], 500);
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

public function importExcel()
{
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $model = new DataModel($db);

    $json = $this->request->getJSON(true);
    if (!$json || !is_array($json)) {
        return $this->fail('Invalid JSON data.');
    }

    $insertData = [];
    $updatedData = [];
    $processedMobile = [];

    foreach ($json as $row) {
        // Skip invalid rows
        if (empty($row['fullName']) || empty($row['mobileNo']) || empty($row['dob'])) continue;

        $mobileNo = $row['mobileNo'];

        // Prevent processing duplicates in same import
        if (in_array($mobileNo, $processedMobile)) continue;
        $processedMobile[] = $mobileNo;

        // Check if mobileNo exists already
        $existing = $model->where('mobileNo', $mobileNo)->first();

        $dataRow = [
            'fullName'         => $row['fullName'],
            'gender'           => $row['gender'] ?? '-',
            'mobileNo'         => $mobileNo,
            'dob'              => $row['dob'],
            'age'              => $row['age'] ?? null,
            'email'            => $row['email'] ?? null,
            'address'          => $row['address'] ?? null,
            'villageTown'      => $row['villageTown'] ?? null,
            'talukaBlock'      => $row['talukaBlock'] ?? null,
            'district'         => $row['district'] ?? null,
            'state'            => $row['state'] ?? null,
            'pincode'          => $row['pincode'] ?? null,
            'voterIdNo'        => $row['voterIdNo'] ?? null,
            'wardBoothNo'      => $row['wardBoothNo'] ?? null,
            'serialNo'         => $row['serialNo'] ?? null,
            'assemblyNo'       => $row['assemblyNo'] ?? null,
            'aadharNo'         => $row['aadharNo'] ?? null,
            'voterCategory'    => $row['voterCategory'] ?? null,
            'voterSubCategory' => $row['voterSubCategory'] ?? null,
            'locationCoord'    => $row['locationCoord'] ?? null,
            'createdDate'      => date('Y-m-d H:i:s'),
            'businessId'       => $row['businessId'] ?? 0,
        ];

        if ($existing) {
            // Update existing record
            $model->update($existing['id'], $dataRow);
            $updatedData[] = $mobileNo;
        } else {
            $insertData[] = $dataRow;
        }
    }

    if (!empty($insertData)) {
        $model->insertBatch($insertData);
    }

    $dataList = $model->whereIn('mobileNo', array_merge($processedMobile, $updatedData))->findAll();

    return $this->respond([
        'success' => true,
        'data'    => $dataList,
        'message' => 'Data imported successfully!'
    ]);
}
public function checkMobileExists()
{
    $mobileNo = $this->request->getPost('mobileNo');
    if (!$mobileNo) return $this->fail('Mobile No required', 400);

    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $model = new DataModel($db);

    $existing = $model->where('mobileNo', $mobileNo)->first();
    return $this->respond([
        'exists' => $existing ? true : false,
        'dataId' => $existing['id'] ?? null
    ]);
}


}
