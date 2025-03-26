<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\PoModel;
use App\Models\PoDetailModel;
use App\Models\ItemModel;
use App\Libraries\TenantService;

use Config\Database;

class Po extends BaseController
{
    use ResponseTrait;

    public function index()
    {
       
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load UserModel with the tenant database connection
        $PoModel = new PoModel($db);
        return $this->respond(['quotation' => $PoModel->findAll()], 200);
    }

    
    public function getPosPaging()
{
    $input = $this->request->getJSON();

    // Get the page number from the input, default to 1 if not provided
    $page = isset($input->page) ? $input->page : 1;
    $perPage = isset($input->perPage) ? $input->perPage : 10;
    $sortField = isset($input->sortField) ? $input->sortField : 'poId';
    $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
    $search = isset($input->search) ? $input->search : '';
    $filter = $input->filter;

    $tenantService = new TenantService();
    
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    
    // Load the models with the tenant database connection
    $PoModel = new PoModel($db);
    $PoDetailModel = new PoDetailModel($db);
    $ItemModel = new ItemModel($db);  // Assuming ItemModel exists

    // Base query for Pos
    $query = $PoModel;

    // Apply filters based on user input
    if (!empty($filter)) {
        $filter = json_decode(json_encode($filter), true);

        foreach ($filter as $key => $value) {
            if (in_array($key, ['poNo', 'poCode',  'businessNameFor'])) {
                $query->like($key, $value); // LIKE filter for specific fields
            } else if (in_array($key, ['createdDate'])) {
                $query->where($key, $value); // Exact match filter
            }
        }

        // Apply Date Range Filter
        if (!empty($filter['startDate']) && !empty($filter['endDate'])) {
            $query->where('createdDate >=', $filter['startDate'])
                ->where('createdDate <=', $filter['endDate']);
        }
    }

    $query->where('isDeleted', 0);

    // Apply Sorting
    if (!empty($sortField) && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
        $query->orderBy($sortField, $sortOrder);
    }

    // Fetch all Pos
    $Pos = $query->paginate($perPage, 'default', $page);
    $pager = $PoModel->pager;

    // Fetch details for each Pos and merge with the main Pos data
    foreach ($Pos as &$Po) {
        // Fetch related Pos details for each PosId
        $PosDetails = $PoDetailModel->where('poId', $Po['poId'])->findAll();

        // Merge the details under 'items', and for each Pos detail, fetch the corresponding item data
        foreach ($PosDetails as &$PosDetail) {
            // Fetch item data using itemId from ItemModel
            $item = $ItemModel->find($PosDetail['itemId']);  // Assuming 'itemId' is a field in PoDetailModel
            
            // Merge item data into PosDetail
            if ($item) {
                $PosDetail['item'] = $item;  // Now each PosDetail will have item details merged into it
            }
        }

        // Add the Pos details under 'items'
        $Po['items'] = $PosDetails;
    }

    // Prepare the response
    $response = [
        "status" => true,
        "message" => "All Lead Data Fetched",
        "data" => $Pos,
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
    
        // Validation rules for PO
        $rules = [
            'poCode'=> ['rules' => 'required'],
            'poDate'=> ['rules' => 'required'],
        ];
    
        // Validate form data
        if ($this->validate($rules)) {
          
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new PoModel($db);
    
            // Insert the PO into the 'po' table
            $poData = [
                'poCode' => $input->poCode,
                'poDate' => $input->poDate,
                'taxInvoiceNumber' => $input->taxInvoiceNumber,
                'businessNameFor' => $input->businessNameFor,
                'phoneFor' => $input->phoneFor,
                'addressFor' => $input->addressFor,
                'emailFor' => $input->emailFor,
                'total'=> $input->total,
                'totalItem'=> $input->totalItems,
                'totalPrice'=> $input->totalPrice,
            ];
    
            // Insert the PO and retrieve the generated poId
            $poId = $model->insert($poData);
            if ($poId) {
                // Now insert the items into the item_details table using the poId
    
                $itemDetailsModel = new PoDetailModel($db); // Assuming you have this model for the item details
    
                // Iterate through each item in the input and insert into item_details
                foreach ($input->items as $item) {
                    $itemData = [
                        'poId' => $poId,  // Foreign key linking to the quotation
                        'itemId' => $item->itemId,
                        'quantity' => $item->quantity,
                        'rate' => $item->rate,
                        'amount' => $item->amount
                    ];
                    
                    // Insert the item into the item_details table
                     $itemDetailsModel->insert($itemData);  // Assuming insert() method returns the inserted item ID
                     
                }
    
                return $this->respond(['status' => true, 'message' => 'PO and items added successfully'], 200);
            } else {
                return $this->respond(['status' => false, 'message' => 'Failed to create the po'], 500);
            }
        
        } else {
            // Return validation errors
            $response = [
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ];
            return $this->fail($response, 409);
        }
    }
    



    public function update()
    {
        // Get input data from the request body
        $input = $this->request->getJSON();

        // Validation rules for the po and Quotation Details
        $rules = [
            'poId' => ['rules' => 'required|numeric'], // Ensure poId is provided and is numeric
        ];

        // Validate the input
        if (!$this->validate($rules)) {
            // Validation failed
            $response = [
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ];
            return $this->fail($response, 409);
        }

        // Get tenant database configuration
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

        // Start a database transaction
        $db->transBegin();

        // Instantiate the models
        $PoModel = new PoModel($db);
        $PoDetailModel = new PoDetailModel($db);

        // Retrieve the po by poId
        $poId = $input->poId;
        $po = $PoModel->find($poId); // Assuming find method retrieves the po by poId

        if (!$po) {
            $db->transRollback();
            return $this->fail(['status' => false, 'message' => 'po not found'], 404);
        }

        // Prepare the data to be updated for the PoModel
        $updateData = [
            'poCode' => $input->poCode,
            'poDate' => $input->poDate,
            'taxInvoiceNumber' => $input->taxInvoiceNumber,
            'businessNameFor' => $input->businessNameFor,
            'phoneFor' => $input->phoneFor,
            'addressFor' => $input->addressFor,
            'emailFor' => $input->emailFor,
            'PanCardFor' => $input->PanCardFor,     
            'total'=> $input->total,
            'totalItem'=> $input->totalItems,
            'totalPrice'=> $input->totalPrice,              
        ];

        // Update the po in the PoModel
        $updated = $PoModel->update($poId, $updateData);

        if (!$updated) {
            $db->transRollback();
            return $this->fail(['status' => false, 'message' => 'Failed to update po'], 500);
        }

        // Handle quotation details (multiple items)
        if (isset($input->items) && is_array($input->items)) {
            foreach ($input->items as $item) {
                // Ensure itemId is provided and valid
                if (empty($item->itemId)) {
                    $db->transRollback();
                    return $this->fail(['status' => false, 'message' => 'itemId is required and cannot be null for all items'], 400);
                }

                // Prepare the detail data for update or insert
                $detailData = [
                    'poId' => $poId,  // Ensure the poId is linked to the detail
                    'itemId' => $item->itemId,  // Use the provided itemId
                    'quantity' => $item->quantity,  // Quantity
                    'rate' => $item->rate,  // Rate
                    'amount' => $item->amount,  // Amount = quantity * rate
                    // You can add more fields as needed
                ];

                // Check if PoDetailId  exists to update or if we need to insert it
                if (isset($item->PoDetailId ) && $item->PoDetailId ) {
                    // Update the existing po detail using PoDetailId 
                    $updatedDetail = $PoDetailModel->update($item->PoDetailId , $detailData);
                    if (!$updatedDetail) {
                        $db->transRollback();
                        return $this->fail(['status' => false, 'message' => 'Failed to update po Detail for PoDetailId  ' . $item->PoDetailId ], 500);
                    }
                } else {
                    // Check if the item already exists in the po details before inserting
                    $existingItem = $PoDetailModel->where('poId', $poId)
                                                        ->where('itemId', $item->itemId)
                                                        ->first();

                    if ($existingItem) {
                        // If item exists, update it instead of inserting
                        $detailData['poDetailId'] = $existingItem['poDetailId'];
                        $updatedDetail = $PoDetailModel->update($existingItem['poDetailId'], $detailData);
                        if (!$updatedDetail) {
                            $db->transRollback();
                            return $this->fail(['status' => false, 'message' => 'Failed to update existing po Detail'], 500);
                        }
                    } else {
                        // Insert a new detail if no PoDetailId  is provided and it's not already in the po
                        $insertedDetail = $PoDetailModel->insert($detailData);
                        if (!$insertedDetail) {
                            $db->transRollback();
                            return $this->fail(['status' => false, 'message' => 'Failed to insert new po Detail'], 500);
                        }
                    }
                }
            }
        }

        // Commit the transaction if everything is successful
        $db->transCommit();

        // Return success message if both po and details are updated successfully
        return $this->respond(['status' => true, 'message' => 'po and Details Updated Successfully'], 200);
    }


    public function delete()
    {
        $input = $this->request->getJSON();
        
        // Validation rules for the Quote
        $rules = [
            'poId ' => ['rules' => 'required'], // Ensure eventId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
           
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 
            $model = new PoModel($db);

            // Retrieve the Quote by eventId
            $poId  = $input->poId ;
            $item = $model->find($poId ); // Assuming find method retrieves the Quote

            if (!$item) {
                return $this->fail(['status' => false, 'message' => 'Quote not found'], 404);
            }

            // Proceed to delete the Quote
            $deleted = $model->delete($poId);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Quote Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete Quote'], 500);
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
        $quoteId  = $this->request->getPost('quoteId '); // Example field

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

        $model = new QuotationsModel();
        $model->update($quoteId ,$data);

        return $this->respond([
            'status' => 201,
            'message' => 'File and data uploaded successfully',
            'data' => $data,
        ]);
    }
}
