<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use Config\Database;
use App\Models\NaacModel;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;


class Naac extends BaseController
{
    use ResponseTrait;

    // Get all leads
    public function index()
    {
           // Insert the product data into the database
           $tenantService = new TenantService();
           // Connect to the tenant's database
           $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 
        $naacModel = new NaacModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $naacModel->findAll()], 200);
    }

    public function getNaacsPaging()
    {
        $input = $this->request->getJSON();

        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'documentId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
        

        $tenantService = new TenantService();
        
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load leadModel with the tenant database connection
        $naacModel = new NaacModel($db);

        $query = $naacModel;

        if (!empty($filter)) {
            $filter = json_decode(json_encode($filter), true);

            foreach ($filter as $key => $value) {
                if (in_array($key, ['fName','lName','email', 'primaryMobileNo'])) {
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
        $naacs = $query->paginate($perPage, 'default', $page);
        $pager = $naacModel->pager;

        $response = [
            "status" => true,
            "message" => "All Lead Data Fetched",
            "data" => $naacs,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]
        ];

        return $this->respond($response, 200);
    }


public function create()
{
    $input = $this->request->getPost();

    $rules = [
    'name' => ['rules' => 'required'],
    'type' => ['rules' => 'required'],
    'file' => [
        'rules' => 'uploaded[file]|max_size[file,10240]|mime_in[file,image/jpeg,image/jpg,image/png,application/pdf]'
    ],
    ];


    if ($this->validate($rules)) {
        $tenantService = new \App\Libraries\TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

        $model = new \App\Models\NaacModel($db);

        // Handle file upload
        $file = $this->request->getFile('file');
        if ($file->isValid() && !$file->hasMoved()) {
            // Set upload path
            $uploadPath = WRITEPATH . 'uploads/naac/';
            $file->move($uploadPath); // Move the uploaded file

            // Get the file URL or path
            $fileUrl = base_url('writable/uploads/naac/' . $file->getName());

            // Add the URL to input data
            $input['url'] = $fileUrl;
        }

        // Insert the record into the database
        $model->insert($input);

        return $this->respond([
            'status' => true,
            'message' => 'Document Created Successfully',
            'url' => $fileUrl ?? ''
        ], 200);
    } else {
        return $this->fail([
            'status' => false,
            'errors' => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ], 409);
    }
}


public function delete()
{
    $input = $this->request->getJSON();

    // Validation rule
    $rules = [
        'documentId' => ['rules' => 'required|numeric'], // Assuming primary key column is `id`
    ];

    if ($this->validate($rules)) {
        $tenantService = new \App\Libraries\TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

        $model = new \App\Models\NaacModel($db);

        $documentId = $input->documentId;
        $document = $model->find($documentId);

        if (!$document) {
            return $this->fail(['status' => false, 'message' => 'Document not found'], 404);
        }

        // Optionally delete the file from the server
        if (!empty($document['url'])) {
            $filePath = WRITEPATH . 'uploads/naac/' . basename($document['url']);
            if (file_exists($filePath)) {
                unlink($filePath); // Delete the file
            }
        }

        // Delete DB record
        if ($model->delete($documentId)) {
            return $this->respond([
                'status' => true,
                'message' => 'Document deleted successfully'
            ], 200);
        } else {
            return $this->fail([
                'status' => false,
                'message' => 'Failed to delete document'
            ], 500);
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


