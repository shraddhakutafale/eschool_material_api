<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\AddressModel;
use App\Models\OrderDetailModel;
use App\Models\ItemModel;
use App\Libraries\TenantService;

use Config\Database;

class Address extends BaseController
{
    use ResponseTrait;

  public function index()
    {
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new AddressModel($db);

        $data = $model->where('isDeleted', 0)->findAll();

        return $this->respond([
            'status' => true,
            'message' => 'All addresses fetched successfully',
            'data' => $data
        ]);
    }

    public function getAddressesPaging()
    {
        $input = $this->request->getJSON();

        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'addressId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = isset($input->filter) ? $input->filter : [];

        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new AddressModel($db);

        $query = $model->where('isDeleted', 0);

        // 🔍 Apply filters
        if (!empty($search)) {
            $query->groupStart()
                  ->like('name', $search)
                  ->orLike('mobileNo', $search)
                  ->orLike('city', $search)
                  ->groupEnd();
        }

        if (!empty($filter)) {
            foreach ($filter as $key => $value) {
                if (!empty($value)) {
                    $query->where($key, $value);
                }
            }
        }

        // 📋 Apply sorting
        $query->orderBy($sortField, $sortOrder);

        // 📦 Pagination
        $addresses = $query->paginate($perPage, 'default', $page);
        $pager = $model->pager;

        return $this->respond([
            'status' => true,
            'message' => 'Addresses fetched successfully',
            'data' => $addresses,
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
        $input = $this->request->getJSON();

        $rules = [
            'name' => ['rules' => 'required'],
            'mobileNo' => ['rules' => 'required|min_length[10]|max_length[15]'],
            'addressLine' => ['rules' => 'required']
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors([
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid inputs'
            ]);
        }

        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new AddressModel($db);

        $data = [
            'name' => $input->name ?? '',
            'mobileNo' => $input->mobileNo ?? '',
            'email' => $input->email ?? '',
            'addressLine' => $input->addressLine ?? '',
            'city' => $input->city ?? '',
            'state' => $input->state ?? '',
            'pincode' => $input->pincode ?? '',
            'country' => $input->country ?? '',
            'createdDate' => date('Y-m-d H:i:s'),
            'isDeleted' => 0
        ];

        $addressId = $model->insert($data);

        if ($addressId) {
            return $this->respond([
                'status' => true,
                'message' => 'Address added successfully',
                'addressId' => $addressId
            ], 200);
        } else {
            return $this->respond([
                'status' => false,
                'message' => 'Failed to add address'
            ], 500);
        }
    }


    
}
