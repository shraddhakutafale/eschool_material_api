<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use Config\Database;
use App\Models\VehicleModel;
use App\Models\VendorModel;
use App\Models\RouteModel;
use App\Models\StopageModel;
use App\Models\StopageRouteMapModel;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;


class Transport extends BaseController
{
    use ResponseTrait;

    // Get all leads
    public function index()
    {
           // Insert the product data into the database
           $tenantService = new TenantService();
           // Connect to the tenant's database
           $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 
        $leadModel = new LeadModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $leadModel->findAll()], 200);
    }


public function getAllVendor()
{
    try {
        // Get default DB config
        $dbConfig = config('Database')->default;
        $dbConfig['database'] = 'eschool_db_tenant';  // override only database name

        // Connect using modified config
        $db = \Config\Database::connect($dbConfig);

        // Use VendorModel
        $vendorModel = new \App\Models\VendorModel($db);

        // Fetch all active and not-deleted vendors
        $vendors = $vendorModel->where('isDeleted', 0)->where('isActive', 1)->findAll();

        return $this->respond([
            "status" => true,
            "message" => "All Vendors Fetched",
            "data" => $vendors
        ], 200);

    } catch (\Throwable $e) {
        log_message('error', 'Get All Vendor Exception: ' . $e->getMessage());
        return $this->fail([
            "status" => false,
            "message" => "Server Error",
            "error" => $e->getMessage()
        ], 500);
    }
}
public function getAllVehicle()
{
    try {
        // Get default DB config
        $dbConfig = config('Database')->default;
        $dbConfig['database'] = 'eschool_db_tenant';  // tenant DB

        // Connect using modified DB config
        $db = \Config\Database::connect($dbConfig);

        // Vehicle Model
        $vehicleModel = new \App\Models\VehicleModel($db);

        // Fetch active & not deleted vehicles
        $vehicles = $vehicleModel
            ->where('isDeleted', 0)
            ->where('isActive', 1)
            ->findAll();

        return $this->respond([
            "status" => true,
            "message" => "All Vehicles Fetched",
            "data"    => $vehicles
        ], 200);

    } catch (\Throwable $e) {
        log_message('error', 'Get All Vehicle Exception: ' . $e->getMessage());

        return $this->fail([
            "status"  => false,
            "message" => "Server Error",
            "error"   => $e->getMessage()
        ], 500);
    }
}



public function getVehiclesPaging()
{
    $input = $this->request->getJSON();

    // Pagination
    $page = isset($input->page) ? $input->page : 1;
    $perPage = isset($input->perPage) ? $input->perPage : 10;

    // Sorting
    $sortField = isset($input->sortField) ? $input->sortField : 'vehicleId';
    $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';

    // Search + Filter
    $search = isset($input->search) ? $input->search : '';
    $filter = $input->filter;

    // ------------------------
    // FIXED DB CONNECTION
    // ------------------------
    $dbConfig = [
        'DSN'      => '',
        'hostname' => 'localhost',
        'username' => 'root',             // your DB username
        'password' => '',                 // your DB password
        'database' => 'eschool_db_tenant',// fixed database
        'DBDriver' => 'MySQLi',
        'DBPrefix' => '',
        'pConnect' => false,
        'DBDebug'  => true,
        'charset'  => 'utf8mb4',
        'DBCollat' => 'utf8mb4_general_ci',
        'port'     => 3306,
    ];

    $db = \Config\Database::connect($dbConfig);

    $vehicleModel = new \App\Models\VehicleModel($db);
    $query = $vehicleModel;

    // ------------------------
    // SEARCH
    // ------------------------
    if (!empty($search)) {
        $query->groupStart()
              ->like('vehicleName', $search)
              ->orLike('vehicleNumber', $search)
              ->orLike('driverName', $search)
              ->orLike('driverMobile', $search)
              ->groupEnd();
    }

    // ------------------------
    // FILTER
    // ------------------------
    if (!empty($filter)) {
        $filter = json_decode(json_encode($filter), true);

        foreach ($filter as $key => $value) {
            if (in_array($key, ['vehicleName', 'vehicleNumber', 'driverName', 'driverMobile'])) {
                $query->like($key, $value);
            }

            if ($key === 'vendorId') {
                $query->where('vendorId', $value);
            }

            if ($key === 'createdDate') {
                $query->where('DATE(createdDate)', $value);
            }
        }

        // Date Range Filter
        if (!empty($filter['startDate']) && !empty($filter['endDate'])) {
            $query->where('DATE(createdDate) >=', $filter['startDate'])
                  ->where('DATE(createdDate) <=', $filter['endDate']);
        }
    }

    // Active & Not Deleted
    $query->where('isDeleted', 0);

    // ------------------------
    // SORTING
    // ------------------------
    if (!empty($sortField) && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
        $query->orderBy($sortField, $sortOrder);
    }

    // ------------------------
    // PAGINATION
    // ------------------------
    $vehicles = $query->paginate($perPage, 'default', $page);

    // Add GPS location (lat,lng) in each object
    foreach ($vehicles as &$v) {
        $v['location'] = [
            "latitude"  => isset($v['latitude']) ? floatval($v['latitude']) : null,
            "longitude" => isset($v['longitude']) ? floatval($v['longitude']) : null
        ];
    }

    $pager = $vehicleModel->pager;

    $response = [
        "status" => true,
        "message" => "All Vehicle Data Fetched",
        "data" => $vehicles,
        "pagination" => [
            "currentPage" => $pager->getCurrentPage(),
            "totalPages" => $pager->getPageCount(),
            "totalItems" => $pager->getTotal(),
            "perPage" => $perPage
        ]
    ];

    return $this->respond($response, 200);
}


