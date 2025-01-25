<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\StockModel;
use Config\Database;

class Stock extends BaseController
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
        $stockModel = new StockModel($db);
        return $this->respond(['stocks' => $stockModel->findAll()], 200);
    }

    public function getStocksPaging()
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
        $StockModel = new StockModel($db);
        $stocks = $StockModel->orderBy('createdDate', 'DESC')->paginate($perPage, 'default', $page);
        $pager = $StockModel->pager;

        $response = [
            "status" => true,
            "message" => "All Data Fetched",
            "data" => $stocks,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]   
        ];
        return $this->respond($response, 200);
    }

    public function getStocksWebsite()
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
        $StockModel = new StockModel($db);
        $stocks = $StockModel->orderBy('createdDate', 'DESC')->where('isActive', 1)->where('isDeleted', 0)->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $stocks], 200);
    }

  
    public function create()
    {
        $input = $this->request->getJSON();
        $rules = [
            'stockName'=> ['rules' => 'required'], 
            'stockAlias'=> ['rules' => 'required'], 
            'stockDetail'=> ['rules' => 'required'], 
            'stockQuantity'=> ['rules' => 'required'],
            
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
            $model = new StockModel($db);
        
            $model->insert($input);
             
            return $this->respond(['status'=>true,'message' => 'Stock Added Successfully'], 200);
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
        
        // Validation rules for the course
        $rules = [
            'stockId' => ['rules' => 'required|numeric'], // Ensure eventId is provided and is numeric
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
            $model = new StockModel($db);

            // Retrieve the course by eventId
            $stockId = $input->stockId;
            $stock = $model->find($stockId); // Assuming find method retrieves the course

            if (!$stock) {
                return $this->fail(['status' => false, 'message' => 'Stock not found'], 404);
            }

            // Prepare the data to be updated (exclude eventId if it's included)
            $updateData = [
            //    ' eventName'=> $input->eventName,
            //     'autherName'=>$input->autherName,
            //     'eventDesc'=> $input->eventDesc,
            //     'venue'=> $input->venue,
            //     'endDate'=>$input->endDate,
            //     'startDate'=>$input->startDate
             'stockName'=> $input->stockName,
            'stockAlias'=> $input->stockAlias,
            'stockDetail'=> $input->stockDetail,
            'stockQuantity'=> $input->stockQuantity,
            // 'costPrice'=> $input->costPrice,
            // 'gstPercentage'=> $input->gstPercentage,
            // 'barcode'=> $input->barcode,
            // 'hsnCode'=> $input->hsnCode,
            // 'minStockLevel'=> $input->minStockLevel,
            ];

            // Update the course with new data
            $updated = $model->update($stockId, $updateData);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Stock Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update stock'], 500);
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
        
        // Validation rules for the course
        $rules = [
            'stockId' => ['rules' => 'required'], // Ensure eventId is provided and is numeric
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
            $model = new StockModel($db);

            // Retrieve the course by eventId
            $stockId = $input->stockId;
            $stock = $model->find($stockId); // Assuming find method retrieves the course

            if (!$stock) {
                return $this->fail(['status' => false, 'message' => 'Stock not found'], 404);
            }

            // Proceed to delete the course
            $deleted = $model->delete($stockId);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Stock Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete stock'], 500);
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
        $stockId = $this->request->getPost('stockId'); // Example field

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

        $model = new StockModel();
        $model->update($stockId,$data);

        return $this->respond([
            'status' => 201,
            'message' => 'File and data uploaded successfully',
            'data' => $data,
        ]);
    }
}
