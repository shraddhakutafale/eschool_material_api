<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\VendorModel;
use App\Libraries\TenantService;

use Config\Database;

class Vendor extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load VendorModel with the tenant database connection
        $VendorModel = new VendorModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $VendorModel->findAll()], 200);
    }

    public function getVendorsPaging()
   
    {
        $input = $this->request->getJSON();

        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'vendorId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
        

        $tenantService = new TenantService();
        
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load StaffModel with the tenant database connection
        $vendorModel = new VendorModel($db);

        $vendor = $vendorModel->orderBy($sortField, $sortOrder)->like('name', $search)->orLike('mobileNo', $search)->paginate($perPage, 'default', $page);
        if ($filter) {
            $filter = json_decode(json_encode($filter), true);
            $vendor = $vendorModel->where($filter)->paginate($perPage, 'default', $page);   
        }
        $pager = $vendorModel->pager;

        $response = [
            "status" => true,
            "message" => "All Vendor Data Fetched",
            "data" => $vendor,
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
            'name' => ['rules' => 'required'],
            'mobileNo' => ['rules' => 'required']
        ];

        if($this->validate($rules)){
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new VendorModel($db);
        
            $model->insert($input);
             
            return $this->respond(['status'=>true,'message' => 'Vendor Added Successfully'], 200);
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
        
        // Validation rules for the vendor
        $rules = [
            'vendorId' => ['rules' => 'required|numeric'], // Ensure vendorId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
            $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new VendorModel($db);

            // Retrieve the vendor by vendorId
            $vendorId = $input->vendorId;
            $vendor = $model->find($vendorId); // Assuming find method retrieves the vendor

            if (!$vendor) {
                return $this->fail(['status' => false, 'message' => 'Vendor not found'], 404);
            }

            // Prepare the data to be updated (exclude vendorId if it's included)
            $updateData = [
                'name' =>$input->name,
                'mobileNo' => $input->mobileNo,
                'alternateMobileNo' => $input->alternateMobileNo,
                'emailId' => $input->emailId,
                'dateOfBirth' => $input->dateOfBirth,
                'gender' => $input->gender
            ];

            // Update the vendor with new data
            $updated = $model->update($vendorId, $updateData);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Vendor Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update vendor'], 500);
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
        
        // Validation rules for the vendor
        $rules = [
            'vendorId' => ['rules' => 'required'], // Ensure vendorId is provided
        ];
    
        // Validate the input
        if ($this->validate($rules)) {
            $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new VendorModel($db);
    
            // Retrieve the vendor by vendorId
            $vendorId = $input->vendorId;
            $vendor = $model->find($vendorId); // Assuming the find method retrieves the vendor
    
            if (!$vendor) {
                return $this->fail(['status' => false, 'message' => 'Vendor not found'], 404);
            }
    
            // Proceed to delete the vendor
            // Soft delete by marking 'isDeleted' as 1
            $updateData = [
                'isDeleted' => 1,
            ];
            $deleted = $model->update($vendorId, $updateData);
    
            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Vendor Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete vendor'], 500);
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
        $vendorId = $this->request->getPost('vendorId'); // Example field

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

        $model = new VendorModel();
        $model->update($vendorId, $data);

        return $this->respond([
            'status' => 201,
            'message' => 'File and data uploaded successfully',
            'data' => $data,
        ]);
    }
}
