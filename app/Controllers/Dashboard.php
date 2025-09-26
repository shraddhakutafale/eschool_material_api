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

    // Tenant configuration
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    $paymentDetailModel = new PaymentDetailModel($db);

    $paidAmount = 0;
    $pendingAmount = 0;

    if ($fromDate && $toDate) {
        // Paid amount
        $paid = $paymentDetailModel
            ->select('SUM(paidAmount) as totalAmount')
            ->where('paymentDate >=', $fromDate)
            ->where('paymentDate <=', $toDate)
            ->where('businessId', $filter->businessId)
            ->first();

        $paidAmount = $paid ? $paid['totalAmount'] : 0;

        // Pending amount
        $pending = $paymentDetailModel
            ->select('SUM(paidAmount) as totalPendingAmount')
            ->where('dueDate >=', $fromDate)
            ->where('dueDate <=', $toDate)
            ->where('businessId', $filter->businessId)
            ->first();

        $pendingAmount = $pending ? $pending['totalPendingAmount'] : 0;
    }

    return $this->respond([
        'status' => true,
        'message' => 'Statistics fetched successfully',
        'data' => [
            'paidAmount' => $paidAmount,
            'pendingAmount' => $pendingAmount
        ]
    ], 200);
}

public function getStatsForOrder()
{
    $filter = $this->request->getJSON();

    $fromDate = null;
    $toDate = null;

    // Determine date range based on filter type
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

    // Tenant configuration
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    $orderModel = new OrderModel($db);

    // Query orders
    $query = $orderModel->builder('order_mst');

    if ($fromDate && $toDate) {
        $query->where('orderDate >=', $fromDate)
              ->where('orderDate <=', $toDate);
    }

    if (isset($filter->businessId)) {
        $query->where('businessId', $filter->businessId);
    }

    $orders = $query->get()->getResultArray();

    $orderCount = count($orders);
    $totalOrderAmount = array_sum(array_column($orders, 'total'));

    $message = ($orderCount > 0) ? 'Order statistics fetched successfully' : 'No orders found for selected filter';

    return $this->respond([
        'status' => $orderCount > 0,
        'message' => $message,
        'data' => [
            'orders' => $orders,
            'orderCount' => $orderCount,
            'totalOrderAmount' => $totalOrderAmount
        ]
    ], 200);
}



 public function getFilteredOrders()
    {
        $input = $this->request->getJSON(true);

        $businessId = $input['businessId'] ?? null;
        $fromDate = $input['fromDate'] ?? null;
        $toDate   = $input['toDate'] ?? null;

        if (!$businessId || !$fromDate || !$toDate) {
            return $this->failValidationErrors('Missing businessId or date range');
        }

        // Convert to proper datetime
        $fromDate = date('Y-m-d 00:00:00', strtotime($fromDate));
        $toDate   = date('Y-m-d 23:59:59', strtotime($toDate));

        // Fetch filtered orders
        $orders = $this->model
            ->where('businessId', $businessId)
            ->where('orderDate >=', $fromDate)
            ->where('orderDate <=', $toDate)
            ->findAll();

        $totalOrders = count($orders);

        $totalAmount = 0;
        $totalRevenue = 0;
        $emails = [];

        foreach ($orders as $order) {
            $price = floatval($order['totalPrice']);
            $status = strtolower($order['status'] ?? '');

            $totalAmount += $price;

            if ($status === 'completed' || $status === 'paid') {
                $totalRevenue += $price;
            } elseif ($status === 'cancelled' || $status === 'refunded') {
                $totalRevenue -= $price;
            }

            if (!empty($order['email'])) {
                $emails[] = $order['email'];
            }
        }

        $totalCustomers = count(array_unique($emails));

        return $this->respond([
            'status' => true,
            'data' => [
                'orders' => $orders,
                'orderCount' => $totalOrders,
                'totalOrderAmount' => $totalAmount,
                'totalRevenue' => $totalRevenue,
                'totalCustomers' => $totalCustomers
            ]
        ]);
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
