<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\FormModel;
use App\Models\TransactionModel;
use App\Libraries\TenantService;
use Config\Database;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

class Form extends BaseController
{
    use ResponseTrait;
  

    public function index()
    {
        $tenantService = new TenantService();

        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load UserModel with the tenant database connection
        $formModel = new FormModel($db);
        $response = [
            "status" => true,
            "message" => "All Data Fetched",
            "data" => $formModel->findAll(),
        ];
        return $this->respond($response, 200);
    }
  
  
    public function getFormsPaging()
    {
        $input = $this->request->getJSON();

        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'formId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
        

        $tenantService = new TenantService();
        
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load leadModel with the tenant database connection
        $formModel = new FormModel($db);

        $query = $formModel;

        if (!empty($filter)) {
            $filter = json_decode(json_encode($filter), true);

            foreach ($filter as $key => $value) {
                if (in_array($key, ['formHeader'])) {
                    $query->like($key, $value); // LIKE filter for specific fields
                }  else if ($key === 'createdDate') {
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
        
        $query->where('isDeleted',0)->where('businessId', $input->businessId);
        // Apply Sorting
        if (!empty($sortField) && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
            $query->orderBy($sortField, $sortOrder);
        }

        // Get Paginated Results
        $forms = $query->paginate($perPage, 'default', $page);
        $pager = $formModel->pager;

        $response = [
            "status" => true,
            "message" => "All Survey Data Fetched",
            "data" => $forms,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]
        ];

        return $this->respond($response, 200);
    }

    public function getAllFormByBusiness()
    {
        $input = $this->request->getJSON();
        // Decode token
        $key = "Exiaa@11";
        $header = $this->request->getHeader("Authorization");
        $token = null;
        if (!empty($header) && preg_match('/Bearer\s(\S+)/', $header->getValue(), $matches)) {
            $token = $matches[1];
        }
        $decoded = null;
        if ($token) {
            try {
                $decoded = JWT::decode($token, new Key($key, 'HS256'));
            } catch (\Exception $e) {
                return $this->failUnauthorized('Invalid or expired token');
            }
        }
        $businessId = $decoded->businessId ?? $input->businessId ?? null;
        // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 

        // Load UserModel with the tenant database connection
        $FormModel = new FormModel($db);
        $forms = $FormModel->where('businessId', $businessId)->orderBy('createdDate', 'DESC')->where('active', 1)->where('isDeleted', 0)->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $forms], 200);
    }

 

    public function getFormsWebsite()
    {
        $input = $this->request->getJSON();
        // Decode token
        $key = "Exiaa@11";
        $header = $this->request->getHeader("Authorization");
        $token = null;
        if (!empty($header) && preg_match('/Bearer\s(\S+)/', $header->getValue(), $matches)) {
            $token = $matches[1];
        }
        $decoded = null;
        if ($token) {
            try {
                $decoded = JWT::decode($token, new Key($key, 'HS256'));
            } catch (\Exception $e) {
                return $this->failUnauthorized('Invalid or expired token');
            }
        }
        $businessId = $decoded->businessId ?? $input->businessId ?? null;
        // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 

        // Load UserModel with the tenant database connection
        $FormModel = new FormModel($db);
        $forms = $FormModel->orderBy('createdDate', 'DESC')->where('isActive', 1)->where('isDeleted', 0)->where('businessId', $this->request->getHeaderLine('X-Business-Id'))->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $forms], 200);
    }


    public function create()
    {
        $input = $this->request->getJSON(true); // Get JSON input as assoc array

        // Validation (formUrl not required)
        $rules = [
            'formHeader'        => ['rules' => 'required'],
            'formStructureJson' => ['rules' => 'required'],
            'formDescripton'    => ['rules' => 'required'],
            'businessId'        => ['rules' => 'required|integer'],
        ];

        if ($this->validate($rules)) {
            // Decode token
            $key = "Exiaa@11";
            $header = $this->request->getHeader("Authorization");
            $token = null;

            if (!empty($header) && preg_match('/Bearer\s(\S+)/', $header->getValue(), $matches)) {
                $token = $matches[1];
            }

            if ($token) {
                try {
                    $decoded = JWT::decode($token, new Key($key, 'HS256'));
                    $input['businessId'] = $decoded->businessId ?? $input['businessId'] ?? null;
                } catch (\Exception $e) {
                    return $this->failUnauthorized('Invalid or expired token');
                }
            }

            // Connect to tenant DB
            $tenantService = new TenantService();
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new FormModel($db);

            // Generate a unique 15-character alphanumeric formUrl
            do {
                $randomUrl = substr(bin2hex(random_bytes(10)), 0, 15); // 15 chars
            } while ($model->where('formUrl', $randomUrl)->first());

            $input['formUrl'] = $randomUrl;

            // Insert into database
            $model->insert($input);

            return $this->respond([
                'status' => true,
                'message' => 'Form Added Successfully',
                'formUrl' => $randomUrl
            ], 200);
        } else {
            return $this->fail([
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs',
            ], 409);
        }
    }

    public function addFormData()
    {
        $input = $this->request->getJSON(true); // Get JSON input as assoc array

        $rules = [
            'formId'        => ['rules' => 'required|integer'],
            'formDataJson'  => ['rules' => 'required'],
        ];

        if (!$this->validate($rules)) {
            return $this->fail([
                'status' => false,
                'message' => 'Validation Failed',
                'errors' => $this->validator->getErrors()
            ]);
        }

        // Get userId from token
        $key = "Exiaa@11";
        $header = $this->request->getHeader("Authorization");
        $token = null;

        if (!empty($header) && preg_match('/Bearer\s(\S+)/', $header->getValue(), $matches)) {
            $token = $matches[1];
        }

        $userId = null;
        if ($token) {
            try {
                $decoded = JWT::decode($token, new \Firebase\JWT\Key($key, 'HS256'));
                $userId = $decoded->userId ?? null;
            } catch (\Exception $e) {
                return $this->failUnauthorized('Invalid or expired token');
            }
        }

        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $formDataModel = new \App\Models\FormDataModel($db);

        $saveData = [
            'formId'        => $input['formId'],
            'formDataJson'  => json_encode($input['formDataJson']), // array of form field structure
            'userId'        => $userId,
            'isActive'      => 1,
            'isDeleted'     => 0,
            'createdDate'   => date('Y-m-d H:i:s')
        ];

        if ($formDataModel->insert($saveData)) {
            return $this->respond([
                'status' => true,
                'message' => 'Form structure saved successfully'
            ]);
        } else {
            return $this->failServerError('Failed to save form structure');
        }
    }

    public function delete()
    {
        $input = $this->request->getJSON();

        $rules = [
            'formId' => ['rules' => 'required|integer']
        ];

        if ($this->validate($rules)) {
            $tenantService = new TenantService();
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

            $model = new FormModel($db);

            $formId = $input->formId;
            $form = $model->find($formId);

            if (!$form) {
                return $this->fail(['status' => false, 'message' => 'Form not found'], 404);
            }

            // Soft delete by setting isDeleted = 1
            $updated = $model->update($formId, ['isDeleted' => 1]);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Form deleted successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete form'], 500);
            }
        } else {
            return $this->fail([
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ], 409);
        }
    }


}