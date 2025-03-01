<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\QuotationModel;
use App\Models\QuotationDetailModel;
use App\Libraries\TenantService;

use Config\Database;

class Quotation extends BaseController
{
    use ResponseTrait;

    public function index()
    {
       
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 
        
        // Load UserModel with the tenant database connection
        $quotationModel = new QuotationModel($db);
        return $this->respond(['quotation' => $quotationModel->findAll()], 200);
    }

    public function getQuotationsPaging()
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
        $QuotationModel = new QuotationModel($db);
        $quotations = $QuotationModel->orderBy('createdDate', 'DESC')->paginate($perPage, 'default', $page);
        $pager = $QuotationModel->pager;

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
    //         'quoteNo'=> ['rules' => 'required'], 
    //         'quoteDate'=> ['rules' => 'required'], 
    //         'validDate'=> ['rules' => 'required'], 
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
    //         $model = new QuotationModel($db);
        
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

    // Validation rules for quotation
    $rules = [
        'quoteNo'=> ['rules' => 'required'], 
        'quoteDate'=> ['rules' => 'required'], 
        'validDate'=> ['rules' => 'required'], 
        // 'businessNameFrom'=> ['rules' => 'required'],
        // 'phoneFrom'=> ['rules' => 'required'],
        // 'addressFrom'=> ['rules' => 'required'], 
        // 'emailFrom'=> ['rules' => 'required'],
        // 'PanFrom'=> ['rules' => 'required'], 
        // 'businessNameFor'=> ['rules' => 'required'], 
        // 'phoneFor'=> ['rules' => 'required'], 
        // 'addressFor'=> ['rules' => 'required'],
        // 'emailFor'=> ['rules' => 'required'],
        // 'PanCardFor'=> ['rules' => 'required'], 
    ];

    // Validate form data
    if ($this->validate($rules)) {
       
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new QuotationModel($db);
        
        // Insert the quotation into the 'quotation' table
        $quotationData = [
            'quoteNo' => $input->quoteNo,
            'quoteDate' => $input->quoteDate,
            'validDate' => $input->validDate,
            'businessNameFrom' => $input->businessNameFrom,
            'phoneFrom' => $input->phoneFrom,
            'addressFrom' => $input->addressFrom,
            'emailFrom' => $input->emailFrom,
            'PanFrom' => $input->PanFrom,
            'businessNameFor' => $input->businessNameFor,
            'phoneFor' => $input->phoneFor,
            'addressFor' => $input->addressFor,
            'emailFor' => $input->emailFor,
            'PanCardFor' => $input->PanCardFor
        ];
        
        // Insert the quotation and retrieve the generated quoteId
        $quoteId = $model->insert($quotationData);
        
        if ($quoteId) {
            // Now insert the items into the item_details table using the quoteId

            $itemDetailsModel = new QuotationDetailModel($db); // Assuming you have this model for the item details

            // Iterate through each item in the input and insert into item_details
            foreach ($input->items as $item) {
                $itemData = [
                    'quoteId' => $quoteId,  // Foreign key linking to the quotation
                    'itemName' => $item->itemName,
                    'quantity' => $item->quantity,
                    'rate' => $item->rate,
                    'amount' => $item->amount
                ];
                // Insert the item into the item_details table
                $itemDetailsModel->insert($itemData);
            }

            return $this->respond(['status' => true, 'message' => 'Quotation and items added successfully'], 200);
        } else {
            return $this->respond(['status' => false, 'message' => 'Failed to create the quotation'], 500);
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
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new QuotationsModel($db);

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


    public function sendQuoteEmail()
    {
        $input = $this->request->getJSON();
        $emailService = new EmailService();

        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 

        // Load UserModel with the tenant database connection
        $quoteModel = new SmtpConfig($db);

        $smtpConfig = $quoteModel->where('isActive', 1)->where('isDeleted', 0)->first();

        if (!$smtpConfig) {
            return $this->respond(['status' => false, 'message' => 'SMTP configuration not found.'], ResponseInterface::HTTP_NOT_FOUND);
        }

        // Prepare email content
        $to = $input->email;
        $subject = 'Quote Request';
        $message = 'this is mail';

        $response = $emailService->sendEmail($smtpConfig, $to, $subject, $message);
        return $this->respond($response);
        
    }
}
