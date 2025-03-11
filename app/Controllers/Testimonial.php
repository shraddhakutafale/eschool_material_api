<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\TestimonialModel;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

use Config\Database;


class Testimonial extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load VendorModel with the tenant database connection
        $TestimonialModel = new TestimonialModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $TestimonialModel->findAll()], 200);
    }

    public function getTestimonialsPaging()
    {
        $input = $this->request->getJSON();
    
        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'testimonialId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
    
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    
        // Load VendorModel with the tenant database connection
        $testimonialModel = new TestimonialModel($db);
        $query = $testimonialModel;
    
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
        $testimonials = $query->paginate($perPage, 'default', $page);
        $pager = $testimonialModel->pager;
    
        $response = [
            "status" => true,
            "message" => "All Vendor Data Fetched",
            "data" => $testimonials,
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
            'designation' => ['rules' => 'required']

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
            $profilePic = $this->request->getFile('profilePic');
            $profilePicName = null;
    
            if ($profilePic && $profilePic->isValid() && !$profilePic->hasMoved()) {
                // Define the upload path for the cover image
                $profilePicPath = FCPATH . 'uploads/'. $decoded->tenantName .'/testimonialImages/';
                if (!is_dir($profilePicPath)) {
                    mkdir($profilePicPath, 0777, true); // Create directory if it doesn't exist
                }
    
                // Move the file to the desired directory with a unique name
                $profilePicName = $profilePic->getRandomName();
                $profilePic->move($profilePicPath, $profilePicName);
    
                // Get the URL of the uploaded cover image and remove the 'uploads/coverImages/' prefix
                $profilePicUrl = 'uploads/testimonialImages/' . $profilePicName;
                $profilePicUrl = str_replace('uploads/testimonialImages/', '', $profilePicUrl);
    
                // Add the cover image URL to the input data
                $input['profilePic'] = $decoded->tenantName . '/testimonialImages/' .$profilePicUrl; 
            }
    
           
    
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new TestimonialModel($db);
            $model->insert($input);
    
            return $this->respond(['status' => true, 'message' => 'Vendor Added Successfully'], 200);
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
            'testimonialId' => ['rules' => 'required|numeric'], // Ensure vendorId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
            $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new TestimonialModel($db);

            // Retrieve the vendor by vendorId
            $testimonialId = $input['testimonialId'];
            $testimonial = $model->find($testimonialId); // Assuming find method retrieves the vendor
            



            if (!$testimonial) {
                return $this->fail(['status' => false, 'message' => 'Testimonial not found'], 404);
            }

            // Prepare the data to be updated (exclude vendorId if it's included)
            $updateData = [
                'name' =>$input['name'],
                'designation' =>$input['designation'],
                'mobileNo' => $input['mobileNo'],
                'alternateMobileNo' => $input['alternateMobileNo'],  // Corrected here
                'emailId' => $input['emailId'],  // Corrected here
                'dateOfBirth' => $input['dateOfBirth'],  // Corrected here
                'gender' => $input['gender'],  // Corrected here
            ];

            // Update the vendor with new data
            $updated = $model->update($testimonialId, $updateData);


            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Testimonial Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update testimonial'], 500);
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
            'testimonialId' => ['rules' => 'required'], // Ensure vendorId is provided
        ];
    

        // Validate the input
        if ($this->validate($rules)) {
            $tenantService = new TenantService();
                // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new TestimonialModel($db);
    
            // Retrieve the vendor by vendorId
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 
          $model = new TestimonialModel($db);

            // Retrieve the lead by leadId
            $testimonialId = $input->testimonialId;
            $testimonial = $model->find($testimonialId); // Assuming the find method retrieves the vendor
    
            
            $testimonial = $model->find($testimonialId); // Assuming find method retrieves the lead

            if (!$testimonial) {
                return $this->fail(['status' => false, 'message' => 'Testimonial not found'], 404);
            }
    
            // Proceed to delete the vendor
            // Soft delete by marking 'isDeleted' as 1
            $updateData = [
                'isDeleted' => 1,
            ];
    

            // Proceed to delete the lead
            $deleted = $model->delete($testimonialId);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Testimonial Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete testimonial'], 500);
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
        $testimonialId = $this->request->getPost('testimonialId'); // Example field

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

        $model = new TestimonialModel();
        $model->update($testimonialId, $data);

        return $this->respond([
            'status' => 201,
            'message' => 'File and data uploaded successfully',
            'data' => $data,
        ]);
    }
}
