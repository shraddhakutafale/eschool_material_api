<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\VendorModel;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

use Config\Database;


class Vendor extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load VendorModel with the tenant database connection
        $VendorModel = new VendorModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $VendorModel->findAll()], 200);
    }

    public function getVendorsPaging()
    {
        $input = $this->request->getJSON();
    
        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'vendorId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
    
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    
        // Load VendorModel with the tenant database connection
        $vendorModel = new VendorModel($db);
        $query = $vendorModel;
    
        // Apply search filter for name and mobile number
        if (!empty($search)) {
            $query->groupStart()
                  ->like('name', $search)
                  ->orLike('mobileNo', $search)
                  ->groupEnd();
        }
    
       // Apply filtering
        if (!empty($filter)) {
     $filter = json_decode(json_encode($filter), true);

    foreach ($filter as $key => $value) {
        if (in_array($key, ['name', 'mobileNo', 'email'])) {
            $query->like($key, $value);
        } else if ($key === 'createdDate' && !empty($value)) {
            $query->where($key, $value);
        }
    }

    // Apply Date Range Filter using startDate and endDate
    if (!empty($filter['startDate']) && !empty($filter['endDate'])) {
        $query->where('createdDate >=', $filter['startDate'])
              ->where('createdDate <=', $filter['endDate']);
    }
}

        $query->where('isDeleted',0);
        // Apply Sorting
        if (!empty($sortField) && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
            $query->orderBy($sortField, $sortOrder);
        }
    
        // Get Paginated Results
        $vendors = $query->paginate($perPage, 'default', $page);
        $pager = $vendorModel->pager;
    
        $response = [
            "status" => true,
            "message" => "All Vendor Data Fetched",
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
    
    
    public function create()
    {
        // Retrieve the input data from the request
        $input = $this->request->getPost();
        
        // Define validation rules for required fields
        $rules = [
            'name' => ['rules' => 'required'],
            'mobileNo' => ['rules' => 'required']
        ];
    
        if ($this->validate($rules)) {
            $key = "Exiaa@11";
            $header = $this->request->getHeader("Authorization");
            $token = null;
    
            // extract the token from the header
            if(!empty($header)) {
                if (preg_match('/Bearer\s(\S+)/', $header, $matches)) {
                    $token = $matches[1];
                }
            }
            
            $decoded = JWT::decode($token, new Key($key, 'HS256')); $key = "Exiaa@11";
            $header = $this->request->getHeader("Authorization");
            $token = null;
    
            // extract the token from the header
            if(!empty($header)) {
                if (preg_match('/Bearer\s(\S+)/', $header, $matches)) {
                    $token = $matches[1];
                }
            }
            
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
           
            // Handle image upload for the cover image
            $coverImage = $this->request->getFile('coverImage');
            $coverImageName = null;
    
            if ($coverImage && $coverImage->isValid() && !$coverImage->hasMoved()) {
                // Define the upload path for the cover image
                $coverImagePath = FCPATH . 'uploads/'. $decoded->tenantName .'/vendorImages/';
                if (!is_dir($coverImagePath)) {
                    mkdir($coverImagePath, 0777, true); // Create directory if it doesn't exist
                }
    
                // Move the file to the desired directory with a unique name
                $coverImageName = $coverImage->getRandomName();
                $coverImage->move($coverImagePath, $coverImageName);
    
                // Get the URL of the uploaded cover image and remove the 'uploads/coverImages/' prefix
                $coverImageUrl = 'uploads/vendorImages/' . $coverImageName;
                $coverImageUrl = str_replace('uploads/vendorImages/', '', $coverImageUrl);
    
                // Add the cover image URL to the input data
                $input['profilePic'] = $decoded->tenantName . '/vendorImages/' .$coverImageUrl; 
            }
    
           
    
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new VendorModel($db);
            $model->insert($input);
    
            return $this->respond(['status' => true, 'message' => 'Staff Added Successfully'], 200);
        } else {
            // If validation fails, return the error messages
            $response = [
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs',
            ];
            return $this->fail($response, 409);
        }
    }


    public function update()
    {
        $input = $this->request->getPost();
        
        // Validation rules for the vendor
        $rules = [
            'vendorId' => ['rules' => 'required|numeric'], // Ensure vendorId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
            $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new VendorModel($db);

            // Retrieve the vendor by vendorId
            $vendorId = $input['vendorId'];
            $vendor = $model->find($vendorId); // Assuming find method retrieves the vendor
            



            if (!$vendor) {
                return $this->fail(['status' => false, 'message' => 'Vendor not found'], 404);
            }

            // Prepare the data to be updated (exclude vendorId if it's included)
            $updateData = [
                'name' =>$input['name'],
                'vendorCode' =>$input['vendorCode'],
                'mobileNo' => $input['mobileNo'],
                'alternateMobileNo' => $input['alternateMobileNo'],  // Corrected here
                'emailId' => $input['emailId'],  // Corrected here
                'dateOfBirth' => $input['dateOfBirth'],  // Corrected here
                'gender' => $input['gender'],  // Corrected here
            ];

            // Update the vendor with new data
            $updated = $model->update($vendorId, $updateData);


            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Vendor Updated Successfully'], 200);
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
        
        // Validation rules for the vendor

        // Validation rules for the lead
        $rules = [
            'vendorId' => ['rules' => 'required'], // Ensure vendorId is provided
        ];
    

        // Validate the input
        if ($this->validate($rules)) {
            $tenantService = new TenantService();
                // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new VendorModel($db);
    
            // Retrieve the vendor by vendorId
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));   $model = new VendorModel($db);

            // Retrieve the lead by leadId
            $vendorId = $input->vendorId;
            $vendor = $model->find($vendorId); // Assuming the find method retrieves the vendor
    
            
            $vendor = $model->find($vendorId); // Assuming find method retrieves the lead

            if (!$vendor) {
                return $this->fail(['status' => false, 'message' => 'Vendor not found'], 404);
            }
    
            // Proceed to delete the vendor
            // Soft delete by marking 'isDeleted' as 1
            $updateData = [
                'isDeleted' => 1,
            ];
    

            // Proceed to delete the lead
            $deleted = $model->delete($vendorId);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Vendor Deleted Successfully'], 200);
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
        $model->update($vendorId, $data);

        return $this->respond([
            'status' => 201,
            'message' => 'File and data uploaded successfully',
            'data' => $data,
        ]);
    }
}
