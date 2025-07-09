<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\BrandModel;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;
use Config\Database;

class Brand extends BaseController
{
    use ResponseTrait;

    public function index()
    {
       // Insert the product data into the database
       $tenantService = new TenantService();
       // Connect to the tenant's database
       $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); // Load UserModel with the tenant database connection
        $BrandModel = new BrandModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $BrandModel->findAll()], 200);
    }

    public function getBrandsPaging()
    {
        $input = $this->request->getJSON();
    
        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'brandId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
    
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load EventModel with the tenant database connection
        $BrandModel = new BrandModel($db);
    
        $query = $BrandModel;
    
        if (!empty($filter)) {
            $filter = json_decode(json_encode($filter), true);
    
            foreach ($filter as $key => $value) {
                if (in_array($key, ['brandName'])) {
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
        $brands = $query->paginate($perPage, 'default', $page);
        $pager = $BrandModel->pager;
    
        $response = [
            "status" => true,
            "message" => "All Brand Data Fetched",
            "data" => $brands,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]
        ];
    
        return $this->respond($response, 200);
    }

    public function getBrandsWebsite()
    {// Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load UserModel with the tenant database connection
        $BrandModel = new BrandModel($db);
        $brands = $BrandModel->orderBy('createdDate', 'DESC')->where('isActive', 1)->where('isDeleted', 0)->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $brands], 200);
    }

    public function create()
    {
        // Retrieve the input data from the request
        $input = $this->request->getJSON();

        // Define validation rules for required fields
        $rules = [
            'brandName' => ['rules' => 'required'],
        ];

        if ($this->validate($rules)) {
            // Insert the product data into the database
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new BrandModel($db);
            $brandModel = $model->insert($input);
            return $this->respond(["status" => true, "message" => "Brand Added Successfully", "data" => $brandModel], 200);
        }else{
            $response = [
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ];
            return $this->fail($response, 409);
        }
    }
    
    public function updateItemGroup()
    {
        $input = $this->request->getPost();
    
        // Validation rules for the item
        $rules = [
            'brandId ' => ['rules' => 'required|numeric'], // Ensure itemId is provided and is numeric
        ];
    
        // Validate the input
        if ($this->validate($rules)) {
            // Insert the product data into the database
            $tenantService = new TenantService();
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    
            $model = new BrandModel($db);
    
            // Retrieve the item by itemId
            $brandId = $input['brandId'];
            $item = $model->find($brandId);
    
            if (!$item) {
                return $this->fail(['status' => false, 'message' => 'Grouo not found'], 404);
            }
    
            // Prepare the data to be updated
            $updateData = [
                'brandName'=> $input['brandName'],	
                	
            ];
    
            // Update the item with new data
            $updated = $model->update($brandId, $updateData);
    
            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Brand Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update Brand'], 500);
            }
        } else {
            // Validation failed
            $response = [
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ];
            return $this->fail($response, 409);
        }
    }


    public function delete()
    {
        $input = $this->request->getJSON();
        

        $rules = [
            'brandId' => ['rules' => 'required'], // Ensure vendorId is provided
        ];
    

        // Validate the input
        if ($this->validate($rules)) {
            $tenantService = new TenantService();
                // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new BrandModel($db);
    
            // Retrieve the vendor by vendorId
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));   $model = new BrandModel($db);

            // Retrieve the lead by leadId
            $brandId = $input->brandId;
            $brand = $model->find($brandId); // Assuming the find method retrieves the vendor
    
            
            $brand = $model->find($brandId); // Assuming find method retrieves the lead

            if (!$brand) {
                return $this->fail(['status' => false, 'message' => 'brand not found'], 404);
            }
    
            // Soft delete by marking 'isDeleted' as 1
            $updateData = [
                'isDeleted' => 1,
            ];
    

            // Proceed to delete the lead
            $deleted = $model->delete($brandId);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'brand Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete brand'], 500);
            }
        } else {
            // Validation failed
            $response = [
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ];
            return $this->fail($response, 409);
        }
    }
    

    public function uploadPageProfile()
    {
        // Retrieve form fields
        $brandId = $this->request->getPost('brandId'); // Example field

        // Retrieve the file
        $file = $this->request->getFile('photoUrl');

        
        // Validate file
        if (!$file->isValid()) {
            return $this->fail($file->getErrorString());
        }

        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif'])) {
            return $this->fail('Invalid file type. Only JPEG, PNG, and GIF are allowed.');
        }

        // Validate file type and size
        if ($file->getSize() > 2048 * 1024) {
            return $this->fail('Invalid file type or size exceeds 2MB');
        }

        // Generate a random file name and move the file
        $newName = $file->getRandomName();
        $filePath = '/uploads/' . $newName;
        $file->move(WRITEPATH . '../public/uploads', $newName);

        // Save file and additional data in the database
        $data = [
            'photoUrl' => $newName,
        ];

        $model = new BrandModel();
        $model->update($brandId,$data);

        return $this->respond([
            'status' => 201,
            'message' => 'File and data uploaded successfully',
            'data' => $data,
        ]);
    }


    


}
