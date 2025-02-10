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
            'aadharCard'=> ['rules' => 'required'],
            'panNo'=> ['rules' => 'required'],
            'email'=> ['rules' => 'required'],
            'mobileNo'=> ['rules' => 'required'], 
            'address'=> ['rules' => 'required'], 
            'state'=> ['rules' => 'required'], 
            'district'=> ['rules' => 'required'], 
            'taluka'=> ['rules' => 'required'], 
            'pincode'=> ['rules' => 'required'], 
            'receiptNo'=> ['rules' => 'required'],
            'donationDate'=> ['rules' => 'required'],
            'financialNo'=> ['rules' => 'required'],
            'amount'=> ['rules' => 'required'],
            'amountInWords'=> ['rules' => 'required'],
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

            $donation = [
                'name' => $input->name,
                'aadharCard' => $input->aadharCard,
                'panNo' => $input->panNo,
                'email' => $input->email,
                'mobileNo' => $input->mobileNo,
                'address' => $input->address,
                'state' => $input->state,
                'district' => $input->district,
                'taluka' => $input->taluka,
                'pincode' => $input->pincode,
                'receiptNo' => $input->receiptNo,
                'donationDate' => $input->donationDate,
                'financialNo' => $input->financialNo,
                'amount' => $input->amount,
                'amountInWords' => $input->amountInWords,

            ];

            $model = new DonationModel($db);
        
            $memberId = $model->insert($member);
            $transaction = [
                'memberId' => $memberId,
                'transactionFor' => 'donation',
                'transactionNo' => $input->transactionNo,
                'transactionDate' => $input->transactionDate,
                'razorpayNo' => $input->razorpayNo,
                'amount' => $input->fees,
                'paymentMode' => $input->paymentMode,
                'status' => $input->status
            ];
            $modelTransaction = new TransactionModel($db);
            $modelTransaction->insert($transaction);
            
            return $this->respond(['status'=>true,'message' => 'Donation Added Successfully'], 200);
        }else{
            $response = [
                'status'=>false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ];
            return $this->fail($response , 409);
            
        }
            
    }
}
