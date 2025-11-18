<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\StatusModel;
use Config\Database;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;


class Status extends BaseController
{
    use ResponseTrait;

   public function index()
    {
       // Insert the product data into the database
       $tenantService = new TenantService();
       // Connect to the tenant's database
       $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); // Load UserModel with the tenant database connection
        $statusModel = new StatusModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $statusModel->findAll()], 200);
    }
    
  public function create()
{
    helper(['form']);

    // Get PDF file
    $pdfFile = $this->request->getFile('reportPdf');

    $rules = [
        'workName' => 'required',
        'funds'    => 'permit_empty|decimal',
        'workDate' => 'required',
        'status'   => 'permit_empty',
        'reportPdf' => 'ext_in[reportPdf,pdf]|max_size[reportPdf,2048]' // PDF optional
    ];

    if (!$this->validate($rules)) {
        return $this->fail([
            'status' => false,
            'errors' => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ], 409);
    }

    // Upload file if provided
    $pdfName = null;
    if ($pdfFile && $pdfFile->isValid() && !$pdfFile->hasMoved()) {
        $pdfName = $pdfFile->getRandomName();
        $pdfFile->move('uploads/status', $pdfName);
    }

    // Collect other POST fields
    $data = [
        'workName'  => $this->request->getPost('workName'),
        'funds'     => $this->request->getPost('funds'),
        'workDate'  => $this->request->getPost('workDate'),
        'status'    => $this->request->getPost('status'),
        'reportPdf' => $pdfName,
        'isActive'  => 1,
        'isDeleted' => 0
    ];

    // Insert to DB with tenant connection
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    $model = new StatusModel($db);
    $id = $model->insert($data);

    return $this->respond([
        'status' => true,
        'message' => 'Status added successfully',
        'data' => $id
    ], 200);
}



   public function getAllStatusPaging()
{
    $input = $this->request->getJSON();

    $page = isset($input->page) ? (int)$input->page : 1;
    $perPage = isset($input->perPage) ? (int)$input->perPage : 10;
    $sortField = isset($input->sortField) ? $input->sortField : 'statusId';
    $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
    $search = isset($input->search) ? trim($input->search) : '';
    $filter = isset($input->filter) ? $input->filter : null;

    // 🔗 Tenant DB connect
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    $statusModel = new StatusModel($db);

    // Base query
    $query = $statusModel
        ->where('isActive', 1)
        ->where('isDeleted', 0)
        ->orderBy($sortField, $sortOrder);

    // 🔍 Search only for Status fields
    if (!empty($search)) {
        $query->groupStart()
              ->like('statusName', $search)
              ->orLike('statusDescription', $search)
              ->groupEnd();
    }

    // 🎯 Filter
    if ($filter) {
        $filter = json_decode(json_encode($filter), true);

        foreach ($filter as $key => $value) {
            if ($value === '' || $value === null) continue;

            // Text filters
            if (in_array($key, [
                'statusName',
                'statusDescription'
            ])) {
                $query->like($key, $value);
            }

            // Exact date filter
            elseif ($key === 'created_at') {
                $query->where($key, $value);
            }
        }

        // 📅 Date range filter
        if (!empty($filter['startDate']) && !empty($filter['endDate'])) {
            $query->where('created_at >=', $filter['startDate'])
                  ->where('created_at <=', $filter['endDate']);
        }

        // Date range shortcuts
        if (!empty($filter['dateRange'])) {
            if ($filter['dateRange'] === 'last7days') {
                $query->where('created_at >=', date('Y-m-d', strtotime('-7 days')));
            } elseif ($filter['dateRange'] === 'last30days') {
                $query->where('created_at >=', date('Y-m-d', strtotime('-30 days')));
            }
        }
    }

    // 🧾 Pagination
    $records = $query->paginate($perPage, 'default', $page);
    $pager = $statusModel->pager;

    return $this->respond([
        "status" => true,
        "message" => "All Status Data Fetched Successfully",
        "data" => $records,
        "pagination" => [
            "currentPage" => $pager->getCurrentPage(),
            "totalPages"  => $pager->getPageCount(),
            "totalItems"  => $pager->getTotal(),
            "perPage"     => $perPage
        ]
    ], 200);
}







public function update()
{
    helper(['form', 'filesystem']);

    $input = $this->request->getPost();
    $pdfFile = $this->request->getFile('reportPdf');

    // Validate ID
    if (!$this->validate(['statusId' => 'required|numeric'])) {
        return $this->fail([
            'status' => false,
            'errors' => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ], 409);
    }

    $statusId = $input['statusId'];

    // Tenant DB config
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig(
        $this->request->getHeaderLine('X-Tenant-Config')
    );

    $model = new StatusModel($db);

    // Check existing record
    $existing = $model->find($statusId);
    if (!$existing) {
        return $this->fail(['status' => false, 'message' => 'Status not found'], 404);
    }

    // Handle PDF replacement
    $newPdfName = $existing['reportPdf']; // default: keep old

    if ($pdfFile && $pdfFile->isValid() && !$pdfFile->hasMoved()) {

        // Generate new file name
        $newPdfName = $pdfFile->getRandomName();

        // Upload folder
        $uploadPath = 'uploads/status/';

        // Create if not exists
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }

        // Move new file
        $pdfFile->move($uploadPath, $newPdfName);

        // Delete OLD file
        if (!empty($existing['reportPdf']) && file_exists($uploadPath . $existing['reportPdf'])) {
            unlink($uploadPath . $existing['reportPdf']);
        }
    }

    // Prepare update data
    $updateData = [
        'workName'  => $input['workName'],
        'funds'     => $input['funds'],
        'workDate'  => $input['workDate'],
        'status'    => $input['status'],
        'reportPdf' => $newPdfName,
        'modifiedDate' => date('Y-m-d H:i:s')
    ];

    // Update DB
    $model->update($statusId, $updateData);

    return $this->respond([
        'status' => true,
        'message' => 'Status updated successfully',
        'dataId' => $statusId,
        'pdfName' => $newPdfName
    ], 200);
}


public function delete()
{
    $input = $this->request->getJSON(true);

    if (empty($input['id'])) {
        return $this->fail([
            'status' => false,
            'message' => 'Status ID is required'
        ], 400);
    }

    $statusId = $input['id'];

    // Tenant DB load
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig(
        $this->request->getHeaderLine('X-Tenant-Config')
    );

    $model = new StatusModel($db);

    // Check existing record
    $existing = $model->find($statusId);
    if (!$existing) {
        return $this->fail([
            'status' => false,
            'message' => 'Status record not found'
        ], 404);
    }

    // Delete PDF if exists
    if (!empty($existing['reportPdf'])) {
        $filePath = 'uploads/status/' . $existing['reportPdf'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    // Hard delete
    if ($model->delete($statusId, true)) {
        return $this->respond([
            'status' => true,
            'message' => 'Status deleted successfully'
        ], 200);
    }

    return $this->fail([
        'status' => false,
        'message' => 'Failed to delete status'
    ], 500);
}















}
