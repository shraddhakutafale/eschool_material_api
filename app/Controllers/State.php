<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\StateModel;
use App\Models\AdmissionModel;
use App\Models\AttendanceModel;
use App\Models\ItemModel;
use App\Models\ItemFeeMapModel;
use App\Models\FeeModel;
use App\Models\PaymentDetailModel;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

use Config\Database;

class State extends BaseController
{
    use ResponseTrait;

    public function index()
    {
         
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load UserModel with the tenant database connection
        $stateModel = new StateModel($db);
        $states = $stateModel
           
            ->where('student_mst.isDeleted', 0)
            ->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $stateModel->findAll()], 200);
    }

public function getAllStatePaging()
{
    $input = $this->request->getJSON();

    $page = isset($input->page) ? (int)$input->page : 1;
    $perPage = isset($input->perPage) ? (int)$input->perPage : 100;

    $search = isset($input->search) ? trim($input->search) : '';

    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    $stateModel = new \App\Models\StateModel($db);

    $query = $stateModel->where('is_deleted', 0)->where('is_active', 1);

    if ($search !== '') {
        $query->like('state_name', $search);
    }

    $records = $query->paginate($perPage, 'default', $page);
    $pager = $stateModel->pager;

    return $this->respond([
        "status" => true,
        "message" => "All States Fetched Successfully",
        "data" => $records,
        "pagination" => [
            "currentPage" => $pager->getCurrentPage(),
            "totalPages" => $pager->getPageCount(),
            "totalItems" => $pager->getTotal(),
            "perPage" => $perPage
        ]
    ]);
}



}