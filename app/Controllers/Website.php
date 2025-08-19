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






       public function getContentPaging()
    {
        $input = $this->request->getJSON();

        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'contentId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
        

        $tenantService = new TenantService();
        
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load leadModel with the tenant database connection
        $contentModel = new ContentModel($db);

        $query = $contentModel;

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
        $contents = $query->paginate($perPage, 'default', $page);
        $pager = $contentModel->pager;

        $response = [
            "status" => true,
            "message" => "All  Data Fetched",
            "data" => $contents,
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
    $file  = $this->request->getFile('file');

    $rules = [
        'menuName' => [],
        'menuType' => [],
    ];

    // Conditional rules for 'linkValue' or 'file'
    if ($input['menuType'] === 'URL') {
        $rules['linkValue'] = ['rules' => 'required'];
    } elseif ($input['menuType'] === 'File') {
        $rules['file'] = [
            'rules' => 'uploaded[file]|max_size[file,10240]|mime_in[file,image/jpeg,image/jpg,image/png,application/pdf]'
        ];
    }

    if (!$this->validate($rules)) {
        return $this->fail([
            'status'  => false,
            'errors'  => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ], 409);
    }

    // ✅ Get tenant DB
    $tenantService = new \App\Libraries\TenantService();
    $db            = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $model         = new \App\Models\WebsiteModel($db);

    // ✅ Use parentMenu passed from form
    $parentMenuId = !empty($input['parentMenu']) ? (int) $input['parentMenu'] : 0;

    // ✅ Prepare data for insert
    $data = [
        'menuName'     => $input['menuName'],
        'menuType'     => $input['menuType'],
        'businessId'   => $input['businessId'] ?? null,
        'parentMenuId' => $parentMenuId,
        'isActive'     => 1,
        'isDeleted'    => 0,
        'createdBy'    => 9,
        'modifiedBy'   => 0,
        'createdDate'  => date('Y-m-d H:i:s'),
        'modifiedDate' => date('Y-m-d H:i:s'),
    ];

    // ✅ Set the value field based on menuType
    if ($input['menuType'] === 'URL') {
        $data['value'] = $input['linkValue'];

    } elseif ($input['menuType'] === 'File' && $file && $file->isValid() && !$file->hasMoved()) {
        $uploadPath = WRITEPATH . 'uploads/website/';
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }

        $newFileName  = $file->getRandomName();   // random save name
        $originalName = $file->getClientName();   // ✅ original filename

        $file->move($uploadPath, $newFileName);

        $data['value']        = base_url('writable/uploads/website/' . $newFileName);
        $data['originalName'] = $originalName;   // ✅ correct column name
    }

    // ✅ Insert menu
    $model->insert($data);
    $menuId = $model->insertID(); // Get newly inserted menu ID

    // ✅ Only if menuType is Link → insert into content_menu
    if ($input['menuType'] === 'Link') {
        $contentModel = new \App\Models\ContentModel($db);

        $contentData = [
            'menuId'       => $menuId,
            'businessId'   => $input['businessId'] ?? null,
            'menu'         => $input['menuName'],  // ✅ yaha menuName bhi save hoga
            'title'        => '',
            'content'      => '',
            'createdBy'    => 9,
            'createdDate'  => date('Y-m-d H:i:s'),
            'modifiedBy'   => 0,
            'modifiedDate' => date('Y-m-d H:i:s'),
            'isActive'     => 1,
            'isDeleted'    => 0,
        ];

        $contentModel->insert($contentData);
    }

    return $this->respond([
        'status'  => true,
        'message' => 'Menu created successfully.',
        'data'    => $data
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
        ->where('m.menuType', 'LINK')
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
    // Get POST data
    $input = $this->request->getPost();

    $logo   = $this->request->getFile('logo');
    $banner = $this->request->getFile('banner');

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

    // ✅ Upload path inside public so browser can access
    $uploadPath = ROOTPATH . 'public/uploads/';
    if (!is_dir($uploadPath)) mkdir($uploadPath, 0777, true);

    // Move files
    $logo->move($uploadPath, $logoName);
    $banner->move($uploadPath, $bannerName);

    // Prepare full URLs
    $baseUrl = rtrim(base_url(), '/'); // http://localhost:8080
    $logoUrl   = $baseUrl . '/uploads/' . $logoName;
    $bannerUrl = $baseUrl . '/uploads/' . $bannerName;

    // Load tenant DB
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

    // Prepare data (store relative path in DB)
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

    return $this->respond([
        'status'  => true,
        'message' => 'Logo and banner saved successfully.',
        'data'    => [
            'logo'   => $logoUrl,
            'banner' => $bannerUrl
        ]
    ]);
}







public function getAllMenu()
{
    try {
        // ✅ Get tenant DB
        $tenantService = new \App\Libraries\TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

        // ✅ Get businessId from GET or POST
        $businessId = $this->request->getVar('businessId'); // works for both GET & POST

        if (!$businessId) {
            return $this->respond([
                'status' => false,
                'message' => 'Business ID is required.'
            ], 400);
        }

        // ✅ Load model
        $model = new \App\Models\WebsiteModel($db);

        $menus = $model->where('businessId', $businessId)
                       ->where('isActive', 1)
                       ->where('isDeleted', 0)
                       ->orderBy('parentMenuId ASC, menuId ASC')
                       ->findAll();

        return $this->respond([
            'status' => true,
            'message' => 'Menus fetched successfully.',
            'data' => $menus
        ], 200);

    } catch (\Exception $e) {
        return $this->respond([
            'status' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}


    
public function getLogoBanner()
{
    // Get POST or JSON input
    $input = $this->request->getPost();
    if (empty($input)) $input = $this->request->getJSON(true);

    $businessId = $input['businessId'] ?? null;

    if (!$businessId) {
        return $this->fail([
            'status' => false,
            'message' => 'Business ID is required.'
        ], 409);
    }

    // Tenant DB
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    $logoModel = new LogoBannerModel($db);

    $records = $logoModel
        ->where('isDeleted', 0)
        ->where('businessId', $businessId)
        ->findAll();

    if (empty($records)) {
        return $this->respond([
            'status' => false,
            'message' => 'No logo/banner found.'
        ], 404);
    }

    // Base URL
    $baseUrl = rtrim(base_url(), '/');

    foreach ($records as &$record) {
        if (!empty($record['logo']) && !str_starts_with($record['logo'], 'http')) {
            $record['logo'] = $baseUrl . $record['logo'];
        }
        if (!empty($record['banner']) && !str_starts_with($record['banner'], 'http')) {
            $record['banner'] = $baseUrl . $record['banner'];
        }
    }

    return $this->respond([
        'status' => true,
        'data'   => $records
    ]);
}
}


