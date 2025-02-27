<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\PoModel;
use App\Models\PoDetailModel;
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

    public function getQuotationsPaging()
    {
        $input = $this->request->getJSON();

        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        // Define the number of items per page
        $perPage = isset($input->perPage) ? $input->perPage : 10;

     
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));        // Load UserModel with the tenant database connection
        $PoModel = new PoModel($db);
        $quotations = $PoModel->orderBy('createdDate', 'DESC')->paginate($perPage, 'default', $page);
        $pager = $PoModel->pager;

        $response = [
            "status" => true,
            "message" => "All Data Fetched",
            "data" => $quotations,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]   
        ];
        return $this->respond($response, 200);
    }

    public function getQuotationsWebsite()
    {
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load UserModel with the tenant database connection
        $QuotationsModel = new QuotationsModel($db);
        $quotations = $QuotationsModel->orderBy('createdDate', 'DESC')->where('isActive', 1)->where('isDeleted', 0)->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $quotations], 200);
    }

  
    // public function create()
    // {
    //     $input = $this->request->getJSON();
    //     $rules = [
    //         'poNo'=> ['rules' => 'required'], 
    //         'poDate'=> ['rules' => 'required'],
    //         'vendor'=> ['rules' => 'required'],
    //         'taxInvoiceNumber'=> ['rules' => 'required'],
    //         'businessNameFrom'=> ['rules' => 'required'],
    //         'phoneFrom'=> ['rules' => 'required'],
    //         'addressFrom'=> ['rules' => 'required'], 
    //         'emailFrom'=> ['rules' => 'required'],
    //         'PanFrom'=> ['rules' => 'required'], 
    //         'businessNameFor'=> ['rules' => 'required'], 
    //         'phoneFor'=> ['rules' => 'required'], 
    //         'addressFor'=> ['rules' => 'required'],
    //         'emailFor'=> ['rules' => 'required'],
    //         'PanCardFor'=> ['rules' => 'required'], 
    //         // // 'quotationForEmail'=> ['rules' => 'required'],

    //         // // 'quotationForPan'=> ['rules' => 'required'],
    //         // // 'PanCardFor'=> ['rules' => 'required'], 
    //         // // 'quotationForEmail'=> ['rules' => 'required'],
    //     ];
  
    //     if($this->validate($rules)){
    //         // Retrieve tenantConfig from the headers
    //         $tenantConfigHeader = $this->request->getHeaderLine('X-Tenant-Config');
    //         if (!$tenantConfigHeader) {
    //             throw new \Exception('Tenant configuration not found.');
    //         }

    //         // Decode the tenantConfig JSON
    //         $tenantConfig = json_decode($tenantConfigHeader, true);

    //         if (!$tenantConfig) {
    //             throw new \Exception('Invalid tenant configuration.');
    //         }

    //         // Connect to the tenant's database
    //         $db = Database::connect($tenantConfig);
    //         $model = new PoModel($db);
        
    //         $model->insert($input);
             
    //         return $this->respond(['status'=>true,'message' => 'Item Added Successfully'], 200);
    //     }else{
    //         $response = [
    //             'status'=>false,
    //             'errors' => $this->validator->getErrors(),
    //             'message' => 'Invalid Inputs'
    //         ];
    //         return $this->fail($response , 409);
             
    //     }
            
    // }

    public function create()
    {
        $input = $this->request->getJSON();
    
        // Validation rules for PO
        $rules = [
            'poNo' => ['rules' => 'required'],
            'poDate' => ['rules' => 'required'],
            'vendor' => ['rules' => 'required'],
            'taxInvoiceNumber' => ['rules' => 'required'],
            'businessNameFrom' => ['rules' => 'required'],
            'phoneFrom' => ['rules' => 'required'],
            'addressFrom' => ['rules' => 'required'],
            'emailFrom' => ['rules' => 'required'],
            'PanFrom' => ['rules' => 'required'],
            'businessNameFor' => ['rules' => 'required'],
            'phoneFor' => ['rules' => 'required'],
            'addressFor' => ['rules' => 'required'],
            'emailFor' => ['rules' => 'required'],
            'PanCardFor' => ['rules' => 'required'],
        ];
    
        // Validate form data
        if ($this->validate($rules)) {
          
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new PoModel($db);
    
            // Insert the PO into the 'po' table
            $poData = [
                'poNo' => $input->poNo,
                'poDate' => $input->poDate,
                'vendor' => $input->vendor,
                'taxInvoiceNumber' => $input->taxInvoiceNumber,
                'businessNameFrom' => $input->businessNameFrom,
                'phoneFrom' => $input->phoneFrom,
                'addressFrom' => $input->addressFrom,
                'emailFrom' => $input->emailFrom,
                'PanFrom' => $input->PanFrom,
                'businessNameFor' => $input->businessNameFor,
                'phoneFor' => $input->phoneFor,
                'addressFor' => $input->addressFor,
                'emailFor' => $input->emailFor,
                'PanCardFor' => $input->PanCardFor,
            ];
    
            // Insert the PO and retrieve the generated poId
            $poId = $model->insert($poData);
    
            if ($poId) {
                // Now insert the items into the po_details table using the poId
                $poDetailsModel = new PoDetailModel($db); // Assuming you have this model for PO details
    
                // Iterate through each item in the input and insert into po_details
                foreach ($input->items as $item) {
                    $poDetailsData = [
                        'poId' => $poId,  // Foreign key linking to the PO
                        'itemName' => $item->itemName,
                        'quantity' => $item->quantity,
                        'rate' => $item->rate,
                        'amount' => $item->amount,
                    ];
    
                    // Insert the item into the po_details table
                    $poDetailsModel->insert($poDetailsData);
                }
    
                return $this->respond(['status' => true, 'message' => 'PO and items added successfully'], 200);
            } else {
                return $this->respond(['status' => false, 'message' => 'Failed to create the PO'], 500);
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
        $input = $this->request->getJSON();
        
        // Validation rules for the Quote
        $rules = [
            'quoteId ' => ['rules' => 'required|numeric'], // Ensure eventId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
           
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new QuotationsModel($db);

            // Retrieve the Quote by eventId
            $quoteId  = $input->quoteId ;
            $item = $model->find($quoteId ); // Assuming find method retrieves the Quote

            if (!$item) {
                return $this->fail(['status' => false, 'message' => 'Quote not found'], 404);
            }

            // Prepare the data to be updated (exclude eventId if it's included)
            $updateData = [
             'itemName'=> $input->itemName,
            'categoryName'=> $input->categoryName,
            'brandName'=> $input->brandName,
            'unit'=> $input->unit,
            'price'=> $input->price,
            // 'costPrice'=> $input->costPrice,
            // 'gstPercentage'=> $input->gstPercentage,
            'discount'=> $input->discount,
            // 'barcode'=> $input->barcode,
            // 'hsnCode'=> $input->hsnCode,
            // 'minStockLevel'=> $input->minStockLevel,
            'description'=> $input->description,
            ];

            // Update the Quote with new data
            $updated = $model->update($quoteId , $updateData);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Item Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update Quote'], 500);
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
        
        // Validation rules for the Quote
        $rules = [
            'quoteId ' => ['rules' => 'required'], // Ensure eventId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
           
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); $model = new QuotationsModel($db);

            // Retrieve the Quote by eventId
            $quoteId  = $input->quoteId ;
            $item = $model->find($quoteId ); // Assuming find method retrieves the Quote

            if (!$item) {
                return $this->fail(['status' => false, 'message' => 'Quote not found'], 404);
            }

            // Proceed to delete the Quote
            $deleted = $model->delete($quoteId );

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
