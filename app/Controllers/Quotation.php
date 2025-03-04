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

    // public function getQuotationsPaging()
    // {
    //     $input = $this->request->getJSON();

    //     // Get the page number from the input, default to 1 if not provided
    //     $page = isset($input->page) ? $input->page : 1;
    //     // Define the number of items per page
    //     $perPage = isset($input->perPage) ? $input->perPage : 10;

    //     $tenantConfigHeader = $this->request->getHeaderLine('X-Tenant-Config');
    //     if (!$tenantConfigHeader) {
    //         throw new \Exception('Tenant configuration not found.');
    //     }

    //     // Decode the tenantConfig JSON
    //     $tenantConfig = json_decode($tenantConfigHeader, true);

    //     if (!$tenantConfig) {
    //         throw new \Exception('Invalid tenant configuration.');
    //     }

    //     // Connect to the tenant's database
    //     $db = Database::connect($tenantConfig);
    //     // Load UserModel with the tenant database connection
    //     $QuotationModel = new QuotationModel($db);
    //     $quotations = $QuotationModel->orderBy('createdDate', 'DESC')->paginate($perPage, 'default', $page);
    //     $pager = $QuotationModel->pager;

    //     $response = [
    //         "status" => true,
    //         "message" => "All Data Fetched",
    //         "data" => $quotations,
    //         "pagination" => [
    //             "currentPage" => $pager->getCurrentPage(),
    //             "totalPages" => $pager->getPageCount(),
    //             "totalItems" => $pager->getTotal(),
    //             "perPage" => $perPage
    //         ]   
    //     ];
    //     return $this->respond($response, 200);
    // }
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
    // Load QuotationModel with the tenant database connection
    $QuotationModel = new QuotationModel($db);
    $QuoteDetailModel = new QuotationDetailModel($db); // Assuming QuoteDetailModel is defined

    // Join quote_mst and quote_details on quoteId
    // Retrieve the quotations along with their details using JOIN
    $quotations = $QuotationModel->select('quote_mst.*, quote_details.*') // Select both master and details fields
        ->join('quote_details', 'quote_mst.quoteId = quote_details.quoteId', 'left') // Left join to include all quotations even if no details
        ->where('quote_mst.isDeleted', 0)
        ->orderBy('quote_mst.createdDate', 'DESC')
        ->paginate($perPage, 'default', $page);

    $pager = $QuotationModel->pager;

    // Prepare response with the merged data
    $response = [
        "status" => true,
        "message" => "All Data Fetched",
        "data" => $quotations, // Merged data from both tables
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
            'quoteNo' => ['rules' => 'required'],
            'quoteDate' => ['rules' => 'required'],
            'validDate' => ['rules' => 'required'],
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
                    $quoteItemId = $itemDetailsModel->insert($itemData);  // Assuming insert() method returns the inserted item ID
                    
                    // Optionally, if you want to handle the quoteitemid (like logging or returning it)
                    if ($quoteItemId) {
                        // This step is optional, but you could handle the `quoteitemid` here if necessary
                        // For example, adding the inserted `quoteitemid` to the response:
                        $itemData['quoteItemId'] = $quoteItemId;
                    }
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
        $model = new QuotationModel($db);

            // Retrieve the Quote by eventId
            $quoteId  = $input->quoteId ;
            $item = $model->find($quoteId ); // Assuming find method retrieves the Quote

            if (!$item) {
                return $this->fail(['status' => false, 'message' => 'Quote not found'], 404);
            }

            // Prepare the data to be updated (exclude eventId if it's included)
            $updateData = [
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
            $model = new QuotationModel($db);

            // Retrieve the Quote by eventId
            $quoteId  = $input->quoteId ;
            $item = $model->find($quoteId ); // Assuming find method retrieves the Quote

            if (!$item) {
                return $this->fail(['status' => false, 'message' => 'Quote not found'], 404);
            }

            // Proceed to delete the Quote
            // $deleted = $model->delete($quoteId );
              // Proceed to soft delete the Quote (set is_deleted to 1)
                $data = ['isDeleted' => 1];  // Soft delete by setting is_deleted flag
                $deleted = $model->update($quoteId, $data); // Use update method instead of delete

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


    // public function getDetailsByQuoteId($quoteId) {
    //     // Get tenant-specific database configuration using the X-Tenant-Config header
    //     $tenantService = new TenantService();
    //     $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    
    //     // Pass the tenant-specific database connection to the models
    //     $quoteModel = new QuoteModel($db);
    //     $quoteDetailModel = new QuoteDetailModel($db);
    
    //     // Fetch the quote data based on the provided quoteId
    //     $quote = $quoteModel->find($quoteId);
    
    //     // If no quote is found, return an error response
    //     if (!$quote) {
    //         return $this->respond(['status' => false, 'message' => 'Quote not found'], 404);
    //     }
    
    //     // Fetch all quote details associated with the quoteId
    //     $quoteDetails = $quoteDetailModel->where('quoteId', $quoteId)->findAll();
    
    //     // Return the response with the fetched data
    //     return $this->respond([
    //         'status' => true, 
    //         'message' => 'Quote details fetched successfully', 
    //         'data' => [
    //             'quote' => $quote,
    //             'quote_details' => $quoteDetails
    //         ]
    //     ], 200);
    // }

    public function getDetailsByQuoteId($quoteId) {
        // Get tenant-specific database configuration using the X-Tenant-Config header
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        
        // Pass the tenant-specific database connection to the models
        $quoteModel = new QuotationModel($db);
        $quoteDetailModel = new QuotationDetailModel($db);
        
        // Fetch the quote data based on the provided quoteId
        $quote = $quoteModel->find($quoteId);
        
        // If no quote is found, return an error response
        if (!$quote) {
            return $this->respond(['status' => false, 'message' => 'Quote not found'], 404);
        }
        
        // Fetch all quote details associated with the quoteId
        $quoteDetails = $quoteDetailModel->where('quoteId', $quoteId)->findAll();
        
        // Merge the quote and quote details into a single array
        $responseData = [
            'quote' => $quote,
            'quote_details' => $quoteDetails
        ];
        
        // Return the response with the merged data
        return $this->respond([
            'status' => true, 
            'message' => 'Quote details fetched successfully', 
            'data' => $responseData
        ], 200);
    }
    
    
    
}
