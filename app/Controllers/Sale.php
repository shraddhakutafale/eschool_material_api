<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\SaleModel;
use Config\Database;

class Sale extends BaseController
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
        $saleModel = new SaleModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $saleModel->findAll()], 200);
    }

    public function getSalesPaging()
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
        $saleModel = new SaleModel($db);
        $sales = $saleModel->orderBy('createdDate', 'DESC')->paginate($perPage, 'default', $page);
        $pager = $saleModel->pager;

        $response = [
            "status" => true,
            "message" => "All Data Fetched",
            "data" => $sales,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]   
        ];
        return $this->respond($response, 200);
    }

    public function getSalesWebsite()
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
        $saleModel = new SaleModel($db);
        $sales = $saleModel->orderBy('createdDate', 'DESC')->where('isActive', 1)->where('isDeleted', 0)->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $sales], 200);
    }

    public function create()
    {
        $input = $this->request->getJSON();
        $rules = [

            'saleName' => ['rules' => 'required'],
            'saleDesc' => ['rules' => 'required'],
            'saleQuantity' => ['rules' => 'required'],
            'salePrice' => ['rules' => 'required'],


            
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
            $model = new SaleModel($db);
        
            $model->insert($input);
             
            return $this->respond(['status'=>true,'message' => 'Sale Added Successfully'], 200);
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
            'saleId' => ['rules' => 'required|numeric'], // Ensure courseId is provided and is numeric
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
            $model = new SaleModel($db);

            // Retrieve the course by courseId
            $saleId = $input->saleId;
            $sale = $model->find($saleId); // Assuming find method retrieves the course

            if (!$sale) {
                return $this->fail(['status' => false, 'message' => 'Sale not found'], 404);
            }

            // Prepare the data to be updated (exclude courseId if it's included)
            $updateData = [

                'saleName' => $input->	saleName,
                'saleDesc' => $input->	saleDesc,
                'saleQuantity' => $input->saleQuantity,

                'salePrice' => $input->salePrice,
            ];

            // Update the course with new data
            $updated = $model->update($saleId, $updateData);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => ' Sale Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update sale'], 500);
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
        
        $rules = [
            'saleId' => ['rules' => 'required'],
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
            $model = new SaleModel($db);

            // Retrieve the course by courseId
            $saleId = $input->saleId;
            $sale = $model->find($saleId); // Assuming find method retrieves the course

            if (!$sale) {
                return $this->fail(['status' => false, 'message' => 'Sale not found'], 404);
            }

            // Proceed to delete the course
            $deleted = $model->delete($saleId);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Sale Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete sale'], 500);
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
        $saleId = $this->request->getPost('saleId'); // Example field

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

        $model = new SaleModel();
        $model->update($saleId,$data);

        return $this->respond([
            'status' => 201,
            'message' => 'File and data uploaded successfully',
            'data' => $data,
        ]);
    }


    public function getSaleById($saleId)
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

    // Load the CourseModel with the tenant database connection
    $saleModel = new SaleModel($db);

    // Fetch course by ID
    $sale = $saleModel->find($saleId); // find method returns a single record by its ID

    // Check if course was found
    if (!$sale) {
        throw new \Exception('Sale not found.');
    }

    // Respond with the course data
    return $this->respond(["status" => true, "message" => "Sale fetched successfully", "data" => $sale], 200);
}

}
