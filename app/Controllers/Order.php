<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\OrderModel;
use App\Models\OrderDetailModel;
use App\Libraries\TenantService;

use Config\Database;

class Order extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        $tenantService = new TenantService();

        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

        // Load UserModel with the tenant database connection
        $OrderModel = new OrderModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $OrderModel->findAll()], 200);
    }

    public function getOrdersPaging()
    {
        $input = $this->request->getJSON();

        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'orderId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
        

        $tenantService = new TenantService();
        
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load OrderModel with the tenant database connection
        $orderModel = new OrderModel($db);

        $order = $orderModel->orderBy($sortField, $sortOrder)
            ->like('orderCode', $search)->orLike('orderDate', $search)->paginate($perPage, 'default', $page);
        if ($filter) {
            $filter = json_decode(json_encode($filter), true);
            $order = $orderModel->where($filter)->paginate($perPage, 'default', $page);   
        }
        $pager = $orderModel->pager;

        $response = [
            "status" => true,
            "message" => "All Order Data Fetched",
            "data" => $order,
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
       
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load UserModel with the tenant database connection
        $OrderModel = new OrderModel($db);
        $Order = $OrderModel->orderBy('createdDate', 'DESC')->where('isActive', 1)->where('isDeleted', 0)->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $OrderModel], 200);
    }

    // public function create()
    // {
    //     $input = $this->request->getJSON();
    //     $rules = [
    //          // 'customerName' => ['rules' => 'required'],
    //         // 'contactNumber' => ['rules' => 'required'],
    //         // 'deliveryDate' => ['rules' => 'required'],
    //         // 'shipToStreetAddress' => ['rules' => 'required'],
    //         // 'shipToPhone' => ['rules' => 'required'],
    //         // 'shipToCity' => ['rules' => 'required'],
    //         // 'pincode' => ['rules' => 'required'], // Validate the array field
    //         // 'sku' => ['rules' => 'required'],
    //         // 'productName' => ['rules' => 'required'],
    //         // 'email' => ['rules' => 'required'],                   
    //         'orderCode' => ['rules' => 'required'],
    //         'orderDate' => ['rules' => 'required'],
    //         'businessNameFrom' => ['rules' => 'required'],
    //         'phoneFrom' => ['rules' => 'required'],
    //         'addressFrom' => ['rules' => 'required'],
    //         'emailFrom'=> ['rules' => 'required'],
    //         'PanFrom'=> ['rules' => 'required'],
    //         'businessNameFor'=> ['rules' => 'required'],
    //         'phoneFor' => ['rules' => 'required'],
    //         'addressFor' => ['rules' => 'required'],
    //         'emailFor'=> ['rules' => 'required'],
    //         'PanCardFor'=> ['rules' => 'required'],                 


    //     ];

    //     if($this->validate($rules)){
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
        
    //         $model->insert($input);
            
    //         return $this->respond(['status'=>true,'message' => 'order Added Successfully'], 200);
    //     }else{
    //         $response = [
    //             'status'=>false,
    //             'errors' => $this->validator->getErrors(),
    //             'message' => 'Invalid Inputs'
    //         ];
    //         return $this->fail($response , 409);
            
    //     }
            
    // }

    public function create()
{
    $input = $this->request->getJSON();

    // Validation rules for order
    $rules = [
        'orderCode' => ['rules' => 'required'],
        'orderDate' => ['rules' => 'required'],
        'businessNameFrom' => ['rules' => 'required'],
        'phoneFrom' => ['rules' => 'required'],
        'addressFrom' => ['rules' => 'required'],
        'emailFrom' => ['rules' => 'required'],
        'PanFrom' => ['rules' => 'required'],
        'businessNameFor' => ['rules' => 'required'],
        'phoneFor' => ['rules' => 'required'],
        'addressFor' => ['rules' => 'required'],
        'emailFor' => ['rules' => 'required'],
        'PanCardFor' => ['rules' => 'required'],
    ];

    // Validate form data
    if ($this->validate($rules)) {
       
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new OrderModel($db);

        // Insert the order into the 'order' table
        $orderData = [
            'orderCode' => $input->orderCode,
            'orderDate' => $input->orderDate,
            'businessNameFrom' => $input->businessNameFrom,
            'phoneFrom' => $input->phoneFrom,
            'addressFrom' => $input->addressFrom,
            'emailFrom' => $input->emailFrom,
            'PanFrom' => $input->PanFrom,
            'businessNameFor' => $input->businessNameFor,
            'phoneFor' => $input->phoneFor,
            'addressFor' => $input->addressFor,
            'emailFor' => $input->emailFor,
            'PanCardFor' => $input->PanCardFor,
        ];

        // Insert the order and retrieve the generated orderId
        $orderId= $model->insert($orderData);

        if ($orderId) {
            // Now insert the items into the order_details table using the orderId
            $orderDetailsModel = new OrderDetailModel($db); // Assuming you have this model for order details

            // Iterate through each item in the input and insert into order_details
            foreach ($input->items as $item) {
                $orderDetailsData = [
                    'orderId' => $orderId,  // Foreign key linking to the order
                    'item' => $item->item,
                    'rate' => $item->rate,
                    'quantity' => $item->quantity,
                    'amount' => $item->amount,
                ];

                // Insert the item into the order_details table
                $orderDetailsModel->insert($orderDetailsData);
            }

            return $this->respond(['status' => true, 'message' => 'Order and items added successfully'], 200);
        } else {
            return $this->respond(['status' => false, 'message' => 'Failed to create the order'], 500);
        }
    } else {
        // Return validation errors
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
            'orderId' => ['rules' => 'required|numeric'], // Ensure customerId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
           
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
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
           
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
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
       
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new OrderModel($db);

        // Retrieve the last order
        $lastOrder = $model->orderBy('createdDate', 'DESC')->first();

        if (!$lastOrder) {
            return $this->respond(['status' => false, 'message' => 'No orders found', 'data' => null], 200);
        }

        return $this->respond(['status' => true, 'message' => 'Last Order Fetched Successfully', 'data' => $lastOrder], 200);
    }
}
