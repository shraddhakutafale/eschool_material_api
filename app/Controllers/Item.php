<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\ItemModel;
use App\Models\ItemCategory;
use App\Models\Unit;
use Config\Database;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

class Item extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
       
        // Load UserModel with the tenant database connection
        $itemModel = new ItemModel($db);
        $response = [
            "status" => true,
            "message" => "All Data Fetched",
            "data" => $itemModel->findAll(),
        ];
        return $this->respond($response, 200);
    }

    public function getAllUnit()
    {
        // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Connect to the tenant's database
        $db = Database::connect($tenantConfig);

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
        // Define the number of items per page
        $perPage = isset($input->perPage) ? $input->perPage : 10;

        // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

        // Connect to the tenant's database
        $db = Database::connect($tenantConfig);
        // Load UserModel with the tenant database connection
        $ItemModel = new ItemModel($db);
        $items = $ItemModel->orderBy('createdDate', 'DESC')->paginate($perPage, 'default', $page);
        $pager = $ItemModel->pager;

        $response = [
            "status" => true,
            "message" => "All Data Fetched",
            "data" => $items,
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
            'itemName' => ['rules' => 'required'],
            'description' => ['rules' => 'required'],
            'itemCategoryId' => ['rules' => 'required'],
            'mrp' => ['rules' => 'required'],
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
            
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
            // Handle image upload for the cover image
            $coverImage = $this->request->getFile('coverImage');
            $coverImageName = null;

            if ($coverImage && $coverImage->isValid() && !$coverImage->hasMoved()) {
                // Define the upload path for the cover image
                $coverImagePath = FCPATH . 'uploads/' . $decoded->tenantName . '/itemImages/';
                if (!is_dir($coverImagePath)) {
                    mkdir($coverImagePath, 0777, true); // Create directory if it doesn't exist
                }

                // Move the file to the desired directory with a unique name
                $coverImageName = $coverImage->getRandomName();
                $coverImage->move($coverImagePath, $coverImageName);

                // Get the URL of the uploaded cover image and remove the 'uploads/coverImages/' prefix
                $coverImageUrl = 'uploads/itemImages/' . $coverImageName;
                $coverImageUrl = str_replace('uploads/itemImages/', '', $coverImageUrl);

                // Add the cover image URL to the input data
                $input['coverImage'] = $coverImageUrl; 
            }

            
            $productImages = $this->request->getFiles('images');  // 'images' is the name for multiple images
            $imageUrls = []; // Initialize the array for image URLs

            if ($productImages && count($productImages) > 0) {
                foreach ($productImages as $image) {
                    // Validate the image: Ensure it's valid, hasn't moved, and exists
                    if ($image && $image->isValid() && !$image->hasMoved()) {
                        // Define the upload path for product images
                        $productImagePath = FCPATH . 'uploads/'. $decoded->tenantName .'/itemSlideImages/';

                        // Check if the directory exists; if not, create it
                        if (!is_dir($productImagePath)) {
                            mkdir($productImagePath, 0777, true); // Create directory if it doesn't exist
                        }

                        // Generate a unique name for the image to avoid overwriting
                        $imageName = $image->getRandomName();

                        // Move the uploaded image to the target directory
                        $image->move($productImagePath, $imageName);

                        // Get the URL for the uploaded image and add it to the array
                        $imageUrl = 'uploads/itemSlideImages/' . $imageName;
                        $imageUrl = str_replace('uploads/itemSlideImages/', '', $imageUrl);

                        $imageUrls[] = $imageUrl; // Add the image URL to the array
                    }
                }

                // If there are multiple images, join the URLs with commas and save in the input data
                if (!empty($imageUrls)) {
                    $input['productImages'] = implode(',', $imageUrls); // Join image URLs with commas
                }
            }


            // Insert the product data into the database
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new ItemModel($db);
            $model->insert($input);

            return $this->respond(['status' => true, 'message' => 'Item Added Successfully'], 200);
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
        $input = $this->request->getJSON();
        
        // Validation rules for the course
        $rules = [
            'itemId' => ['rules' => 'required|numeric'], // Ensure eventId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
            // Insert the product data into the database
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            // Connect to the tenant's database
            $db = Database::connect($tenantConfig);
            $model = new ItemModel($db);

            // Retrieve the course by eventId
            $itemId = $input->itemId;
            $item = $model->find($itemId); // Assuming find method retrieves the course

            if (!$item) {
                return $this->fail(['status' => false, 'message' => 'Course not found'], 404);
            }

            // Prepare the data to be updated (exclude eventId if it's included)
            $updateData = [
                'itemName' => $input->itemName,
                'brandName' => $input->brandName,
                'itemCategoryId' => $input->itemCategoryId,
                'unit' => $input->unit,
                'unitSize' => $input->unitSize,
                'mrp' => $input->mrp,
                'discountType' => $input->discountType,
                'discount' => $input->discount,
                'gstPercentage' => $input->gstPercentage,
                'barcode' => $input->barcode,
                'hsnCode' => $input->hsnCode,
                'minStockLevel' => $input->minStockLevel,
                'description' => $input->description,
            ];

            // Update the course with new data
            $updated = $model->update($itemId, $updateData);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Item Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update course'], 500);
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
            // Connect to the tenant's database
            $db = Database::connect($tenantConfig);
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
        $input = $this->request->getJSON();

        // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

        // Connect to the tenant's database
        $db = Database::connect($tenantConfig);
        // Load UserModel with the tenant database connection
        $model = new ItemCategory($db);
        $itemCategories = $model->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $itemCategories], 200);
    }

    public function createCategory()
    {
        $input = $this->request->getJSON();

        // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

        // Connect to the tenant's database
        $db = Database::connect($tenantConfig);
        $model = new ItemCategory($db);
        $model->insert($input);
        return $this->respond(["status" => true, "message" => "Category Created Successfully"], 200);
    }

    public function getAllCategoryWeb()
    {
        $input = $this->request->getJSON();

         // Insert the product data into the database
         $tenantService = new TenantService();
         // Connect to the tenant's database
         $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

        // Connect to the tenant's database
        $db = Database::connect($tenantConfig);
        // Load UserModel with the tenant database connection
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
    
        // Connect to the tenant's database
        try {
            $db = Database::connect($tenantConfig);
        } catch (\Exception $e) {
            return $this->respond(["status" => false, "message" => "Failed to connect to the database: " . $e->getMessage()], 500);
        }
    
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
    
    
    // public function findAllByCategoryId($categoryId)
    // {
    //     return $this->where('itemCategoryId', $categoryId)->findAll();
    // }
   

    public function getAllItemByTagWeb()
    {
        $tag = $this->request->getSegment(1);

         // Insert the product data into the database
         $tenantService = new TenantService();
         // Connect to the tenant's database
         $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

        // Connect to the tenant's database
        $db = Database::connect($tenantConfig);
        // Load UserModel with the tenant database connection
        $model = new Item($db);
        $items = $model->findAllByTag($tag);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $items], 200);
    }

    public function getFourItemByCategoryWeb()
    {
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load UserModel with the tenant database connection
        $category = new ItemCategory($db);
        $finalCategories = array();
        $categories = $category->findAll();
        $model = new ItemModel($db);
        foreach($categories as $category){
            $finalCategory = array();
            $finalCategory['categoryId'] = $category['itemCategoryId'];
            $finalCategory['categoryName'] = $category['itemCategoryName'];
            $finalCategory['items'] = $model->where('itemCategoryId', $category['itemCategoryId'])->limit(4)->findAll();
            array_push($finalCategories, $finalCategory);
        }
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $finalCategories], 200);
    }

    public function getFourItemByTagWeb()
    {
        $tag = $this->request->getSegment(1);

        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load UserModel with the tenant database connection
        $model = new Item($db);
        $items = $model->findAllByTag($tag);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $items], 200);
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


}
