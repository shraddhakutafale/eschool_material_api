<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\BlogModel;
use App\Models\MediaModel;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;
use Config\Database;

class Blog extends BaseController
{
    use ResponseTrait;

    public function index()
    {
       // Insert the product data into the database
       $tenantService = new TenantService();
       // Connect to the tenant's database
       $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); // Load UserModel with the tenant database connection
        $BlogModel = new BlogModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $BlogModel->findAll()], 200);
    }

    public function getBlogsPaging()
    {
        $input = $this->request->getJSON();
    
        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'blogId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
        $businessId = isset($input->businessId) ? $input->businessId : 0;
    
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load EventModel with the tenant database connection
        $blogModel = new BlogModel($db);
    
        $query = $blogModel;
    
        if (!empty($filter)) {
            $filter = json_decode(json_encode($filter), true);
    
            foreach ($filter as $key => $value) {
                if (in_array($key, ['title', 'description', 'authorName','categoryName'])) {
                    $query->like($key, $value); // LIKE filter for specific fields
                } else if ($key === 'createdDate') {
                    $query->where($key, $value); // Exact match filter for createdDate
                }
            }
    
            // Apply Date Range Filter (startDate and endDate)
            if (!empty($filter['startDate']) && !empty($filter['endDate'])) {
                $query->where('createdDate >=', $filter['startDate'])
                      ->where('createdDate <=', $filter['endDate']);
            }
    
            // Apply Last 7 Days Filter if requested
            if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last7days') {
                $last7DaysStart = date('Y-m-d', strtotime('-7 days'));  // 7 days ago from today
                $query->where('createdDate >=', $last7DaysStart);
            }
    
            // Apply Last 30 Days Filter if requested
            if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last30days') {
                $last30DaysStart = date('Y-m-d', strtotime('-30 days'));  // 30 days ago from today
                $query->where('createdDate >=', $last30DaysStart);
            }
        }
    
        // Ensure that the "deleted" status is 0 (active records)
        $query->where('isDeleted', 0);
        $query->where('businessId', $businessId);
    
        // Apply Sorting
        if (!empty($sortField) && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
            $query->orderBy($sortField, $sortOrder);
        }
    
        // Get Paginated Results
        $blogs = $query->paginate($perPage, 'default', $page);
        $pager = $blogModel->pager;
    
        $response = [
            "status" => true,
            "message" => "All Event Data Fetched",
            "data" => $blogs,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]
        ];
    
        return $this->respond($response, 200);
    }

    public function getEventsWebsite()
    {// Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load UserModel with the tenant database connection
        $BlogModel = new BlogModel($db);
        $blogs = $BlogModel->orderBy('createdDate', 'DESC')->where('isActive', 1)->where('isDeleted', 0)->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $blogs], 200);
    }

    public function create()
    {
        // Retrieve the input data from the request
        $input = $this->request->getPost();
        
        // Define validation rules for required fields
        $rules = [
            'title'  => ['rules' => 'required'],


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
            $profilePic= $this->request->getFile('profilePic');
            $profilePicName = null;
    
            if ($profilePic && $profilePic->isValid() && !$profilePic->hasMoved()) {
                // Define the upload path for the cover image
                $profilePicPath = FCPATH . 'uploads/'. $decoded->tenantName .'/blogImages/';
                if (!is_dir($profilePicPath)) {
                    mkdir($profilePicPath, 0777, true); // Create directory if it doesn't exist
                }
    
                // Move the file to the desired directory with a unique name
                $profilePicName = $profilePic->getRandomName();
                $profilePic->move($profilePicPath, $profilePicName);
    
                // Get the URL of the uploaded cover image and remove the 'uploads/coverImages/' prefix
                $profilePicUrl = 'uploads/blogImages/' . $profilePicName;
                $profilePicUrl = str_replace('uploads/blogImages/', '', $profilePicUrl);
    
                // Add the cover image URL to the input data
                $input['profilePic'] = $decoded->tenantName . '/blogImages/' .$profilePicUrl; 
            }
    
           
    
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new BlogModel($db);
            $model->insert($input);
    
            return $this->respond(['status' => true, 'message' => 'Blog Added Successfully'], 200);
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
        
        // Validation rules for the studentId
        $rules = [
            'blogId' => ['rules' => 'required|numeric'], // Ensure studentId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
             
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); $model = new BlogModel($db);

            // Retrieve the student by studentId
            $blogId = $input['blogId'];  // Corrected here
            $blog = $model->find($blogId); // Assuming find method retrieves the student

            if (!$blog) {
                return $this->fail(['status' => false, 'message' => 'blog not found'], 404);
            }

            // Prepare the data to be updated (exclude studentId if it's included)
            $updateData = [

                'title' => $input['title'],  // Corrected here
                'authorName' => $input['authorName'],  // Corrected here
                'description' => $input['description'],  // Corrected here

            ];

            // Update the student with new data
            $updated = $model->update($blogId, $updateData);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'blog Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update blog'], 500);
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
            'blogId' => ['rules' => 'required'], // Ensure vendorId is provided
        ];
    

        // Validate the input
        if ($this->validate($rules)) {
            $tenantService = new TenantService();
                // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new BlogModel($db);
    
            // Retrieve the vendor by vendorId
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));   $model = new BlogModel($db);

            // Retrieve the lead by leadId
            $blogId = $input->blogId;
            $blog = $model->find($blogId); // Assuming the find method retrieves the vendor
    
            
            $blog = $model->find($blogId); // Assuming find method retrieves the lead

            if (!$blog) {
                return $this->fail(['status' => false, 'message' => 'blog not found'], 404);
            }
    
            // Soft delete by marking 'isDeleted' as 1
            $updateData = [
                'isDeleted' => 1,
            ];
    

            // Proceed to delete the lead
            $deleted = $model->delete($blogId);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'blog Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete blog'], 500);
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
        $eventId = $this->request->getPost('eventId'); // Example field

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

        $model = new EventModel();
        $model->update($eventId,$data);

        return $this->respond([
            'status' => 201,
            'message' => 'File and data uploaded successfully',
            'data' => $data,
        ]);
    }

    public function createMedia()
    {
        $file = $this->request->getFile('file');
        $businessId = $this->request->getPost('businessId'); 

        if ($file && $file->isValid() && !$file->hasMoved()) {
            $newName = $file->getRandomName();
            $uploadPath = FCPATH . 'uploads/media/';

            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0777, true);
            }

            // Move file
            $file->move($uploadPath, $newName);

            // ✅ Detect only "image" or "video"
            $mimeType = $file->getClientMimeType();
            if (strpos($mimeType, 'image') === 0) {
                $mediaType = 'image';
            } elseif (strpos($mimeType, 'video') === 0) {
                $mediaType = 'video';
            } else {
                // agar image/video nahi hai to reject kar do
                return $this->fail([
                    'status'  => false,
                    'message' => 'Only images and videos are allowed'
                ], 400);
            }

            // Prepare record for DB insert
            $data = [
                'type'        => $mediaType,  
                'mediaUrl'    => 'uploads/media/' . $newName,
                'businessId'  => $businessId ?: 0,
                'createdDate' => date('Y-m-d H:i:s'),
                'isDeleted'   => 0,
                'isActive'    => 1
            ];

            // Save to DB
            $tenantService = new TenantService();
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $mediaModel = new MediaModel($db);
            $mediaId = $mediaModel->insert($data);

            $data['mediaId'] = $mediaId;

            return $this->respond([
                'status'  => true,
                'message' => 'Media uploaded successfully',
                'data'    => $data
            ], 200);
        }

        return $this->fail([
            'status'  => false,
            'message' => 'File upload failed'
        ], 400);
    }

    public function getAllMedia()
    {
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $mediaModel = new MediaModel($db);
        $media = $mediaModel->where('isDeleted', 0)->findAll();

        $baseUrl = base_url(); 

        foreach ($media as &$m) {
            $m['mediaUrl'] = $baseUrl . $m['mediaUrl']; 
        }

        return $this->respond([
            'status' => true,
            'message' => 'Media fetched successfully',
            'data' => $media
        ], 200);
    }


}
