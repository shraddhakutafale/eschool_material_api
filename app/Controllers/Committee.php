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
       // Insert the product data into the database
       $tenantService = new TenantService();
       // Connect to the tenant's database
       $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); // Load UserModel with the tenant database connection
        $CommitteeModel = new CommitteeModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $CommitteeModel->findAll()], 200);
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

    // ✅ Connect to the tenant DB directly
    $db = \Config\Database::connect();
    $db->query("USE exiaa_ex0009"); // Make sure you're using the correct database

    $builder = $db->table('committee_mst')
                  ->where('isDeleted', 0)
                  ->where('businessId', $input->businessId);

    // Filters
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

    // Search
    if (!empty($search)) {
        $builder->groupStart()
                ->like('committeeName', $search)
                ->orLike('committeeType', $search)
                ->orLike('committeeCode', $search)
                ->groupEnd();
    }

    $builder->orderBy($sortField, $sortOrder);

    // Pagination
    $total = $builder->countAllResults(false); // total without limit
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

    // --- JWT token decode ---
    $key = "Exiaa@11";
    $header = $this->request->getHeaderLine("Authorization");
    $token = null;
    if ($header && preg_match('/Bearer\s(\S+)/', $header, $matches)) {
        $token = $matches[1];
    }

    if (!$token) {
        return $this->failUnauthorized('JWT token missing');
    }

    $decoded = JWT::decode($token, new Key($key, 'HS256'));
    $tenant = $decoded->tenantName ?? 'default';

    // --- Profile picture upload ---
    $profilePic = $this->request->getFile('profilePic');
    if ($profilePic && $profilePic->isValid() && !$profilePic->hasMoved()) {
        $uploadPath = FCPATH . 'uploads/' . $tenant . '/committeeImages/';
        if (!is_dir($uploadPath)) mkdir($uploadPath, 0777, true);

        $newName = $profilePic->getRandomName();
        $profilePic->move($uploadPath, $newName);

        $input['profilePic'] = $tenant . '/committeeImages/' . $newName;
    }

    // --- Database insert ---
    $db = \Config\Database::connect();
    $db->query("USE exiaa_ex0009"); // keep fixed database
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

    $rules = ['committeeId' => ['rules' => 'required|numeric']];
    if (!$this->validate($rules)) {
        return $this->fail([
            'status' => false,
            'errors' => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ], 409);
    }

    $db = \Config\Database::connect();
    $db->query("USE exiaa_ex0009"); // keep fixed database
    $model = new CommitteeModel($db);

    $committeeId = $input['committeeId'];
    $committee = $model->find($committeeId);

    if (!$committee) {
        return $this->fail(['status' => false, 'message' => 'Committee not found'], 404);
    }

    // --- Tenant dynamic from JWT ---
    $key = "Exiaa@11";
    $header = $this->request->getHeaderLine("Authorization");
    $token = null;
    if ($header && preg_match('/Bearer\s(\S+)/', $header, $matches)) {
        $token = $matches[1];
    }
    $decoded = $token ? JWT::decode($token, new Key($key, 'HS256')) : null;
    $tenant = $decoded->tenantName ?? 'default';

    $uploadFolder = FCPATH . 'uploads/' . $tenant . '/committeeImages/';
    if (!is_dir($uploadFolder)) mkdir($uploadFolder, 0777, true);

    // --- Handle Profile Picture ---
    $profilePicPath = $committee['profilePic'] ?? null;
    $file = $this->request->getFile('profilePic');

    if ($file && $file->isValid() && !$file->hasMoved()) {
        $ext = $file->getClientExtension() ?: 'jpg';
        $newName = uniqid() . '.' . $ext;
        $file->move($uploadFolder, $newName);

        if (!empty($profilePicPath) && file_exists(FCPATH . 'uploads/' . $profilePicPath)) {
            unlink(FCPATH . 'uploads/' . $profilePicPath);
        }

        $profilePicPath = $tenant . '/committeeImages/' . $newName;
    } elseif (!empty($input['profilePicOld'])) {
        $profilePicPath = $input['profilePicOld'];
    }

    // --- Update data ---
    $updateData = [
        'committeeMember'    => $input['committeeMember'] ?? $committee['committeeMember'],
        'qualification'      => $input['qualification'] ?? $committee['qualification'],
        'email'              => $input['email'] ?? $committee['email'],
        'committeeMemberDob' => $input['committeeMemberDob'] ?? $committee['committeeMemberDob'],
        'phoneNo'            => $input['phoneNo'] ?? $committee['phoneNo'],
        'facebookUrl'        => $input['facebookUrl'] ?? $committee['facebookUrl'],
        'instaUrl'           => $input['instaUrl'] ?? $committee['instaUrl'],
        'twitterUrl'         => $input['twitterUrl'] ?? $committee['twitterUrl'],
        'profilePic'         => $profilePicPath,
    ];

    $model->update($committeeId, $updateData);

    return $this->respond([
        'status'  => true,
        'message' => 'Committee updated successfully',
        'data'    => $updateData
    ], 200);
}




    public function delete()
    {
        $input = $this->request->getJSON();

        $rules = [
            'committeeId' => ['rules' => 'required|numeric'],
        ];

        if (!$this->validate($rules)) {
            return $this->fail([
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ], 409);
        }

        $db = \Config\Database::connect();
        $db->query("USE exiaa_ex0009"); 
        $model = new CommitteeModel($db);

        $committeeId = $input->committeeId;
        $committee = $model->find($committeeId);

        if (!$committee) {
            return $this->fail(['status' => false, 'message' => 'Committee not found'], 404);
        }

        // Delete the record
        $deleted = $model->delete($committeeId);

        if ($deleted) {
            return $this->respond(['status' => true, 'message' => 'Committee Deleted Successfully'], 200);
        } else {
            return $this->fail(['status' => false, 'message' => 'Failed to delete committee'], 500);
        }
    }
    
}
