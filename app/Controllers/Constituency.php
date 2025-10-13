<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\ConstituencyModel;
use Config\Database;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;


class Constituency extends BaseController
{
    use ResponseTrait;

   public function index()
    {
       // Insert the product data into the database
       $tenantService = new TenantService();
       // Connect to the tenant's database
       $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); // Load UserModel with the tenant database connection
        $constituencyModel = new ConstituencyModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $constituencyModel->findAll()], 200);
    }
    

    public function getAllConstituencyPaging()
{
    $input = $this->request->getJSON();

    $page = isset($input->page) ? (int)$input->page : 1;
    $perPage = isset($input->perPage) ? (int)$input->perPage : 10;
    $sortField = isset($input->sortField) ? $input->sortField : 'constituencyId';
    $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
    $search = isset($input->search) ? trim($input->search) : '';
    $filter = isset($input->filter) ? $input->filter : null;

    // 🔗 Tenant DB connect (if multi-tenant)
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    $constituencyModel = new ConstituencyModel($db);

    // Base query
    $query = $constituencyModel
        ->where('isActive', 1)
        ->where('isDeleted', 0)
        ->orderBy($sortField, $sortOrder);

    // 🔍 Search
    if (!empty($search)) {
        $query->groupStart()
              ->like('constituencyCode', $search)
              ->orLike('constituencyName', $search)
              ->orLike('districtName', $search)
              ->orLike('stateName', $search)
              ->orLike('loksabhaConstituency', $search)
              ->groupEnd();
    }

    // 🎯 Filter
    if ($filter) {
        $filter = json_decode(json_encode($filter), true);

        foreach ($filter as $key => $value) {
            if ($value === '' || $value === null) continue;

            if (in_array($key, [
                'constituencyCode',
                'constituencyName',
                'districtName',
                'stateName',
                'reservationType',
                'loksabhaConstituency'
            ])) {
                $query->like($key, $value);
            } elseif ($key === 'created_at') {
                $query->where($key, $value);
            }
        }

        // Date range filter
        if (!empty($filter['startDate']) && !empty($filter['endDate'])) {
            $query->where('created_at >=', $filter['startDate'])
                  ->where('created_at <=', $filter['endDate']);
        }

        // Date range shortcuts
        if (!empty($filter['dateRange'])) {
            if ($filter['dateRange'] === 'last7days') {
                $query->where('created_at >=', date('Y-m-d', strtotime('-7 days')));
            } elseif ($filter['dateRange'] === 'last30days') {
                $query->where('created_at >=', date('Y-m-d', strtotime('-30 days')));
            }
        }
    }

    // 🧾 Pagination
    $records = $query->paginate($perPage, 'default', $page);
    $pager = $constituencyModel->pager;

    return $this->respond([
        "status" => true,
        "message" => "All Constituency Data Fetched Successfully",
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

    // Validation rules for constituency fields
    $rules = [
        'constituencyCode'      => ['rules' => 'required|alpha_numeric'], // Code can be alphanumeric
        'constituencyName'      => ['rules' => 'required'],
        'districtName'          => ['rules' => 'required'],
        'stateName'             => ['rules' => 'required'],
        'reservationType'       => ['rules' => 'permit_empty'],
        'constituencyNumber'    => ['rules' => 'permit_empty|numeric'],
        'totalVoters'           => ['rules' => 'permit_empty|numeric'],
        'loksabhaConstituency'  => ['rules' => 'permit_empty']
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
    if ($header && preg_match('/Bearer\s(\S+)/', $header, $matches)) {
        $token = $matches[1];
    }
    $decoded = $token ? JWT::decode($token, new Key($key, 'HS256')) : null;

    // Prepare constituency data array
    $data = [
    'constituencyCode'       => $input['constituencyCode'],
    'constituencyName'       => $input['constituencyName'],
    'districtName'           => $input['districtName'],
    'stateName'              => $input['stateName'],
    'constituencyNumber'     => $input['constituencyNumber'] ?? null,
    'totalVoters'            => $input['totalVoters'] ?? 0,
    'reservationType'        => $input['reservationType'] ?? 'General',
    'loksabhaConstituency'   => $input['loksabhaConstituency'] ?? null,
    'created_at'             => date('Y-m-d H:i:s'),
    'updated_at'             => date('Y-m-d H:i:s'),
    'addedBy'                => $decoded->userId ?? null,
    'isActive'               => 1
];


    // Use tenant-specific database if applicable
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $model = new ConstituencyModel($db); // Make sure you have a ConstituencyModel
    $id = $model->insert($data);

    return $this->respond([
        'status' => true,
        'message' => 'Constituency added successfully',
        'data' => $id
    ], 200);
}


   















}
