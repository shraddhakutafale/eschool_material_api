<?php
 
namespace App\Controllers;
 
use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\FirebaseModel;
use App\Models\SmsModel;
use App\Models\SmtpModel;

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;
 
class Setting extends BaseController
{
    use ResponseTrait;
     
    public function index()
    {
        $firebases = new FirebaseModel;
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $firebases->findAll()], 200);
    }
    
    
    public function createFirebase()
{
    $input = $this->request->getJSON();
    $rules = [
        'firebaseName'       => ['rules' => 'required'],
        'apiKey'             => ['rules' => 'required'],
        'authDomain'         => ['rules' => 'required'],
        'projectId'          => ['rules' => 'required'],
        'storageBucket'      => ['rules' => 'required'],
        'messagingSenderId'  => ['rules' => 'required'],
        'appId'              => ['rules' => 'required'],
        'businessId'         => ['rules' => 'required'],
    ];

    if ($this->validate($rules)) {
        $model = new FirebaseModel();
        $data = [
            'firebaseName'      => $input->firebaseName,
            'apiKey'            => $input->apiKey,
            'authDomain'        => $input->authDomain,
            'projectId'         => $input->projectId,
            'storageBucket'     => $input->storageBucket,
            'messagingSenderId' => $input->messagingSenderId,
            'appId'             => $input->appId,
            'businessId'        => $input->businessId
        ];
        $model->insert($data);

        return $this->respond(["status" => true, 'message' => 'Created Successfully'], 200);
    } else {
        $response = [
            'status' => false,
            'errors' => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ];
        return $this->fail($response, 409);
    }
}


