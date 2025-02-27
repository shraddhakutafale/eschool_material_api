<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\SmtpConfig;
use App\Libraries\EmailService;
use App\Libraries\TenantService;

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
}
