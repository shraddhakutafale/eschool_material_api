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
    
    
    // public function create()
    // {
    //     $input = $this->request->getJSON();
    //     $rules = [
    //         'name' => ['rules' => 'required|min_length[4]|max_length[255]'],
    //         'email' => ['rules' => 'required|min_length[4]|max_length[255]|valid_email|is_unique[user_mst.email]'],
    //         'mobileNo' => ['rules' => 'required|min_length[10]|max_length[10]|is_unique[user_mst.mobileNo]'],
    //         'password' => ['rules' => 'required|min_length[8]|max_length[255]'],
    //     ];
  
    //     if($this->validate($rules)){
    //         $model = new UserModel();
    //         $data = [
    //             'name'     => $input->name,
    //             'mobileNo' => $input->mobileNo,
    //             'email'    => $input->email,
    //             'password' => password_hash($input->password, PASSWORD_DEFAULT)
    //         ];
    //         $model->insert($data);
             
    //         return $this->respond(["status" => true, 'message' => 'Created Successfully'], 200);
    //     }else{
    //         $response = [
    //             'status'=>false,
    //             'errors' => $this->validator->getErrors(),
    //             'message' => 'Invalid Inputs'
    //         ];
    //         return $this->fail($response , 409);
             
    //     }
            
    // }

    // public function update()
    // {
    //     $input = $this->request->getJSON();
    
    //     // Validation rules for updating user
    //     $rules = [
    //         'userId'   => ['rules' => 'required|numeric'],
    //         'name'     => ['rules' => 'required|min_length[4]|max_length[255]'],
    //         'email'    => ['rules' => 'required|min_length[4]|max_length[255]|valid_email'],
    //         'mobileNo' => ['rules' => 'required|min_length[10]|max_length[10]']
    //     ];
    
    //     if ($this->validate($rules)) {
    //         $model = new UserModel();
    
    //         // Check if the user exists
    //         $user = $model->find($input->userId);
    //         if (!$user) {
    //             return $this->fail(['status' => false, 'message' => 'User not found'], 404);
    //         }
    
    //         // Data to update
    //         $updateData = [
    //             'name'     => $input->name,
    //             'email'    => $input->email,
    //             'mobileNo' => $input->mobileNo
    //         ];
    
    //         // Check if the user is updating their email or mobile number
    //         if ($user['email'] !== $input->email) {
    //             $rules['email']['rules'] .= '|is_unique[user_mst.email]';
    //         }
    //         if ($user['mobileNo'] !== $input->mobileNo) {
    //             $rules['mobileNo']['rules'] .= '|is_unique[user_mst.mobileNo]';
    //         }
    
    //         // Validate unique email and mobile number separately
    //         if (!$this->validate($rules)) {
    //             return $this->fail([
    //                 'status'  => false,
    //                 'errors'  => $this->validator->getErrors(),
    //                 'message' => 'Invalid Inputs'
    //             ], 409);
    //         }
    
    //         // Update the user
    //         $model->update($input->userId, $updateData);
    
    //         return $this->respond(["status" => true, 'message' => 'User Updated Successfully'], 200);
    //     } else {
    //         return $this->fail([
    //             'status'  => false,
    //             'errors'  => $this->validator->getErrors(),
    //             'message' => 'Invalid Inputs'
    //         ], 409);
    //     }
    // }
    
    // public function delete()
    // {
    //     $input = $this->request->getJSON();
        
    //     // Validation rules for userId
    //     $rules = [
    //         'userId' => ['rules' => 'required|numeric']
    //     ];
    
    //     if ($this->validate($rules)) {
    //         $model = new UserModel();
    
    //         // Check if the user exists
    //         $user = $model->find($input->userId);
    //         if (!$user) {
    //             return $this->fail(['status' => false, 'message' => 'User not found'], 404);
    //         }
    
    //         // Soft delete by updating isDeleted flag
    //         $updateData = [
    //             'isDeleted' => 1
    //         ];
    //         $deleted = $model->update($input->userId, $updateData);
    
    //         if ($deleted) {
    //             return $this->respond(["status" => true, 'message' => 'User Deleted Successfully'], 200);
    //         } else {
    //             return $this->fail(['status' => false, 'message' => 'Failed to delete user'], 500);
    //         }
    //     } else {
    //         return $this->fail([
    //             'status'  => false,
    //             'errors'  => $this->validator->getErrors(),
    //             'message' => 'Invalid Inputs'
    //         ], 409);
    //     }
    // }
    


    // get all role api

    public function getAllSms()
    {
        $sms = new SmsModel;
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $sms->findAll()], 200);
    }
   

    // public function createRole()
    // {
    //     $input = $this->request->getJSON();
    //     $rules = [
    //         'roleName' => ['rules' => 'required'],
    //         'note'     => ['rules' => 'required']
    //     ];
    
    //     if ($this->validate($rules)) {
    //         $model = new RoleModel();
    //         $data = [
    //             'roleName' => $input->roleName,
    //             'note'     => $input->note
    //         ];
    //         $model->insert($data);
    
    //         return $this->respond(["status" => true, 'message' => 'Role Created Successfully'], 200);
    //     } else {
    //         $response = [
    //             'status' => false,
    //             'errors' => $this->validator->getErrors(),
    //             'message' => 'Invalid Inputs'
    //         ];
    //         return $this->fail($response, 409);
    //     }
    // }
    

    // public function deleteRole()
    // {
    //     $input = $this->request->getJSON();
        
    //     // Validation rules for the roleId
    //     $rules = [
    //         'roleId' => ['rules' => 'required|numeric']
    //     ];
    
    //     if ($this->validate($rules)) {
    //         $model = new RoleModel();
    
    //         // Check if the role exists
    //         $role = $model->find($input->roleId);
    //         if (!$role) {
    //             return $this->fail(['status' => false, 'message' => 'Role not found'], 404);
    //         }
    
    //         // Soft delete by setting isDeleted to 1
    //         $updateData = ['isDeleted' => 1];
    //         $model->update($input->roleId, $updateData);
    
    //         return $this->respond(["status" => true, 'message' => 'Role Deleted Successfully'], 200);
    //     } else {
    //         $response = [
    //             'status' => false,
    //             'errors' => $this->validator->getErrors(),
    //             'message' => 'Invalid Inputs'
    //         ];
    //         return $this->fail($response, 409);
    //     }
    // }
    

    
    // public function updateRole()
    // {
    //     $input = $this->request->getJSON();
    
    //     // Validation rules for updating role
    //     $rules = [
    //         'roleId'   => ['rules' => 'required|numeric'],
    //         'roleName' => ['rules' => 'required'],
    //         'note'     => ['rules' => 'required']
    //     ];
    
    //     if ($this->validate($rules)) {
    //         $model = new RoleModel();
    
    //         // Check if the role exists
    //         $role = $model->find($input->roleId);
    //         if (!$role) {
    //             return $this->fail(['status' => false, 'message' => 'Role not found'], 404);
    //         }
    
    //         // Data to update
    //         $updateData = [
    //             'roleName' => $input->roleName,
    //             'note'     => $input->note
    //         ];
    
    //         // Update the role
    //         $model->update($input->roleId, $updateData);
    
    //         return $this->respond(["status" => true, 'message' => 'Role Updated Successfully'], 200);
    //     } else {
    //         $response = [
    //             'status' => false,
    //             'errors' => $this->validator->getErrors(),
    //             'message' => 'Invalid Inputs'
    //         ];
    //         return $this->fail($response, 409);
    //     }
    // }



    // get all right api 

    public function getAllSmtp()
    {
        $smtp = new SmtpModel;
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $smtp->findAll()], 200);
    }

    // public function createRight()
    // {
    //     $input = $this->request->getJSON();
    //     $rules = [
    //         'rightName'  => ['rules' => 'required'],
    //         'rightLabel' => ['rules' => 'required'],
    //         'iconUrl'    => ['rules' => 'required'],
    //         'route'      => ['rules' => 'required']
    //     ];
    
    //     if ($this->validate($rules)) {
    //         $model = new RightModel();  // Assuming you have a RightModel
    //         $data = [
    //             'rightName'  => $input->rightName,
    //             'rightLabel' => $input->rightLabel,
    //             'iconUrl'    => $input->iconUrl,
    //             'route'      => $input->route
    //         ];
    //         $model->insert($data);
    
    //         return $this->respond(["status" => true, 'message' => 'Right Created Successfully'], 200);
    //     } else {
    //         $response = [
    //             'status'  => false,
    //             'errors'  => $this->validator->getErrors(),
    //             'message' => 'Invalid Inputs'
    //         ];
    //         return $this->fail($response, 409);
    //     }
    // }
    

    // public function deleteRight()
    // {
    //     $input = $this->request->getJSON();
        
    //     // Validation rules for the roleId
    //     $rules = [
    //         'rightId' => ['rules' => 'required|numeric']
    //     ];
    
    //     if ($this->validate($rules)) {
    //         $model = new RightModel();
    
    //         // Check if the role exists
    //         $right = $model->find($input->rightId);
    //         if (!$right) {
    //             return $this->fail(['status' => false, 'message' => 'Right not found'], 404);
    //         }
    
    //         // Soft delete by setting isDeleted to 1
    //         $updateData = ['isDeleted' => 1];
    //         $model->update($input->rightId, $updateData);
    
    //         return $this->respond(["status" => true, 'message' => 'Right Deleted Successfully'], 200);
    //     } else {
    //         $response = [
    //             'status' => false,
    //             'errors' => $this->validator->getErrors(),
    //             'message' => 'Invalid Inputs'
    //         ];
    //         return $this->fail($response, 409);
    //     }
    // }
    

    
    // public function updateRight()
    // {
    //     $input = $this->request->getJSON();
    
    //     // Validation rules for updating role
    //     $rules = [
    //         'rightName'  => ['rules' => 'required'],
    //         'rightLabel' => ['rules' => 'required'],
    //         'iconUrl'    => ['rules' => 'required'],
    //         'route'      => ['rules' => 'required']
    //     ];
    
    //     if ($this->validate($rules)) {
    //         $model = new RightModel();
    
    //         // Check if the role exists
    //         $right = $model->find($input->rightId);
    //         if (!$right) {
    //             return $this->fail(['status' => false, 'message' => 'Right not found'], 404);
    //         }
    
    //         // Data to update
    //         $updateData = [
    //             'rightName'  => $input->rightName,
    //             'rightLabel' => $input->rightLabel,
    //             'iconUrl'    => $input->iconUrl,
    //             'route'      => $input->route
    //         ];
    
    //         // Update the role
    //         $model->update($input->rightId, $updateData);
    
    //         return $this->respond(["status" => true, 'message' => 'Right Updated Successfully'], 200);
    //     } else {
    //         $response = [
    //             'status' => false,
    //             'errors' => $this->validator->getErrors(),
    //             'message' => 'Invalid Inputs'
    //         ];
    //         return $this->fail($response, 409);
    //     }
    // }

  }