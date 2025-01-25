<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\ServiceModel;
use CodeIgniter\API\ResponseTrait;
use App\Models\ItemModel;
use Config\Database;

class Service extends BaseController
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
        $serviceModel = new ServiceModel($db);
        return $this->respond(['service' => $serviceModel->findAll()], 200);
    }

    public function getServicesPaging()
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
        $ServiceModel = new ServiceModel($db);
        $services = $ServiceModel->orderBy('createdDate', 'DESC')->paginate($perPage, 'default', $page);
        $pager = $ServiceModel->pager;

        $response = [
            "status" => true,
            "message" => "All Data Fetched",
            "data" => $services,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]   
        ];
        return $this->respond($response, 200);
    }

    public function getServicesWebsite()
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
        $ServiceModel = new ServiceModel($db);
        $services = $ServiceModel->orderBy('createdDate', 'DESC')->where('isActive', 1)->where('isDeleted', 0)->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $services], 200);
    }

  
    public function create()
    {
        $input = $this->request->getJSON();
        $rules = [
            'serviceName'=> ['rules' => 'required'], 
            'serviceCategory'=> ['rules' => 'required'], 
            'servicePrice'=> ['rules' => 'required'],
            'serviceDesc'=> ['rules' => 'required'],
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
            $model = new ServiceModel($db);
        
            $model->insert($input);
             
            return $this->respond(['status'=>true,'message' => 'Item Added Successfully'], 200);
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
        
        // Validation rules for the service
        $rules = [
            'serviceId' => ['rules' => 'required|numeric'], // Ensure serviceId is provided and is numeric
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
            $model = new ServiceModel($db);
    
            // Retrieve the service by serviceId
            $serviceId = $input->serviceId;
            $service = $model->find($serviceId); // Assuming find method retrieves the service
    
            if (!$service) {
                return $this->fail(['status' => false, 'message' => 'Service not found'], 404);
            }
    
            // Prepare the data to be updated
            $updateData = [
                'serviceName' => $input->serviceName,
                'serviceCategory' => $input->serviceCategory,
                'servicePrice' => $input->servicePrice,
                'serviceDesc' => $input->serviceDesc,
            ];
    
            // Update the service with new data
            $updated = $model->update($serviceId, $updateData);
    
            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Service Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update service'], 500);
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
            'serviceId' => ['rules' => 'required'], // Ensure eventId is provided and is numeric
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
            $model = new ServiceModel($db);

            // Retrieve the course by eventId
            $serviceId = $input->serviceId;
            $service = $model->find($serviceId); // Assuming find method retrieves the course

            if (!$service) {
                return $this->fail(['status' => false, 'message' => 'Course not found'], 404);
            }

            // Proceed to delete the course
            $deleted = $model->delete($serviceId);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Course Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete course'], 500);
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
        $serviceId = $this->request->getPost('serviceId'); // Example field

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

        $model = new ServiceModel();
        $model->update($serviceId,$data);

        return $this->respond([
            'status' => 201,
            'message' => 'File and data uploaded successfully',
            'data' => $data,
        ]);
    }

    private function getTenantDb()
{
    $tenantConfigHeader = $this->request->getHeaderLine('X-Tenant-Config');
    if (!$tenantConfigHeader) {
        throw new \Exception('Tenant configuration not found.');
    }

    $tenantConfig = json_decode($tenantConfigHeader, true);
    if (!$tenantConfig) {
        throw new \Exception('Invalid tenant configuration.');
    }

    return Database::connect($tenantConfig);
}
private function respondSuccess($data, $message = 'Operation successful', $statusCode = 200)
{
    return $this->respond([
        'status' => true,
        'message' => $message,
        'data' => $data,
    ], $statusCode);
}

private function respondError($errors, $message = 'Operation failed', $statusCode = 400)
{
    return $this->respond([
        'status' => false,
        'message' => $message,
        'errors' => $errors,
    ], $statusCode);
}


}
