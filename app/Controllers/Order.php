<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\OrderModel;
use Config\Database;

class Order extends BaseController
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
        $OrderModel = new OrderModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $OrderModel->findAll()], 200);
    }

    public function getOrdersPaging()
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
        $OrderModel = new OrderModel($db);
        $Orders = $OrderModel->orderBy('createdDate', 'DESC')->paginate($perPage, 'default', $page);
        $pager = $OrderModel->pager;

        $response = [
            "status" => true,
            "message" => "All Data Fetched",
            "data" => $Orders,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]   
        ];
        return $this->respond($response, 200);
    }

    public function getOrdersWebsite()
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
        $OrderModel = new OrderModel($db);
        $Order = $OrderModel->orderBy('createdDate', 'DESC')->where('isActive', 1)->where('isDeleted', 0)->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $OrderModel], 200);
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
    

//     public function create()
// {
//     $input = $this->request->getJSON();

//     $rules = [
//         'customerName' => ['rules' => 'required'],
//         'contactNumber' => ['rules' => 'required'],
//         'deliveryDate' => ['rules' => 'required'],
//         'shipToStreetAddress' => ['rules' => 'required'],
//         'shipToPhone' => ['rules' => 'required'],
//         'shipToCity' => ['rules' => 'required'],
//         'pincode' => ['rules' => 'required'], // Validate the array field
//         'sku' => ['rules' => 'required'],
//         'productName' => ['rules' => 'required'],
//         'email' => ['rules' => 'required'],
       



//     ];

//     if ($this->validate($rules)) {
//         // Ensure all input is properly converted to strings/arrays as needed
//         $inputData = [
//             'customerName' => (string) $input->companyName,
//             'contactNumber' => (string) $input->contactNumber,
//             'deliveryDate' => (string) $input->deliveryDate,
//             'shipToStreetAddress' => (string) $input->shipToStreetAddress,
//             'shipToPhone' => (string) $input->shipToPhone,
//             'shipToCity' => (string) $input->shipToCity,
//             'pincode' => (string) $input->pincode,
//             'sku' => (string) $input->sku,
//             'productName' => (string) $input->productName,
//             'email' => (string) $input->email,

             
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
//         $model = new OrderModel($db);

//         // Insert the data into the database
//         if ($model->insert($inputData)) {
//             return $this->respond(['status' => true, 'message' => 'Order Added Successfully'], 200);
//         } else {
//             return $this->fail(['status' => false, 'message' => 'Failed to insert Order data'], 500);
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
        'customerName' => ['rules' => 'required'],
                'contactNumber' => ['rules' => 'required'],
                'deliveryDate' => ['rules' => 'required'],
                'shipToStreetAddress' => ['rules' => 'required'],
                'shipToPhone' => ['rules' => 'required'],
                'shipToCity' => ['rules' => 'required'],
                'pincode' => ['rules' => 'required'], // Validate the array field
                'sku' => ['rules' => 'required'],
                'productName' => ['rules' => 'required'],
                'email' => ['rules' => 'required'],


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
        $model = new OrderModel($db);
    
        $model->insert($input);
         
        return $this->respond(['status'=>true,'message' => 'order Added Successfully'], 200);
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
            'orderId' => ['rules' => 'required|numeric'], // Ensure customerId is provided and is numeric
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
            $model = new OrderModel($db);

            // Retrieve the customer by customerId
            $orderId = $input->orderId;
            $order = $model->find($orderId); // Assuming find method retrieves the customer

            if (!$order) {
                return $this->fail(['status' => false, 'message' => 'order not found'], 404);
            }

            // Prepare the data to be updated (exclude customerId if it's included)
            $updateData = [
            'customerName' =>$input->customerName,
            'contactNumber' => $input->contactNumber,
            'deliveryDate' => $input->deliveryDate,
            'shipToStreetAddress' => $input->shipToStreetAddress,
            'shipToPhone' => $input->shipToPhone,
            'shipToCity' => $input->shipToCity,
            'pincode' => $input->pincode,
            'sku' => $input->sku,
            'productName' => $input->productName,
            'email' => $input->email,
            
           






            ];

            // Update the customer with new data
            $updated = $model->update($orderId, $updateData);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'order Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update order'], 500);
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
            'orderId' => ['rules' => 'required'], // Ensure customerId is provided and is numeric
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
            $model = new OrderModel($db);

            // Retrieve the customer by customerId
            $orderId = $input->orderId;
            $order = $model->find($orderId); // Assuming find method retrieves the customer

            if (!$order) {
                return $this->fail(['status' => false, 'message' => 'order not found'], 404);
            }

            // Proceed to delete the customer
            $deleted = $model->delete($orderId);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'order Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete order'], 500);
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
        $orderId = $this->request->getPost('orderId'); // Example field

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

        $model = new OrderModel();
        $model->update($orderId,$data);

        return $this->respond([
            'status' => 201,
            'message' => 'File and data uploaded successfully',
            'data' => $data,
        ]);
    }
}
