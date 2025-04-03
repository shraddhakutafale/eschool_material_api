<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use Config\Database;
use App\Models\IotDeviceModel;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;


class IotDevice extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        // Retrieve tenantConfig from the headers
        $tenantConfigHeader = $this->request->getHeaderLine('X-Tenant-Config');
        if (!$tenantConfigHeader) {
            throw new \Exception('Tenant configuration not found.');
        }

        // Decode the tenantConfig JSON
        $tenantConfig = json_decode($tenantConfigHeader, true);

        if (!$tenantConfig) {
            throw new \Exception('Invalid tenant configuration.');
        }

        // Connect to the tenant's database
        $db = Database::connect($tenantConfig);
        // Load StaffModel with the tenant database connection
        $iotdeviceModel = new IotDeviceModel($db);

        return $this->respond(["status" => true, "message" => "All iot Data Fetched", "data" => $iotdeviceModel->findAll()], 200);
    }

    public function getIotDevicePaging() {
        
        $input = $this->request->getJSON();
    
        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'iotId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
    
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load StaffModel with the tenant database connection
        $iotdeviceModel = new IotDeviceModel($db);
    
        $query = $iotdeviceModel;
    
        if (!empty($filter)) {
            $filter = json_decode(json_encode($filter), true);
    
            foreach ($filter as $key => $value) {
                if (in_array($key, [])) {
                    $query->like($key, $value); // LIKE filter for specific fields
                } else if ($key === 'createdDate') {
                    $query->where($key, $value); // Exact match filter for createdDate
                }
            }
    
            // Apply Date Range Filter (startDate and endDate)
            if (!empty($filter['startDate']) && !empty($filter['endDate'])) {
                $query->where('createdDate >=', $filter['startDate'])
                      ->where('createdDate <=', $filter['endDate']);
            }
    
            // Apply Last 7 Days Filter if requested
            if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last7days') {
                $last7DaysStart = date('Y-m-d', strtotime('-7 days'));  // 7 days ago from today
                $query->where('createdDate >=', $last7DaysStart);
            }
    
            // Apply Last 30 Days Filter if requested
            if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last30days') {
                $last30DaysStart = date('Y-m-d', strtotime('-30 days'));  // 30 days ago from today
                $query->where('createdDate >=', $last30DaysStart);
            }
        }
    
        // Ensure that the "deleted" status is 0 (active records)
        $query->where('isDeleted', 0);
    
        // Apply Sorting
        if (!empty($sortField) && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
            $query->orderBy($sortField, $sortOrder);
        }
    
        // Get Paginated Results
        $iotDevices = $query->paginate($perPage, 'default', $page);
        $pager = $iotdeviceModel->pager;
    
        $response = [
            "status" => true,
            "message" => "All Staff Data Fetched",
            "data" => $iotDevices ,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]
        ];
    
        return $this->respond($response, 200);
    }

    public function addParameter()
    {
        $input = $this->request->getJSON();
        
        // Validation rules
        $rules = [
            'ipAddress' => ['rules' => 'required'],
            'postId' => ['rules' => 'required'],
            'deviceId' => ['rules' => 'required'],
        ];

        if ($this->validate($rules)) {
            // Connect to the tenant's database
            $tenantService = new TenantService();
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new IotDeviceModel($db);

            // Insert data into the database
            $model->insert($input);

            // Success response
            return $this->respond(['status' => true, 'message' => 'IoT Device Created Successfully'], 200);
        } else {
            // Return validation errors
            return $this->fail([
                
                'status' => false,
                
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ], 409);
        }
    }
}
