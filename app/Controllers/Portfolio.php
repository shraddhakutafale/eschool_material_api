<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\PortfolioModel;
use Config\Database;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;


class Portfolio extends BaseController
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
        // Load PortfolioModel with the tenant database connection
        $portfolioModel = new PortfolioModel($db);

        return $this->respond(["status" => true, "message" => "All Portfolio Data Fetched", "data" => $portfolioModel->findAll()], 200);
    }

    public function getPortfolioPaging() {
        $input = $this->request->getJSON();
    
        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'portfolioId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
        $businessId = isset($input->businessId) ? $input->businessId : 0;
    
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load portfolioModel with the tenant database connection
        $portfolioModel = new PortfolioModel($db);
    
        $query = $portfolioModel;
    
        if (!empty($filter)) {
            $filter = json_decode(json_encode($filter), true);
    
            foreach ($filter as $key => $value) {
                if (in_array($key, ['category', 'projectName', 'description'])) {
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
        $query->where('businessId', $businessId);
    
        // Apply Sorting
        if (!empty($sortField) && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
            $query->orderBy($sortField, $sortOrder);
        }
    
        // Get Paginated Results
        $portfolios = $query->paginate($perPage, 'default', $page);
        $pager = $portfolioModel->pager;
    
        $response = [
            "status" => true,
            "message" => "All Portfolio Data Fetched",
            "data" => $portfolios,
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
        // Retrieve the input data from the request
        $input = $this->request->getPost();
        
        // Define validation rules for required fields
        $rules = [
            'category' => ['rules' => 'required'],
            'projectName' => ['rules' => 'required'],
        ];
    
        if ($this->validate($rules)) {
            $key = "Exiaa@11";
            $header = $this->request->getHeader("Authorization");
            $token = null;
    
            // extract the token from the header
            if(!empty($header)) {
                if (preg_match('/Bearer\s(\S+)/', $header, $matches)) {
                    $token = $matches[1];
                }
            }
            
            $decoded = JWT::decode($token, new Key($key, 'HS256')); $key = "Exiaa@11";
            $header = $this->request->getHeader("Authorization");
            $token = null;
    
            // extract the token from the header
            if(!empty($header)) {
                if (preg_match('/Bearer\s(\S+)/', $header, $matches)) {
                    $token = $matches[1];
                }
            }
            
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
           
            // Handle image upload for the cover image
            $profilePic = $this->request->getFile('profilePic');
            $profilePicName = null;
    
            if ($profilePic && $profilePic->isValid() && !$profilePic->hasMoved()) {
                // Define the upload path for the cover image
                $profilePicPath = FCPATH . 'uploads/'. $decoded->tenantName .'/portfolioImages/';
                if (!is_dir($profilePicPath)) {
                    mkdir($profilePicPath, 0777, true); // Create directory if it doesn't exist
                }
    
                // Move the file to the desired directory with a unique name
                $profilePicName = $profilePic->getRandomName();
                $profilePic->move($profilePicPath, $profilePicName);
    
                // Get the URL of the uploaded cover image and remove the 'uploads/profilePics/' prefix
                $profilePicUrl = 'uploads/portfolioImages/' . $profilePicName;
                $profilePicUrl = str_replace('uploads/portfolioImages/', '', $profilePicUrl);
    
                // Add the cover image URL to the input data
                // $input['profilePic'] = $profilePicUrl; 
                $input['profilePic'] = $decoded->tenantName . '/portfolioImages/' .$profilePicUrl;
            }
    
           
    
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new PortfolioModel($db);
            $model->insert($input);
    
            return $this->respond(['status' => true, 'message' => 'Portfolio Added Successfully'], 200);
        } else {
            // If validation fails, return the error messages
            $response = [
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs',
            ];
            return $this->fail($response, 409);
        }
    }

    public function update()
    {
        $input = $this->request->getPost();
        
        // Validation rules for the portfolioId
        $rules = [
            'portfolioId' => ['rules' => 'required|numeric'], // Ensure portfolioId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
             
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); $model = new PortfolioModel($db);

            // Retrieve the student by studentId
            $portfolioId = $input['portfolioId'];  // Corrected here
            $portfolio = $model->find($portfolioId); // Assuming find method retrieves the student

            if (!$portfolio) {
                return $this->fail(['status' => false, 'message' => 'portfolio not found'], 404);
            }

            // Prepare the data to be updated (exclude studentId if it's included)
            $updateData = [

                'category' => $input['category'],  // Corrected here
                'projectName' => $input['projectName'],  // Corrected here
                'description' => $input['description'],  // Corrected here
               
            ];

            // Update the student with new data
            $updated = $model->update($portfolioId, $updateData);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'portfolio Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update portfolio'], 500);
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

        // Validation rules for the Portfolio
        $rules = [
            'portfolioId' => ['rules' => 'required'], // Ensure PortfolioID is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
                // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));   $model = new PortfolioModel($db);

            // Retrieve the portfolios by portfolioId
            $portfolioId = $input->portfolioId;
            $portfolio = $model->find($portfolioId); // Assuming find method retrieves the portfolio

            if (!$portfolio) {
                return $this->fail(['status' => false, 'message' => 'Portfolio not found'], 404);
            }

            // Proceed to delete the portfolios
            $deleted = $model->delete($portfolioId);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'portfolios Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete portfolios'], 500);
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
    
}
