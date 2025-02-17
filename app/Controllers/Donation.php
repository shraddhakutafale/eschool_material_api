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
       // log_message('Donation Object', json_encode($input));
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
    
        if ($this->validate($rules)) {
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
    
            // Retrieve the last donation's receipt number
            $model = new DonationModel($db);
            $lastDonation = $model->select('receiptNo')->orderBy('donationId', 'DESC')->first();
    
            // Generate the next receipt number
            if ($lastDonation) {
                // Extract numeric part from the last receiptNo (e.g., SPG00001 -> 00001)
                preg_match('/(\d+)$/', $lastDonation['receiptNo'], $matches);
                $lastNumber = (int) $matches[0]; // The numeric part of the receiptNo
                $nextNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT); // Increment and pad to 5 digits
            } else {
                // If no donations exist, start from SPG00001
                $nextNumber = '00001';
            }
    
            // Format the receiptNo (e.g., SPG00001, SPG00002, ...)
            $receiptNo = 'SPG' . $nextNumber;
    
            // Prepare donation data
            $donation = [
                'name' => $input->name,
                'mobileNo' => $input->mobileNo,
                'financialYear' => $input->financialYear,
                'amount' => $input->amount,
                'receiptNo' => $receiptNo, // Use the newly generated receipt number
            ];
    
            // Insert the donation record into the database
            $donationId = $model->insert($donation);
    
            // Prepare transaction data
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
    
            // Insert the transaction record into the database
            $transactionModel = new TransactionModel($db);
            $transactionModel->insert($transaction);
         //   log_message('Donation Success',$receiptNo);
            // Respond with success message
            return $this->respond(['status' => true, 'message' => 'Donation Added Successfully' ,'data' => $receiptNo], 200);
        } else {
            // Validation failed, return errors
         //   log_message('Donation Error',$this->validator->getErrors());
            $response = [
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ];
            return $this->fail($response, 409);
        }
    }
    




}
