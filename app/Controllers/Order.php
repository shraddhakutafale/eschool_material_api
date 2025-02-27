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

    public function create()
    {
        $input = $this->request->getJSON();
        $rules = [
             // 'customerName' => ['rules' => 'required'],
            // 'contactNumber' => ['rules' => 'required'],
            // 'deliveryDate' => ['rules' => 'required'],
            // 'shipToStreetAddress' => ['rules' => 'required'],
            // 'shipToPhone' => ['rules' => 'required'],
            // 'shipToCity' => ['rules' => 'required'],
            // 'pincode' => ['rules' => 'required'], // Validate the array field
            // 'sku' => ['rules' => 'required'],
            // 'productName' => ['rules' => 'required'],
            // 'email' => ['rules' => 'required'],                   
            'orderCode' => ['rules' => 'required'],
            'orderDate' => ['rules' => 'required'],
            'businessNameFrom' => ['rules' => 'required'],
            'phoneFrom' => ['rules' => 'required'],
            'addressFrom' => ['rules' => 'required'],
            'emailFrom'=> ['rules' => 'required'],
            'PanFrom'=> ['rules' => 'required'],
            'businessNameFor'=> ['rules' => 'required'],
            'phoneFor' => ['rules' => 'required'],
            'addressFor' => ['rules' => 'required'],
            'emailFor'=> ['rules' => 'required'],
            'PanCardFor'=> ['rules' => 'required'],                 


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

    public function getLastOrder()
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
        $model = new OrderModel($db);

        // Retrieve the last order
        $lastOrder = $model->orderBy('createdDate', 'DESC')->first();

        if (!$lastOrder) {
            return $this->respond(['status' => false, 'message' => 'No orders found', 'data' => null], 200);
        }

        return $this->respond(['status' => true, 'message' => 'Last Order Fetched Successfully', 'data' => $lastOrder], 200);
    }
}
