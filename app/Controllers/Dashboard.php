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
use App\Models\OrderModel;


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
                ->where('businessId', $filter->businessId)
                ->countAllResults();

            $admissionCount = $admissionModel
                ->where('createdDate >=', $fromDate)
                ->where('createdDate <=', $toDate)
                ->where('businessId', $filter->businessId)
                ->countAllResults();

            $paidAmount = $paymentDetailModel
                ->select('SUM(paidAmount) as totalAmount')
                ->where('paymentDate >=', $fromDate)
                ->where('paymentDate <=', $toDate)
                ->where('businessId', $filter->businessId)
                ->first();

            $pendingAmount = $paymentDetailModel
                ->select('SUM(paidAmount) as totalPendingAmount')
                ->where('dueDate >=', $fromDate)
                ->where('dueDate <=', $toDate)
                ->where('businessId', $filter->businessId)
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


public function getStatsForOrder()
{
    $filter = $this->request->getJSON();
    $fromDate = null;
    $toDate = null;

    // Determine date range
    if (!empty($filter->filterBy)) {
        switch ($filter->filterBy) {
            case 'financialYear':
                if (!empty($filter->financialYear)) {
                    [$startYear, $endYear] = explode('-', $filter->financialYear);
                    $fromDate = "$startYear-04-01";
                    $toDate   = "$endYear-03-31";
                }
                break;

            case 'monthly':
                if (!empty($filter->month)) {
                    [$monthStr, $yearStr] = explode('-', $filter->month);
                    $fromDate = date('Y-m-d', strtotime("first day of $monthStr $yearStr"));
                    $toDate   = date('Y-m-d', strtotime("last day of $monthStr $yearStr"));
                }
                break;

            case 'specificDate':
                if (!empty($filter->specificDate)) {
                    $fromDate = date('Y-m-d', strtotime($filter->specificDate));
                    $toDate   = $fromDate;
                }
                break;

            case 'dateRange':
                if (!empty($filter->fromDate) && !empty($filter->toDate)) {
                    $fromDate = date('Y-m-d', strtotime($filter->fromDate));
                    $toDate   = date('Y-m-d', strtotime($filter->toDate));
                }
                break;
        }
    }

    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    $orderModel = new OrderModel($db);

    // --- Use separate builders ---
    $countQuery = $orderModel->builder('order_mst');
    $totalQuery = $orderModel->builder('order_mst');

    // Apply filters
    if ($fromDate && $toDate) {
        $countQuery->where('orderDate >=', $fromDate)->where('orderDate <=', $toDate);
        $totalQuery->where('orderDate >=', $fromDate)->where('orderDate <=', $toDate);
    }

    if (isset($filter->businessId)) {
        $countQuery->where('businessId', $filter->businessId);
        $totalQuery->where('businessId', $filter->businessId);
    }

    // Count orders
    $orderCount = $countQuery->countAllResults();

    // Sum total
    $totalRow = $totalQuery->select('SUM(total) as totalOrderAmount')->get()->getRowArray();
    $totalOrderAmount = $totalRow ? $totalRow['totalOrderAmount'] : 0;

    $message = ($orderCount > 0) ? 'Order statistics fetched successfully' : 'No orders found for selected filter';

    return $this->respond([
        'status' => $orderCount > 0,
        'message' => $message,
        'data' => [
            'orderCount' => $orderCount,
            'totalOrderAmount' => $totalOrderAmount
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
