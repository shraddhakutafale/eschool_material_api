<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\CustomerModel;
use Config\Database;

class Customer extends BaseController
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
        // Load UserModel with the tenant database connection
        $CustomerModel = new CustomerModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $CustomerModel->findAll()], 200);
    }

    public function getCustomersPaging()
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
        // Load UserModel with the tenant database connection
        $CustomerModel = new CustomerModel($db);
        $customer = $CustomerModel->orderBy('createdDate', 'DESC')->paginate($perPage, 'default', $page);
        $pager = $CustomerModel->pager;

        $response = [
            "status" => true,
            "message" => "All Data Fetched",
            "data" => $customer,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]   
        ];
        return $this->respond($response, 200);
    }

    public function getCustomersWebsite()
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
        // Load UserModel with the tenant database connection
        $CustomerModel = new CustomerModel($db);
        $customer = $CustomerModel->orderBy('createdDate', 'DESC')->where('isActive', 1)->where('isDeleted', 0)->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $customer], 200);
    }

    public function create()
    {
        $input = $this->request->getJSON();
        $rules = [
            'name' => ['rules' => 'required'],
            'mobileNo' => ['rules' => 'required'],
            'alternateMobileNo' => ['rules' => 'required'],
            'emailId' => ['rules' => 'required'],
            'websiteUrl' => ['rules' => 'required'],
            'landlineNo' => ['rules' => 'required'],
            'businessAddress' => ['rules' => 'required'],
            'permanantAddress' =>['rules' => 'required'], 
            'businessPincode' =>['rules' => 'required'], 
            'permanantPincode' =>['rules' => 'required'],

    
        ];
  
        if($this->validate($rules)){
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
            $model = new CustomerModel($db);
        
            $model->insert($input);
             
            return $this->respond(['status'=>true,'message' => 'Customer Added Successfully'], 200);
        }else{
            $response = [
                'status'=>false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ];
            return $this->fail($response , 409);
             
        }
            
    }

    public function update()
    {
        $input = $this->request->getJSON();
        
        // Validation rules for the customer
        $rules = [
            'customerId' => ['rules' => 'required|numeric'], // Ensure customerId is provided and is numeric
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
            $model = new CustomerModel($db);

            // Retrieve the customer by customerId
            $customerId = $input->customerId;
            $customer = $model->find($customerId); // Assuming find method retrieves the customer

            if (!$customer) {
                return $this->fail(['status' => false, 'message' => 'Customer not found'], 404);
            }

            // Prepare the data to be updated (exclude customerId if it's included)
            $updateData = [
            'name' =>$input->name,
            'mobileNo' => $input->mobileNo,
            'alternateMobileNo' => $input->alternateMobileNo,
            'emailId' => $input->emailId,
            'websiteUrl' => $input->websiteUrl,
            'landlineNo' => $input->landlineNo,
            'businessAddress' => $input->businessAddress,
            'permanantAddress' => $input->permanantAddress,
            'businessPincode' => $input->businessPincode,
            'permanantPincode' => $input->permanantPincode,






            ];

            // Update the customer with new data
            $updated = $model->update($customerId, $updateData);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Customer Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update customer'], 500);
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
        
        // Validation rules for the customer
        $rules = [
            'customerId' => ['rules' => 'required'], // Ensure customerId is provided and is numeric
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
            $model = new CustomerModel($db);

            // Retrieve the customer by customerId
            $customerId = $input->customerId;
            $customer = $model->find($customerId); // Assuming find method retrieves the customer

            if (!$customer) {
                return $this->fail(['status' => false, 'message' => 'Customer not found'], 404);
            }

            // Proceed to delete the customer
            $deleted = $model->delete($customerId);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Customer Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete customer'], 500);
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
        $customerId = $this->request->getPost('customerId'); // Example field

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

        $model = new CustomerModel();
        $model->update($customerId,$data);

        return $this->respond([
            'status' => 201,
            'message' => 'File and data uploaded successfully',
            'data' => $data,
        ]);
    }
}
