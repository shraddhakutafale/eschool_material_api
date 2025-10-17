<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\ColorCodeModel;
use CodeIgniter\API\ResponseTrait;
use App\Models\ItemModel;
use App\Models\ItemTypeModel;
use App\Models\ItemCategory;
use App\Models\ItemSubCategory;
use App\Models\ItemGroup;
use App\Models\BrandModel;
use App\Models\Unit;
use Config\Database;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;
use App\Models\SlideModel;
use App\Models\ColorCodesModel;



class ColorCode extends BaseController
{
    use ResponseTrait;


 public function index()
    {
       // Insert the product data into the database
       $tenantService = new TenantService();
       // Connect to the tenant's database
       $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); // Load UserModel with the tenant database connection
        $colorCodeModel = new ColorCodeModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $colorCodeModel->findAll()], 200);
    }
    
  public function getAllColorCodes()
    {
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

        $model = new ColorCodeModel($db);
        $data = $model->findAll();

        return $this->respond([
            'status' => true,
            'message' => 'Color Codes Fetched Successfully',
            'data' => $data,
        ]);
    }

    public function getAllColorCodesPaging()
    {
        $model = new ColorCodeModel();
        $page = $this->request->getVar('page') ?? 1;
        $limit = $this->request->getVar('limit') ?? 10;

        $data = $model->paginate($limit, 'default', $page);
        return $this->respond([
            'status' => true,
            'data' => $data,
            'pager' => $model->pager->getDetails()
        ]);
    }

    public function create()
    {
        $model = new ColorCodeModel();
        $data = $this->request->getJSON(true);
        $model->insert($data);
        return $this->respond(['status' => true, 'message' => 'Color Code added successfully']);
    }

    public function update($id = null)
    {
        $model = new ColorCodeModel();
        $data = $this->request->getJSON(true);
        $model->update($id, $data);
        return $this->respond(['status' => true, 'message' => 'Color Code updated successfully']);
    }

    public function delete($id = null)
    {
        $model = new ColorCodeModel();
        $model->delete($id);
        return $this->respond(['status' => true, 'message' => 'Color Code deleted successfully']);
    }
}