<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\CustomerModel;
use Config\Database;
use App\Libraries\TenantService;

class Customer extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load UserModel with the tenant database connection
        $CustomerModel = new CustomerModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $CustomerModel->findAll()], 200);
    }

    public function getCustomersPaging()
   
    {
        $input = $this->request->getJSON();

        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'customerId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
        

        $tenantService = new TenantService();
        
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load StaffModel with the tenant database connection
        $customerModel = new CustomerModel($db);

        $customer = $customerModel->orderBy($sortField, $sortOrder)->paginate($perPage, 'default', $page);
        if ($filter) {
            $filter = json_decode(json_encode($filter), true);
            $customer = $customerModel->like($filter)->paginate($perPage, 'default', $page);   
        }
        $pager = $customerModel->pager;

        $response = [
            "status" => true,
            "message" => "All Customer Data Fetched",
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
        // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        
        // Load UserModel with the tenant database connection
        $CustomerModel = new CustomerModel($db);
        $customer = $CustomerModel->orderBy('createdDate', 'DESC')->where('isActive', 1)->where('isDeleted', 0)->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $customer], 200);
    }

    public function create()
    {
        $input = $this->request->getPost();
        $rules = [
            'name' => ['rules' => 'required'],
            'mobileNo' => ['rules' => 'required']
        ];
  
        if($this->validate($rules)){
           // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        
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
           // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        
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
                'customerCode' =>$input->customerCode,
                'mobileNo' => $input->mobileNo,
                'alternateMobileNo' => $input->alternateMobileNo,
                'emailId' => $input->emailId,
                'dateOfBirth' => $input->dateOfBirth,
                'gender' => $input->gender
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

    // public function delete()
    // {
    //     $input = $this->request->getJSON();

    //     // Validation rules for the lead
    //     $rules = [
    //         'customerId' => ['rules' => 'required'], // Ensure leadId is provided and is numeric
    //     ];

    //     // Validate the input
    //     if ($this->validate($rules)) {
    //             // Insert the product data into the database
    //     $tenantService = new TenantService();
    //     // Connect to the tenant's database
    //     $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));   $model = new CustomerModel($db);

    //         // Retrieve the lead by leadId
    //         $customerId = $input->customerId;
    //         $customer = $model->find($customerId); // Assuming find method retrieves the lead

    //         if (!$customer) {
    //             return $this->fail(['status' => false, 'message' => 'Lead not found'], 404);
    //         }

    //         // Proceed to delete the lead
    //         $deleted = $model->delete($customerId);

    //         if ($deleted) {
    //             return $this->respond(['status' => true, 'message' => 'Customer Deleted Successfully'], 200);
    //         } else {
    //             return $this->fail(['status' => false, 'message' => 'Failed to delete customer'], 500);
    //         }
    //     } else {
    //         // Validation failed
    //         $response = [
    //             'status' => false,
    //             'errors' => $this->validator->getErrors(),
    //             'message' => 'Invalid Inputs'
    //         ];
    //         return $this->fail($response, 409);
    //     }
    // }

    public function delete()
{
    $input = $this->request->getJSON();

    // Validation rules for the customer
    $rules = [
        'customerId' => ['rules' => 'required'], // Ensure customerId is provided
    ];

    // Validate the input
    if ($this->validate($rules)) {
        // Connect to the tenant's database
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));   
        $model = new CustomerModel($db);

        // Retrieve the customer by customerId
        $customerId = $input->customerId;
        $customer = $model->where('customerId', $customerId)->where('isDeleted', 0)->first(); // Only find active customers

        if (!$customer) {
            return $this->fail(['status' => false, 'message' => 'Customer not found or already deleted'], 404);
        }

        // Perform a soft delete (mark as deleted instead of removing the record)
        $updateData = [
            'isDeleted' => 1,
        ];
        $deleted = $model->update($customerId, $updateData);
        

        if ($deleted) {
            return $this->respond(['status' => true, 'message' => 'Customer marked as deleted'], 200);
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
