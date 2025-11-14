<?php




namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\PartModel;
use Config\Database;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;


class Part extends BaseController
{
    use ResponseTrait;

  
     public function index()
    {
       // Insert the product data into the database
       $tenantService = new TenantService();
       // Connect to the tenant's database
       $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); // Load UserModel with the tenant database connection
        $PartModel = new PartModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $PartModel->findAll()], 200);
    }

   

public function create()
{
    $input = $this->request->getJSON(true);


    // Validation rules for PART creation
    $rules = [
        'constituencyId' => ['rules' => 'required'],
        'partNo'           => ['rules' => 'required'],
        'partName'         => ['rules' => 'required']
    ];

    if (!$this->validate($rules)) {
        return $this->fail([
            'status' => false,
            'errors' => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ], 409);
    }

    // Decode JWT token
    $key = "Exiaa@11";
    $header = $this->request->getHeaderLine("Authorization");
    $token = null;
    if ($header && preg_match('/Bearer\s(\S+)/', $header, $matches)) {
        $token = $matches[1];
    }
    $decoded = $token ? JWT::decode($token, new Key($key, 'HS256')) : null;

    // Prepare PART data
    $data = [
    'stateName'        => $input['stateName'] ?? null,
    'districtName'     => $input['districtName'] ?? null,
    'constituencyId'   => $input['constituencyId'] ?? null,
    'constituencyName' => $input['constituencyName'] ?? null,   // FIXED
    'partNo'           => $input['partNo'],
    'partName'         => $input['partName'],
    'createdDate'      => date('Y-m-d H:i:s'),
    'modifiedDate'     => date('Y-m-d H:i:s'),
    'addedBy'          => $decoded->userId ?? null,
    'isActive'         => 1,
    'isDeleted'        => 0
];


    // Tenant DB Config
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    // Use your PartModel (Change model name accordingly)
    $model = new PartModel($db);
    $id = $model->insert($data);

    return $this->respond([
        'status' => true,
        'message' => 'Part added successfully',
        'data' => $id
    ], 200);
}

public function getAllPartPaging()
{
    $input = $this->request->getJSON();

    $page = isset($input->page) ? (int)$input->page : 1;
    $perPage = isset($input->perPage) ? (int)$input->perPage : 10;
    $sortField = isset($input->sortField) ? $input->sortField : 'partId';
    $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
    $search = isset($input->search) ? trim($input->search) : '';
    $filter = isset($input->filter) ? $input->filter : null;

    // 🔗 Tenant DB connect (if multi-tenant)
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    $partModel = new PartModel($db);

    // Base query
    $query = $partModel
        ->where('isActive', 1)
        ->where('isDeleted', 0)
        ->orderBy($sortField, $sortOrder);

    // 🔍 Search
 

    // 🎯 Filter
    if ($filter) {
        $filter = json_decode(json_encode($filter), true);

        foreach ($filter as $key => $value) {
            if ($value === '' || $value === null) continue;

            if (in_array($key, ['constituencyCode', 'constituencyName', 'stateName', 'reservationType'])) {
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
    $pager = $partModel->pager;

    return $this->respond([
        "status" => true,
        "message" => "All Parliament Constituency Data Fetched Successfully",
        "data" => $records,
        "pagination" => [
            "currentPage" => $pager->getCurrentPage(),
            "totalPages"  => $pager->getPageCount(),
            "totalItems"  => $pager->getTotal(),
            "perPage"     => $perPage
        ]
    ], 200);
}




public function update()
{
    helper(['form', 'filesystem']);

    $input = $this->request->getPost();

    // Validate parliamentConstituencyId
    if (!$this->validate(['parliamentConstituencyId' => 'required|numeric'])) {
        return $this->fail([
            'status' => false,
            'errors' => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ], 409);
    }

    $parliamentId = $input['parliamentConstituencyId'];

    // Multi-tenant DB
    $tenantName = $this->request->getHeaderLine('X-Tenant-Config');
    if (empty($tenantName)) {
        return $this->fail(['status' => false, 'message' => 'Tenant database not specified'], 400);
    }

    $tenantService = new \App\Libraries\TenantService();
    try {
        $dbConfig = $tenantService->getTenantConfig($tenantName);
    } catch (\Exception $e) {
        return $this->fail(['status' => false, 'message' => 'Invalid tenant configuration'], 400);
    }

    $parliamentModel = new \App\Models\ParliamentModel($dbConfig);

    // Check if record exists
    $existing = $parliamentModel->find($parliamentId);
    if (!$existing) {
        return $this->fail(['status' => false, 'message' => 'Parliament constituency not found'], 404);
    }

    // Optional: update audit fields
    $input['modifiedDate'] = date('Y-m-d H:i:s');

    // Only update allowed fields
    $allowedColumns = $parliamentModel->allowedFields;
    $updateData = array_intersect_key($input, array_flip($allowedColumns));

    try {
        $updated = $parliamentModel->update($parliamentId, $updateData);
        if ($updated) {
            return $this->respond([
                'status' => true,
                'message' => 'Parliament constituency updated successfully',
                'dataId' => $parliamentId
            ], 200);
        } else {
            return $this->fail([
                'status' => false,
                'message' => 'No changes made or update failed'
            ], 400);
        }
    } catch (\Exception $e) {
        return $this->fail([
            'status' => false,
            'message' => 'Error updating constituency: ' . $e->getMessage()
        ], 500);
    }
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
    $input = $this->request->getJSON(true);

    if (empty($input['id'])) {
        return $this->fail(['status' => false, 'message' => 'Parliament ID is required'], 400);
    }

    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    // Use ParliamentModel instead of ConstituencyModel
    $model = new ParliamentModel($db); 

    $parliament = $model->find($input['id']);
    if (!$parliament) {
        return $this->fail(['status' => false, 'message' => 'Parliament record not found'], 404);
    }

    // Properly delete the record (force delete if soft delete is enabled)
    if ($model->delete($input['id'], true)) {
        return $this->respond(['status' => true, 'message' => 'Parliament record deleted successfully'], 200);
    } else {
        return $this->fail(['status' => false, 'message' => 'Failed to delete parliament record'], 500);
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
