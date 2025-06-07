<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\LeadModel;
use App\Models\StudentModel;
use App\Models\AdmissionModel;
use App\Models\ItemModel;
use App\Models\ItemFeeMapModel;
use App\Models\FeeModel;
use App\Models\PaymentDetailModel;
use App\Libraries\TenantService;

class Dashboard extends BaseController
{

    use ResponseTrait;

    public function getStatsForInstitute()
    {
        $filter = $this->request->getJSON();
        $fromDate = $filter->fromDate ?? null;
        $toDate   = $filter->toDate ?? null;
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

        $leadModel = new LeadModel($db);
        $admissionModel = new AdmissionModel($db);
        $paymentDetailModel = new PaymentDetailModel($db);
        
        $leadCount = 0;
        $admissionCount = 0;
        $pendingAmount = 0;
        $paidAmount = 0;  
        if ($fromDate && $toDate) {
            $leadCount = $leadModel
                ->where('createdDate >=', $fromDate)
                ->where('createdDate <=', $toDate)
                ->countAllResults();

            $admissionCount = $admissionModel
                ->where('admissionDate >=', $fromDate)
                ->where('admissionDate <=', $toDate)
                ->countAllResults();

            $paidAmount = $paymentDetailModel
                ->select('SUM(paidAmount) as totalAmount')
                ->where('paymentDate >=', $fromDate)
                ->where('paymentDate <=', $toDate)
                ->first();

            $pendingAmount = $paymentDetailModel
                ->select('SUM(paidAmount) as totalPendingAmount')
                ->where('dueDate >=', $fromDate)
                ->where('dueDate <=', $toDate)
                ->first();

        }

        return $this->respond([
            'status' => true,
            'message' => 'Statistics fetched successfully',
            'data' => [
                'leadCount' => $leadCount,
                'admissionCount' => $admissionCount,
                'paidAmount' => $paidAmount ? $paidAmount['totalAmount'] : 0,
                'pendingAmount' => $pendingAmount ? $pendingAmount['totalPendingAmount'] : 0
            ]
        ], 200);

    }

    public function getTotalAndPendingCollection()
    {
        $filter = $this->request->getJSON();
        $fromDate = $filter->fromDate ?? null;
        $toDate   = $filter->toDate ?? null;
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

        $paymentDetailModel = new PaymentDetailModel($db);

        $paidAmount = $paymentDetailModel
            ->select('SUM(paidAmount) as totalAmount')
            ->where('paymentDate >=', $fromDate)
            ->where('paymentDate <=', $toDate)
            ->first();

        $pendingAmount = $paymentDetailModel
            ->select('SUM(paidAmount) as totalPendingAmount')
            ->where('dueDate >=', $fromDate)
            ->where('dueDate <=', $toDate)
            ->first();

        return $this->respond([
            'status' => true,
            'message' => 'Statistics fetched successfully',
            'data' => [
                'paidAmount' => $paidAmount ? $paidAmount['totalAmount'] : 0,
                'pendingAmount' => $pendingAmount ? $pendingAmount['totalPendingAmount'] : 0
            ]
        ], 200);

    }
}
