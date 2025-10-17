<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\VoterModel;
use CodeIgniter\API\ResponseTrait;
use Config\Database;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;


class Voter extends BaseController
{
    use ResponseTrait;

     public function index()
    {
       // Insert the product data into the database
       $tenantService = new TenantService();
       // Connect to the tenant's database
       $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); // Load UserModel with the tenant database connection
        $VoterModel = new VoterModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $VoterModel->findAll()], 200);
    }

   
public function getAllVoterPaging()
{
    $input = $this->request->getJSON();

    $page = isset($input->page) ? (int)$input->page : 1;
    $perPage = isset($input->perPage) ? (int)$input->perPage : 10;
    $sortField = isset($input->sortField) ? $input->sortField : 'id';
    $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
    $search = isset($input->search) ? trim($input->search) : '';
    $filter = isset($input->filter) ? $input->filter : null;
    $businessId = isset($input->businessId) ? $input->businessId : null;

    // Tenant DB connect
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    $voterModel = new VoterModel($db);

    // Base query
    $query = $voterModel->orderBy($sortField, $sortOrder);

    // Optional: filter by businessId if your table has it
    if ($businessId) {
        $query->where('businessId', $businessId);
    }

    // Search across multiple fields
    if (!empty($search)) {
        $query->groupStart()
              ->like('full_name', $search)
              ->orLike('epic_no', $search)
              ->orLike('address', $search)
              ->groupEnd();
    }

    // Apply filters
   if ($filter) {
    $filter = json_decode(json_encode($filter), true);

    foreach ($filter as $key => $value) {
        if ($value === '' || $value === null) continue;

        // Numeric fields
        if (in_array($key, ['age', 'booth_no', 'serial_no', 'ward_no', 'part_no'])) {
            $query->where($key, $value); // exact match
        } else {
            $query->like($key, $value);  // partial match for string fields
        }
    }

    // Date range filter
    if (!empty($filter['startDate']) && !empty($filter['endDate'])) {
        $query->where('created_at >=', $filter['startDate'])
              ->where('created_at <=', $filter['endDate']);
    }
}

    // Pagination
    $records = $query->paginate($perPage, 'default', $page);
    $pager = $voterModel->pager;

    return $this->respond([
        "status" => true,
        "message" => "All Voter Data Fetched Successfully",
        "data" => $records,
        "pagination" => [
            "currentPage" => $pager->getCurrentPage(),
            "totalPages"  => $pager->getPageCount(),
            "totalItems"  => $pager->getTotal(),
            "perPage"     => $perPage
        ]
    ], 200);
}


  public function getAllWithoutTenant()
    {
        $input = $this->request->getJSON(true);
        $page = $input['page'] ?? 1;
        $perPage = $input['perPage'] ?? 10;

        $voterModel = new VoterModel();
        $data = $voterModel->paginate($perPage, 'default', $page);
        $pager = $voterModel->pager;

        return $this->respond([
            'status' => true,
            'message' => 'Voters fetched successfully (no tenant)',
            'data' => $data,
            'pagination' => [
                'currentPage' => $pager->getCurrentPage(),
                'totalPages'  => $pager->getPageCount(),
                'totalItems'  => $pager->getTotal(),
                'perPage'     => $perPage
            ]
        ]);
    }











public function create()
{
    $input = $this->request->getPost();

    if (empty($input)) {
        return $this->fail([
            'status' => false,
            'message' => 'No input received'
        ], 400);
    }

    $rules = [
        'epic_no'   => ['rules' => 'required'],
        'full_name' => ['rules' => 'required'],
    ];

    if (!$this->validate($rules)) {
        return $this->fail([
            'status' => false,
            'errors' => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ], 409);
    }

    $data = [
        'epic_no'             => $input['epic_no'],
        'full_name'           => $input['full_name'],
        'husband_father_name' => $input['husband_father_name'] ?? null,
        'relation_type'       => $input['relation_type'] ?? null,
        'first_name'          => $input['first_name'] ?? null,
        'last_name'           => $input['last_name'] ?? null,
        'father_name'         => $input['father_name'] ?? null,
        'age'                 => $input['age'] ?? null,
        'gender'              => $input['gender'] ?? null,
        'address'             => $input['address'] ?? null,
        'booth_no'            => $input['booth_no'] ?? null,
        'serial_no'           => $input['serial_no'] ?? null,
        'part_no'             => $input['part_no'] ?? null,
        'assembly_code'       => $input['assembly_code'] ?? null,
        'ward_no'             => $input['ward_no'] ?? null,
        'source_page'         => $input['source_page'] ?? null,
        'extraction_date'     => $input['extraction_date'] ?? null,
        'created_at'          => date('Y-m-d H:i:s'),
        'updated_at'          => date('Y-m-d H:i:s'),
    ];

    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $model = new VoterModel($db);

    $id = $model->insert($data);

    if (!$id) {
        return $this->fail([
            'status' => false,
            'message' => 'Failed to add voter'
        ], 500);
    }

    return $this->respond([
        'status' => true,
        'message' => 'Voter added successfully',
        'data' => $id
    ], 200);
}






