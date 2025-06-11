<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\ItemModel;
use App\Models\ItemTypeModel;
use App\Models\ItemCategory;
use App\Models\ItemSubCategory;
use App\Models\ItemGroup;
use App\Models\Unit;
use Config\Database;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;
use App\Models\SlideModel;


class Item extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    
        // Load ItemModel with the tenant database connection
        $itemModel = new ItemModel($db);
    
        $items = $itemModel
            ->select('item_mst.*, item_category.itemCategoryName, item_mst.discount,item_mst.discountType, item_category.gstTax')  
            ->join('item_category', 'item_category.itemCategoryId = item_mst.itemCategoryId', 'left')  
            ->where('item_mst.isDeleted', 0)  
            ->findAll();

    
        // Prepare response
        $response = [
            "status" => true,
            "message" => "All Data Fetched",
            "data" => $items,
        ];
    
        return $this->respond($response, 200);
    }
    
    

    public function getAllUnit()
    {
        // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    
        // Load UserModel with the tenant database connection
        $itemModel = new Unit($db);
        $response = [
            "status" => true,
            "message" => "All Data Fetched",
            "data" => $itemModel->findAll(),
        ];
        return $this->respond($response, 200);
    }

    public function getItemsPaging()
    {
        $input = $this->request->getJSON();

        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'itemId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;

        $tenantService = new TenantService();
        
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load StaffModel with the tenant database connection
        $itemModel = new ItemModel($db);

        // Initialize query with 'isDeleted' condition
        $query = $itemModel->where('isDeleted', 0)->where('itemTypeId', $input->itemTypeId)->where('businessId', $input->businessId); // Apply the deleted check at the beginning

        // Apply search filter for itemName and mrp
        if (!empty($search)) {
            $query->like('itemName', $search)
                ->orLike('mrp', $search);
        }

        // Apply additional filters if provided
        if (!empty($filter)) {
            $filter = json_decode(json_encode($filter), true);
            
            foreach ($filter as $key => $value) {
                if (in_array($key, ['itemName', 'mrp', 'sku'])) {
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

        // Apply sorting
        $query->orderBy('itemId', 'desc');

        // Execute the query with pagination
        $item = $query->paginate($perPage, 'default', $page);

        // Get pagination data
        $pager = $itemModel->pager;

        // Prepare the response
        $response = [
            "status" => true,
            "message" => "All item Data Fetched",
            "data" => $item,
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
        // Retrieve POST data
        $input = $this->request->getPost();
    
        // Validate required fields
        $rules = [
            'itemName' => ['rules' => 'required']
        ];
    
        if (!$this->validate($rules)) {
            return $this->fail([
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs',
            ], 409);
        }
    
        // JWT decode from header
        $key = "Exiaa@11";
        $header = $this->request->getHeader("Authorization");
        $token = null;
    
        if (!empty($header) && preg_match('/Bearer\s(\S+)/', $header, $matches)) {
            $token = $matches[1];
        }
    
        try {
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
        } catch (\Exception $e) {
            return $this->failUnauthorized('Invalid Token');
        }
    
        // ✅ Handle cover image (base64)
        if (!empty($input['coverImage'])) {
            $coverImageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $input['coverImage']));
            $coverImagePath = FCPATH . 'uploads/' . $decoded->tenantName . '/itemImages/';
            if (!is_dir($coverImagePath)) {
                mkdir($coverImagePath, 0777, true);
            }
    
            $coverImageName = uniqid() . '.png';
            file_put_contents($coverImagePath . $coverImageName, $coverImageData);
            $input['coverImage'] = $decoded->tenantName . '/itemImages/' . $coverImageName;
        }
    
        // ✅ Handle multiple product images (array of base64)
        if (!empty($input['productImages']) && is_array($input['productImages'])) {
            $imageUrls = [];
            $productImagePath = FCPATH . 'uploads/' . $decoded->tenantName . '/itemSlideImages/';
            if (!is_dir($productImagePath)) {
                mkdir($productImagePath, 0777, true);
            }
    
            foreach ($input['productImages'] as $base64Image) {
                if (empty($base64Image)) continue;
    
                $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Image));
                $imageName = uniqid() . '.png';
                file_put_contents($productImagePath . $imageName, $imageData);
                $imageUrls[] = $decoded->tenantName . '/itemSlideImages/' . $imageName;
            }
    
            if (!empty($imageUrls)) {
                $input['productImages'] = implode(',', $imageUrls);
            }
        }
    
        // ✅ Insert into database
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new ItemModel($db);
        $model->insert($input);
    
        return $this->respond(['status' => true, 'message' => 'Item Added Successfully'], 200);
    }






    public function update()
{
    $input = $this->request->getPost();

    $rules = [
        'itemId' => ['rules' => 'required|numeric'],
    ];

    if (!$this->validate($rules)) {
        return $this->fail([
            'status' => false,
            'errors' => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ], 409);
    }

    // Get DB config
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $model = new ItemModel($db);

    $itemId = $input['itemId'];
    $item = $model->find($itemId);

    if (!$item) {
        return $this->fail(['status' => false, 'message' => 'Item not found'], 404);
    }

    // JWT Auth
    $key = "Exiaa@11";
    $header = $this->request->getHeader("Authorization");
    $token = null;

    if (!empty($header) && preg_match('/Bearer\s(\S+)/', $header, $matches)) {
        $token = $matches[1];
    }

    try {
        $decoded = JWT::decode($token, new Key($key, 'HS256'));
    } catch (\Exception $e) {
        return $this->failUnauthorized('Invalid Token');
    }

    // Prepare update data
    $updateData = [
        'itemName' => $input['itemName'],
        'itemCategoryId' => $input['itemCategoryId'],
        'mrp' => $input['mrp'],
        'discountType' => $input['discountType'],
        'discount' => $input['discount'],
        'barcode' => $input['barcode'],
        'description' => $input['description'],
        'itemTypeId' => $input['itemTypeId'],
        'sku' => $input['sku'],
        'hsnCode' => $input['hsnCode'],
        'feature' => $input['feature'],
        'unitName' => $input['unitName'],
    ];

    // ✅ Handle cover image update
    if (!empty($input['coverImage'])) {
        $coverImageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $input['coverImage']));
        $coverImagePath = FCPATH . 'uploads/' . $decoded->tenantName . '/itemImages/';
        if (!is_dir($coverImagePath)) {
            mkdir($coverImagePath, 0777, true);
        }

        // Delete old cover image if exists
        if (!empty($item['coverImage'])) {
            $oldCoverImage = FCPATH . 'uploads/' . $item['coverImage'];
            if (file_exists($oldCoverImage)) {
                unlink($oldCoverImage);
            }
        }

        $coverImageName = uniqid() . '.png';
        file_put_contents($coverImagePath . $coverImageName, $coverImageData);
        $updateData['coverImage'] = $decoded->tenantName . '/itemImages/' . $coverImageName;
    }

    // ✅ Handle multiple product images
    $existingImages = [];
    if (!empty($item['productImages'])) {
        $existingImages = explode(',', $item['productImages']);
    }

    $newImages = [];
    $productImagePath = FCPATH . 'uploads/' . $decoded->tenantName . '/itemSlideImages/';
    if (!is_dir($productImagePath)) {
        mkdir($productImagePath, 0777, true);
    }

    if (!empty($input['productImages']) && is_array($input['productImages'])) {
        foreach ($input['productImages'] as $img) {
            if (empty($img)) continue;

            // If base64 image
            if (str_starts_with($img, 'data:image')) {
                $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $img));
                $imageName = uniqid() . '.png';
                file_put_contents($productImagePath . $imageName, $imageData);
                $newImages[] = $decoded->tenantName . '/itemSlideImages/' . $imageName;
            }
            // If already existing image URL, just keep
            elseif (!str_contains($img, 'base64')) {
                $newImages[] = $img;
            }
        }
    }

    // Merge old + new images (ensure no duplicates)
    $finalImageList = array_unique(array_merge($existingImages, $newImages));

    $updateData['productImages'] = implode(',', $finalImageList);

    // 🔄 Perform the update
    $updated = $model->update($itemId, $updateData);

    if ($updated) {
        return $this->respond(['status' => true, 'message' => 'Item Updated Successfully'], 200);
    } else {
        return $this->fail(['status' => false, 'message' => 'Failed to update item'], 500);
    }
}



    
    public function delete()
    {
        $input = $this->request->getJSON();
        
        // Validation rules for the course
        $rules = [
            'itemId' => ['rules' => 'required'], // Ensure eventId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
           
            // Insert the product data into the database
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new ItemModel($db);

            // Retrieve the course by eventId
            $itemId = $input->itemId;
            $item = $model->find($itemId); // Assuming find method retrieves the course

            if (!$item) {
                return $this->fail(['status' => false, 'message' => 'Course not found'], 404);
            }

            $updateData = [
                'isDeleted' => 1,
            ];
            $deleted = $model->update($itemId, $updateData);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Item Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete course'], 500);
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

    public function getAllItemCategory()
    {
        // 🧪 Debug logs
        log_message('error', 'Inside getAllItemCategory function');
    
        $header = $this->request->getHeaderLine('X-Tenant-Config');
        log_message('error', 'Tenant Header: ' . $header);
    
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($header);
    
        if (!$db) {
            log_message('error', 'ERROR: Database config is NULL');
            return $this->respond(['status' => false, 'message' => 'Database config error'], 500);
        }
    
        try {
            $model = new ItemCategory($db);
            $itemCategories = $model->where('isDeleted', 0)->findAll();
            return $this->respond(['status' => true, 'message' => 'Data fetched successfully', 'data' => $itemCategories], 200);
        } catch (\Throwable $e) {
            log_message('error', 'Exception: ' . $e->getMessage());
            return $this->respond(['status' => false, 'message' => 'Internal Server Error'], 500);
        }
    }
    

   public function createCategory()
    {
        // Get input based on content type
        $input = $this->request->getPost();
        if (empty($input)) {
            $input = $this->request->getJSON(true);
        }

        $rules = [
            'itemCategoryName' => ['rules' => 'required'],
        ];

        if (!$this->validate($rules)) {
            return $this->fail([
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ], 409);
        }

        // JWT and tenant handling
        $key = "Exiaa@11";
        $header = $this->request->getHeader("Authorization");
        $token = $header ? (preg_match('/Bearer\s(\S+)/', $header, $matches) ? $matches[1] : null) : null;
        
        if (!$token) {
            return $this->fail('Authorization token missing', 401);
        }

        try {
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
        } catch (Exception $e) {
            return $this->fail('Invalid token', 401);
        }

        // Handle file upload
        $coverImage = $this->request->getFile('coverImage');
        if ($coverImage && $coverImage->isValid() && !$coverImage->hasMoved()) {
            $coverImagePath = FCPATH . 'uploads/' . $decoded->tenantName . '/itemCategoryImages/';
            if (!is_dir($coverImagePath)) {
                mkdir($coverImagePath, 0777, true);
            }
            $coverImageName = $coverImage->getRandomName();
            $coverImage->move($coverImagePath, $coverImageName);
            $input['coverImage'] = $decoded->tenantName . '/itemCategoryImages/' . $coverImageName;
        }

        // Database operations
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new ItemCategory($db);

        // Final validation before insert
        if (empty($input)) {
            return $this->fail('No valid data to insert', 400);
        }

        $itemCategory = $model->insert($input);
        return $this->respond([
            'status' => true,
            'message' => "Item Category Added Successfully",
            'data' => $itemCategory
        ], 200);
    }

    public function updateCategory()
    {
        $input = $this->request->getPost();
    
        // Validation rules for the item
        $rules = [
            'itemCategoryId' => ['rules' => 'required|numeric'], // Ensure itemId is provided and is numeric
        ];
    
        // Validate the input
        if ($this->validate($rules)) {
            // Insert the product data into the database
            $tenantService = new TenantService();
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    
            $model = new ItemCategory($db);
    
            // Retrieve the item by itemId
            $itemCategoryId = $input['itemCategoryId'];
            $item = $model->find($itemCategoryId);
    
            if (!$item) {
                return $this->fail(['status' => false, 'message' => 'Item not found'], 404);
            }
    
            // Prepare the data to be updated
                $updateData = [
                    'itemCategoryName'=> $input['itemCategoryName'],	
                    'gstTax'=> $input['gstTax'],
                    'itemTypeId'=> $input['itemTypeId'],				
                    'description'=> $input['description'],	
                    'hsnCode'=> $input['hsnCode'],
                ];
    
                    // Handle cover image update as base64
                    if (isset($input['coverImage']) && !empty($input['coverImage'])) {
                        $coverImageData = base64_decode(preg_replace('#^data:image/png;base64,#i', '', $input['coverImage']));
    
                        // Handle cover image upload
                        $key = "Exiaa@11";
                        $header = $this->request->getHeader("Authorization");
                        $token = null;
    
                        // Extract the token from the header
                        if (!empty($header)) {
                            if (preg_match('/Bearer\s(\S+)/', $header, $matches)) {
                                $token = $matches[1];
                            }
                        }
    
                        $decoded = JWT::decode($token, new Key($key, 'HS256'));
                        $coverImagePath = FCPATH . 'uploads/' . $decoded->tenantName . '/itemImages/';
    
                        if (!is_dir($coverImagePath)) {
                            mkdir($coverImagePath, 0777, true);
                        }
    
                        $coverImageName = uniqid() . '.png'; // Ensure the file extension is .png
                        file_put_contents($coverImagePath . $coverImageName, $coverImageData);
    
                        $input['coverImage'] = $decoded->tenantName . '/itemImages/' . $coverImageName;
                        $updateData['coverImage'] = $input['coverImage'];
                    }
    
                
    
    
            // Update the item with new data
            $updated = $model->update($itemCategoryId, $updateData);
    
            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Item Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update item'], 500);
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

    public function deleteCategory()
    {
        $input = $this->request->getJSON();
        
        // Validation rules for the course
        $rules = [
            'itemCategoryId' => ['rules' => 'required'], // Ensure eventId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
           
            // Insert the product data into the database
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new ItemCategory($db);

            // Retrieve the course by eventId
            $itemCategoryId = $input->itemCategoryId;
            $item = $model->find($itemCategoryId); // Assuming find method retrieves the course

            if (!$item) {
                return $this->fail(['status' => false, 'message' => 'Course not found'], 404);
            }

            $updateData = [
                'isDeleted' => 1,
            ];
            $deleted = $model->update($itemCategoryId, $updateData);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Item Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete course'], 500);
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


    public function getAllItemSubCategory()
    {
        $data = $this->request->getJSON();
        // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    
        // Load ItemCategory model with the tenant database connection
        $itemSubCategory = new ItemSubCategory($db);
        
        $itemSubCategories = $itemSubCategory->select('item_sub_category.*, item_category.itemCategoryId as itemCategoryId, item_category.itemCategoryName as itemCategoryName, item_category.businessId as businessId')
        ->join('item_category', 'item_category.itemCategoryId = item_sub_category.itemCategoryId')
        ->where('item_sub_category.isDeleted', 0)
        ->where('item_category.businessId', $data->businessId)->where('item_category.isDeleted', 0)
        ->findAll();

        $response = [
            "status" => true,
            "message" => "All Data Fetched",
            "data" => $itemSubCategories,
        ];
    
        return $this->respond($response, 200);
    }

    public function createSubCategory()
    {
        // Retrieve the input data from the request
        $input = $this->request->getJSON();

        // Define validation rules for required fields
        $rules = [
            'itemSubCategoryName' => ['rules' => 'required'],
            'itemCategoryId' => ['rules' => 'required|numeric'],
        ];

        if ($this->validate($rules)) {
            // Insert the product data into the database
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new ItemSubCategory($db);
            $itemSubCategory = $model->insert($input);
            return $this->respond(["status" => true, "message" => "Item Sub Category Added Successfully", "data" => $itemSubCategory], 200);
        }else{
            $response = [
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ];
            return $this->fail($response, 409);
        }
    }

    public function updateSubCategory()
    {
        $input = $this->request->getPost();
    
        // Validation rules for the item
        $rules = [
            'itemSubCategoryId' => ['rules' => 'required|numeric'], // Ensure itemId is provided and is numeric
        ];
    
        // Validate the input
        if ($this->validate($rules)) {
            // Insert the product data into the database
            $tenantService = new TenantService();
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    
            $model = new ItemSubCategory($db);
    
            // Retrieve the item by itemId
            $itemSubCategoryId = $input['itemSubCategoryId'];
            $item = $model->find($itemSubCategoryId);
    
            if (!$item) {
                return $this->fail(['status' => false, 'message' => 'Item not found'], 404);
            }
    
            // Prepare the data to be updated
            $updateData = [
                'itemSubCategoryName'=> $input['itemSubCategoryName'],	
                'itemCategoryId'=> $input['itemCategoryId'],				
                'description'=> $input['description'],	
                'hsnCode'=> $input['hsnCode'],
            ];
    
            // Update the item with new data
            $updated = $model->update($itemSubCategoryId, $updateData);
    
            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Item Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update item'], 500);
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

    public function deleteSubCategory()
    {
        $input = $this->request->getPost();
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new ItemSubCategory($db);
        $itemSubCategoryId = $input['itemSubCategoryId'];
        $deleted = $model->delete($itemSubCategoryId);
        if ($deleted) {
            return $this->respond(['status' => true, 'message' => 'Item Deleted Successfully'], 200);
        } else {
            return $this->fail(['status' => false, 'message' => 'Failed to delete item'], 500);
        }
    }

    public function getAllItemGroup()
    {
        $data = $this->request->getJSON();
        // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    
        // Load ItemGroup model with the tenant database connection
        $itemGroup = new ItemGroup($db);
        
        $itemGroups = $itemGroup->where('isDeleted', 0)->where('businessId',$data->businessId)->findAll();

        $response = [
            "status" => true,
            "message" => "All Data Fetched",
            "data" => $itemGroups,
        ];
    
        return $this->respond($response, 200);
    }

    public function getAllCategoryWeb()
    {
        $input = $this->request->getJSON();

         // Insert the product data into the database
         $tenantService = new TenantService();
         // Connect to the tenant's database
         $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new ItemCategory($db);
        $itemCategories = $model->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $itemCategories], 200);
    }

    public function getAllItemByCategoryWeb()
    {
        // Retrieve categoryId from URI segment
        $categoryId = $this->request->getUri()->getSegment(1);
        
        if (!$categoryId) {
            return $this->respond(["status" => false, "message" => "Category ID not provided."], 400);
        }
    
         // Insert the product data into the database
         $tenantService = new TenantService();
         // Connect to the tenant's database
         $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    
        // Load ItemCategory model with the tenant database connection
        $category = new ItemCategory($db);
        $categories = $category->findAll(); // This loads all categories
    
        // Load ItemModel with the tenant database connection
        $model = new ItemModel($db);
    
        // Fetch items by category ID
        try {
            // Use the where method directly
            $items = $model->where('itemCategoryId', $categoryId)->findAll();
        } catch (\Exception $e) {
            return $this->respond(["status" => false, "message" => "Failed to fetch items: " . $e->getMessage()], 500);
        }
    
        // Return the response
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $items], 200);
    }

    public function getItemByItemTypeId($itemTypeId){

        // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new ItemModel($db);
        $items = $model->where('itemTypeId', $itemTypeId)->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $items], 200);

    }

    public function getAllItemByTagWeb()
    {
        $tag = $this->request->getSegment(1);

         // Insert the product data into the database
         $tenantService = new TenantService();
         // Connect to the tenant's database
         $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

        // Load UserModel with the tenant database connection
        $model = new ItemModel($db);
        $items = $model->findAllByTag($tag);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $items], 200);
    }

   

    public function getFourItemProductByCategory()
    {
        try {
            $tenantService = new TenantService();
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    
            $categoryModel = new ItemCategory($db);
            $categories = $categoryModel->where('itemTypeId', 3)->where('isDeleted', 0)->where('isActive', 1)->findAll(); // Fetch all categories')->where('isActive', 1)->where('isDeleted', 0)->findAll(); // Fetch all categories

            if (empty($categories)) {
                return $this->respond(["status" => false, "message" => "No categories found"], 404);
            }

            $categoryList = array();

            foreach ($categories as $category) {;
                $category['items'] = []; // Initialize items array for each category
    
                $itemModel = new ItemModel($db);
                // Fetch 4 random items by category ID that are not deleted
                $items = $itemModel->where('itemCategoryId', $category['itemCategoryId'])
                ->where('isActive', 1)
                ->where('isDeleted', 0)
                ->orderBy('RAND()') // Random order
                ->findAll(4); // Limit to 4 items
                $category['items'] = $items;
                array_push($categoryList, $category);
            }

            return $this->respond(["status" => true, "message" => "Items fetched successfully", "data" => $categoryList], 200);
        } catch (\Exception $e) {
            return $this->failServerError("Server Error: " . $e->getMessage());
        }
    }
    

    public function show()
    {
        $input = $this->request->getJSON();
        $itemId = $input->itemId;
        log_message('error', 'Item Id: ' . $itemId);
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new ItemModel($db);
        $item = $model->find($itemId);
        $category = new ItemCategory($db);
        $item['category'] = $category->find($item['itemCategoryId']);
        if (!$item) {
            return $this->respond(["status" => false, "message" => "Item not found"], 404);
        }
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $item], 200);
    }

    public function filteredItems()
    {
        $input = $this->request->getJSON();

        // Check if required fields exist in the input, if not, set them to a default value or handle the error
        $categories = isset($input->selectedCategoryIds) ? (is_array($input->selectedCategoryIds) ? $input->selectedCategoryIds : explode(',', $input->selectedCategoryIds)) : [];
        $brands = isset($input->brands) ? (is_array($input->brands) ? $input->brands : explode(',', $input->brands)) : [];
        $minPrice = isset($input->minPrice) ? $input->minPrice : null;
        $maxPrice = isset($input->maxPrice) ? $input->maxPrice : null;
        
        // Pagination parameters
        $page = isset($input->page) ? (int)$input->page : 1;  // Default to page 1 if not provided
        $limit = isset($input->limit) ? (int)$input->limit : 30;  // Default to 10 items per page if not provided
        
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new ItemModel($db);

        $model = $model->where('isDeleted', 0)->where('itemTypeId', $input->itemTypeId);
        // Get filtered items with pagination
        $items = $model->getFilteredItems($categories, $brands, $minPrice, $maxPrice, $page, $limit);
        
        // Get the total count of items for pagination info
        $totalItems = $model->getFilteredItemsCount($categories, $brands, $minPrice, $maxPrice);
        
        // Calculate total pages
        $totalPages = ceil($totalItems / $limit);
        
        // Return paginated response
        return $this->respond([
            "status" => true,
            "message" => "All Data Fetched",
            "data" => $items,
            "pagination" => [
                "currentPage" => $page,
                "totalPages" => $totalPages,
                "totalItems" => $totalItems,
                "limit" => $limit
            ]
        ], 200);
        

    }

    public function getall()
    {
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $slideModel = new SlideModel($db);
        
        $slides = $slideModel->findAll();
        return $this->respond(["status" => true, "message" => "All Slides Fetched", "data" => $slides], 200);
    }

    public function deleteItem($id)
    {
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new ItemModel($db);

        // Check if item exists
        $item = $model->find($id);
        if (!$item) {
            return $this->respond([
                'status' => false,
                'message' => 'Item not found.'
            ], 404);
        }

        // Soft delete the item (set isDeleted = 1)
        $model->update($id, ['isDeleted' => 1]);

        return $this->respond([
            'status' => true,
            'message' => 'Item deleted successfully.'
        ], 200);
    }


}