public function createVehicle()
{
    try {
        $input = (array) $this->request->getJSON(true);

        $rules = [
            'vehicleName' => 'required',
        ];

        if ($this->validate($rules)) {

            // Get default DB config
            $dbConfig = config('Database')->default;

            // Only override the database name
            $dbConfig['database'] = 'eschool_db_tenant';

            // Connect using modified config
            $db = \Config\Database::connect($dbConfig);

            $vehicleModel = new \App\Models\VehicleModel($db);

            // Optional: Set default flags
            $input['isActive']  = 1;
            $input['isDeleted'] = 0;

            // Insert vehicle
            if (!$vehicleModel->insert($input)) {
                log_message('error', 'Insert Vehicle Failed: ' . print_r($vehicleModel->errors(), true));
                return $this->fail([
                    'status'  => false,
                    'message' => 'Insert Failed',
                    'errors'  => $vehicleModel->errors()
                ], 500);
            }

            $vehicleId = $vehicleModel->getInsertID();

            return $this->respond([
                'status'     => true,
                'message'    => 'Vehicle Created Successfully',
                'vehicleId'  => $vehicleId,
            ], 200);

        } else {
            return $this->fail([
                'status'  => false,
                'errors'  => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ], 409);
        }

    } catch (\Throwable $e) {
        log_message('error', 'Create Vehicle Exception: ' . $e->getMessage());
        return $this->fail([
            'status'  => false,
            'message' => 'Server Error',
            'error'   => $e->getMessage()
        ], 500);
    }
}

  public function updateVehicle()
{
    $input = $this->request->getJSON();

    $rules = [
        'vehicleId' => ['rules' => 'required|numeric'], // Ensure vehicleId is provided and numeric
    ];

    if ($this->validate($rules)) {
        $dbConfig = config('Database')->default;
        $dbConfig['database'] = 'eschool_db_tenant';
        $db = \Config\Database::connect($dbConfig);

        $vehicleModel = new \App\Models\VehicleModel($db);

        $vehicleId = $input->vehicleId;
        $vehicle = $vehicleModel->find($vehicleId);

        if (!$vehicle) {
            return $this->fail(['status' => false, 'message' => 'Vehicle not found'], 404);
        }

        // Fields allowed to update
        $allowedFields = [
            'vehicleName', 'vehicleNumber', 'vendorId',
            'driverName', 'driverMobile', 'isActive'
        ];

        $updateData = [];

        foreach ($allowedFields as $field) {
            if (isset($input->$field)) {
                $updateData[$field] = $input->$field;
            }
        }

        if (!empty($updateData)) {
            $updated = $vehicleModel->update($vehicleId, $updateData);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Vehicle updated successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update vehicle'], 500);
            }
        } else {
            return $this->fail(['status' => false, 'message' => 'No data provided to update'], 400);
        }
    } else {
        return $this->fail([
            'status' => false,
            'errors' => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ], 409);
    }
}



