<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\PurchaseModel;
use Config\Database;

class Purchase extends BaseController
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
        $PurchaseModel = new PurchaseModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $PurchaseModel->findAll()], 200);
    }

    public function getPurchasesPaging()
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
        $PurchaseModel = new PurchaseModel($db);
        $purchase = $PurchaseModel->orderBy('createdDate', 'DESC')->paginate($perPage, 'default', $page);
        $pager = $PurchaseModel->pager;

        $response = [
            "status" => true,
            "message" => "All Data Fetched",
            "data" => $purchase,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]   
        ];
        return $this->respond($response, 200);
    }

    public function getPurchasesWebsite()
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
        $PurchaseModel = new PurchaseModel($db);
        $purchase = $PurchaseModel->orderBy('createdDate', 'DESC')->where('isActive', 1)->where('isDeleted', 0)->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $purchase], 200);
    }



    

    // public function create()
    // {
    //     $input = $this->request->getJSON();
    //     $rules = [
    //         'companyName' => ['rules' => 'required'],
    //         'address' => ['rules' => 'required'],
    //         'phone' => ['rules' => 'required'],
    //         'fax' => ['rules' => 'required'],
    //         'website' => ['rules' => 'required'],
    //         'city' => ['rules' => 'required'],
    //     ];
    
    //     if ($this->validate($rules)) {
    //         // Ensure all input is properly converted to strings/arrays as needed
    //         $inputData = [
    //             'companyName' => (string) $input->companyName,
    //             'address' => (string) $input->address,
    //             'phone' => (string) $input->phone,
    //             'fax' => (string) $input->fax,
    //             'website' => (string) $input->website,
    //             'city' => (string) $input->city,
    //             // Convert any complex object data to a suitable format (strings, arrays, etc.)
    //             // Add additional fields here similarly
    //         ];
    
    //         // Retrieve tenantConfig from the headers
    //         $tenantConfigHeader = $this->request->getHeaderLine('X-Tenant-Config');
    //         if (!$tenantConfigHeader) {
    //             throw new \Exception('Tenant configuration not found.');
    //         }
    
    //         // Decode the tenantConfig JSON
    //         $tenantConfig = json_decode($tenantConfigHeader, true);
    
    //         if (!$tenantConfig) {
    //             throw new \Exception('Invalid tenant configuration.');
    //         }
    
    //         // Connect to the tenant's database
    //         $db = Database::connect($tenantConfig);
    //         $model = new PurchaseModel($db);
    
    //         // Insert the data into the database
    //         if ($model->insert($inputData)) {
    //             return $this->respond(['status' => true, 'message' => 'Purchase Added Successfully'], 200);
    //         } else {
    //             return $this->fail(['status' => false, 'message' => 'Failed to insert purchase data'], 500);
    //         }
    //     } else {
    //         $response = [
    //             'status' => false,
    //             'errors' => $this->validator->getErrors(),
    //             'message' => 'Invalid Inputs'
    //         ];
    //         return $this->fail($response, 409);
    //     }
    // }
    

    public function create()
{
    $input = $this->request->getJSON();

    $rules = [
        'companyName' => ['rules' => 'required'],
        'address' => ['rules' => 'required'],
        'phone' => ['rules' => 'required'],
        'fax' => ['rules' => 'required'],
        'website' => ['rules' => 'required'],
        'city' => ['rules' => 'required'],
        'items' => ['rules' => 'required'], // Validate the array field
    ];

    if ($this->validate($rules)) {
        // Ensure all input is properly converted to strings/arrays as needed
        $inputData = [
            'companyName' => (string) $input->companyName,
            'address' => (string) $input->address,
            'phone' => (string) $input->phone,
            'fax' => (string) $input->fax,
            'website' => (string) $input->website,
            'city' => (string) $input->city,
            'items' => json_encode($input->items), // Encode the array as JSON
        ];

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
        $model = new PurchaseModel($db);

        // Insert the data into the database
        if ($model->insert($inputData)) {
            return $this->respond(['status' => true, 'message' => 'Purchase Added Successfully'], 200);
        } else {
            return $this->fail(['status' => false, 'message' => 'Failed to insert purchase data'], 500);
        }
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
        
        // Validation rules for the customer
        $rules = [
            'purchaseId' => ['rules' => 'required|numeric'], // Ensure customerId is provided and is numeric
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
            $model = new PurchaseModel($db);

            // Retrieve the customer by customerId
            $purchaseId = $input->purchaseId;
            $purchase = $model->find($purchaseId); // Assuming find method retrieves the customer

            if (!$purchase) {
                return $this->fail(['status' => false, 'message' => 'Purchase not found'], 404);
            }

            // Prepare the data to be updated (exclude customerId if it's included)
            $updateData = [
            'companyName' =>$input->companyName,
            'address' => $input->address,
            'phone' => $input->phone,
            'fax' => $input->fax,
            'website' => $input->website,
            'city' => $input->city,
            // 'businessAddress' => $input->businessAddress,
            // 'permanantAddress' => $input->permanantAddress,
            // 'businessPincode' => $input->businessPincode,
            // 'permanantPincode' => $input->permanantPincode,






            ];

            // Update the customer with new data
            $updated = $model->update($purchaseId, $updateData);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Purchase Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update purchase'], 500);
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
            'purchaseId' => ['rules' => 'required'], // Ensure customerId is provided and is numeric
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
            $model = new PurchaseModel($db);

            // Retrieve the customer by customerId
            $purchaseId = $input->purchaseId;
            $purchase = $model->find($purchaseId); // Assuming find method retrieves the customer

            if (!$purchase) {
                return $this->fail(['status' => false, 'message' => 'Purchase not found'], 404);
            }

            // Proceed to delete the customer
            $deleted = $model->delete($purchaseId);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Purchase Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete purchase'], 500);
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
        $purchaseId = $this->request->getPost('purchaseId'); // Example field

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

        $model = new PurchaseModel();
        $model->update($purchaseId,$data);

        return $this->respond([
            'status' => 201,
            'message' => 'File and data uploaded successfully',
            'data' => $data,
        ]);
    }
}
