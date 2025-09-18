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
        
        $query->where('isDeleted',0)->where('businessId', $input->businessId);
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
    $input = $this->request->getPost();

    $rules = [
        'type'             => ['rules' => 'required|in_list[gallery,event]'],
        'galleryTitle'     => ['rules' => 'required'],
        'galleryDescription' => ['rules' => 'permit_empty'],
        'businessId'       => ['rules' => 'required|integer']
    ];
    if (!$this->validate($rules)) {
        return $this->fail([
            'status'  => false,
            'errors'  => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ], 409);
    }

    // --- Token decode (clean) ---
    $key    = "Exiaa@11";
    $header = $this->request->getHeaderLine("Authorization");
    $token  = null;
    if ($header && preg_match('/Bearer\s(\S+)/', $header, $m)) $token = $m[1];

    $decoded = $token ? JWT::decode($token, new Key($key, 'HS256')) : null;
    $tenant  = $decoded->tenantName ?? 'default';

    // --- Paths ---
    $uploadBase = FCPATH . 'uploads/' . $tenant . '/galleryImages/';
    if (!is_dir($uploadBase)) mkdir($uploadBase, 0777, true);

    // --- Cover image ---
    $cover = $this->request->getFile('coverImage');
    if ($cover && $cover->isValid() && !$cover->hasMoved()) {
        $name = $cover->getRandomName();
        $cover->move($uploadBase, $name);
        $input['coverImage'] = $tenant . '/galleryImages/' . $name;
    }
   // --- Multiple images ---
    $files = $this->request->getFileMultiple('images');
    $imageNames = [];
    if ($files) {
        foreach ($files as $img) {
            if ($img && $img->isValid() && !$img->hasMoved()) {
                $name = $img->getRandomName();
                $img->move($uploadBase, $name);
                // ✅ Full path store karo
                $imageNames[] = $tenant . '/galleryImages/' . $name;
            }
        }
        if ($imageNames) {
            $input['galleryImages'] = implode(',', $imageNames);
        }
    }
    // --- Audit fields (optional) ---
    $input['createdBy']   = $decoded->userId ?? null;
    $input['createdDate'] = date('Y-m-d H:i:s');
    $input['isActive']    = 1;
    $input['isDeleted']   = 0;

    // --- Save ---
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $model = new GalleryModel($db);
    $id = $model->insert($input, true);

    return $this->respond([
        'status'  => true,
        'id'      => $id,
        'message' => 'Gallery/Event Added Successfully'
    ], 201);
}


public function update()
{
    $input = $this->request->getPost();

    // Validation
    $rules = ['galleryId' => ['rules' => 'required|numeric']];
    if (!$this->validate($rules)) {
        return $this->fail([
            'status' => false,
            'errors' => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ], 409);
    }

    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $model = new GalleryModel($db);

    $galleryId = $input['galleryId'];
    $gallery = $model->find($galleryId);
    if (!$gallery) {
        return $this->fail(['status' => false, 'message' => 'Gallery not found'], 404);
    }

    // Upload folder
    $uploadFolder = FCPATH . 'uploads/exEducationTraining/galleryImages/';
    if (!is_dir($uploadFolder)) mkdir($uploadFolder, 0777, true);

    /** ------------------------
     * 1️⃣ Handle Cover Image
     * ------------------------ */
    $coverImagePath = $gallery['coverImage'] ?? null;
    $coverFile = $this->request->getFile('coverImage');
    if ($coverFile && $coverFile->isValid() && !$coverFile->hasMoved()) {
        // Fix: ensure extension always present
        $ext = $coverFile->getClientExtension();
        if (!$ext) {
            $ext = pathinfo($coverFile->getName(), PATHINFO_EXTENSION) ?: 'jpg';
        }
        $newName = uniqid() . '.' . $ext;
        $coverFile->move($uploadFolder, $newName);
        $coverImagePath = 'exEducationTraining/galleryImages/' . $newName;
    } elseif (!empty($input['coverImageOld'])) {
        $coverImagePath = $input['coverImageOld'];
    }

    /** ------------------------
     * 2️⃣ Handle Gallery Images
     * ------------------------ */
    // Existing images from DB
    $existingImages = !empty($gallery['galleryImages'])
        ? (is_array($gallery['galleryImages']) ? $gallery['galleryImages'] : explode(',', $gallery['galleryImages']))
            : [];
    // Kept images from frontend
    $keptImages = !empty($input['existingImages'])
        ? (is_array($input['existingImages']) ? $input['existingImages'] : explode(',', $input['existingImages']))
        : [];

    // Normalize → ensure only exEducationTraining/galleryImages/xxx.ext
    $keptImages = array_map(function ($img) {
        return 'exEducationTraining/galleryImages/' . basename(trim($img));
    }, $keptImages);

    // Remove duplicates + empty
    $keptImages = array_values(array_unique(array_filter($keptImages)));

        // Delete removed images from server
        $deletedImages = array_diff($existingImages, $keptImages);
        foreach ($deletedImages as $delImg) {
            $filePath = FCPATH . 'uploads/' . $delImg;
            if (file_exists($filePath)) unlink($filePath);
        }

    // Handle new uploaded images
    $newImages = [];
    $files = $this->request->getFiles();
    if (!empty($files['images'])) {
        foreach ($files['images'] as $file) {
            if ($file->isValid() && !$file->hasMoved()) {
                // Fix: ensure extension always present
                $ext = $file->getClientExtension();
                if (!$ext) {
                    $ext = pathinfo($file->getName(), PATHINFO_EXTENSION) ?: 'jpg';
                }
                $newName = uniqid() . '.' . $ext;
                $file->move($uploadFolder, $newName);

                $newImages[] = 'exEducationTraining/galleryImages/' . $newName;
            }
        }
    }

    // Merge kept + new images
    $finalImages = array_values(array_unique(array_merge($keptImages, $newImages)));

    /** ------------------------
     * 3️⃣ Update Gallery in DB
     * ------------------------ */
    $updateData = [
        'galleryTitle'       => $input['galleryTitle'] ?? $gallery['galleryTitle'],
        'galleryDescription' => $input['galleryDescription'] ?? $gallery['galleryDescription'],
        'type'               => $input['type'] ?? $gallery['type'],
        'businessId'         => $input['businessId'] ?? $gallery['businessId'],
        'coverImage'         => $coverImagePath,
        'galleryImages'      => implode(',', $finalImages),
    ];

    $model->update($galleryId, $updateData);

    return $this->respond([
        'status'  => true,
        'message' => 'Gallery updated successfully',
        'data'    => $updateData
    ], 200);
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

    public function getAllGallery()
{
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $GalleryModel = new GalleryModel($db);

    $input = $this->request->getJSON(true); // get JSON body
    $filter = $input['filter'] ?? [];

    if (isset($filter['galleryId']) && !empty($filter['galleryId'])) {
        $galleryId = $filter['galleryId'];
        $gallery = $GalleryModel->where('id', $galleryId)->first();
        return $this->respond([
            "status" => true,
            "message" => "Gallery fetched",
            "data" => $gallery ? [$gallery] : []
        ], 200);
    }

    return $this->respond([
        "status" => true,
        "message" => "All Gallery Data Fetched",
        "data" => $GalleryModel->findAll()
    ], 200);
}


}