public function deleteVehicle()
{
    $input = $this->request->getJSON(true);

    if (empty($input['vehicleId']) || !is_numeric($input['vehicleId'])) {
        return $this->fail([
            'status' => false,
            'message' => 'vehicleId is required and must be numeric'
        ], 409);
    }

    try {
        // Use default DB config, override only the database name
        $db = \Config\Database::connect();
        $db->setDatabase('eschool_db_tenant'); // Override database dynamically
        $vehicleModel = new \App\Models\VehicleModel($db);

        $vehicleId = $input['vehicleId'];
        $vehicle = $vehicleModel->find($vehicleId);

        if (!$vehicle) {
            return $this->fail(['status' => false, 'message' => 'Vehicle not found'], 404);
        }

        // Soft delete
        $vehicleModel->update($vehicleId, ['isDeleted' => 1]);

        return $this->respond(['status' => true, 'message' => 'Vehicle Deleted Successfully'], 200);

    } catch (\Throwable $e) {
        log_message('error', 'Delete Vehicle Exception: ' . $e->getMessage());
        return $this->fail([
            'status'  => false,
            'message' => 'Server Error',
            'error'   => $e->getMessage()
        ], 500);
    }
}


public function getVendorsPaging()
{
    $input = $this->request->getJSON();

  
    $page = isset($input->page) ? $input->page : 1;
    $perPage = isset($input->perPage) ? $input->perPage : 10;

  
    $sortField = isset($input->sortField) ? $input->sortField : 'vendorId';
    $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';

    $search = isset($input->search) ? $input->search : '';
    $filter = $input->filter;

    $db = \Config\Database::connect();
    $db->query("USE eschool_db_tenant"); // select your tenant database

    $vendorModel = new \App\Models\VendorModel($db);
    $query = $vendorModel;

    if (!empty($search)) {
        $query->groupStart()
              ->like('vendorName', $search)
              ->orLike('mobNo', $search)
              ->orLike('address', $search)
              ->groupEnd();
    }

    if (!empty($filter)) {
        $filter = json_decode(json_encode($filter), true);

        foreach ($filter as $key => $value) {
            if (in_array($key, ['vendorName', 'mobNo', 'address'])) {
                $query->like($key, $value);
            }

            if ($key === 'createdDate') {
                $query->where('DATE(createdDate)', $value);
            }
        }

        // Date Range Filter
        if (!empty($filter['startDate']) && !empty($filter['endDate'])) {
            $query->where('DATE(createdDate) >=', $filter['startDate'])
                  ->where('DATE(createdDate) <=', $filter['endDate']);
        }
    }

    $query->where('isDeleted', 0);


    if (!empty($sortField) && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
        $query->orderBy($sortField, $sortOrder);
    }

    $vendors = $query->paginate($perPage, 'default', $page);
    $pager = $vendorModel->pager;

    $response = [
        "status" => true,
        "message" => "All Vendor Data Fetched",
        "data" => $vendors,
        "pagination" => [
            "currentPage" => $pager->getCurrentPage(),
            "totalPages" => $pager->getPageCount(),
            "totalItems" => $pager->getTotal(),
            "perPage" => $perPage
        ]
    ];

    return $this->respond($response, 200);
}