public function updateFirebase()
{
    $input = $this->request->getJSON();

    // Validation rules for updating user and Firebase details
    $rules = [
        'firebaseId'            => ['rules' => 'required|numeric'],
        'firebaseName'      => ['rules' => 'required'],
        'apiKey'            => ['rules' => 'required'],
        'authDomain'        => ['rules' => 'required'],
        'projectId'         => ['rules' => 'required'],
        'storageBucket'     => ['rules' => 'required'],
        'messagingSenderId' => ['rules' => 'required'],
        'appId'             => ['rules' => 'required'],
        'businessId'        => ['rules' => 'required']
    ];

    if ($this->validate($rules)) {
        $model = new FirebaseModel();

        // Check if the user exists
        $firebase = $model->find($input->firebaseId);
        if (!$firebase) {
            return $this->fail(['status' => false, 'message' => 'User not found'], 404);
        }

        // Data to update
        $updateData = [
            'firebaseName'      => $input->firebaseName,
            'apiKey'            => $input->apiKey,
            'authDomain'        => $input->authDomain,
            'projectId'         => $input->projectId,
            'storageBucket'     => $input->storageBucket,
            'messagingSenderId' => $input->messagingSenderId,
            'appId'             => $input->appId,
            'businessId'        => $input->businessId
        ];

        // Update Firebase details
        $model->update($input->firebaseId, $updateData);

        return $this->respond(["status" => true, 'message' => 'Firebase details updated successfully'], 200);
    } else {
        return $this->fail([
            'status'  => false,
            'errors'  => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ], 409);
    }
}

      public function deleteFirebase()
    {
        $input = $this->request->getJSON();
        
        // Validation rules for the roleId
        $rules = [
            'firebaseId' => ['rules' => 'required|numeric']
        ];
    
        if ($this->validate($rules)) {
            $model = new FirebaseModel();
    
            // Check if the role exists
            $firebase = $model->find($input->firebaseId);
            if (!$firebase) {
                return $this->fail(['status' => false, 'message' => 'Firebase not found'], 404);
            }
    
            // Soft delete by setting isDeleted to 1
            $updateData = ['isDeleted' => 1];
            $model->update($input->firebaseId, $updateData);
    
            return $this->respond(["status" => true, 'message' => 'Firebase Deleted Successfully'], 200);
        } else {
            $response = [
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ];
            return $this->fail($response, 409);
        }
    }
    



    // get all role api

    public function getAllSms()
    {
        $sms = new SmsModel;
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $sms->findAll()], 200);
    }
   

    public function createSms()
    {
        $input = $this->request->getJSON();
    
        // Validation rules for SMS configuration fields
        $rules = [
            'templateId'    => ['rules' => 'required'],
            'smsGatewayUrl' => ['rules' => 'required'],
            'authkey'       => ['rules' => 'required'],
            'apiElement'    => ['rules' => 'required'],
            'updUserId'     => ['rules' => 'required'],
            'updDatetime'   => ['rules' => 'required']
        ];
    
        if ($this->validate($rules)) {
            $model = new SmsModel(); // Ensure you have this model for SMS configurations
    
            $data = [
                'templateId'    => $input->templateId,
                'smsGatewayUrl' => $input->smsGatewayUrl,
                'authkey'       => $input->authkey,
                'apiElement'    => $input->apiElement,
                'updUserId'     => $input->updUserId,
                'updDatetime'   => $input->updDatetime
            ];
    
            $model->insert($data);
    
            return $this->respond([
                "status"  => true,
                "message" => "SMS Configuration Created Successfully"
            ], 200);
        } else {
            return $this->fail([
                'status'  => false,
                'errors'  => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ], 409);
        }
    }
    

    public function deleteSms()
    {
        $input = $this->request->getJSON();
        
        $rules = [
            'smsConfigId' => ['rules' => 'required|numeric']
        ];
    
        if ($this->validate($rules)) {
            $model = new SmsModel();
    
            // Check if the role exists
            $sms= $model->find($input->smsConfigId);
            if (!$sms) {
                return $this->fail(['status' => false, 'message' => 'Sms not found'], 404);
            }
    
            // Soft delete by setting isDeleted to 1
            $updateData = ['isDeleted' => 1];
            $model->update($input->smsConfigId, $updateData);
    
            return $this->respond(["status" => true, 'message' => 'Sms Deleted Successfully'], 200);
        } else {
            $response = [
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ];
            return $this->fail($response, 409);
        }
    }
    
    public function updateSms()
    {
        $input = $this->request->getJSON();
    
        // Validation rules for updating SMS configuration
        $rules = [
            'smsConfigId'   => ['rules' => 'required'],
            'templateId'    => ['rules' => 'required'],
            'smsGatewayUrl' => ['rules' => 'required'],
            'authkey'       => ['rules' => 'required'],
            'apiElement'    => ['rules' => 'required'],
            'updUserId'     => ['rules' => 'required'],
            'updDatetime'   => ['rules' => 'required']
        ];
    
        if ($this->validate($rules)) {
            $model = new SmsModel(); 
    
            // Check if the SMS config exists
            $smsConfig = $model->find($input->smsConfigId);
            if (!$smsConfig) {
                return $this->fail(['status' => false, 'message' => 'SMS  not found'], 404);
            }
    
            // Data to update
            $updateData = [
                'templateId'    => $input->templateId,
                'smsGatewayUrl' => $input->smsGatewayUrl,
                'authkey'       => $input->authkey,
                'apiElement'    => $input->apiElement,
                'updUserId'     => $input->updUserId,
                'updDatetime'   => $input->updDatetime
            ];
    
            // Update the SMS configuration
            $model->update($input->smsConfigId, $updateData);
    
            return $this->respond([
                "status"  => true,
                "message" => "SMS  Updated Successfully"
            ], 200);
        } else {
            return $this->fail([
                'status'  => false,
                'errors'  => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ], 409);
        }
    }
    



    // get all right api 

    public function getAllSmtp()
    {
        $smtp = new SmtpModel;
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $smtp->findAll()], 200);
    }


    public function createSmtp()
    {
        $input = $this->request->getJSON();
    
        // Validation rules for SMTP configuration
        $rules = [
            'protocol'  => ['rules' => 'required'],
            'smtpHost'  => ['rules' => 'required'],
            'smtpPort'  => ['rules' => 'required'],
            'fromMail'  => ['rules' => 'required'],
            'smtpUser'  => ['rules' => 'required'],
            'smtpPass'  => ['rules' => 'required'],
            'updUserId' => ['rules' => 'required']
        ];
    
        if ($this->validate($rules)) {
            $model = new SmtpModel(); // Ensure this model exists for SMTP configurations
    
            // Data to insert
            $data = [
                'protocol'  => $input->protocol,
                'smtpHost'  => $input->smtpHost,
                'smtpPort'  => $input->smtpPort,
                'fromMail'  => $input->fromMail,
                'smtpUser'  => $input->smtpUser,
                'smtpPass'  => $input->smtpPass,
                'updUserId' => $input->updUserId
            ];
    
            // Insert the SMTP configuration
            $model->insert($data);
    
            return $this->respond([
                "status"  => true,
                "message" => "SMTP Configuration Created Successfully"
            ], 200);
        } else {
            return $this->fail([
                'status'  => false,
                'errors'  => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ], 409);
        }
    }
    

    public function deleteSmtp()
    {
        $input = $this->request->getJSON();
        
        // Validation rules for the roleId
        $rules = [
            'smtpId' => ['rules' => 'required|numeric']
        ];
    
        if ($this->validate($rules)) {
            $model = new SmtpModel();
    
            // Check if the role exists
            $smtp = $model->find($input->smtpId);
            if (!$smtp) {
                return $this->fail(['status' => false, 'message' => 'Smtp not found'], 404);
            }
    
            // Soft delete by setting isDeleted to 1
            $updateData = ['isDeleted' => 1];
            $model->update($input->smtpId, $updateData);
    
            return $this->respond(["status" => true, 'message' => 'Smtp Deleted Successfully'], 200);
        } else {
            $response = [
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ];
            return $this->fail($response, 409);
        }
    }
    

    
    public function updateSmtp()
    {
        $input = $this->request->getJSON();
    
        // Validation rules for updating SMTP configuration
        $rules = [
            'smtpId'    => ['rules' => 'required'],
            'protocol'  => ['rules' => 'required'],
            'smtpHost'  => ['rules' => 'required'],
            'smtpPort'  => ['rules' => 'required'],
            'fromMail'  => ['rules' => 'required'],
            'smtpUser'  => ['rules' => 'required'],
            'smtpPass'  => ['rules' => 'required'],
            'updUserId' => ['rules' => 'required']
        ];
    
        if ($this->validate($rules)) {
            $model = new SmtpModel(); // Ensure this model exists for SMTP configurations
    
            // Check if the SMTP configuration exists
            $smtp = $model->find($input->smtpId);
            if (!$smtp) {
                return $this->fail(['status' => false, 'message' => 'SMTP Configuration not found'], 404);
            }
    
            // Data to update
            $updateData = [
                'protocol'  => $input->protocol,
                'smtpHost'  => $input->smtpHost,
                'smtpPort'  => $input->smtpPort,
                'fromMail'  => $input->fromMail,
                'smtpUser'  => $input->smtpUser,
                'smtpPass'  => $input->smtpPass,
                'updUserId' => $input->updUserId
            ];
    
            // Update the SMTP configuration
            $model->update($input->smtpId, $updateData);
    
            return $this->respond([
                "status"  => true,
                "message" => "SMTP Configuration Updated Successfully"
            ], 200);
        } else {
            return $this->fail([
                'status'  => false,
                'errors'  => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ], 409);
        }
    }
    
  }