<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

class Lead extends BaseController
{
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
         $leadModel = new \App\Models\LeadModel($db);
         return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $leadModel->findAll()], 200);
        
    }

    public function getAllLeadSource(){
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
        $leadSourceModel = new \App\Models\LeadSourceModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $leadSourceModel->findAll()], 200);
    }

    public function getAllLeadInterested(){
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
        $leadInterestedModel = new \App\Models\LeadInterestedModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $leadInterestedModel->findAll()], 200);
    }
}
