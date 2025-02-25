<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\StaffModel;
use Config\Database;

class Staff extends BaseController
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
        $staffModel = new StaffModel($db);

        return $this->respond(["status" => true, "message" => "All Staff Data Fetched", "data" => $staffModel->findAll()], 200);
    }

    public function getStaffPaging()
    {
        $input = $this->request->getJSON();

        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        // Define the number of staffs per page
        $perPage = isset($input->perPage) ? $input->perPage : 10;

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
        $staffModel = new StaffModel($db);

        $staff = $staffModel->orderBy('createdDate', 'DESC')->paginate($perPage, 'default', $page);
        $pager = $staffModel->pager;

        $response = [
            "status" => true,
            "message" => "All Staff Data Fetched",
            "data" => $staff,
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
            'empName' => ['rules' => 'required'],
            'empCode' => ['rules' => 'required'],
            'empSal' => ['rules' => 'required|numeric']
        ];
    
        if ($this->validate($rules)) {
            // Retrieve tenantConfig from the headers (for multi-tenancy support)
            $tenantConfigHeader = $this->request->getHeaderLine('X-Tenant-Config');
            if (!$tenantConfigHeader) {
                throw new \Exception('Tenant configuration not found.');
            }
    
            // Decode the tenantConfig JSON
            $tenantConfig = json_decode($tenantConfigHeader, true);
    
            if (!$tenantConfig) {
                throw new \Exception('Invalid tenant configuration.');
            }
    
            // Handle image upload for the cover image
            $coverImage = $this->request->getFile('coverImage');
            $coverImageName = null;
    
            if ($coverImage && $coverImage->isValid() && !$coverImage->hasMoved()) {
                // Define the upload path for the cover image
                $coverImagePath = FCPATH . 'uploads/staffImages/';
                if (!is_dir($coverImagePath)) {
                    mkdir($coverImagePath, 0777, true); // Create directory if it doesn't exist
                }
    
                // Move the file to the desired directory with a unique name
                $coverImageName = $coverImage->getRandomName();
                $coverImage->move($coverImagePath, $coverImageName);
    
                // Get the URL of the uploaded cover image and remove the 'uploads/coverImages/' prefix
                $coverImageUrl = 'uploads/staffImages/' . $coverImageName;
                $coverImageUrl = str_replace('uploads/staffImages/', '', $coverImageUrl);
    
                // Add the cover image URL to the input data
                $input['coverImage'] = $coverImageUrl; 
            }
    
           
    
            // Insert the product data into the database
            $db = Database::connect($tenantConfig);
            $model = new StaffModel($db);
            $model->insert($input);
    
            return $this->respond(['status' => true, 'message' => 'Staff Added Successfully'], 200);
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
        $input = $this->request->getJSON();

        // Validation rules for the staff
        $rules = [
            'staffId' => ['rules' => 'required|numeric'], // Ensure staffId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
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
            $model = new StaffModel($db);

            // Retrieve the staff by staffId
            $staffId = $input->staffId;
            $staff = $model->find($staffId);

            if (!$staff) {
                return $this->fail(['status' => false, 'message' => 'Staff not found'], 404);
            }

            // Prepare the data to be updated (exclude staffId if it's included)
            $updateData = [
                'empName' => $input->empName,
                'empCategory' => $input->empCategory,
                'empCode' => $input->empCode,
                'aadharNumber' => $input->aadharNumber,
                'panNumber' => $input->panNumber,
                'uanNumber' => $input->uanNumber,
                'ipNumber' => $input->ipNumber,
                'fatherName' => $input->fatherName,
                'empSal' => $input->empSal,
                'empDoj' => $input->empDoj,
                'empDol' => $input->empDol
               
            ];

            // Update the staff with new data
            $updated = $model->update($staffId, $updateData);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Staff Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update staff'], 500);
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
        
        // Validation rules for the staff
        $rules = [
            'staffId' => ['rules' => 'required'], // Ensure staffId is provided and is numeric
        ];
    
        // Validate the input
        if ($this->validate($rules)) {
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
            $model = new StaffModel($db);
    
            // Retrieve the staff by staffId
            $staffId = $input->staffId;
            $staff = $model->find($staffId); // Assuming find method retrieves the staff
    
            if (!$staff) {
                return $this->fail(['status' => false, 'message' => 'Staff not found'], 404);
            }
    
            // Proceed to delete the staff
            // Soft delete by marking 'isDeleted' as 1
            $updateData = [
                'isDeleted' => 1,
            ];
            $deleted = $model->update($staffId, $updateData);
    
            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Staff Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete staff'], 500);
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
