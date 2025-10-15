<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\DistrictModel;
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

class District extends BaseController
{
    use ResponseTrait;

    public function index()
    {
         
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load UserModel with the tenant database connection
        $districtModel = new DistrictModel($db);
        $districts = $districtModel
           
            ->where('student_mst.isDeleted', 0)
            ->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $districtModel->findAll()], 200);
    }

public function getAllDistrictPaging()
{
    $input = $this->request->getJSON();

    $page = isset($input->page) ? (int)$input->page : 1;
    $perPage = isset($input->perPage) ? (int)$input->perPage : 200;
    $search = isset($input->search) ? trim($input->search) : '';

    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    $districtModel = new \App\Models\DistrictModel($db);

    // ✅ Show all districts where not deleted
    $query = $districtModel->where('is_deleted', 0);

    // Optional search
    if ($search !== '') {
        $query->like('dist_name', $search);
    }

    // Paginate
    $records = $query->paginate($perPage, 'default', $page);
    $pager = $districtModel->pager;

    return $this->respond([
        "status" => true,
        "message" => "All Districts Fetched Successfully",
        "data" => $records,
        "pagination" => [
            "currentPage" => $pager->getCurrentPage(),
            "totalPages" => $pager->getPageCount(),
            "totalItems" => $pager->getTotal(),
            "perPage" => $perPage
        ]
    ], 200);
}






}