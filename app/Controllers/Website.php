<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use Config\Database;
use App\Models\WebsiteModel;
use App\Models\ContentModel;
use App\Models\LogoBannerModel;

use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;


class Website extends BaseController
{
    use ResponseTrait;

    public function index()
    {
           // Insert the product data into the database
           $tenantService = new TenantService();
           // Connect to the tenant's database
           $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 
        $websiteModel = new WebsiteModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $websiteModel->findAll()], 200);
    }

    public function getWebsitesPaging()
    {
        $input = $this->request->getJSON();

        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'menuId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
        

        $tenantService = new TenantService();
        
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load leadModel with the tenant database connection
        $websiteModel = new WebsiteModel($db);

        $query = $websiteModel;

        if (!empty($filter)) {
            $filter = json_decode(json_encode($filter), true);

            foreach ($filter as $key => $value) {
                if (in_array($key, ['fName','lName','email', 'primaryMobileNo'])) {
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
        $websites = $query->paginate($perPage, 'default', $page);
        $pager = $websiteModel->pager;

        $response = [
            "status" => true,
            "message" => "All  Data Fetched",
            "data" => $websites,
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
    $input = $this->request->getPost();
    $file = $this->request->getFile('file');

    $rules = [
        'menuName' => ['rules' => 'required'],
        'menuType' => ['rules' => 'required'],
    ];

    // Conditional rules for 'linkValue' or 'file'
    if ($input['menuType'] === 'URL' || $input['menuType'] === 'Link') {
        $rules['linkValue'] = ['rules' => 'required'];
    } elseif ($input['menuType'] === 'File') {
        $rules['file'] = [
            'rules' => 'uploaded[file]|max_size[file,10240]|mime_in[file,image/jpeg,image/jpg,image/png,application/pdf]'
        ];
    }

    if (!$this->validate($rules)) {
        return $this->fail([
            'status' => false,
            'errors' => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ], 409);
    }

    // ✅ Get tenant DB
    $tenantService = new \App\Libraries\TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $model = new \App\Models\WebsiteModel($db);

    // ✅ Use parentMenu passed from form
    $parentMenuId = !empty($input['parentMenu']) ? (int)$input['parentMenu'] : 0;

    // ✅ Prepare data for insert
    $data = [
        'menuName' => $input['menuName'],
        'menuType' => $input['menuType'],
        'businessId' => $input['businessId'] ?? null,
        'parentMenuId' => $parentMenuId,
        'isActive' => 1,
        'isDeleted' => 0,
        'createdBy' => 9,
        'modifiedBy' => 0,
        'createdDate' => date('Y-m-d H:i:s'),
        'modifiedDate' => date('Y-m-d H:i:s'),
    ];

    // ✅ Set the value field based on menuType
    if ($input['menuType'] === 'URL' || $input['menuType'] === 'Link') {
        $data['value'] = $input['linkValue'];
    } elseif ($input['menuType'] === 'File' && $file && $file->isValid() && !$file->hasMoved()) {
        $uploadPath = WRITEPATH . 'uploads/website/';
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }
        $newFileName = $file->getRandomName();
        $file->move($uploadPath, $newFileName);
        $data['value'] = base_url('writable/uploads/website/' . $newFileName);
    }

    // ✅ Insert menu
    $model->insert($data);
    $menuId = $model->insertID(); // Get newly inserted menu ID

    // ✅ If menuType is URL, insert into content_menu
    if ($input['menuType'] === 'URL') {
        $contentModel = new \App\Models\ContentModel($db);

        $contentData = [
            'menuId' => $menuId,
            'businessId' => $input['businessId'] ?? null,
            'title' => '',
            'content' => '',
            'createdBy' => 9,
            'createdDate' => date('Y-m-d H:i:s'),
            'modifiedBy' => 0,
            'modifiedDate' => date('Y-m-d H:i:s'),
            'isActive' => 1,
            'isDeleted' => 0,
        ];

        $contentModel->insert($contentData);
    }

    return $this->respond([
        'status' => true,
        'message' => 'Menu created successfully.',
        'data' => $data
    ], 200);
}


public function createContent()
{
    $input = $this->request->getJSON(true);

    $rules = [
        'title' => 'required|string',
        'content' => 'required|string',
    ];

    if (!$this->validate($rules)) {
        return $this->fail([
            'status' => false,
            'errors' => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ], 409);
    }

    $tenantService = new \App\Libraries\TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    $menuModel = new \App\Models\WebsiteModel($db);
    $contentModel = new \App\Models\ContentModel($db);

    // Find all URL-type menus with blank content/title (in content table)
    $query = $db->table('content_menu AS c')
        ->join('website_menus AS m', 'm.menuId = c.menuId')
        ->where('m.menuType', 'URL')
        ->where('c.isDeleted', 0)
        ->groupStart()
            ->where('c.title', '')
            ->orWhere('c.content', '')
        ->groupEnd()
        ->select('c.contentId')
        ->get();

    $results = $query->getResultArray();

    if (empty($results)) {
        return $this->respond([
            'status' => false,
            'message' => 'No empty URL content entries found.'
        ], 200);
    }

    foreach ($results as $row) {
        $contentModel->update($row['contentId'], [
            'title' => $input['title'],
            'content' => $input['content'],
            'modifiedBy' => 9,
            'modifiedDate' => date('Y-m-d H:i:s')
        ]);
    }

    return $this->respond([
        'status' => true,
        'message' => 'Content updated successfully for all matching URL menus.',
    ], 200);
}
public function createLogoBanner()
{
    // Get files    
     $input = $this->request->getPost();

    $logo   = $this->request->getFile('logo');
    $banner = $this->request->getFile('banner');

    // Get businessId from POST
    $businessId = $input['businessId'] ?? null;

    if (!$businessId) {
        return $this->fail([
            'status' => false,
            'message' => 'Business ID is required.'
        ], 409);
    }

    // Validate files
    if (!$logo || !$logo->isValid() || !$banner || !$banner->isValid()) {
        return $this->fail([
            'status'  => false,
            'errors'  => [
                'logo'   => $logo ? $logo->getErrorString() : 'Logo file missing',
                'banner' => $banner ? $banner->getErrorString() : 'Banner file missing',
            ],
            'message' => 'Invalid file upload'
        ], 409);
    }

    // Generate random file names
    $logoName   = $logo->getRandomName();
    $bannerName = $banner->getRandomName();

    // Define upload path
    $uploadPath = ROOTPATH . 'writable/uploads/';
    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0777, true);
    }

    // Move files
    $logo->move($uploadPath, $logoName);
    $banner->move($uploadPath, $bannerName);

    // Load tenant-specific DB
    $tenantService = new \App\Libraries\TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    // Load model
    $logoModel = new \App\Models\LogoBannerModel($db);

    // Check if record exists
    $existing = $logoModel
        ->where('isDeleted', 0)
        ->where('businessId', $businessId)
        ->get()
        ->getRowArray();

    // Prepare data
    $data = [
        'logo'         => '/uploads/' . $logoName,
        'banner'       => '/uploads/' . $bannerName,
        'businessId'   => $businessId,
        'modifiedBy'   => 9,
        'modifiedDate' => date('Y-m-d H:i:s')
    ];

    // Update or insert
    if (is_array($existing) && isset($existing['id'])) {
        $logoModel->update($existing['id'], $data);
    } else {
        $data['createdBy']   = 9;
        $data['createdDate'] = date('Y-m-d H:i:s');
        $logoModel->insert($data);
    }

    // Response
    return $this->respond([
        'status'  => true,
        'message' => 'Logo and banner saved successfully.'
    ]);
}











// public function delete()
// {
//     $input = $this->request->getJSON();

//     // Validation rule
//     $rules = [
//         'documentId' => ['rules' => 'required|numeric'], // Assuming primary key column is `id`
//     ];

//     if ($this->validate($rules)) {
//         $tenantService = new \App\Libraries\TenantService();
//         $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

//         $model = new \App\Models\NaacModel($db);

//         $documentId = $input->documentId;
//         $document = $model->find($documentId);

//         if (!$document) {
//             return $this->fail(['status' => false, 'message' => 'Document not found'], 404);
//         }

//         // Optionally delete the file from the server
//         if (!empty($document['url'])) {
//             $filePath = WRITEPATH . 'uploads/naac/' . basename($document['url']);
//             if (file_exists($filePath)) {
//                 unlink($filePath); // Delete the file
//             }
//         }

//         // Delete DB record
//         if ($model->delete($documentId)) {
//             return $this->respond([
//                 'status' => true,
//                 'message' => 'Document deleted successfully'
//             ], 200);
//         } else {
//             return $this->fail([
//                 'status' => false,
//                 'message' => 'Failed to delete document'
//             ], 500);
//         }
//     } else {
//         return $this->fail([
//             'status' => false,
//             'errors' => $this->validator->getErrors(),
//             'message' => 'Invalid Inputs'
//         ], 409);
//     }
// }


}


