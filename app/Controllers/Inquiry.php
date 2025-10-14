<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use Config\Database;
use App\Models\InquiryModel;

use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;


class Inquiry extends BaseController
{
    use ResponseTrait;

    // Get all leads
    public function index()
    {
           // Insert the product data into the database
           $tenantService = new TenantService();
           // Connect to the tenant's database
           $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 
        $InquiryModel = new InquiryModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $InquiryModel->findAll()], 200);
    }
public function getInquiryPaging()
{
    $input = $this->request->getJSON();

    $page = $input->page ?? 1;
    $perPage = $input->perPage ?? 10;
    $sortField = $input->sortField ?? 'inquiryId';
    $sortOrder = $input->sortOrder ?? 'ASC';

    // 🔑 Get tenant DB
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    // ✅ Load inquiry model (must point to inquiry_mst)
    $inquiryModel = new InquiryModel($db);
    $inquiryModel->setTable('inquiry_mst');

    // ✅ Exclude deleted inquiries
    $query = $inquiryModel->where('isDeleted', 0);

    // ✅ Apply sorting
    if (!empty($sortField) && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
        $query->orderBy($sortField, $sortOrder);
    }

    // ✅ Paginate results
    $inquirys = $query->paginate($perPage, 'default', $page);
    $pager = $inquiryModel->pager;

    // ✅ Response format
    $response = [
        "status" => true,
        "message" => "All Inquiry Data Fetched",
        "data" => $inquirys,
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
    try {
        // Get JSON input (all fields optional)
        $input = (array) $this->request->getJSON(true);

        // Ensure all required DB columns have a value
        $fields = ['name', 'email', 'phone', 'subject', 'message', 'remarks', 'status', 'assignedTo'];
        foreach ($fields as $field) {
            if (!isset($input[$field]) || $input[$field] === null) {
                $input[$field] = ''; // default to empty string
            }
        }

        // 🔑 Get tenant DB config
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

        // Load model with tenant DB
        $model = new InquiryModel($db);

        // Generate unique inquiryNo: e.g., INQ-001, INQ-002, ...
        $lastInquiry = $model->orderBy('inquiryNo', 'DESC')->first();
        if ($lastInquiry && isset($lastInquiry['inquiryNo'])) {
            $lastNumber = intval(substr($lastInquiry['inquiryNo'], 4)); // remove "INQ-"
            $newNumber  = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '001';
        }

        $input['inquiryNo'] = 'INQ-' . $newNumber;

        // Insert into DB
        if (!$model->insert($input)) {
            log_message('error', 'Insert Inquiry Failed: ' . print_r($model->errors(), true));
            return $this->fail([
                'status'  => false,
                'message' => 'Insert Failed',
                'errors'  => $model->errors()
            ], 500);
        }

        return $this->respond([
            'status'    => true,
            'message'   => 'Inquiry Created Successfully',
            'inquiryNo' => $input['inquiryNo']
        ], 200);

    } catch (\Throwable $e) {
        log_message('error', 'Create Inquiry Exception: ' . $e->getMessage());
        return $this->fail([
            'status'  => false,
            'message' => 'Server Error',
            'error'   => $e->getMessage()
        ], 500);
    }
}




  // Update an existing inquiry
public function update()
{
    $input = $this->request->getJSON();

    // Validation: inquiryId must be present
    $rules = [
        'inquiryId' => ['rules' => 'required|numeric'],
    ];

    if (!$this->validate($rules)) {
        return $this->fail([
            'status' => false,
            'errors' => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ], 409);
    }

    try {
        // 🔑 Tenant DB connection
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

        $model = new InquiryModel($db);
        $inquiryId = $input->inquiryId;

        // Check if inquiry exists
        $inquiry = $model->find($inquiryId);
        if (!$inquiry) {
            return $this->fail([
                'status' => false,
                'message' => 'Inquiry not found'
            ], 404);
        }

        // Fields allowed to update
        $allowedFields = [
            'name', 'email', 'phone', 'subject',
            'message', 'remarks', 'status', 'assignedTo'
        ];

        $updateData = [];
        foreach ($allowedFields as $field) {
            if (isset($input->$field)) {
                $updateData[$field] = $input->$field;
            }
        }

        if (empty($updateData)) {
            return $this->fail([
                'status' => false,
                'message' => 'No data provided to update'
            ], 400);
        }

        // Perform update
        $updated = $model->update($inquiryId, $updateData);

        if ($updated) {
            return $this->respond([
                'status' => true,
                'message' => 'Inquiry updated successfully'
            ], 200);
        } else {
            return $this->fail([
                'status' => false,
                'message' => 'Failed to update Inquiry'
            ], 500);
        }
    } catch (\Throwable $e) {
        log_message('error', 'Update Inquiry Exception: ' . $e->getMessage());
        return $this->fail([
            'status' => false,
            'message' => 'Server Error',
            'error' => $e->getMessage()
        ], 500);
    }
}



// Delete an inquiry (soft delete)
public function delete()
{
    $input = $this->request->getJSON();

    $rules = [
        'inquiryId' => ['rules' => 'required|numeric'],
    ];

    if (!$this->validate($rules)) {
        return $this->fail([
            'status' => false,
            'errors' => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ], 409);
    }

    try {
        // 🔑 Tenant DB connection
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

        $model = new InquiryModel($db);
        $inquiryId = $input->inquiryId;

        // Check if inquiry exists
        $inquiry = $model->find($inquiryId);
        if (!$inquiry) {
            return $this->fail([
                'status' => false,
                'message' => 'Inquiry not found'
            ], 404);
        }

        // ✅ Soft delete: set isDeleted = 1 instead of removing record
        $deleted = $model->update($inquiryId, ['isDeleted' => 1]);

        if ($deleted) {
            return $this->respond([
                'status' => true,
                'message' => 'Inquiry deleted successfully'
            ], 200);
        } else {
            return $this->fail([
                'status' => false,
                'message' => 'Failed to delete inquiry'
            ], 500);
        }
    } catch (\Throwable $e) {
        log_message('error', 'Delete Inquiry Exception: ' . $e->getMessage());
        return $this->fail([
            'status' => false,
            'message' => 'Server Error',
            'error' => $e->getMessage()
        ], 500);
    }
}

}


