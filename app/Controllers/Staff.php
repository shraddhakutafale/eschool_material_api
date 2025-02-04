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
        // Define the number of items per page
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
        $input = $this->request->getJSON();
        $rules = [
            'empName' => ['rules' => 'required'],
            'empCode' => ['rules' => 'required'],
            'empSal' => ['rules' => 'required|numeric']
        ];

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

            // Prepare the data to be inserted
            $data = [
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

            // Insert the data into the database
            $model->insert($data);

            return $this->respond(['status' => true, 'message' => 'Staff Added Successfully'], 200);
        } else {
            $response = [
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ];
            return $this->fail($response, 409);
        }
    }

    public function update()
    {
        $input = $this->request->getJSON();

        // Validation rules for the staff
        $rules = [
            'staff_id' => ['rules' => 'required|numeric'], // Ensure staff_id is provided and is numeric
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

            // Retrieve the staff by staff_id
            $staff_id = $input->staff_id;
            $staff = $model->find($staff_id);

            if (!$staff) {
                return $this->fail(['status' => false, 'message' => 'Staff not found'], 404);
            }

            // Prepare the data to be updated (exclude staff_id if it's included)
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
            $updated = $model->update($staff_id, $updateData);

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
            'staff_id' => ['rules' => 'required'], // Ensure staff_id is provided and is numeric
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
    
            // Retrieve the staff by staff_id
            $staff_id = $input->staff_id;
            $staff = $model->find($staff_id); // Assuming find method retrieves the staff
    
            if (!$staff) {
                return $this->fail(['status' => false, 'message' => 'Staff not found'], 404);
            }
    
            // Proceed to delete the staff
            // Soft delete by marking 'isDeleted' as 1
            $updateData = [
                'isDeleted' => 1,
            ];
            $deleted = $model->update($staff_id, $updateData);
    
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
