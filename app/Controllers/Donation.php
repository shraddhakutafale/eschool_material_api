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
        //
    }

    public function createWeb()
    {
        $input = $this->request->getJSON();
        $rules = [
            
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
                'type' => $input->type,
                'name' => $input->name,
                'dob' => $input->dob,
                'bloodGroup' => $input->bloodGroup,
                'email' => $input->email,
                'mobileNo' => $input->mobileNo,
                'address' => $input->address,
                'state' => $input->state,
                'district' => $input->district,
                'taluka' => $input->taluka,
                'pincode' => $input->pincode,
                'fees' => $input->fees,
                'aadharCard' => $input->aadharCard
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
            
            return $this->respond(['status'=>true,'message' => 'Member Added Successfully'], 200);
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
