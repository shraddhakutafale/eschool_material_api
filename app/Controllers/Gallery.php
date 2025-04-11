<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\GalleryModel;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

use Config\Database;

class Gallery extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $GalleryModel = new GalleryModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $GalleryModel->findAll()], 200);
    
    }

    public function getGallerysPaging()
    {
        $input = $this->request->getJSON();

        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'galleryId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
        

        $tenantService = new TenantService();
        
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load leadModel with the tenant database connection
        $galleryModel = new GalleryModel($db);

        $query = $galleryModel;

        if (!empty($filter)) {
            $filter = json_decode(json_encode($filter), true);

            foreach ($filter as $key => $value) {
                if (in_array($key, ['galleryTitle'])) {
                    $query->like($key, $value); // LIKE filter for specific fields
                }  else if ($key === 'createdDate') {
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
        
        $query->where('isDeleted',0);
        // Apply Sorting
        if (!empty($sortField) && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
            $query->orderBy($sortField, $sortOrder);
        }

        // Get Paginated Results
        $gallerys = $query->paginate($perPage, 'default', $page);
        $pager = $galleryModel->pager;

        $response = [
            "status" => true,
            "message" => "All Gallery Data Fetched",
            "data" => $gallerys,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]
        ];

        return $this->respond($response, 200);
    }

    public function getGallerysWebsite()
    {
           // Insert the product data into the database
           $tenantService = new TenantService();
           // Connect to the tenant's database
           $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 
           // Load UserModel with the tenant database connection
           $GalleryModel = new GalleryModel($db);
           $gallery = $GalleryModel->orderBy('createdDate', 'DESC')->where('isActive', 1)->where('isDeleted', 0)->findAll();
           return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $gallery], 200);
       }
    

    public function create()
    {
        // Retrieve the input data from the request
        $input = $this->request->getPost();
        
        // Define validation rules for required fields
        $rules = [
            'galleryTitle' => ['rules' => 'required'],
            'galleryDescription' => ['rules' => 'required']
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
            $coverImage= $this->request->getFile('coverImage');
            $coverImageName = null;
    
            if ($coverImage && $coverImage->isValid() && !$coverImage->hasMoved()) {
                // Define the upload path for the cover image
                $coverImagePath = FCPATH . 'uploads/'. $decoded->tenantName .'/galleryImages/';
                if (!is_dir($coverImagePath)) {
                    mkdir($coverImagePath, 0777, true); // Create directory if it doesn't exist
                }
    
                // Move the file to the desired directory with a unique name
                $coverImageName = $coverImage->getRandomName();
                $coverImage->move($coverImagePath, $coverImageName);
    
                // Get the URL of the uploaded cover image and remove the 'uploads/coverImages/' prefix
                $coverImageUrl = 'uploads/galleryImages/' . $coverImageName;
                $coverImageUrl = str_replace('uploads/galleryImages/', '', $coverImageUrl);
    
                // Add the cover image URL to the input data
                $input['coverImage'] = $decoded->tenantName . '/galleryImages/' .$coverImageUrl; 
            }
    

             
            $galleryImages = $this->request->getFiles('images');  // 'images' is the name for multiple images
            $imageUrls = []; // Initialize the array for image URLs

            if ($galleryImages && count($galleryImages) > 0) {
                foreach ($galleryImages as $image) {
                    // Validate the image: Ensure it's valid, hasn't moved, and exists
                    if ($image && $image->isValid() && !$image->hasMoved()) {
                        // Define the upload path for product images
                        $galleryImagePath = FCPATH . 'uploads/'. $decoded->tenantName .'/galleryImages/';

                        // Check if the directory exists; if not, create it
                        if (!is_dir($galleryImagePath)) {
                            mkdir($galleryImagePath, 0777, true); // Create directory if it doesn't exist
                        }

                        // Generate a unique name for the image to avoid overwriting
                        $imageName = $image->getRandomName();

                        // Move the uploaded image to the target directory
                        $image->move($galleryImagePath, $imageName);

                        // Get the URL for the uploaded image and add it to the array
                        $imageUrl = 'uploads/galleryImages/' . $imageName;
                        $imageUrl = str_replace('uploads/galleryImages/', '', $imageUrl);

                        $imageUrls[] = $imageUrl; // Add the image URL to the array
                    }
                }

                // If there are multiple images, join the URLs with commas and save in the input data
                if (!empty($imageUrls)) {
                    $input['galleryImages'] = implode(',', $imageUrls); // Join image URLs with commas
                }
            }
           
    
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new GalleryModel($db);
            $model->insert($input);
    
            return $this->respond(['status' => true, 'message' => 'Gallery Added Successfully'], 200);
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

        // Validation rules for the gallery
        $rules = [
            'galleryId' => ['rules' => 'required|numeric'], // Ensure galleryId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new GalleryModel($db);

            // Retrieve the gallery by galleryId
            $galleryId = $input['galleryId'];  // Corrected here
            $gallery = $model->find($galleryId);

            if (!$gallery) {
                return $this->fail(['status' => false, 'message' => 'Gallery not found'], 404);
            }

            // Prepare the data to be updated (exclude galleryId if it's included)
            $updateData = [
               'galleryTitle'=> $input['galleryTitle'],
               'galleryDescription'=> $input['galleryDescription'],
            ];

            // Update the gallery with new data
            $updated = $model->update($galleryId, $updateData);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Gallery Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update Gallery'], 500);
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
                'galleryId' => ['rules' => 'required'], // Ensure vendorId is provided
            ];
        
    
            // Validate the input
            if ($this->validate($rules)) {
                $tenantService = new TenantService();
                    // Insert the product data into the database
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
                $model = new GalleryModel($db);
        
                // Retrieve the vendor by vendorId
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));   $model = new GalleryModel($db);
    
                // Retrieve the lead by leadId
                $galleryId = $input->galleryId;
                $gallery = $model->find($galleryId); // Assuming the find method retrieves the vendor
        
                
                $gallery = $model->find($galleryId); // Assuming find method retrieves the lead
    
                if (!$gallery) {
                    return $this->fail(['status' => false, 'message' => 'gallery not found'], 404);
                }
        
                // Soft delete by marking 'isDeleted' as 1
                $updateData = [
                    'isDeleted' => 1,
                ];
        
    
                // Proceed to delete the lead
                $deleted = $model->delete($galleryId);
    
                if ($deleted) {
                    return $this->respond(['status' => true, 'message' => 'gallery Deleted Successfully'], 200);
                } else {
                    return $this->fail(['status' => false, 'message' => 'Failed to delete gallery'], 500);
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
        $courseId = $this->request->getPost('galleryId'); // Example field

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

        $model = new GalleryModel();
        $model->update($galleryId,$data);

        return $this->respond([
            'status' => 201,
            'message' => 'File and data uploaded successfully',
            'data' => $data,
        ]);
    }


}
