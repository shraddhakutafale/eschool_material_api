<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\TeamModel;
use App\Libraries\TenantService;

use Config\Database;

class Team extends BaseController
{
    use ResponseTrait;

    public function index()
    {
         
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load UserModel with the tenant database connection
        $teamModel = new TeamModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $teamModel->findAll()], 200);
    }

    public function getTeamsPaging()
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
        $teamModel = new TeamModel($db);
        $teams = $teamModel->orderBy('createdDate', 'DESC')->paginate($perPage, 'default', $page);
        $pager = $teamModel->pager;

        $response = [
            "status" => true,
            "message" => "All Data Fetched",
            "data" => $teams,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]   
        ];
        return $this->respond($response, 200);
    }

    public function getTeamsWebsite()
    {
         
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load UserModel with the tenant database connection
        $teamModel = new TeamModel($db);
        $teams = $teamModel->orderBy('createdDate', 'DESC')->where('isActive', 1)->where('isDeleted', 0)->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $teams], 200);
    }

    public function create()
    {
        $input = $this->request->getJSON();
        $rules = [

            'Name' => ['rules' => 'required'],
            'Description' => ['rules' => 'required'],
            'Tags' => ['rules' => 'required'],
            'Role' => ['rules' => 'required'],


            
        ];
  
        if($this->validate($rules)){
             
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new TeamModel($db);
        
            $model->insert($input);
             
            return $this->respond(['status'=>true,'message' => 'Team Added Successfully'], 200);
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
            'teamId' => ['rules' => 'required|numeric'], // Ensure courseId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
             
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new TeamModel($db);

            // Retrieve the course by courseId
            $teamId = $input->teamId;
            $team = $model->find($teamId); // Assuming find method retrieves the course

            if (!$team) {
                return $this->fail(['status' => false, 'message' => 'Team not found'], 404);
            }

            // Prepare the data to be updated (exclude courseId if it's included)
            $updateData = [

                'Name' => $input->Name,
                'Description' => $input->Description,
                'Tags' => $input->Tags,

                'Role' => $input->Role,
            ];

            // Update the course with new data
            $updated = $model->update($teamId, $updateData);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => ' Team Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update team'], 500);
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
        
        $rules = [
            'teamId' => ['rules' => 'required'],
        ];

        // Validate the input
        if ($this->validate($rules)) {
             
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new TeamModel($db);

            // Retrieve the course by courseId
            $teamId = $input->teamId;
            $team = $model->find($teamId); // Assuming find method retrieves the course

            if (!$team) {
                return $this->fail(['status' => false, 'message' => 'Team not found'], 404);
            }

            // Proceed to delete the course
            $deleted = $model->delete($teamId);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Team Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete tam'], 500);
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
        $teamId = $this->request->getPost('teamId'); // Example field

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

        $model = new TeamModel();
        $model->update($teamId,$data);

        return $this->respond([
            'status' => 201,
            'message' => 'File and data uploaded successfully',
            'data' => $data,
        ]);
    }


    public function getTeamById($teamId)
{
    
    $tenantService = new TenantService();
    // Connect to the tenant's database
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    // Load the CourseModel with the tenant database connection
    $teamModel = new TeamModel($db);

    // Fetch course by ID
    $team = $teamModel->find($teamId); // find method returns a single record by its ID

    // Check if course was found
    if (!$team) {
        throw new \Exception('Team not found.');
    }

    // Respond with the course data
    return $this->respond(["status" => true, "message" => "Team fetched successfully", "data" => $team], 200);
}

}
