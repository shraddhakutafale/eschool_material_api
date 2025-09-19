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
        if (!empty($header)) {
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
            $profilePicPath = FCPATH . 'uploads/' . $decoded->tenantName . '/blogImages/';
            if (!is_dir($profilePicPath)) {
                mkdir($profilePicPath, 0777, true); // Create directory if it doesn't exist
            }

            // Move the file to the desired directory with a unique name
            $profilePicName = $profilePic->getRandomName();
            $profilePic->move($profilePicPath, $profilePicName);

            // Build the blog image path
            $profilePicUrl = $profilePicName;

            // Add the cover image URL to the input data
            $input['profilePic'] = $decoded->tenantName . '/blogImages/' . $profilePicUrl;
        }

        // ✅ Normalize editor images to same path (tenantName/blogImages/)
        if (!empty($input['description'])) {
            // Remove absolute URLs like http://localhost:8080/uploads/
            $input['description'] = preg_replace(
                '#https?://[^/]+/uploads/#',
                '',
                $input['description']
            );

            // Replace any folder with tenantName/blogImages/
            $input['description'] = preg_replace(
                '#/?' . preg_quote($decoded->tenantName, '#') . '/[^"]+/#',
                $decoded->tenantName . '/blogImages/',
                $input['description']
            );
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

    $rules = [
        'blogId' => ['rules' => 'required|numeric'],
    ];

    if ($this->validate($rules)) {
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new BlogModel($db);

        $blogId = $input['blogId'];
        $blog = $model->find($blogId);

        if (!$blog) {
            return $this->fail(['status' => false, 'message' => 'Blog not found'], 404);
        }

        // 🔑 JWT decoding for tenant folder
        $key = "Exiaa@11";
        $header = $this->request->getHeader("Authorization");
        $token = null;
        if (!empty($header)) {
            if (preg_match('/Bearer\s(\S+)/', $header, $matches)) {
                $token = $matches[1];
            }
        }
        $decoded = JWT::decode($token, new Key($key, 'HS256'));

        // 🔄 Handle new image upload (optional)
        $profilePic = $this->request->getFile('profilePic');
        if ($profilePic && $profilePic->isValid() && !$profilePic->hasMoved()) {
            $profilePicPath = FCPATH . 'uploads/' . $decoded->tenantName . '/blogImages/';
            if (!is_dir($profilePicPath)) {
                mkdir($profilePicPath, 0777, true);
            }

            $profilePicName = $profilePic->getRandomName();
            $profilePic->move($profilePicPath, $profilePicName);

            $input['profilePic'] = $decoded->tenantName . '/blogImages/' . $profilePicName;
        } elseif (!empty($input['profilePic'])) {
            // If an existing relative path is sent → keep it
            $input['profilePic'] = str_replace($this->request->getServer('HTTP_ORIGIN') . '/uploads/', '', $input['profilePic']);
        } else {
            unset($input['profilePic']); // Prevent overwriting with null
        }

        // 🔄 Normalize editor images path
        // 🔄 Normalize editor images path if description is sent
if (!empty($input['description'])) {
    $input['description'] = preg_replace(
        '#https?://[^/]+/uploads/#',
        '',
        $input['description']
    );
    $input['description'] = preg_replace(
        '#/?' . preg_quote($decoded->tenantName, '#') . '/[^"]+/#',
        $decoded->tenantName . '/blogImages/',
        $input['description']
    );
}

// ✅ Build update data (patch only provided fields)
$updateData = [
    'title'       => $input['title'] ?? $blog['title'],
    'authorName'  => $input['authorName'] ?? $blog['authorName'],
    'description' => array_key_exists('description', $input) && $input['description'] !== null
                    ? $input['description']
                    : $blog['description'],
];





        if (!empty($input['profilePic'])) {
            $updateData['profilePic'] = $input['profilePic'];
        }

        $updated = $model->update($blogId, $updateData);

        if ($updated) {
            return $this->respond(['status' => true, 'message' => 'Blog updated successfully'], 200);
        } else {
            return $this->fail(['status' => false, 'message' => 'Failed to update blog'], 500);
        }
    } else {
        return $this->fail([
            'status'  => false,
            'errors'  => $this->validator->getErrors(),
            'message' => 'Invalid Inputs',
        ], 409);
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
// public function createMedia()
// {
//     $key = "Exiaa@11";
//     $header = $this->request->getHeader("Authorization");
//     $token = null;

//     // extract token
//     if (!empty($header) && preg_match('/Bearer\s(\S+)/', $header, $matches)) {
//         $token = $matches[1];
//     }

//     $decoded = JWT::decode($token, new Key($key, 'HS256'));

//     $file = $this->request->getFile('file');
//     $businessId = $this->request->getPost('businessId');
//     $createdBy = $decoded->userId ?? 0;

//     if (!$file->isValid()) {
//         return $this->fail($file->getErrorString());
//     }

//     $mimeType = $file->getMimeType();
//     $allowed = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4'];

//     if (!in_array($mimeType, $allowed)) {
//         return $this->fail('Invalid file type. Only JPEG, PNG, GIF, and MP4 are allowed.');
//     }

//     // Make directory per tenant
//     $uploadPath = FCPATH . 'uploads/' . $decoded->tenantName . '/media/';
//     if (!is_dir($uploadPath)) {
//         mkdir($uploadPath, 0777, true);
//     }

//     $newName = $file->getRandomName();
//     $file->move($uploadPath, $newName);

//     // Build relative path
//     $mediaUrl = $decoded->tenantName . '/media/' . $newName;

//     // Insert into DB (media_mst)
//     $db = db_connect();
//     $db->table('media_mst')->insert([
//         'type'        => explode('/', $mimeType)[0], // "image" or "video"
//         'mediaUrl'    => $mediaUrl,
//         'businessId'  => $businessId,
//         'createdBy'   => $createdBy,
//         'createdDate' => date('Y-m-d H:i:s'),
//         'modifiedBy'  => $createdBy,
//         'modifiedDate'=> date('Y-m-d H:i:s'),
//         'isDeleted'   => 0,
//         'isActive'    => 1
//     ]);

//     return $this->respond([
//         'status'  => true,
//         'message' => 'Media uploaded successfully',
//         'data'    => [
//             'mediaUrl' => $mediaUrl,
//             'type'     => explode('/', $mimeType)[0]
//         ]
//     ], 200);
// }

// public function getAllMedia()
// {
//     // Connect to the main database (exiaa_db)
//     $db = db_connect(); // default connection should point to exiaa_db

//     // Fetch media from media_mst table
//     $builder = $db->table('media_mst');
//     $builder->select('*');
//     $builder->where('isDeleted', 0);
//     $builder->where('isActive', 1);

//     $media = $builder->get()->getResult();

//     // Prepend full URL for Angular
//     $baseUrl = base_url('uploads/');
   

//     return $this->respond([
//         'status' => true,
//         'message' => 'Media fetched successfully',
//         'data' => $media
//     ], 200);
// }



public function createMedia()
{
    $key = "Exiaa@11";
    $header = $this->request->getHeader("Authorization");
    $token = null;

    if (!empty($header) && preg_match('/Bearer\s(\S+)/', $header, $matches)) {
        $token = $matches[1];
    }

    $decoded = JWT::decode($token, new Key($key, 'HS256'));
    $file = $this->request->getFile('file');
    $businessId = $this->request->getPost('businessId');
    $createdBy = $decoded->userId ?? 0;

    if (!$file->isValid()) {
        return $this->fail($file->getErrorString());
    }

    $mimeType = $file->getMimeType();
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4'];
    if (!in_array($mimeType, $allowed)) {
        return $this->fail('Invalid file type. Only JPEG, PNG, GIF, and MP4 are allowed.');
    }

    // Make directory per tenant
    $uploadPath = FCPATH . 'uploads/' . $decoded->tenantName . '/media/';
    if (!is_dir($uploadPath)) mkdir($uploadPath, 0777, true);

    $newName = $file->getRandomName();
    $file->move($uploadPath, $newName);

    $mediaUrl = $decoded->tenantName . '/media/' . $newName;

    // Use default DB connection
    $db = db_connect(); 

    $db->table('media_mst')->insert([
        'type'        => explode('/', $mimeType)[0],
        'mediaUrl'    => $mediaUrl,
        'businessId'  => $businessId,
        'createdBy'   => $createdBy,
        'createdDate' => date('Y-m-d H:i:s'),
        'modifiedBy'  => $createdBy,
        'modifiedDate'=> date('Y-m-d H:i:s'),
        'isDeleted'   => 0,
        'isActive'    => 1
    ]);

    return $this->respond([
        'status'  => true,
        'message' => 'Media uploaded successfully',
        'data'    => [
            'mediaUrl' => $mediaUrl,
            'type'     => explode('/', $mimeType)[0]
        ]
    ], 200);
}


public function getAllMedia()
{
    // Default DB connection
    $db = db_connect(); 

    $builder = $db->table('media_mst');
    $builder->select('*');
    $builder->where('isDeleted', 0);
    $builder->where('isActive', 1);

   $media = $builder->get()->getResult();

    // Prepend full URL for Angular
    $baseUrl = base_url('uploads/');

    return $this->respond([
        'status' => true,
        'message' => 'Media fetched successfully',
        'data' => $media
    ], 200);
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
    

}
