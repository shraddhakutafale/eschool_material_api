<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\SmtpConfig;
use App\Libraries\EmailService;
use Config\Database;

class Quote extends BaseController
{
    use ResponseTrait;
    
    public function index()
    {
        //
    }

    public function sendQuoteEmail()
    {
        $input = $this->request->getJSON();
        $emailService = new EmailService();

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
