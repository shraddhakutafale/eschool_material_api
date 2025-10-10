<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\CandidateModel;
use Config\Database;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;


class Candidate extends BaseController
{
    use ResponseTrait;

     public function index()
    {
       // Insert the product data into the database
       $tenantService = new TenantService();
       // Connect to the tenant's database
       $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); // Load UserModel with the tenant database connection
        $CandidateModel = new CandidateModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $CandidateModel->findAll()], 200);
    }

   
  public function getAllCandidatePaging()
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

    $candidateModel = new CandidateModel($db);

    // Base query
    $query = $candidateModel
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
    $pager = $candidateModel->pager;

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

    // Validation rules
    $rules = [
        'fullName'      => ['rules' => 'required'],
        'contactNumber' => ['rules' => 'required'],
        'gender'        => ['rules' => 'permit_empty'],
        'email'         => ['rules' => 'valid_email|permit_empty']
    ];

    if (!$this->validate($rules)) {
        return $this->fail([
            'status' => false,
            'errors' => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ], 409);
    }

    // Decode JWT token for tenant info
    $key = "Exiaa@11";
    $header = $this->request->getHeaderLine("Authorization");
    $token = null;
    if ($header && preg_match('/Bearer\s(\S+)/', $header, $matches)) $token = $matches[1];
    $decoded = $token ? JWT::decode($token, new Key($key, 'HS256')) : null;
    $tenantName = $decoded->tenantName ?? 'default';

    // ✅ File uploads
    $uploadPath = FCPATH . "uploads/{$tenantName}/dataImages/";
    if (!is_dir($uploadPath)) mkdir($uploadPath, 0777, true);

    // Profile Photo
    $profilePhoto = $this->request->getFile('profilePhoto');
    if ($profilePhoto && $profilePhoto->isValid() && !$profilePhoto->hasMoved()) {
        $profileName = $profilePhoto->getRandomName();
        $profilePhoto->move($uploadPath, $profileName);
        $input['profilePhoto'] = "{$tenantName}/dataImages/{$profileName}";
    } else {
        $input['profilePhoto'] = $input['profilePhotoOld'] ?? null;
    }

    // ID Proof
    $idProofFile = $this->request->getFile('idProofFile');
    if ($idProofFile && $idProofFile->isValid() && !$idProofFile->hasMoved()) {
        $idProofName = $idProofFile->getRandomName();
        $idProofFile->move($uploadPath, $idProofName);
        $input['idProofFile'] = "{$tenantName}/dataImages/{$idProofName}";
    } else {
        $input['idProofFile'] = $input['idProofFileOld'] ?? null;
    }

    // Resume
    $resumeFile = $this->request->getFile('resumeFile');
    if ($resumeFile && $resumeFile->isValid() && !$resumeFile->hasMoved()) {
        $resumeName = $resumeFile->getRandomName();
        $resumeFile->move($uploadPath, $resumeName);
        $input['resumeFile'] = "{$tenantName}/dataImages/{$resumeName}";
    } else {
        $input['resumeFile'] = $input['resumeFileOld'] ?? null;
    }

    // Prepare full data array matching Angular form
    $data = [
        'businessId'         => $decoded->businessId ?? $input['businessId'] ?? 0,
        'fullName'           => $input['fullName'],
        'gender'             => $input['gender'] ?? null,
        'dob'                => $input['dob'] ?? null,
        'age'                => $input['age'] ?? null,
        'maritalStatus'      => $input['maritalStatus'] ?? null,
        'religion'           => $input['religion'] ?? null,
        'caste'              => $input['caste'] ?? null,
        'motherTongue'       => $input['motherTongue'] ?? null,
        'height'             => $input['height'] ?? null,
        'weight'             => $input['weight'] ?? null,
        'bloodGroup'         => $input['bloodGroup'] ?? null,
        'education'          => $input['education'] ?? null,
        'profession'         => $input['profession'] ?? null,
        'annualIncome'       => $input['annualIncome'] ?? null,
        'workLocation'       => $input['workLocation'] ?? null,
        'address'            => $input['address'] ?? null,
        'state'              => $input['state'] ?? null,
        'district'           => $input['district'] ?? null,
        'talukaBlock'        => $input['talukaBlock'] ?? null,
        'villageTown'        => $input['villageTown'] ?? null,
        'pincode'            => $input['pincode'] ?? null,
        'contactNumber'      => $input['contactNumber'],
        'alternateNumber'    => $input['alternateNumber'] ?? null,
        'email'              => $input['email'] ?? null,
        'fatherName'         => $input['fatherName'] ?? null,
        'motherName'         => $input['motherName'] ?? null,
        'familyDetails'      => $input['familyDetails'] ?? null,
        'partnerPreferences' => $input['partnerPreferences'] ?? null,
        'idProofType'        => $input['idProofType'] ?? null,
        'idProofNumber'      => $input['idProofNumber'] ?? null,
        'idProofFile'        => $input['idProofFile'] ?? null,
        'resumeFile'         => $input['resumeFile'] ?? null,
        'profilePhoto'       => $input['profilePhoto'] ?? null,
        'registrationDate'   => $input['registrationDate'] ?? date('Y-m-d'),
        'profileStatus'      => $input['profileStatus'] ?? 'Active',
        'addedBy'            => $decoded->userId ?? null,
        'createdDate'        => date('Y-m-d H:i:s'),
        'isDeleted'          => 0,
        'isActive'           => 1,
        'createdBy'          => $decoded->userId ?? null,
        'modifiedBy'         => null
    ];

    // Insert into tenant-specific database
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $model = new CandidateModel($db);
    $id = $model->insert($data);

    return $this->respond([
        'status' => true,
        'message' => 'Candidate added successfully',
        'data' => $id
    ], 200);
}