public function createVendor()
{
    try {
        $input = (array) $this->request->getJSON(true);

        $rules = [
            'vendorName' => 'required',
        ];

        if ($this->validate($rules)) {

            // Use default DB connection
            $db = \Config\Database::connect();
            $db->setDatabase('eschool_db_tenant');

            $vendorModel = new \App\Models\VendorModel($db);

            // Default flags
            $input['isActive']  = 1;
            $input['isDeleted'] = 0;

            if (!$vendorModel->insert($input)) {
                return $this->fail([
                    'status' => false,
                    'message' => 'Insert Failed',
                    'errors' => $vendorModel->errors()
                ], 500);
            }

            return $this->respond([
                'status'   => true,
                'message'  => 'Vendor Created Successfully',
                'vendorId' => $vendorModel->getInsertID(),
            ], 200);

        } else {
            return $this->fail([
                'status'  => false,
                'message' => 'Invalid Inputs',
                'errors'  => $this->validator->getErrors()
            ], 409);
        }
    } catch (\Throwable $e) {
        return $this->fail([
            'status' => false,
            'message' => 'Server Error',
            'error' => $e->getMessage()
        ], 500);
    }
}
public function updateVendor()
{
    $input = $this->request->getJSON();

    $rules = [
        'vendorId' => 'required|numeric'
    ];

    if (!$this->validate($rules)) {
        return $this->fail([
            'status' => false,
            'message' => 'Invalid Inputs',
            'errors' => $this->validator->getErrors()
        ], 409);
    }

    $db = \Config\Database::connect();
    $db->setDatabase('eschool_db_tenant');

    $vendorModel = new \App\Models\VendorModel($db);

    $vendorId = $input->vendorId;

    $vendor = $vendorModel->find($vendorId);
    if (!$vendor) {
        return $this->fail(['status' => false, 'message' => 'Vendor not found'], 404);
    }

    $allowedFields = [
        'vendorName', 'mobNo', 'address', 'isActive'
    ];

    $updateData = [];
    foreach ($allowedFields as $field) {
        if (isset($input->$field)) {
            $updateData[$field] = $input->$field;
        }
    }

    if (empty($updateData)) {
        return $this->fail(['status' => false, 'message' => 'No data provided to update'], 400);
    }

    $vendorModel->update($vendorId, $updateData);

    return $this->respond([
        'status' => true,
        'message' => 'Vendor Updated Successfully'
    ], 200);
}
public function deleteVendor()
{
    $input = $this->request->getJSON(true);

    if (empty($input['vendorId']) || !is_numeric($input['vendorId'])) {
        return $this->fail([
            'status' => false,
            'message' => 'vendorId is required and must be numeric'
        ], 409);
    }

    try {
        $db = \Config\Database::connect();
        $db->setDatabase('eschool_db_tenant');

        $vendorModel = new \App\Models\VendorModel($db);

        $vendorId = $input['vendorId'];

        $vendor = $vendorModel->find($vendorId);
        if (!$vendor) {
            return $this->fail(['status' => false, 'message' => 'Vendor not found'], 404);
        }

        // Soft Delete
        $vendorModel->update($vendorId, ['isDeleted' => 1]);

        return $this->respond([
            'status' => true,
            'message' => 'Vendor Deleted Successfully'
        ], 200);

    } catch (\Throwable $e) {
        return $this->fail([
            'status'  => false,
            'message' => 'Server Error',
            'error'   => $e->getMessage()
        ], 500);
    }
}

