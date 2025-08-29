<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use Config\Database;
use App\Models\WebsiteModel;
use App\Models\IconModel;
use App\Models\ScrollingModel;
use App\Models\ElementModel;
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

    $tenantService = new \App\Libraries\TenantService();
    $db            = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $model         = new \App\Models\WebsiteModel($db);

    $parentMenuId = !empty($input['parentMenu']) ? (int) $input['parentMenu'] : 0;

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

    if ($input['menuType'] === 'URL') {
        $data['value'] = $input['linkValue'];

    } elseif ($input['menuType'] === 'File' && $file && $file->isValid() && !$file->hasMoved()) {
        $uploadPath = WRITEPATH . 'uploads/website/';
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }

        $newFileName  = $file->getRandomName();   
        $originalName = $file->getClientName();  

        $file->move($uploadPath, $newFileName);

        $data['value']        = base_url('writable/uploads/website/' . $newFileName);
        $data['originalName'] = $originalName;   
    }

    $model->insert($data);
    $menuId = $model->insertID(); 

    if ($input['menuType'] === 'Link') {
        $contentModel = new \App\Models\ContentModel($db);

        $contentData = [
            'menuId'       => $menuId,
            'businessId'   => $input['businessId'] ?? null,
            'menu'         => $input['menuName'],  
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


public function delete()
{
    $input = $this->request->getJSON();

    $rules = [
        'menuId' => ['rules' => 'required'], 
    ];

    if ($this->validate($rules)) {
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new WebsiteModel($db);

        $menuId = $input->menuId;

        // Find the menu
        $menu = $model->find($menuId);
        if (!$menu) {
            return $this->fail(['status' => false, 'message' => 'Menu not found'], 404);
        }

        $children = $model->where('parentMenuId', $menuId)
                          ->where('isDeleted', 0)
                          ->findAll();

        if (!empty($children)) {
            return $this->fail(['status' => false, 'message' => 'Cannot delete. This menu has child menus.'], 400);
        }

        $updateData = [
            'isDeleted' => 1,
        ];

        $deleted = $model->update($menuId, $updateData);

        if ($deleted) {
            return $this->respond(['status' => true, 'message' => 'Menu deleted successfully'], 200);
        } else {
            return $this->fail(['status' => false, 'message' => 'Failed to delete menu'], 500);
        }
    } else {
        return $this->fail([
            'status' => false,
            'errors' => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ], 409);
    }
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
    $input = $this->request->getPost();
    $logo  = $this->request->getFile('logo');
    $banners = $this->request->getFiles(); // get all uploaded files

    $businessId = $input['businessId'] ?? null;

    if (!$businessId) {
        return $this->fail([
            'status' => false,
            'message' => 'Business ID is required.'
        ], 409);
    }

    // Upload path
    $uploadPath = WRITEPATH . '/uploads/'; // direct server folder
    if (!is_dir($uploadPath)) mkdir($uploadPath, 0777, true);

    $logoPath = null;
    $bannerPaths = [];

    // Move logo if exists
    if ($logo && $logo->isValid()) {
        $logoName = $logo->getRandomName();
        $logo->move($uploadPath, $logoName);
        $logoPath = '/uploads/' . $logoName; // relative path
    }

    // Move banners if exist
    if (isset($banners['banners'])) {  // frontend must send 'banners[]'
        foreach ($banners['banners'] as $banner) {
            if ($banner->isValid()) {
                $bannerName = $banner->getRandomName();
                $banner->move($uploadPath, $bannerName);
                $bannerPaths[] = '/uploads/' . $bannerName; // relative path
            }
        }
    }

    // If neither logo nor banner uploaded
    if (!$logoPath && empty($bannerPaths)) {
        return $this->fail([
            'status' => false,
            'message' => 'No files uploaded'
        ], 409);
    }

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

    // Prepare data
    $data = [];
    if ($logoPath) $data['logo'] = $logoPath;
    if (!empty($bannerPaths)) $data['banner'] = json_encode($bannerPaths, JSON_UNESCAPED_SLASHES);

    $data['businessId']   = $businessId;
    $data['modifiedBy']   = 9;
    $data['modifiedDate'] = date('Y-m-d H:i:s');

    if (is_array($existing) && isset($existing['id'])) {
        $logoModel->update($existing['id'], $data);
    } else {
        $data['createdBy']   = 9;
        $data['createdDate'] = date('Y-m-d H:i:s');
        $logoModel->insert($data);
    }

    return $this->respond([
        'status'  => true,
        'message' => 'Files saved successfully.',
        'data'    => [
            'logo'    => $logoPath,
            'banners' => $bannerPaths
        ]
    ]);
}








public function getAllMenu()
{
    try {
        $tenantService = new \App\Libraries\TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

        $businessId = $this->request->getVar('businessId'); 

        if (!$businessId) {
            return $this->respond([
                'status' => false,
                'message' => 'Business ID is required.'
            ], 400);
        }

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



public function createElement()
{
    $input = $this->request->getPost();  

    
    $businessId = $this->request->getPost('businessId') ?? $input['businessId'] ?? null;

    // Validation
    $rules = [
        'displayName' => ['rules' => 'required'],
        'label'       => ['rules' => 'required'],
        'businessId'  => ['rules' => 'required|integer']
    ];

    if (! $this->validate($rules)) {
        return $this->fail([
            'status'  => false,
            'errors'  => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ], 400);
    }

    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    $model = new ElementModel($db);

    $data = [
        'displayName' => $input['displayName'],
        'label'       => $input['label'],
        'value'       => $input['value'] ?? null,
        'extra'       => $input['extra'] ?? null,
        'businessId'  => $businessId,
        'createdBy'   => $input['createdBy'] ?? 'system',
        'createdDate' => date('Y-m-d H:i:s'),
        'isActive'    => 1,
        'isDeleted'   => 0
    ];

    if ($model->insert($data)) {
        return $this->respond([
            'status'  => true,
            'message' => '🚀 Element Created Successfully',
            'data'    => $data
        ], 201);
    } else {
        return $this->fail([
            'status'  => false,
            'message' => 'Database Error'
        ], 500);
    }
}


  public function getAllIcon()
    {
           $tenantService = new TenantService();
           $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 
        $iconModel = new IconModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $iconModel->findAll()], 200);
    }


      public function getAllElement()
    {
           $tenantService = new TenantService();
           $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 
        $elementModel = new ElementModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $elementModel->findAll()], 200);
    }

    public function deleteElement()
{
    $input = $this->request->getJSON();

    if (!isset($input->itemId)) {
        return $this->fail([
            'status' => false,
            'message' => 'itemId is required'
        ], 400);
    }

    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $model = new ElementModel($db);

    $itemId = $input->itemId;
    $item = $model->find($itemId);

    if (!$item) {
        return $this->fail(['status' => false, 'message' => 'Item not found'], 404);
    }

    // Soft delete
    $deleted = $model->update($itemId, ['isDeleted' => 1]);

    if ($deleted) {
        return $this->respond(['status' => true, 'message' => 'Item deleted successfully'], 200);
    } else {
        return $this->fail(['status' => false, 'message' => 'Failed to delete item'], 500);
    }
}




    public function getScrollingPaging()
    {
        $input = $this->request->getJSON();

        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'scrollingId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
        

        $tenantService = new TenantService();
        
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load leadModel with the tenant database connection
        $scrollingModel = new ScrollingModel($db);

        $query = $scrollingModel;

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
        $scrollings = $query->paginate($perPage, 'default', $page);
        $pager = $scrollingModel->pager;

        $response = [
            "status" => true,
            "message" => "All  Data Fetched",
            "data" => $scrollings,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]
        ];

        return $this->respond($response, 200);
    }


    public function createScrolling()
{
    $input = $this->request->getPost();
    $file  = $this->request->getFile('file');

    $rules = [
        'name' => [],
        'type' => [],
    ];

    if ($input['type'] === 'URL') {
        $rules['value'] = ['rules' => 'required'];
    } elseif ($input['type'] === 'File') {
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

    $tenantService = new \App\Libraries\TenantService();
    $db            = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $model         = new \App\Models\ScrollingModel($db);

    $data = [
        'name'         => $input['name'],
        'type'         => $input['type'],
        'businessId'   => $input['businessId'] ?? null,
        'isActive'     => 1,
        'isDeleted'    => 0,
        'createdBy'    => 9,
        'modifiedBy'   => 0,
        'createdDate'  => date('Y-m-d H:i:s'),
        'modifiedDate' => date('Y-m-d H:i:s'),
    ];

    if ($input['type'] === 'URL') {
        $data['value'] = $input['value'];

    } elseif ($input['type'] === 'File' && $file && $file->isValid() && !$file->hasMoved()) {
        $uploadPath = WRITEPATH . 'uploads/scrolling/';
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }

        $newFileName  = $file->getRandomName();
        $originalName = $file->getClientName();

        $file->move($uploadPath, $newFileName);

        $data['value']        = base_url('writable/uploads/scrolling/' . $newFileName);
        $data['originalName'] = $originalName;
    }

    $model->insert($data);
    $id = $model->insertID();

    return $this->respond([
        'status'  => true,
        'message' => 'Scrolling item created successfully.',
        'data'    => array_merge($data, ['id' => $id])
    ], 200);
}


 public function deleteScrolling()
    {
        $input = $this->request->getJSON();
        

        $rules = [
            'scrollingId' => ['rules' => 'required'], 
        ];
    

        // Validate the input
        if ($this->validate($rules)) {
            $tenantService = new TenantService();
                // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new ScrollingModel($db);
    
            // Retrieve the vendor by vendorId
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));   $model = new ScrollingModel($db);

            // Retrieve the lead by leadId
            $scrollingId = $input->scrollingId;
            $scrolling = $model->find($scrollingId); // Assuming the find method retrieves the vendor
    
            
            $scrolling = $model->find($scrollingId); // Assuming find method retrieves the lead

            if (!$scrolling) {
                return $this->fail(['status' => false, 'message' => 'event not found'], 404);
            }
    
            // Soft delete by marking 'isDeleted' as 1
            $updateData = [
                'isDeleted' => 1,
            ];
    

            // Proceed to delete the lead
            $deleted = $model->delete($scrollingId);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'scrolling Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete scrolling'], 500);
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


      public function getAllScrolling()
    {
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 
        $scrollingModel = new ScrollingModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $scrollingModel->findAll()], 200);
    }

}


