<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\VendorModel;
use Config\Database;

class Vendor extends BaseController
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
        $vendorModel = new VendorModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $vendorModel->findAll()], 200);
    }

    public function getVendorsPaging()
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
        $vendorModel = new VendorModel($db);
        $vendors = $vendorModel->orderBy('createdDate', 'DESC')->paginate($perPage, 'default', $page);
        $pager = $vendorModel->pager;

        $response = [
            "status" => true,
            "message" => "All Data Fetched",
            "data" => $vendors,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]   
        ];
        return $this->respond($response, 200);
    }

    public function getVendorsWebsite()
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
        $vendorModel = new VendorModel($db);
        $vendors = $vendorModel->orderBy('createdDate', 'DESC')->where('isActive', 1)->where('isDeleted', 0)->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $vendors], 200);
    }

    public function create()
    {
        $input = $this->request->getJSON();
        $rules = [

            'vendorName' => ['rules' => 'required'],
            'branchName' => ['rules' => 'required'],
            'country' => ['rules' => 'required'],
            'vendorAddress' => ['rules' => 'required'],
            'state' => ['rules' => 'required'],
            'gst' => ['rules' => 'required'],
            'bankName' => ['rules' => 'required'],
            'bankAccountNumber' => ['rules' => 'required'],
            'bankIfscCode' => ['rules' => 'required'],
            'bankBranch' => ['rules' => 'required'],
            


            
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
            $model = new VendorModel($db);
        
            $model->insert($input);
             
            return $this->respond(['status'=>true,'message' => 'vendor Added Successfully'], 200);
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
            'vendorId' => ['rules' => 'required|numeric'], // Ensure courseId is provided and is numeric
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
            $model = new VendorModel($db);

            // Retrieve the course by courseId
            $vendorId = $input->vendorId;
            $vendor = $model->find($vendorId); // Assuming find method retrieves the course

            if (!$vendor) {
                return $this->fail(['status' => false, 'message' => 'vendor not found'], 404);
            }

            // Prepare the data to be updated (exclude courseId if it's included)
            $updateData = [

                'vendorId' => $input->	vendorId,
                'vendorName' => $input->	vendorName,
                'branchName' => $input->branchName,

                'country' => $input->country,
                'vendorAddress' => $input->vendorAddress,
                'state' => $input->state,
                'gst' => $input->gst,
                'bankName' => $input->bankName,
                'bankAccountNumber' => $input->bankAccountNumber,
                'bankIfscCode' => $input->bankIfscCode,
                'bankBranch' => $input->bankBranch,
            ];

            // Update the course with new data
            $updated = $model->update($vendorId, $updateData);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => ' vendor Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update vendor'], 500);
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
            'vendorId' => ['rules' => 'required'],
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
            $model = new VendorModel($db);

            // Retrieve the course by courseId
            $vendorId = $input->vendorId;
            $vendor = $model->find($vendorId); // Assuming find method retrieves the course

            if (!$vendor) {
                return $this->fail(['status' => false, 'message' => 'vendor not found'], 404);
            }

            // Proceed to delete the course
            $deleted = $model->delete($vendorId);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'vendor Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete vendor'], 500);
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
        $vendorId = $this->request->getPost('vendorId'); // Example field

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

        $model = new VendorModel();
        $model->update($vendorId,$data);

        return $this->respond([
            'status' => 201,
            'message' => 'File and data uploaded successfully',
            'data' => $data,
        ]);
    }


    public function getVendorById($vendorId)
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
    $vendorModel = new VendorModel($db);

    // Fetch course by ID
    $vendor = $vendorModel->find($vendorId); // find method returns a single record by its ID

    // Check if course was found
    if (!$vendor) {
        throw new \Exception('vendor not found.');
    }

    // Respond with the course data
    return $this->respond(["status" => true, "message" => "vendor fetched successfully", "data" => $vendor], 200);
}

}
