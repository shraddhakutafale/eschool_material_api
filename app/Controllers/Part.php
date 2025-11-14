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

  public function getAllPartPaging()
{
    $input = $this->request->getJSON();

    $page = isset($input->page) ? (int)$input->page : 1;
    $perPage = isset($input->perPage) ? (int)$input->perPage : 10;
    $sortField = isset($input->sortField) ? $input->sortField : 'partId';
    $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
    $search = isset($input->search) ? trim($input->search) : '';
    $filter = isset($input->filter) ? $input->filter : null;

    // Tenant DB connect
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    $partModel = new PartModel($db);

    // Base query
    $query = $partModel
        ->where('isActive', 1)
        ->where('isDeleted', 0)
        ->orderBy($sortField, $sortOrder);

    // 🔍 Search
    if (!empty($search)) {
        $query->groupStart()
              ->like('partCode', $search)
              ->orLike('partName', $search)
              ->orLike('stateName', $search)
              ->orLike('districtName', $search)
              ->groupEnd();
    }

    // 🎯 Filters
    if ($filter) {
        $filter = json_decode(json_encode($filter), true);

        foreach ($filter as $key => $value) {
            if ($value === '' || $value === null) continue;

            if (in_array($key, ['partCode', 'partName', 'stateName', 'districtName'])) {
                $query->like($key, $value);
            } elseif ($key === 'createdDate') {
                $query->where($key, $value);
            }
        }

        // Date range
        if (!empty($filter['startDate']) && !empty($filter['endDate'])) {
            $query->where('createdDate >=', $filter['startDate'])
                  ->where('createdDate <=', $filter['endDate']);
        }

        // Date shortcut
        if (!empty($filter['dateRange'])) {
            if ($filter['dateRange'] === 'last7days') {
                $query->where('createdDate >=', date('Y-m-d', strtotime('-7 days')));
            } elseif ($filter['dateRange'] === 'last30days') {
                $query->where('createdDate >=', date('Y-m-d', strtotime('-30 days')));
            }
        }
    }

    // Pagination
    $records = $query->paginate($perPage, 'default', $page);
    $pager = $partModel->pager;

    return $this->respond([
        "status" => true,
        "message" => "All Part Data Fetched Successfully",
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



public function update()
{
    helper(['form', 'filesystem']);

    $input = $this->request->getJSON(true);

    // Validate partId
    $partId = $input['partId'] ?? null;

    if (!$partId || !is_numeric($partId)) {
        return $this->fail([
            'status' => false,
            'message' => 'Invalid partId'
        ], 409);
    }

    // Multi-tenant DB
    $tenantName = $this->request->getHeaderLine('X-Tenant-Config');
    if (empty($tenantName)) {
        return $this->fail(['status' => false, 'message' => 'Tenant database not specified'], 400);
    }

    $tenantService = new \App\Libraries\TenantService();
    $dbConfig = $tenantService->getTenantConfig($tenantName);

    $partModel = new \App\Models\PartModel($dbConfig);

    // Check if record exists
    $existing = $partModel->find($partId);
    if (!$existing) {
        return $this->fail(['status' => false, 'message' => 'Part not found'], 404);
    }

    // Update audit field
    $input['modifiedDate'] = date('Y-m-d H:i:s');

    // Only update allowed fields
    $allowedColumns = $partModel->allowedFields;
    $updateData = array_intersect_key($input, array_flip($allowedColumns));

    $updated = $partModel->update($partId, $updateData);

    if ($updated) {
        return $this->respond([
            'status' => true,
            'message' => 'Part updated successfully',
            'dataId' => $partId
        ], 200);
    } else {
        return $this->fail([
            'status' => false,
            'message' => 'No changes made or update failed'
        ], 400);
    }
}






public function delete()
{
    $input = $this->request->getJSON(true);

    if (empty($input['id'])) {
        return $this->fail(['status' => false, 'message' => 'Part ID is required'], 400);
    }

    // Tenant Connect
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    // Use PartModel
    $model = new PartModel($db);

    // Check if record exists
    $part = $model->find($input['id']);
    if (!$part) {
        return $this->fail(['status' => false, 'message' => 'Part record not found'], 404);
    }

    // Perform delete (force delete if soft delete enabled)
    if ($model->delete($input['id'], true)) {
        return $this->respond([
            'status'  => true,
            'message' => 'Part record deleted successfully'
        ], 200);
    } else {
        return $this->fail([
            'status' => false,
            'message' => 'Failed to delete Part record'
        ], 500);
    }
}




}
