<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\OrdeModel;
use App\Models\OrderDetailModel;
use App\Models\ItemModel;
use App\Libraries\TenantService;

use Config\Database;

class  OrderDetail extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        $tenantService = new TenantService();

        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

        // Load UserModel with the tenant database connection
        $OrderDetailModel = new OrderDetailModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $OrderDetailModel->findAll()], 200);
    }
public function getByOrder($orderId)
    {
        $model = new OrderDetailModel();
        $data = $model->where('orderId', $orderId)->findAll();

        return $this->respond([
            'status' => true,
            'data' => $data
        ]);
    }



    
}
