<?php
 
namespace App\Controllers;
 
use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\UserModel;
use App\Models\RolePermissionModel;
use App\Models\RightModel;
use App\Models\RoleModel;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;
 
class User extends BaseController
{
    use ResponseTrait;
     
    public function index()
    {
        $users = new UserModel;
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $users->findAll()], 200);
    }
    
    public function profile()
    {
        $key = "Exiaa@11";
        $header = $this->request->getHeader("Authorization");
        $token = null;
  
        // extract the token from the header
        if(!empty($header)) {
            if (preg_match('/Bearer\s(\S+)/', $header, $matches)) {
                $token = $matches[1];
            }
        }
        
        $decoded = JWT::decode($token, new Key($key, 'HS256'));
        $userModel = new UserModel();
        $rolePermissionModel = new RolePermissionModel();
        $rightModel = new RightModel();
        $roleModel = new RoleModel();

        $input = $this->request->getJSON();
        
        $user = $userModel->where('email', $decoded->email)->first();

        $role = $roleModel->where('roleId', $user['roleId'])->first();
        $user['role'] = $role;

        $rolePermissions = $rolePermissionModel->where('roleId', $user['roleId'])->findAll();
        $rolePermissionsArray = [];
        foreach($rolePermissions as $key => $rolePermission){
            $rolePermissionsArray[$key] = $rolePermission;
            $right = $rightModel->where('rightId', $rolePermission['rightId'])->first();
            $rolePermissionsArray[$key]['right'] = $right;
        }
        $user['rolePermissions'] = $rolePermissionsArray;
        if(is_null($user)) {
            return $this->respond(['status' => false, 'message' => 'User not found.'], 404);
        }
         
        return $this->respond($user, 200);
    }

    public function menu()
    {
        $key = "Exiaa@11";
        $header = $this->request->getHeader("Authorization");
        $tenant = $this->request->getHeader('Tenant');
        $token = null;
  
        // extract the token from the header
        if(!empty($header)) {
            if (preg_match('/Bearer\s(\S+)/', $header, $matches)) {
                $token = $matches[1];
            }
        }
        
        if(!empty($tenant)) {
            
        }
        
        $decoded = JWT::decode($token, new Key($key, 'HS256'));
        $userModel = new UserModel();
        $rolePermissionModel = new RolePermissionModel();
        $rightModel = new RightModel();
        $roleModel = new RoleModel();

        $input = $this->request->getJSON();
        
        $user = $userModel->where('email', $decoded->email)->first();

        $role = $roleModel->where('roleId', $user['roleId'])->first();
        $user['role'] = $role;

        $rolePermissions = $rolePermissionModel->where('roleId', $user['roleId'])->findAll();
        $menu = [];
        foreach($rolePermissions as $key => $rolePermission){
            $right = $rightModel->where('rightId', $rolePermission['rightId'])->where('parentRightId',0)->first();
            
            if ($right) {
                // Store the parent menu details
                $menuItem = [
                    'route' => $right['route'],
                    'name' => $right['route'],
                    'icon' => $right['iconUrl'],
                    'type' => 'sub', // Default to 'sub' in case it has children
                    'children' => [] // Initialize children array
                ];
        
                // Fetch the submenus (children) where parentRightId matches this rightId
                $subMenus = $rightModel->where('parentRightId', $right['rightId'])->findAll();
        
                if (empty($subMenus)) {
                    $menuItem['type'] = 'link'; // If no children, it's a simple link
                } else {
                    foreach ($subMenus as $subMenu) {
                        $menuItem['children'][] = [
                            'route' => $subMenu['route'],
                            'name' => $subMenu['route'],
                            'icon' => $subMenu['iconUrl'],
                            'type' => 'link'
                        ];
                    }
                }
        
                // Store in the menu array
                $menu[] = $menuItem;
            }
        }
        if(is_null($user)) {
            return $this->respond(['status' => false, 'message' => 'User not found.'], 404);
        }
         
        return $this->respond(['menu' => $menu], 200);
    }

    // public function login()
    // {
    //     $userModel = new UserModel();
    //     $rolePermissionModel = new RolePermissionModel();
    //     $rightModel = new RightModel();
    //     $roleModel = new RoleModel();

    //     $input = $this->request->getJSON();
        
    //     $user = $userModel->where('email', $input->email)->first();

    //     $role = $roleModel->where('roleId', $user['roleId'])->first();
    //     $user['role'] = $role;

    //     $rolePermissions = $rolePermissionModel->where('roleId', $user['roleId'])->findAll();
    //     $rolePermissionsArray = [];
    //     foreach($rolePermissions as $key => $rolePermission){
    //         $rolePermissionsArray[$key] = $rolePermission;
    //         $right = $rightModel->where('rightId', $rolePermission['rightId'])->first();
    //         $rolePermissionsArray[$key]['right'] = $right;
    //     }
    //     $user['rolePermissions'] = $rolePermissionsArray;
    //     if(is_null($user)) {
    //         return $this->respond(['status' => false, 'message' => 'User not found.'], 401);
    //     }
  
    //     $pwd_verify = password_verify($input->password, $user['password']);
        
    //     if(!$pwd_verify) {
    //         return $this->respond(['status' => false, 'message' => 'Invalid username or password.'], 401);
    //     }
 
    //     $key = "Exiaa@11";
    //     $iat = time(); // current timestamp value
    //     $exp = $iat + 3600;
 
    //     $payload = array(
    //         "iss" => "Issuer of the JWT",
    //         "aud" => "Audience that the JWT",
    //         "sub" => "Subject of the JWT",
    //         "iat" => $iat, //Time the JWT issued at
    //         "exp" => $exp, // Expiration time of token
    //         "email" => $user['email'],
    //     );
         
    //     $token = JWT::encode($payload, $key, 'HS256');
 
    //     $response = [
    //         'status' => true,
    //         'message' => 'Login Succesful',
    //         'token' => $token,
    //         'user' => $user
    //     ];
         
    //     return $this->respond($response, 200);
    // }

    public function login()
    {
        $userModel = new UserModel();
        $rolePermissionModel = new RolePermissionModel();
        $rightModel = new RightModel();
        $roleModel = new RoleModel();

        $input = $this->request->getJSON();
        
        $user = $userModel->where('email', $input->email)->first();
        if(is_null($user)) {
            return $this->respond(['status' => false, 'message' => 'Email not found.'], 401);
        }
        $role = $roleModel->where('roleId', $user['roleId'])->first();
        $user['role'] = $role;

        $rolePermissions = $rolePermissionModel->where('roleId', $user['roleId'])->findAll();
        $rolePermissionsArray = [];
        foreach($rolePermissions as $key => $rolePermission){
            $rolePermissionsArray[$key] = $rolePermission;
            $right = $rightModel->where('rightId', $rolePermission['rightId'])->first();
            $rolePermissionsArray[$key]['right'] = $right;
        }
        $user['rolePermissions'] = $rolePermissionsArray;
        
  
        $pwd_verify = password_verify($input->password, $user['password']);
        
        if(!$pwd_verify) {
            return $this->respond(['status' => false, 'message' => 'Password is incorrect'], 401);
        }
 
        $key = "Exiaa@11";
        $iat = time(); // current timestamp value
        $exp = $iat + 3600;
 
        $payload = array(
            "iss" => "Issuer of the JWT",
            "aud" => "Audience that the JWT",
            "sub" => "Subject of the JWT",
            "iat" => $iat, //Time the JWT issued at
            "exp" => $exp, // Expiration time of token
            "email" => $user['email'],
        );
         
        $token = JWT::encode($payload, $key, 'HS256');
        $refreshToken = bin2hex(random_bytes(32)); // Generate random token
 
        $response = [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $exp,
            'exp' => $exp,
            'refresh_token' => $refreshToken
        ];
         
        return $this->respond($response, 200);
    }

    public function register()
    {
        $input = $this->request->getJSON();
        $rules = [
            'name' => ['rules' => 'required|min_length[4]|max_length[255]'],
            'email' => ['rules' => 'required|min_length[4]|max_length[255]|valid_email|is_unique[user_mst.email]'],
            'mobile' => ['rules' => 'required|min_length[10]|max_length[10]|is_unique[user_mst.mobileNo]'],
            'password' => ['rules' => 'required|min_length[8]|max_length[255]'],
            'confirmPassword'  => [ 'label' => 'confirm password', 'rules' => 'matches[password]']
        ];
  
        if($this->validate($rules)){
            $model = new UserModel();
            $data = [
                'name'     => $input->name,
                'mobileNo' => $input->mobile,
                'email'    => $input->email,
                'password' => password_hash($input->password, PASSWORD_DEFAULT)
            ];
            $model->insert($data);
             
            return $this->respond(["status" => true, 'message' => 'Registered Successfully'], 200);
        }else{
            $response = [
                'status'=>false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ];
            return $this->fail($response , 409);
             
        }
            
    }

    public function getRolesPaging(){
        $input = $this->request->getJSON();
        $roleModel = new RoleModel();

        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        // Define the number of items per page
        $perPage = isset($input->perPage) ? $input->perPage : 10;

        // Fetch paginated data ordered by the latest added first
        $roles = $roleModel->orderBy('createdDate', 'DESC')->paginate($perPage, 'default', $page);
        $pager = $roleModel->pager;

        $response = [
            "status" => true,
            "message" => "All Data Fetched",
            "data" => $roles,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]
        ];
    
        return $this->respond($response, 200);
    }

    
}