public function update()
{
    helper(['form', 'filesystem']);

    // Get POST + FILE input
    $input = $this->request->getPost();

    // --- Validate dataId ---
    if (!$this->validate(['id' => 'required|numeric'])) {
        return $this->fail([
            'status' => false,
            'errors' => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ], 409);
    }

    $Id = $input['id'];

    // --- Tenant configuration ---
    $tenantName = $this->request->getHeaderLine('X-Tenant-Config');
    if (empty($tenantName)) {
        return $this->fail(['status' => false, 'message' => 'Tenant database not specified'], 400);
    }

    // --- Load tenant DB config ---
    $tenantService = new \App\Libraries\TenantService();
    try {
        $dbConfig = $tenantService->getTenantConfig($tenantName);
    } catch (\Exception $e) {
        return $this->fail(['status' => false, 'message' => 'Invalid tenant configuration'], 400);
    }

    // --- Determine tenant folder ---
    $tenantFolder = is_array($dbConfig) && isset($dbConfig['database'])
        ? $dbConfig['database']
        : preg_replace('/[^a-zA-Z0-9_-]/', '', $tenantName);

    // --- Initialize Candidate model (candidate_mst table) ---
    $model = new \App\Models\CandidateModel($dbConfig);

    // --- Check if candidate exists ---
    $existing = $model->find($Id);
    if (!$existing) {
        return $this->fail(['status' => false, 'message' => 'Candidate not found'], 404);
    }

    // --- Handle Profile Photo Upload ---
    $profileImage = $this->request->getFile('profilePic');
    if ($profileImage && $profileImage->isValid() && !$profileImage->hasMoved()) {
        $uploadPath = FCPATH . 'uploads/' . $tenantFolder . '/candidate/profileImage/';
        if (!is_dir($uploadPath)) mkdir($uploadPath, 0777, true);

        $imageName = $profileImage->getRandomName();
        $profileImage->move($uploadPath, $imageName);

        $input['profilePic'] = $tenantFolder . '/candidate/profileImage/' . $imageName;
    }

    // --- Handle ID Proof Upload ---
    $idProofFile = $this->request->getFile('idProof');
    if ($idProofFile && $idProofFile->isValid() && !$idProofFile->hasMoved()) {
        $uploadPath = FCPATH . 'uploads/' . $tenantFolder . '/candidate/idProof/';
        if (!is_dir($uploadPath)) mkdir($uploadPath, 0777, true);

        $idProofName = $idProofFile->getRandomName();
        $idProofFile->move($uploadPath, $idProofName);

        $input['idProofFile'] = $tenantFolder . '/candidate/idProof/' . $idProofName;
    }

    // --- Handle Resume Upload ---
    $resumeFile = $this->request->getFile('resume');
    if ($resumeFile && $resumeFile->isValid() && !$resumeFile->hasMoved()) {
        $uploadPath = FCPATH . 'uploads/' . $tenantFolder . '/candidate/resume/';
        if (!is_dir($uploadPath)) mkdir($uploadPath, 0777, true);

        $resumeName = $resumeFile->getRandomName();
        $resumeFile->move($uploadPath, $resumeName);

        $input['resumeFile'] = $tenantFolder . '/candidate/resume/' . $resumeName;
    }

    // --- Add audit fields ---
    $input['modifiedDate'] = date('Y-m-d H:i:s');

    // --- Sanitize and allow only permitted fields ---
    $allowedColumns = $model->allowedFields;
    $filteredInput = array_intersect_key($input, array_flip($allowedColumns));

    // --- Perform the update ---
    try {
        $updated = $model->update($Id, $filteredInput);
        if ($updated) {
            return $this->respond([
                'status' => true,
                'message' => 'Candidate updated successfully',
                'dataId' => $Id
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
            'message' => 'Error updating candidate: ' . $e->getMessage()
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
            $model = new CandidateModel($db);

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
                return $this->respond(['status' => true, 'message' => 'candidate Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete candidate'], 500);
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