public function getRoutesPaging()
{
    $input = $this->request->getJSON();

    $page    = $input->page ?? 1;
    $perPage = $input->perPage ?? 10;

    $sortField = $input->sortField ?? 'routeId';
    $sortOrder = $input->sortOrder ?? 'asc';

    $search = $input->search ?? '';
    $filter = $input->filter;

    $db = \Config\Database::connect();
    $db->query("USE eschool_db_tenant");

    $routeModel = new \App\Models\RouteModel($db);
    $query = $routeModel;

    // SEARCH (route name)
    if (!empty($search)) {
        $query->like('routeName', $search);
    }

    // FILTERS
    if (!empty($filter)) {
        $filter = json_decode(json_encode($filter), true);

        if (!empty($filter['routeName'])) {
            $query->like('routeName', $filter['routeName']);
        }
    }

    // Exclude deleted
    $query->where('isDeleted', 0);

    // SORTING
    if (!empty($sortField)) {
        $query->orderBy($sortField, $sortOrder);
    }

    $routes = $query->paginate($perPage, 'default', $page);
    $pager = $routeModel->pager;

    return $this->respond([
        "status" => true,
        "message" => "Route list fetched",
        "data" => $routes,
        "pagination" => [
            "currentPage" => $pager->getCurrentPage(),
            "totalPages" => $pager->getPageCount(),
            "totalItems" => $pager->getTotal(),
            "perPage" => $perPage
        ]
    ], 200);
}
public function createRoute()
{
    try {
        $input = (array) $this->request->getJSON(true);

        $rules = [
            'routeName' => 'required',
         
        ];

        if (!$this->validate($rules)) {
            return $this->fail([
                'status' => false,
                'message' => 'Invalid Inputs',
                'errors' => $this->validator->getErrors()
            ], 409);
        }

        $db = \Config\Database::connect();
        $db->setDatabase('eschool_db_tenant');

        $routeModel = new \App\Models\RouteModel($db);

        $input['isActive'] = 1;
        $input['isDeleted'] = 0;
        $input['createdDate'] = date('Y-m-d H:i:s');

        if (!$routeModel->insert($input)) {
            return $this->fail([
                'status' => false,
                'message' => 'Insert Failed',
                'errors' => $routeModel->errors()
            ], 500);
        }

        return $this->respond([
            'status' => true,
            'message' => 'Route Created Successfully',
            'routeId' => $routeModel->getInsertID(),
        ], 200);

    } catch (\Throwable $e) {
        return $this->fail(['status' => false, 'message' => $e->getMessage()], 500);
    }
}
public function updateRoute()
{
    $input = $this->request->getJSON();

    $rules = [
        'routeId' => 'required|numeric'
    ];

    if (!$this->validate($rules)) {
        return $this->fail([
            'status' => false,
            'message' => 'Invalid Inputs',
            'errors' => $this->validator->getErrors()
        ], 409);
    }

    $db = \Config\Database::connect();
    $db->setDatabase('eschool_db_tenant');

    $routeModel = new \App\Models\RouteModel($db);
    $routeId = $input->routeId;

    $route = $routeModel->find($routeId);
    if (!$route) {
        return $this->fail(['status' => false, 'message' => 'Route Not Found'], 404);
    }

    $allowedFields = ['routeName', 'vehicleId', 'vendorId', 'isActive'];

    $updateData = [];
    foreach ($allowedFields as $field) {
        if (isset($input->$field)) {
            $updateData[$field] = $input->$field;
        }
    }

    if (empty($updateData)) {
        return $this->fail(['status' => false, 'message' => 'No data to update'], 400);
    }

    $updateData['modifiedDate'] = date('Y-m-d H:i:s');

    $routeModel->update($routeId, $updateData);

    return $this->respond([
        'status' => true,
        'message' => 'Route Updated Successfully'
    ], 200);
}
public function deleteRoute()
{
    $input = $this->request->getJSON(true);

    if (empty($input['routeId']) || !is_numeric($input['routeId'])) {
        return $this->fail(['status' => false, 'message' => 'routeId required'], 409);
    }

    $db = \Config\Database::connect();
    $db->setDatabase('eschool_db_tenant');

    $routeModel = new \App\Models\RouteModel($db);
    $routeId = $input['routeId'];

    $route = $routeModel->find($routeId);
    if (!$route) {
        return $this->fail(['status' => false, 'message' => 'Route not found'], 404);
    }

    // Soft delete
    $routeModel->update($routeId, ['isDeleted' => 1]);

    return $this->respond([
        'status' => true,
        'message' => 'Route Deleted Successfully'
    ], 200);
}

