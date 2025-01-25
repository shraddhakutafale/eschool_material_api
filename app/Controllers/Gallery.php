<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\GalleryModel;
use Config\Database;

class Gallery extends BaseController
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
        $galleryModel = new GalleryModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $galleryModel->findAll()], 200);
    }

    public function getGallerysPaging()
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
        $galleryModel = new GalleryModel($db);
        $gallerys = $galleryModel->orderBy('createdDate', 'DESC')->paginate($perPage, 'default', $page);
        $pager = $galleryModel->pager;

        $response = [
            "status" => true,
            "message" => "All Data Fetched",
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
        $galleryModel = new GalleryModel($db);
        $gallerys = $galleryModel->orderBy('createdDate', 'DESC')->where('isActive', 1)->where('isDeleted', 0)->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $gallerys], 200);
    }

    public function create()
    {
        $input = $this->request->getJSON();
        $rules = [
            'galleryTitle' => ['rules' => 'required'],
            'imgDesc' => ['rules' => 'required'],
            'authorName' => ['rules' => 'required'],
            'galleryTag' => ['rules' => 'required'],
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

            // Connect to the tenant's database
            $db = Database::connect($tenantConfig);
            $model = new GalleryModel($db);
        
            $model->insert($input);
             
            return $this->respond(['status'=>true,'message' => 'Gallery Added Successfully'], 200);
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
            'galleryId' => ['rules' => 'required|numeric'], // Ensure courseId is provided and is numeric
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
            $model = new GalleryModel($db);

            // Retrieve the course by courseId
            $galleryId = $input->galleryId;
            $gallery = $model->find($galleryId); // Assuming find method retrieves the course

            if (!$gallery) {
                return $this->fail(['status' => false, 'message' => 'Gallery not found'], 404);
            }

            // Prepare the data to be updated (exclude courseId if it's included)
            $updateData = [
                'galleryTitle' => $input->galleryTitle,
                'imgDesc' => $input->imgDesc,
                'galleryTag' => $input->galleryTag,

                'authorName' => $input->authorName,
            ];

            // Update the course with new data
            $updated = $model->update($galleryId, $updateData);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => ' Gallery Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update gallery'], 500);
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
            'galleryId' => ['rules' => 'required'], // Ensure courseId is provided and is numeric
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
            $model = new GalleryModel($db);

            // Retrieve the course by courseId
            $galleryId = $input->galleryId;
            $gallery = $model->find($galleryId); // Assuming find method retrieves the course

            if (!$gallery) {
                return $this->fail(['status' => false, 'message' => 'Gallery not found'], 404);
            }

            // Proceed to delete the course
            $deleted = $model->delete($galleryId);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Gallery Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete Gallery'], 500);
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


    public function getGalleryById($galleryId)
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

    // Load the CourseModel with the tenant database connection
    $galleryModel = new GalleryModel($db);

    // Fetch course by ID
    $gallery = $galleryModel->find($galleryId); // find method returns a single record by its ID

    // Check if course was found
    if (!$gallery) {
        throw new \Exception('Gallery not found.');
    }

    // Respond with the course data
    return $this->respond(["status" => true, "message" => "Gallery fetched successfully", "data" => $gallery], 200);
}

}
