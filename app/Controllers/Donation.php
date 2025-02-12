<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\DonationModel;
use App\Models\TransactionModel;
use CodeIgniter\API\ResponseTrait;
use Config\Database;

class Donation extends BaseController
{
    use ResponseTrait;

  public function index()
    {
        // Retrieve tenantConfig from the headers
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
        $donationModel = new DonationModel($db);
        $response = [
            "status" => true,
            "message" => "All Data Fetched",
            "data" => $donationModel->findAll(),
        ];
        return $this->respond($response, 200);
    }

public function createWeb()
{
    $input = $this->request->getJSON();
    $rules = [
        'name'=> ['rules' => 'required'], 
        'mobileNo'=> ['rules' => 'required'], 
        'financialYear'=> ['rules' => 'required'],
        'amount'=> ['rules' => 'required'],
        'transactionNo' => ['rules' => 'required'],
        'transactionDate' => ['rules' => 'required'],
        'paymentMode' => ['rules' => 'required'],
        'status' => ['rules' => 'required']
    ];

    if($this->validate($rules)){
        // Retrieve tenantConfig from the headers
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
        

        // Retrieve the last used receipt number
        $model = new DonationModel($db);
 
        // Prepare donation data
        $donation = [
            'name' => $input->name,
            'mobileNo' => $input->mobileNo,
            'financialYear' => $input->financialYear,
            'amount' => $input->amount,
            'receiptNo' => $input->receiptNo, // Assign next receipt number
        ];

        // Insert the donation record into the database
        $donationModel = new DonationModel($db);
        $donationId = $donationModel->insert($donation);

        // Insert the transaction data
        $transaction = [
            'donationId' => $donationId,
            'transactionFor' => 'donation',
            'transactionNo' => $input->transactionNo,
            'transactionDate' => $input->transactionDate,
            'razorpayNo' => $input->razorpayNo, // Optional field
            'amount' => $input->amount,
            'paymentMode' => $input->paymentMode,
            'status' => $input->status,
        ];

        // Insert transaction into the database
        $transactionModel = new TransactionModel($db);
        $transactionModel->insert($transaction);

        // Respond with success message
        return $this->respond(['status' => true, 'message' => 'Donation Added Successfully'], 200);
    } else {
        // Validation failed, return errors
        $response = [
            'status' => false,
            'errors' => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ];
        return $this->fail($response , 409);
    }
}




}