public function getStopagesPaging()
{
    $input = $this->request->getJSON();

    $page    = $input->page ?? 1;
    $perPage = $input->perPage ?? 10;

    $sortField = $input->sortField ?? 'stopageId';
    $sortOrder = $input->sortOrder ?? 'asc';

    $filter = $input->filter ?? null;
    $search = $input->search ?? '';

    $db = \Config\Database::connect();
    $db->query("USE eschool_db_tenant");

    $stopageModel = new \App\Models\StopageModel($db);
    $query = $stopageModel;

    // SEARCH
    if (!empty($search)) {
        $query->like('stoppageName', $search);
    }

    // FILTERS
    if (!empty($filter)) {
        $filter = json_decode(json_encode($filter), true);

        if (!empty($filter['stoppageName'])) {
            $query->like('stoppageName', $filter['stoppageName']);
        }

        if (!empty($filter['routeName'])) {
            $query->like('routeName', $filter['routeName']);
        }
    }

    // Exclude deleted
    $query->where('isDeleted', 0);

    // SORT
    $query->orderBy($sortField, $sortOrder);

    $data = $query->paginate($perPage, 'default', $page);
    $pager = $stopageModel->pager;

    return $this->respond([
        "status" => true,
        "message" => "Stoppage list fetched",
        "data" => $data,
        "pagination" => [
            "currentPage" => $pager->getCurrentPage(),
            "totalPages" => $pager->getPageCount(),
            "totalItems" => $pager->getTotal(),
            "perPage" => $perPage
        ]
    ], 200);
}
public function createStopage()
{
    try {
        $input = (array) $this->request->getJSON(true);

        $rules = [
            'stopName' => 'required',
        ];

        if (!$this->validate($rules)) {
            return $this->fail([
                'status' => false,
                'message' => 'Invalid Inputs',
                'errors' => $this->validator->getErrors()
            ], 409);
        }

        $db = \Config\Database::connect();
        $db->setDatabase('eschool_db_tenant');

        $stopageModel = new \App\Models\StopageModel($db);

        $input['isActive'] = 1;
        $input['isDeleted'] = 0;
        $input['createdDate'] = date('Y-m-d H:i:s');

        if (!$stopageModel->insert($input)) {
            return $this->fail([
                'status' => false,
                'message' => 'Insert Failed',
                'errors' => $stopageModel->errors()
            ], 500);
        }

        return $this->respond([
            'status' => true,
            'message' => 'Stoppage Created Successfully',
            'stoppageId' => $stopageModel->getInsertID(),
        ], 200);

    } catch (\Throwable $e) {
        return $this->fail(['status' => false, 'message' => $e->getMessage()], 500);
    }
}
public function updateStopage()
{
    $input = $this->request->getJSON();

    $rules = [
        'stopageId' => 'required|numeric'
    ];

    if (!$this->validate($rules)) {
        return $this->fail([
            'status' => false,
            'message' => 'Invalid Inputs',
            'errors' => $this->validator->getErrors()
        ], 409);
    }

    $db = \Config\Database::connect();
    $db->setDatabase('eschool_db_tenant');

    $stopageModel = new \App\Models\StopageModel($db);
    $stoppageId = $input->stoppageId;

    $stopage = $stopageModel->find($stoppageId);
    if (!$stopage) {
        return $this->fail(['status' => false, 'message' => 'Stoppage Not Found'], 404);
    }

    $allowedFields = [
        'stoppageName',
        'routeId',
        'pickupTime',
        'dropTime',
        'isActive'
    ];

    $updateData = [];
    foreach ($allowedFields as $field) {
        if (isset($input->$field)) {
            $updateData[$field] = $input->$field;
        }
    }

    if (empty($updateData)) {
        return $this->fail(['status' => false, 'message' => 'No data to update'], 400);
    }

    $updateData['modifiedDate'] = date('Y-m-d H:i:s');

    $stopageModel->update($stoppageId, $updateData);

    return $this->respond([
        'status' => true,
        'message' => 'Stoppage Updated Successfully'
    ], 200);
}
public function deleteStopage()
{
    $input = $this->request->getJSON(true);

    if (empty($input['stoppageId']) || !is_numeric($input['stoppageId'])) {
        return $this->fail(['status' => false, 'message' => 'stoppageId required'], 409);
    }

    $db = \Config\Database::connect();
    $db->setDatabase('eschool_db_tenant');

    $stopageModel = new \App\Models\StopageModel($db);
    $stoppageId = $input['stoppageId'];

    $stopage = $stopageModel->find($stoppageId);
    if (!$stopage) {
        return $this->fail(['status' => false, 'message' => 'Stoppage not found'], 404);
    }

    // Soft delete
    $stopageModel->update($stoppageId, ['isDeleted' => 1]);

    return $this->respond([
        'status' => true,
        'message' => 'Stoppage Deleted Successfully'
    ], 200);
}


}


