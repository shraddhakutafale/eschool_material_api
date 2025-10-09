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






    //    public function getContentPaging()
    // {
    //     $input = $this->request->getJSON();

    //     // Get the page number from the input, default to 1 if not provided
    //     $page = isset($input->page) ? $input->page : 1;
    //     $perPage = isset($input->perPage) ? $input->perPage : 10;
    //     $sortField = isset($input->sortField) ? $input->sortField : 'contentId';
    //     $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
    //     $search = isset($input->search) ? $input->search : '';
    //     $filter = $input->filter;
        

    //     $tenantService = new TenantService();
        
    //     $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    //     // Load leadModel with the tenant database connection
    //     $contentModel = new ContentModel($db);

    //     $query = $contentModel;

    //     if (!empty($filter)) {
    //         $filter = json_decode(json_encode($filter), true);

    //         foreach ($filter as $key => $value) {
    //             if (in_array($key, ['fName','lName','email', 'primaryMobileNo'])) {
    //                 $query->like($key, $value); // LIKE filter for specific fields
    //             }  else if ($key === 'createdDate') {
    //                 $query->where($key, $value); // Exact match filter for createdDate
    //             }
    //         }

    //         // Apply Date Range Filter (startDate and endDate)
    //         if (!empty($filter['startDate']) && !empty($filter['endDate'])) {
    //             $query->where('createdDate >=', $filter['startDate'])
    //                   ->where('createdDate <=', $filter['endDate']);
    //         }
    
    //         // Apply Last 7 Days Filter if requested
    //         if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last7days') {
    //             $last7DaysStart = date('Y-m-d', strtotime('-7 days'));  // 7 days ago from today
    //             $query->where('createdDate >=', $last7DaysStart);
    //         }
    
    //         // Apply Last 30 Days Filter if requested
    //         if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last30days') {
    //             $last30DaysStart = date('Y-m-d', strtotime('-30 days'));  // 30 days ago from today
    //             $query->where('createdDate >=', $last30DaysStart);
    //         }
    //     }
        
    //     $query->where('isDeleted',0)->where('businessId', $input->businessId)->where('menuType', 'URL'); // ✅ Add this line;
    //     // Apply Sorting
    //     if (!empty($sortField) && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
    //         $query->orderBy($sortField, $sortOrder);
    //     }

    //     // Get Paginated Results
    //     $contents = $query->paginate($perPage, 'default', $page);
    //     $pager = $contentModel->pager;

    //     $response = [
    //         "status" => true,
    //         "message" => "All  Data Fetched",
    //         "data" => $contents,
    //         "pagination" => [
    //             "currentPage" => $pager->getCurrentPage(),
    //             "totalPages" => $pager->getPageCount(),
    //             "totalItems" => $pager->getTotal(),
    //             "perPage" => $perPage
    //         ]
    //     ];

    //     return $this->respond($response, 200);
    // }

    public function getContentPaging()
{
    $input = $this->request->getJSON();

    // Get pagination & sorting values
    $page      = isset($input->page) ? $input->page : 1;
    $perPage   = isset($input->perPage) ? $input->perPage : 10;
    $sortField = isset($input->sortField) ? $input->sortField : 'contentId';
    $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
    $search    = isset($input->search) ? $input->search : '';
    $filter    = $input->filter;

    // Tenant DB connection
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    $contentModel = new ContentModel($db);
    $elementModel = new ElementModel($db);

    $query = $contentModel;

    // ✅ Apply filters if available
    if (!empty($filter)) {
        $filter = json_decode(json_encode($filter), true);

        foreach ($filter as $key => $value) {
            if (in_array($key, ['fName','lName','email','primaryMobileNo'])) {
                $query->like($key, $value); // partial match
            } else if ($key === 'createdDate') {
                $query->where($key, $value); // exact match
            }
        }

        // Date range filters
        if (!empty($filter['startDate']) && !empty($filter['endDate'])) {
            $query->where('createdDate >=', $filter['startDate'])
                  ->where('createdDate <=', $filter['endDate']);
        }

        if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last7days') {
            $last7DaysStart = date('Y-m-d', strtotime('-7 days'));
            $query->where('createdDate >=', $last7DaysStart);
        }

        if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last30days') {
            $last30DaysStart = date('Y-m-d', strtotime('-30 days'));
            $query->where('createdDate >=', $last30DaysStart);
        }
    }

    // ✅ Basic conditions
    $query->where('isDeleted', 0)
          ->where('businessId', $input->businessId)
          ->where('menuType', 'URL');

    // ✅ Sorting
    if (!empty($sortField) && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
        $query->orderBy($sortField, $sortOrder);
    }

    // ✅ Paginate
    $contents = $query->paginate($perPage, 'default', $page);
    $pager = $contentModel->pager;

    // ✅ Merge elements for each content item
    foreach ($contents as &$content) {
        $content['elements'] = $elementModel
            ->where('contentId', $content['contentId'])
            ->where('isDeleted', 0)
            ->findAll();
    }

    // ✅ Final response
    $response = [
        "status" => true,
        "message" => "All Data Fetched",
        "data" => $contents,
        "pagination" => [
            "currentPage" => $pager->getCurrentPage(),
            "totalPages"  => $pager->getPageCount(),
            "totalItems"  => $pager->getTotal(),
            "perPage"     => $perPage
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

    // if ($input['menuType'] === 'Link') {
    //     $contentModel = new \App\Models\ContentModel($db);

    //     $contentData = [
    //         'menuId'       => $menuId,
    //         'businessId'   => $input['businessId'] ?? null,
    //         'menu'         => $input['menuName'],  
    //         'title'        => '',
    //         'content'      => '',
    //         'createdBy'    => 9,
    //         'createdDate'  => date('Y-m-d H:i:s'),
    //         'modifiedBy'   => 0,
    //         'modifiedDate' => date('Y-m-d H:i:s'),
    //         'isActive'     => 1,
    //         'isDeleted'    => 0,
    //     ];

    //     $contentModel->insert($contentData);
    // }

    $contentModel = new \App\Models\ContentModel($db);

    $value = null;
    if ($input['menuType'] === 'URL') {
        $value = $input['linkValue'];
    } elseif ($input['menuType'] === 'File' && isset($data['value'])) {
        $value = $data['value']; // File URL
    } elseif ($input['menuType'] === 'Link') {
        $value = ''; // Placeholder if no link or file needed
    }

    
    $contentData = [
        'menuId'       => $menuId,
        'menuType'     => $input['menuType'],       // ✅ Add menuType
        'value'        => $value,                   // ✅ Save URL or file path
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



// public function createContent()
// {
//     $input = $this->request->getJSON(true);

//     $rules = [
//         'title' => 'required|string',
//         'content' => 'required|string',
//         'menuId' => 'required|integer',
//         'businessId' => 'required|integer',
//     ];

//     if (!$this->validate($rules)) {
//         return $this->fail([
//             'status' => false,
//             'errors' => $this->validator->getErrors(),
//             'message' => 'Invalid Inputs'
//         ], 409);
//     }

//     $tenantService = new \App\Libraries\TenantService();
//     $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

//     $contentModel = new \App\Models\ContentModel($db);

//     // You may want to update or create based on contentId or menuId
//     // For now, insert new record:

//     $data = [
//         'title' => $input['title'],
//         'content' => $input['content'],
//         'menuId' => $input['menuId'],
//         'businessId' => $input['businessId'],
//         'createdBy' => 9, // Adjust as needed
//         'createdDate' => date('Y-m-d H:i:s'),
//         'isActive' => 1,
//         'isDeleted' => 0,
//     ];

//     $insertId = $contentModel->insert($data);

//     if ($insertId) {
//         return $this->respond([
//             'status' => true,
//             'message' => 'Content saved successfully',
//             'contentId' => $insertId
//         ], 201);
//     } else {
//         return $this->failServerError('Failed to save content');
//     }
// }

public function createContent()
{
    $input = $this->request->getJSON(true);

    // Basic required fields validation
    $rules = [
        'menuId' => 'required|integer',
        'businessId' => 'required|integer',
    ];

    // Add contentId rule if updating (optional for create)
    if (isset($input['contentId'])) {
        $rules['contentId'] = 'integer';
    }

    if (!$this->validate($rules)) {
        return $this->fail([
            'status' => false,
            'errors' => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ], 409);
    }

    $tenantService = new \App\Libraries\TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $contentModel = new \App\Models\ContentModel($db);

    $data = [
        'title' => $input['title'],
        'content' => $input['content'],
        'menuId' => $input['menuId'],
        'businessId' => $input['businessId'],
        'isActive' => 1,
        'isDeleted' => 0,
    ];

    if (isset($input['contentId']) && !empty($input['contentId'])) {
        // Update existing content
        $data['modifiedBy'] = 9; // or current user
        $data['modifiedDate'] = date('Y-m-d H:i:s');

        $updated = $contentModel->update($input['contentId'], $data);

        if ($updated) {
            return $this->respond([
                'status' => true,
                'message' => 'Content updated successfully',
                'contentId' => $input['contentId']
            ], 200);
        } else {
            return $this->failServerError('Failed to update content');
        }
    } else {
        // Create new content
        $data['createdBy'] = 9; // or current user
        $data['createdDate'] = date('Y-m-d H:i:s');

        $insertId = $contentModel->insert($data);

        if ($insertId) {
            return $this->respond([
                'status' => true,
                'message' => 'Content saved successfully',
                'contentId' => $insertId
            ], 201);
        } else {
            return $this->failServerError('Failed to save content');
        }
    }
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

        // Load models
        $menuModel = new \App\Models\WebsiteModel($db);
        $contentModel = new \App\Models\ContentModel($db);
        $elementModel = new \App\Models\ElementModel($db);

        // Get menus
        $menus = $menuModel->where('businessId', $businessId)
                           ->where('isActive', 1)
                           ->where('isDeleted', 0)
                           ->orderBy('parentMenuId ASC, menuId ASC')
                           ->findAll();

        // Loop through each menu
        foreach ($menus as &$menu) {

            // Fetch contents for this menu
            $contents = $contentModel
                ->where('menuId', $menu['menuId'])
                ->where('isDeleted', 0)
                ->findAll();

            // For each content, fetch its elements (items)
            foreach ($contents as &$content) {
                $elements = $elementModel
                    ->where('contentId', $content['contentId'])
                    ->where('isDeleted', 0)
                    ->findAll();

                $content['elements'] = $elements;
            }

            // Attach contents (with elements) to menu
            $menu['contents'] = $contents;
        }

        // Return data
        return $this->respond([
            'status' => true,
            'message' => 'Menus with contents and items fetched successfully.',
            'data' => $menus
        ], 200);

    } catch (\Exception $e) {
        return $this->respond([
            'status' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}






// public function getAllMenu()
// {
//     try {
//         $tenantService = new \App\Libraries\TenantService();
//         $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

//         $businessId = $this->request->getVar('businessId'); 

//         if (!$businessId) {
//             return $this->respond([
//                 'status' => false,
//                 'message' => 'Business ID is required.'
//             ], 400);
//         }

//         $model = new \App\Models\WebsiteModel($db);

//         $menus = $model->where('businessId', $businessId)
//                        ->where('isActive', 1)
//                        ->where('isDeleted', 0)
//                        ->orderBy('parentMenuId ASC, menuId ASC')
//                        ->findAll();

//         return $this->respond([
//             'status' => true,
//             'message' => 'Menus fetched successfully.',
//             'data' => $menus
//         ], 200);

//     } catch (\Exception $e) {
//         return $this->respond([
//             'status' => false,
//             'message' => $e->getMessage()
//         ], 500);
//     }
// }




    public function createLogoBanner()
    {
        $input      = $this->request->getPost();
        $logo       = $this->request->getFile('logo');
        $banners    = $this->request->getFiles();
        $businessId = $input['businessId'] ?? null;

        if (!$businessId) {
            return $this->fail([
                'status'  => false,
                'message' => 'Business ID is required.'
            ], 409);
        }

        $key    = "Exiaa@11";
        $header = $this->request->getHeader("Authorization");
        $token  = null;
        if (!empty($header) && preg_match('/Bearer\s(\S+)/', $header, $matches)) {
            $token = $matches[1];
        }
        $decoded = JWT::decode($token, new Key($key, 'HS256'));

        $uploadPath = FCPATH . 'uploads/' . $decoded->tenantName . '/logoBanner/';
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }

        $logoPath    = null;
        $bannerPaths = [];

        if ($logo && $logo->isValid() && !$logo->hasMoved()) {
            $logoName = $logo->getRandomName();
            $logo->move($uploadPath, $logoName);
            $logoPath = $decoded->tenantName . '/logoBanner/' . $logoName; // relative path
        }

        if (isset($banners['banners'])) {
            foreach ($banners['banners'] as $banner) {
                if ($banner->isValid() && !$banner->hasMoved()) {
                    $bannerName = $banner->getRandomName();
                    $banner->move($uploadPath, $bannerName);
                    $bannerPaths[] = $decoded->tenantName . '/logoBanner/' . $bannerName;
                }
            }
        }

        if (!$logoPath && empty($bannerPaths)) {
            return $this->fail([
                'status'  => false,
                'message' => 'No files uploaded'
            ], 409);
        }

        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $logoModel = new \App\Models\LogoBannerModel($db);

        $existing = $logoModel
            ->where('isDeleted', 0)
            ->where('businessId', $businessId)
            ->get()
            ->getRowArray();

        $data = [];
        if ($logoPath) {
            $data['logo'] = $logoPath;
        }

        if (!empty($bannerPaths)) {
            // If old banners exist → merge
            if ($existing && !empty($existing['banner'])) {
                $oldBanners = explode(',', $existing['banner']);
                $bannerPaths = array_merge($oldBanners, $bannerPaths);
            }
            $data['banner'] = implode(',', $bannerPaths);
        }

        $data['businessId']   = $businessId;
        $data['modifiedBy']   = 9;
        $data['modifiedDate'] = date('Y-m-d H:i:s');

        if ($existing && isset($existing['logoId'])) {
            $logoModel->update($existing['logoId'], $data);
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

    public function getAllLogo($businessId)
    {
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig(
            $this->request->getHeaderLine('X-Tenant-Config')
        );

        $builder = $db->table('logo_mst');
        $builder->select('*');
        $builder->where('businessId', $businessId);
        $builder->where('isDeleted', 0);

        $result = $builder->get()->getRowArray();

        if ($result) {
            $result['banners'] = [];
            if (!empty($result['banner'])) {
                $result['banners'] = array_map('trim', explode(',', $result['banner']));
            }
            unset($result['banner']); // remove raw string
        }

        return $this->respond([
            'status' => true,
            'data'   => $result
        ]);
    }



public function getLogoBanner()
{
    // Get POST or JSON input
    $input = $this->request->getPost() ?: $this->request->getJSON(true);
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

    foreach ($records as &$rec) {
        // Keep logo relative path only
        if (!empty($rec['logo'])) {
            $rec['logo'] = trim($rec['logo']); // e.g., "exEducationTraining/logoBanner/xxx.jpeg"
        }

        // Keep banners relative path only
        if (!empty($rec['banner'])) {
            $banners = json_decode($rec['banner'], true);

            if (!is_array($banners)) {
                $banners = explode(',', $rec['banner']);
            }

            $rec['banner'] = array_map(function ($b) {
                return trim($b); // e.g., "exEducationTraining/logoBanner/xxx.jpg"
            }, $banners);
        } else {
            $rec['banner'] = [];
        }
    }

    return $this->respond([
        'status' => true,
        'data'   => $records
    ]);
}

// public function createElement()
// {
//     $input = $this->request->getPost();
//     $files = $this->request->getFiles();

//     $businessId = $input['businessId'] ?? null;

//     // ✅ Validate required fields
//     $rules = [
//         'businessId'  => ['rules' => 'required|integer']
//     ];

//     if (! $this->validate($rules)) {
//         return $this->fail([
//             'status'  => false,
//             'errors'  => $this->validator->getErrors(),
//             'message' => 'Invalid Inputs'
//         ], 400);
//     }

//     // ✅ Parse the JSON value
//     $value = json_decode($input['value'] ?? '{}', true);

//     // ✅ Handle gallery images
//     if (isset($value['type']) && $value['type'] === 'gallery' && isset($files['images'])) {
//         $uploadedImagePaths = [];
//         foreach ($files['images'] as $img) {
//             if ($img->isValid() && !$img->hasMoved()) {
//                 $newName = $img->getRandomName();
//                 $img->move(WRITEPATH . 'uploads/gallery/', $newName);
//                 $uploadedImagePaths[] = base_url('writable/uploads/gallery/' . $newName);
//             }
//         }
//         $value['selectedImages'] = implode(', ', $uploadedImagePaths);
//     }

//     // ✅ Handle PDF upload
//     if (isset($value['type']) && $value['type'] === 'pdf' && isset($files['pdf'])) {
//         $pdf = $files['pdf'];
//         if ($pdf->isValid() && !$pdf->hasMoved()) {
//             $newName = $pdf->getRandomName();
//             $pdf->move(WRITEPATH . 'uploads/pdf/', $newName);
//             $value['selectedPdf'] = base_url('writable/uploads/pdf/' . $newName);
//         }
//     }

//     // ✅ Prepare DB record
//     $data = [
//         'value'       => json_encode($value),
//         'extra'       => $input['extra'] ?? null,
//         'businessId'  => $businessId,
//         'createdDate' => date('Y-m-d H:i:s'),
//         'isActive'    => 1,
//         'isDeleted'   => 0
//     ];

//     $tenantService = new TenantService();
//     $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
//     $model = new ElementModel($db);

//     // ✅ Save to DB
//     if ($model->insert($data)) {
//         return $this->respond([
//             'status'  => true,
//             'message' => '🚀 Element Created Successfully',
//             'data'    => $data
//         ], 201);
//     } else {
//         return $this->fail([
//             'status'  => false,
//             'message' => 'Database Error'
//         ], 500);
//     }
// }



    public function createElement()
{
    $input = $this->request->getPost();
    $files = $this->request->getFiles();

    $type = strtolower($input['displayName'] ?? '');
    $value = '';

    // Get tenant for folder
    $key = "Exiaa@11";
    $header = $this->request->getHeaderLine("Authorization");
    $token = null;
    if ($header && preg_match('/Bearer\s(\S+)/', $header, $m)) {
        $token = $m[1];
    }
    $decoded = $token ? JWT::decode($token, new Key($key, 'HS256')) : null;
    $tenant = $decoded->tenantName ?? 'default';

    $uploadPath = FCPATH . 'uploads/' . $tenant . '/contentPdf/';
    if (!is_dir($uploadPath)) mkdir($uploadPath, 0777, true);

    // ✅ PDF
    if ($type === 'pdf' && isset($files['pdfFile'])) {
        $pdf = $files['pdfFile'];
        if ($pdf->isValid() && !$pdf->hasMoved()) {
            $newName = $pdf->getRandomName();
            $pdf->move($uploadPath, $newName);
            $value = $tenant . '/contentPdf/' . $newName;
        }
    }

    // ✅ Text
    elseif ($type === 'text') {
        $value = $input['value'] ?? '';
    }

    // ✅ Gallery
    elseif ($type === 'gallery' && isset($files['galleryFiles'])) {
        $galleryPath = FCPATH . 'uploads/' . $tenant . '/contentImages/';
        if (!is_dir($galleryPath)) mkdir($galleryPath, 0777, true);

        $uploadedPaths = [];
        foreach ($files['galleryFiles'] as $img) {
            if ($img->isValid() && !$img->hasMoved()) {
                $newName = $img->getRandomName();
                $img->move($galleryPath, $newName);
                $uploadedPaths[] = $tenant . '/contentImages/' . $newName;
            }
        }
        $value = implode(',', $uploadedPaths);
    }

    // Save data
    $data = [
        'displayName' => $input['displayName'],
        'label'       => $input['label'],
        'value'       => $value,
        'extra'       => $input['extra'] ?? null,
        'businessId'  => $input['businessId'],
        'contentId'   => $input['contentId'],
        'createdBy'   => $input['createdBy'] ?? 'system',
        'createdDate' => date('Y-m-d H:i:s'),
        'isActive'    => 1,
        'isDeleted'   => 0
    ];

    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $model = new ElementModel($db);

    if ($model->insert($data)) {
        return $this->respond([
            'status'  => true,
            'message' => '✅ Element created successfully',
            'data'    => $data
        ], 201);
    }

    return $this->fail([
        'status'  => false,
        'message' => 'Database Error'
    ], 500);
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
    $contentModel = new ContentModel($db);

    // ✅ Fetch all content records (not deleted)
    $contents = $contentModel->where('isDeleted', 0)->findAll();

    foreach ($contents as &$content) {
        $content['elements'] = $elementModel
            ->where('contentId', $content['contentId'])
            ->where('isDeleted', 0)
            ->orderBy('priority', 'ASC')
            ->findAll();
    }

    return $this->respond([
        "status" => true,
        "message" => "All data fetched with elements",
        "data" => $contents,
    ], 200);
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

    // Validation rules
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

    // ✅ Decode tenantName from JWT
    $key    = "Exiaa@11"; // your JWT key
    $header = $this->request->getHeader("Authorization");
    $token  = null;
    if (!empty($header) && preg_match('/Bearer\s(\S+)/', $header, $matches)) {
        $token = $matches[1];
    }

    $decoded    = JWT::decode($token, new Key($key, 'HS256'));
    $tenantName = $decoded->tenantName ?? 'defaultTenant';

    // ✅ Tenant database connection
    $tenantService = new \App\Libraries\TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $model = new \App\Models\ScrollingModel($db);

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
        // ✅ Tenant-wise scrolling upload folder
        $uploadPath = FCPATH . 'uploads/' . $tenantName . '/scrolling/';
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }

        $newFileName  = time() . '_' . $file->getRandomName();
        $originalName = $file->getClientName();

        $file->move($uploadPath, $newFileName);

        // ✅ Relative path DB me save
        $data['value']        = $tenantName . '/scrolling/' . $newFileName;
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

//     public function updateContentOrder()
// {
//     $input = $this->request->getJSON(true);

//     if (!is_array($input)) {
//         return $this->fail('Invalid input');
//     }

//     $tenantService = new \App\Libraries\TenantService();
//     $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
//     $contentModel = new \App\Models\ContentModel($db);

//     foreach ($input as $item) {
//         if (isset($item['contentId']) && isset($item['priority'])) {
//             $contentModel->update($item['contentId'], ['priority' => $item['priority']]);
//         }
//     }

//     return $this->respond(['status' => true, 'message' => 'Order updated']);
// }

public function updateContentOrder()
{
    $input = $this->request->getJSON(true);

    if (!is_array($input)) {
        return $this->failValidationErrors('Invalid data format.');
    }

    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $contentModel = new ElementModel($db);

    foreach ($input as $item) {
        if (!isset($item['itemId']) || !isset($item['contentId'])) {
            continue; // skip invalid rows
        }

        $updateData = [];

        if (isset($item['priority'])) {
            $updateData['priority'] = $item['priority'];
        }

        if (isset($item['modifiedBy'])) {
            $updateData['modifiedBy'] = $item['modifiedBy'];
        }

        $updateData['modifiedDate'] = date('Y-m-d H:i:s');

        if (!empty($updateData)) {
            $contentModel->where('itemId', $item['itemId'])
                         ->where('contentId', $item['contentId'])
                         ->set($updateData)
                         ->update();
        }
    }

    return $this->respond([
        'status'  => true,
        'message' => 'Priorities updated successfully.'
    ]);
}

public function deleteContent()
{
    $input = $this->request->getJSON(true);

    if (!isset($input['contentId'])) {
        return $this->fail(['status' => false, 'message' => 'Content ID is required'], 400);
    }

    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $model = new ContentModel($db);

    $contentId = $input['contentId'];
    $content = $model->find($contentId);

    if (!$content) {
        return $this->fail(['status' => false, 'message' => 'Content not found'], 404);
    }

    $deleted = $model->delete($contentId);

    if ($deleted) {
        return $this->respond(['status' => true, 'message' => 'Content deleted successfully']);
    } else {
        return $this->fail(['status' => false, 'message' => 'Failed to delete content'], 500);
    }
}


}


