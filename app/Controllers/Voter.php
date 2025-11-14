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
    // Get JSON array input
    $inputs = $this->request->getJSON(true); // true converts JSON to array

    if (empty($inputs) || !is_array($inputs)) {
        return $this->fail(['status' => false, 'message' => 'No input received or not an array'], 400);
    }

    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $model = new VoterModel($db);

    $insertedIds = [];

    foreach ($inputs as $input) {
        // Basic validation per row
        if (empty($input['epic_no']) || empty($input['full_name'])) {
            continue; // skip invalid row
        }

        $data = [
            'epic_no'              => $input['epic_no'],
            'full_name'            => $input['full_name'],
            'm_full_name'          => $input['m_full_name'] ?? null,
            'husband_father_name'  => $input['husband_father_name'] ?? null,
            'm_husband_father_name'=> $input['m_husband_father_name'] ?? null,
            'relation_type'        => $input['relation_type'] ?? null,
            'm_relation_type'      => $input['m_relation_type'] ?? null,
            'age'                  => $input['age'] ?? null,
            'gender'               => $input['gender'] ?? null,
            'm_gender'             => $input['m_gender'] ?? null,
            'address'              => $input['address'] ?? null,
            'm_address'            => $input['m_address'] ?? null,
            'booth_no'             => $input['booth_no'] ?? null,
            'serial_no'            => $input['serial_no'] ?? null,
            'part_no'              => $input['part_no'] ?? null,
            'part_name'            => $input['part_name'] ?? null,
            'assembly_code'        => $input['assembly_code'] ?? null,
            'ward_no'              => $input['ward_no'] ?? null,
            'source_page'          => $input['source_page'] ?? null,
            'state_name'           => $input['state_name'] ?? null,
            'district_name'        => $input['district_name'] ?? null,
            'language'             => $input['language'] ?? null,
            'extraction_date'      => $input['extraction_date'] ?? null,
            'constituencyId'       => isset($input['constituencyId']) ? (int)$input['constituencyId'] : null,
        ];

        $id = $model->insert($data);
        if ($id) $insertedIds[] = $id;
    }

    return $this->respond([
        'status'  => true,
        'message' => count($insertedIds) . ' voters added successfully',
        'data'    => $insertedIds
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

 public function updateVoter()
    {
        try {
            $data = $this->request->getJSON(true);

            if (!$data) {
                return $this->respond([
                    'status' => false,
                    'message' => 'No data received.'
                ], 400);
            }

            $voterId = $data['id'] ?? null;

            if (empty($voterId)) {
                return $this->respond([
                    'status' => false,
                    'message' => 'Voter ID is missing.'
                ], 400);
            }

            // ✅ Load tenant-based DB connection
            $tenantService = new \App\Services\TenantService();
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

            $voterModel = new VoterModel($db);
            $voterBusinessModel = new VoterBusinessModel($db);

            // ✅ Check if voter exists
            $voter = $voterModel->find($voterId);
            if (!$voter) {
                return $this->respond([
                    'status' => false,
                    'message' => 'Voter not found.'
                ], 404);
            }

            // ✅ Prepare update data for voter table
            $updateVoter = [
                'full_name'           => $data['full_name'] ?? $voter['full_name'],
                'husband_father_name' => $data['husband_father_name'] ?? $voter['husband_father_name'],
                'relation_type'       => $data['relation_type'] ?? $voter['relation_type'],
                'father_name'         => $data['father_name'] ?? $voter['father_name'],
                'age'                 => $data['age'] ?? $voter['age'],
                'gender'              => $data['gender'] ?? $voter['gender'],
                'address'             => $data['address'] ?? $voter['address'],
                'booth_no'            => $data['booth_no'] ?? $voter['booth_no'],
                'serial_no'           => $data['serial_no'] ?? $voter['serial_no'],
                'part_no'             => $data['part_no'] ?? $voter['part_no'],
                'assembly_code'       => $data['assembly_code'] ?? $voter['assembly_code'],
                'ward_no'             => $data['ward_no'] ?? $voter['ward_no'],
                'modifiedDate'        => date('Y-m-d H:i:s'),
                'modifiedBy'          => $data['modifiedBy'] ?? null,
            ];

            $voterModel->update($voterId, $updateVoter);

            // ✅ Handle voter_business table (insert or update)
            $businessData = [
                'voterId'       => $voterId,
                'mobileNumber'  => $data['mobileNumber'] ?? null,
                'geoLocation'   => $data['geoLocation'] ?? null,
                'colorCodeId'   => $data['colorCodeId'] ?? null,
                'modifiedDate'  => date('Y-m-d H:i:s'),
                'modifiedBy'    => $data['modifiedBy'] ?? null,
                'isActive'      => 1,
                'isDeleted'     => 0,
            ];

            // ✅ Check if voter_business entry exists
            $existingBusiness = $voterBusinessModel->where('voterId', $voterId)->first();

            if ($existingBusiness) {
                $voterBusinessModel->update($existingBusiness['voterBusinessId'], $businessData);
            } else {
                $businessData['createdDate'] = date('Y-m-d H:i:s');
                $voterBusinessModel->insert($businessData);
            }

            return $this->respond([
                'status'  => true,
                'message' => 'Voter and business info updated successfully',
                'data'    => [
                    'voter' => $updateVoter,
                    'business' => $businessData,
                ]
            ], 200);

        } catch (\Exception $e) {
            return $this->respond([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }


}
