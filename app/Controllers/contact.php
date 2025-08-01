<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\ContactModel;
use App\Models\ContactTimetable;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

use Config\Database;


class Contact extends BaseController
{
    use ResponseTrait;

     public function index()
    {
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

        $model = new ContactModel($db);
        $contacts = $model->where('isDeleted', 0)->findAll();

        return $this->respond([
            'status' => true,
            'message' => 'All Contacts Fetched',
            'data' => $contacts
        ], 200);
    }

      public function getContactsPaging()
    {
        $input = $this->request->getJSON(true);

        $page = $input['page'] ?? 1;
        $perPage = $input['perPage'] ?? 10;
        $sortField = $input['sortField'] ?? 'contactId';
        $sortOrder = $input['sortOrder'] ?? 'ASC';
        $search = $input['search'] ?? '';
        $filter = $input['filter'] ?? [];

        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

        $model = new ContactModel($db);

        $builder = $model->where('isDeleted', 0);

        if (!empty($search)) {
            $builder->groupStart()
                    ->like('title', $search)
                    ->orLike('contactType', $search)
                    ->groupEnd();
        }

        if (!empty($filter['startDate']) && !empty($filter['endDate'])) {
            $builder->where('createdDate >=', $filter['startDate'])
                    ->where('createdDate <=', $filter['endDate']);
        }

        if (!empty($filter['contactType'])) {
            $builder->where('contactType', $filter['contactType']);
        }

        $builder->orderBy($sortField, $sortOrder);
        $results = $builder->paginate($perPage, 'default', $page);

        $pager = $model->pager;

        return $this->respond([
            'status' => true,
            'message' => 'Paginated Contact Data Fetched',
            'data' => $results,
            'pagination' => [
                'currentPage' => $pager->getCurrentPage(),
                'totalPages' => $pager->getPageCount(),
                'totalItems' => $pager->getTotal(),
                'perPage' => $perPage
            ]
        ], 200);
    }
    
    
      public function create()
{
    $input = $this->request->getJSON(true);

    // Required validation
    $rules = [
        'contactType' => 'required',
        'title'       => 'required'
    ];

    if (!$this->validate($rules)) {
        return $this->fail([
            'status' => false,
            'errors' => $this->validator->getErrors(),
            'message' => 'Invalid Inputs',
        ], 409);
    }

    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $model = new ContactModel($db);

    // Insert dynamic fields safely using allowedFields
    $model->insert($input);

    return $this->respond([
        'status' => true,
        'message' => 'Contact Added Successfully'
    ], 200);
}



    public function update()
{
    $input = $this->request->getJSON();

    $rules = [
        'contactId' => ['rules' => 'required|numeric'],
    ];

    if ($this->validate($rules)) {
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new ContactModel($db);

        $contactId = $input->contactId;
        $contact = $model->find($contactId);

        if (!$contact) {
            return $this->fail(['status' => false, 'message' => 'Contact not found'], 404);
        }

        $updateData = [
            'title' => $input->title ?? $contact['title'],
            'address' => $input->address ?? $contact['address'],
            'telephone' => $input->telephone ?? $contact['telephone'],
            'fax' => $input->fax ?? $contact['fax'],
            'email' => $input->email ?? $contact['email'],
            'mapUrl' => $input->mapUrl ?? $contact['mapUrl'],
            'extraUrl' => $input->extraUrl ?? $contact['extraUrl'],
        ];

        $updated = $model->update($contactId, $updateData);

        if ($updated) {
            return $this->respond(['status' => true, 'message' => 'Contact updated successfully'], 200);
        } else {
            return $this->fail(['status' => false, 'message' => 'Failed to update contact'], 500);
        }
    } else {
        return $this->fail([
            'status' => false,
            'errors' => $this->validator->getErrors(),
            'message' => 'Invalid Inputs',
        ], 409);
    }
}

    


    public function delete()
{
    $input = $this->request->getJSON();

    $rules = [
        'contactId' => ['rules' => 'required|numeric'],
    ];

    if ($this->validate($rules)) {
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new ContactModel($db);

        $contactId = $input->contactId;
        $contact = $model->find($contactId);

        if (!$contact) {
            return $this->fail(['status' => false, 'message' => 'Contact not found'], 404);
        }

        // Option 1: Soft Delete
        $updateData = ['isDeleted' => 1];
        $deleted = $model->update($contactId, $updateData); // <- soft delete

        // Option 2: Hard Delete (if you're not using `isDeleted`)
        // $deleted = $model->delete($contactId);

        if ($deleted) {
            return $this->respond(['status' => true, 'message' => 'Contact deleted successfully'], 200);
        } else {
            return $this->fail(['status' => false, 'message' => 'Failed to delete contact'], 500);
        }
    } else {
        return $this->fail([
            'status' => false,
            'errors' => $this->validator->getErrors(),
            'message' => 'Invalid Inputs',
        ], 409);
    }
}


    public function addAllExamTimetable(){
        $input = $this->request->getJSON();

        if(empty($input)){
            return $this->respond(['status' => false, 'message' => 'No data found'], 200);
        }
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new ExamModel($db);

        $examTimetable = new ExamTimetable($db);

        $examTimetable->insertbatch($input);

        return $this->respond(['status' => true, 'message' => 'Subjects Assigned Successfully'], 200);
    }

    public function getSubjectsByExam(){
        $input = $this->request->getJSON();
        $examId = $input->examId;
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new ExamTimetable($db);

        $examTimetables = $model->where('examId', $examId)->where('businessId', $input->businessId)->where('isDeleted', 0)->findAll();
        return $this->respond(['status' => true, 'message' => 'Subjects fetched successfully', 'data' => $examTimetables], 200);
    }
}
