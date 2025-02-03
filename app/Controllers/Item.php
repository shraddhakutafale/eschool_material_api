<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\ItemModel;
use App\Models\ItemCategory;
use App\Models\Unit;
use Config\Database;

class Item extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        // Retrieve tenantConfig from the headers
        $tenantConfigHeader = $this->request->getHeaderLine('X-Tenant-Config');
        if (!$tenantConfigHeader) {
            throw new \Exception('Tenant configuration not found.');
        }

        // Decode the tenantConfig JSON
        $tenantConfig = json_decode($tenantConfigHeader, true);

        if (!$tenantConfig) {
            throw new \Exception('Invalid tenant configuration.');
        }

        // Connect to the tenant's database
        $db = Database::connect($tenantConfig);
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
        // Retrieve tenantConfig from the headers
        $tenantConfigHeader = $this->request->getHeaderLine('X-Tenant-Config');
        if (!$tenantConfigHeader) {
            throw new \Exception('Tenant configuration not found.');
        }

        // Decode the tenantConfig JSON 
        $tenantConfig = json_decode($tenantConfigHeader, true);

        if (!$tenantConfig) {
            throw new \Exception('Invalid tenant configuration.');
        }

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

        $tenantConfigHeader = $this->request->getHeaderLine('X-Tenant-Config');
        if (!$tenantConfigHeader) {
            throw new \Exception('Tenant configuration not found.');
        }

        // Decode the tenantConfig JSON
        $tenantConfig = json_decode($tenantConfigHeader, true);

        if (!$tenantConfig) {
            throw new \Exception('Invalid tenant configuration.');
        }

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
        $input = $this->request->getJSON();
        $rules = [
            'itemName'=> ['rules' => 'required'], 
            'mrp'=> ['rules' => 'required'],        
        ];
  
        if($this->validate($rules)){
            // Retrieve tenantConfig from the headers
            $tenantConfigHeader = $this->request->getHeaderLine('X-Tenant-Config');
            if (!$tenantConfigHeader) {
                throw new \Exception('Tenant configuration not found.');
            }

            // Decode the tenantConfig JSON
            $tenantConfig = json_decode($tenantConfigHeader, true);

            if (!$tenantConfig) {
                throw new \Exception('Invalid tenant configuration.');
            }

            // Handle image upload
            $image = $this->request->getFile('coverImage');
            $imageName = null;
    
            if ($image && $image->isValid() && !$image->hasMoved()) {
                // Define upload path
                $uploadPath = WRITEPATH . 'uploads/course_images/';
    
                // Ensure the directory exists
                if (!is_dir($uploadPath)) {
                    mkdir($uploadPath, 0777, true);
                }
    
                // Move the file to the desired directory with a unique name
                $imageName = $image->getRandomName();
                $image->move($uploadPath, $imageName);
    
                // Get the URL of the uploaded image
                $imageUrl = base_url() . '/uploads/itemImages/' . $imageName;
                $input->coverImage = $imageUrl;  // Save the image URL
            }

            // Connect to the tenant's database
            $db = Database::connect($tenantConfig);
            $model = new ItemModel($db);
        
            $model->insert($input);
             
            return $this->respond(['status'=>true,'message' => 'Item Added Successfully'], 200);
        }else{
            $response = [
                'status'=>false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ];
            return $this->fail($response , 409);
             
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
            // Retrieve tenantConfig from the headers
            $tenantConfigHeader = $this->request->getHeaderLine('X-Tenant-Config');
            if (!$tenantConfigHeader) {
                throw new \Exception('Tenant configuration not found.');
            }

            // Decode the tenantConfig JSON
            $tenantConfig = json_decode($tenantConfigHeader, true);

            if (!$tenantConfig) {
                throw new \Exception('Invalid tenant configuration.');
            }

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
            // Retrieve tenantConfig from the headers
            $tenantConfigHeader = $this->request->getHeaderLine('X-Tenant-Config');
            if (!$tenantConfigHeader) {
                throw new \Exception('Tenant configuration not found.');
            }

            // Decode the tenantConfig JSON
            $tenantConfig = json_decode($tenantConfigHeader, true);

            if (!$tenantConfig) {
                throw new \Exception('Invalid tenant configuration.');
            }

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

        // Retrieve tenantConfig from the headers
        $tenantConfigHeader = $this->request->getHeaderLine('X-Tenant-Config');
        if (!$tenantConfigHeader) {
            throw new \Exception('Tenant configuration not found.');
        }

        // Decode the tenantConfig JSON
        $tenantConfig = json_decode($tenantConfigHeader, true);

        if (!$tenantConfig) {
            throw new \Exception('Invalid tenant configuration.');
        }

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

        // Retrieve tenantConfig from the headers
        $tenantConfigHeader = $this->request->getHeaderLine('X-Tenant-Config');
        if (!$tenantConfigHeader) {
            throw new \Exception('Tenant configuration not found.');
        }

        // Decode the tenantConfig JSON
        $tenantConfig = json_decode($tenantConfigHeader, true);

        if (!$tenantConfig) {
            throw new \Exception('Invalid tenant configuration.');
        }

        // Connect to the tenant's database
        $db = Database::connect($tenantConfig);
        $model = new ItemCategory($db);
        $model->insert($input);
        return $this->respond(["status" => true, "message" => "Category Created Successfully"], 200);
    }

    public function getAllCategoryWeb()
    {
        $input = $this->request->getJSON();

        // Retrieve tenantConfig from the headers
        $tenantConfigHeader = $this->request->getHeaderLine('X-Tenant-Config');
        if (!$tenantConfigHeader) {
            throw new \Exception('Tenant configuration not found.');
        }

        // Decode the tenantConfig JSON
        $tenantConfig = json_decode($tenantConfigHeader, true);

        if (!$tenantConfig) {
            throw new \Exception('Invalid tenant configuration.');
        }

        // Connect to the tenant's database
        $db = Database::connect($tenantConfig);
        // Load UserModel with the tenant database connection
        $model = new ItemCategory($db);
        $itemCategories = $model->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $itemCategories], 200);
    }

    public function getAllItemByCategoryWeb()
    {
        $categoryId = $this->request->getSegment(1);

        // Retrieve tenantConfig from the headers
        $tenantConfigHeader = $this->request->getHeaderLine('X-Tenant-Config');
        if (!$tenantConfigHeader) {
            throw new \Exception('Tenant configuration not found.');
        }    

        // Decode the tenantConfig JSON
        $tenantConfig = json_decode($tenantConfigHeader, true);

        if (!$tenantConfig) {
            throw new \Exception('Invalid tenant configuration.');
        }

        // Connect to the tenant's database
        $db = Database::connect($tenantConfig);
        // Load UserModel with the tenant database connection
        $model = new Item($db);
        $items = $model->findAllByCategoryId($categoryId);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $items], 200);
    }

    public function getAllItemByTagWeb()
    {
        $tag = $this->request->getSegment(1);

        // Retrieve tenantConfig from the headers
        $tenantConfigHeader = $this->request->getHeaderLine('X-Tenant-Config');
        if (!$tenantConfigHeader) {
            throw new \Exception('Tenant configuration not found.');
        }

        // Decode the tenantConfig JSON
        $tenantConfig = json_decode($tenantConfigHeader, true);

        if (!$tenantConfig) {
            throw new \Exception('Invalid tenant configuration.');
        }

        // Connect to the tenant's database
        $db = Database::connect($tenantConfig);
        // Load UserModel with the tenant database connection
        $model = new Item($db);
        $items = $model->findAllByTag($tag);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $items], 200);
    }

    public function getFourItemByCategoryWeb()
    {
        // Retrieve tenantConfig from the headers
        $tenantConfigHeader = $this->request->getHeaderLine('X-Tenant-Config');
        if (!$tenantConfigHeader) {
            throw new \Exception('Tenant configuration not found.');
        }    

        // Decode the tenantConfig JSON
        $tenantConfig = json_decode($tenantConfigHeader, true);

        if (!$tenantConfig) {
            throw new \Exception('Invalid tenant configuration.');
        }

        // Connect to the tenant's database
        $db = Database::connect($tenantConfig);
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

        // Retrieve tenantConfig from the headers
        $tenantConfigHeader = $this->request->getHeaderLine('X-Tenant-Config');
        if (!$tenantConfigHeader) {
            throw new \Exception('Tenant configuration not found.');
        }

        // Decode the tenantConfig JSON
        $tenantConfig = json_decode($tenantConfigHeader, true);

        if (!$tenantConfig) {
            throw new \Exception('Invalid tenant configuration.');
        }

        // Connect to the tenant's database
        $db = Database::connect($tenantConfig);
        // Load UserModel with the tenant database connection
        $model = new Item($db);
        $items = $model->findAllByTag($tag);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $items], 200);
    }

}