public function update()
{
    helper(['form', 'filesystem']);

    // Get POST + FILE input
    $input = $this->request->getPost();

    // Validate voter ID
    if (!$this->validate(['id' => 'required|numeric'])) {
        return $this->fail([
            'status' => false,
            'errors' => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ], 409);
    }

    $voterId = $input['id'];

    // Tenant DB configuration
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

    $voterModel = new \App\Models\VoterModel($dbConfig);

    // Check if voter exists
    $existingVoter = $voterModel->find($voterId);
    if (!$existingVoter) {
        return $this->fail(['status' => false, 'message' => 'Voter not found'], 404);
    }

    // Optional: handle file uploads here (if any)

    // Add audit field
    $input['modifiedDate'] = date('Y-m-d H:i:s');

    // Filter only allowed fields
    $allowedColumns = $voterModel->allowedFields;
    $updateData = array_intersect_key($input, array_flip($allowedColumns));

    // Update voter
    try {
        $updated = $voterModel->update($voterId, $updateData);
        if ($updated) {
            return $this->respond([
                'status' => true,
                'message' => 'Voter updated successfully',
                'dataId' => $voterId
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
            'message' => 'Error updating voter: ' . $e->getMessage()
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
        return $this->fail(['status' => false, 'message' => 'Voter ID is required'], 400);
    }

    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $model = new VoterModel($db);

    $voter = $model->find($input['id']);
    if (!$voter) {
        return $this->fail(['status' => false, 'message' => 'Voter not found'], 404);
    }

    // Properly delete the record
    if ($model->delete($input['id'], true)) { // true = force delete, bypass soft delete if enabled
        return $this->respond(['status' => true, 'message' => 'Voter deleted successfully'], 200);
    } else {
        return $this->fail(['status' => false, 'message' => 'Failed to delete voter'], 500);
    }
}




public function importExcel()
{
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $voterModel = new VoterModel($db);

    $json = $this->request->getJSON(true);
    if (!$json || !is_array($json)) {
        return $this->fail('Invalid JSON data.');
    }

    $insertData = [];
    $insertedEpicNos = [];

    foreach ($json as $row) {
        // Mandatory fields: epic_no and full_name
        if (empty($row['epic_no']) || empty($row['full_name'])) continue;

        // Check if voter with same EPIC already exists
        $existing = $voterModel->where('epic_no', $row['epic_no'])->first();
        if (!$existing) {
            $insertData[] = [
                'epic_no' => $row['epic_no'],
                'full_name' => $row['full_name'],
                'husband_father_name' => $row['husband_father_name'] ?? null,
                'relation_type' => $row['relation_type'] ?? null,
                'first_name' => $row['first_name'] ?? null,
                'last_name' => $row['last_name'] ?? null,
                'father_name' => $row['father_name'] ?? null,
                'age' => !empty($row['age']) ? intval($row['age']) : null,
                'gender' => !empty($row['gender']) ? $row['gender'] : '-',
                'address' => !empty($row['address']) ? $row['address'] : '-',
                'booth_no' => !empty($row['booth_no']) ? $row['booth_no'] : null,
                'serial_no' => !empty($row['serial_no']) ? $row['serial_no'] : null,
                'part_no' => !empty($row['part_no']) ? $row['part_no'] : null,
                'assembly_code' => !empty($row['assembly_code']) ? $row['assembly_code'] : null,
                'ward_no' => !empty($row['ward_no']) ? $row['ward_no'] : null,
                'source_page' => !empty($row['source_page']) ? $row['source_page'] : null,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            $insertedEpicNos[] = $row['epic_no'];
        }
    }

    if (!empty($insertData)) {
        $voterModel->insertBatch($insertData);
    }

    // Return only newly inserted voters
    $voters = [];
    if (!empty($insertedEpicNos)) {
        $voters = $voterModel->whereIn('epic_no', $insertedEpicNos)->findAll();
    }

    return $this->respond([
        'success' => true,
        'data' => $voters,
        'message' => count($voters).' voters processed successfully!'
    ]);
}




}
