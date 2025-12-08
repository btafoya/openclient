<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\ClientModel;
use App\Models\ProjectModel;
use App\Models\InvoiceModel;
use App\Models\PaymentModel;
use App\Models\ActivityLogModel;

class DashboardController extends BaseController
{
    private ClientModel $clientModel;
    private ProjectModel $projectModel;
    private InvoiceModel $invoiceModel;
    private PaymentModel $paymentModel;
    private ActivityLogModel $activityModel;

    public function __construct()
    {
        $this->clientModel = new ClientModel();
        $this->projectModel = new ProjectModel();
        $this->invoiceModel = new InvoiceModel();
        $this->paymentModel = new PaymentModel();
        $this->activityModel = new ActivityLogModel();
    }

    /**
     * Display the dashboard view
     */
    public function index()
    {
        $user = session()->get('user');

        if (!$user) {
            return redirect()->to('/auth/login');
        }

        return view('dashboard/index', [
            'title' => 'Dashboard',
            'user' => $user
        ]);
    }

    /**
     * API endpoint for dashboard data
     */
    public function data()
    {
        $user = session()->get('user');

        if (!$user) {
            return $this->response->setStatusCode(401)->setJSON([
                'error' => 'Unauthorized'
            ]);
        }

        $stats = $this->getStats($user);
        $recentActivity = $this->getRecentActivity($user);

        return $this->response->setJSON([
            'stats' => $stats,
            'recentActivity' => $recentActivity
        ]);
    }

    /**
     * Get dashboard statistics based on user role and permissions
     */
    private function getStats(array $user): array
    {
        $role = $user['role'];
        $agencyId = $user['agency_id'] ?? null;

        // Initialize stats
        $stats = [
            'clients' => [
                'total' => 0,
                'trend' => null
            ],
            'projects' => [
                'total' => 0,
                'active' => 0,
                'trend' => null
            ],
            'invoices' => [
                'pending' => 0,
                'total' => 0,
                'trend' => null
            ],
            'revenue' => [
                'current' => 0,
                'trend' => null
            ]
        ];

        // Get client stats based on role
        switch ($role) {
            case 'owner':
                $stats['clients']['total'] = $this->clientModel->countAllResults();
                $stats['clients']['trend'] = $this->calculateClientTrend();
                break;

            case 'agency':
                $stats['clients']['total'] = $this->clientModel
                    ->where('agency_id', $agencyId)
                    ->countAllResults();
                $stats['clients']['trend'] = $this->calculateClientTrend($agencyId);
                break;

            case 'direct_client':
            case 'end_client':
                // Direct/End clients see their own client record
                $stats['clients']['total'] = 1;
                break;
        }

        // Get project stats
        $stats['projects'] = $this->getProjectStats($user);

        // Get financial stats (only for roles with financial permissions)
        if (in_array($role, ['owner', 'agency', 'direct_client'])) {
            $stats['invoices'] = $this->getInvoiceStats($user);
            $stats['revenue'] = $this->getRevenueStats($user);
        }

        return $stats;
    }

    /**
     * Get project statistics
     */
    private function getProjectStats(array $user): array
    {
        $role = $user['role'];
        $agencyId = $user['agency_id'] ?? null;
        $userId = $user['id'];

        $builder = $this->projectModel->builder();

        switch ($role) {
            case 'owner':
                // Owners see all projects
                break;

            case 'agency':
                // Agencies see their projects
                $builder->where('agency_id', $agencyId);
                break;

            case 'direct_client':
            case 'end_client':
                // Clients see projects they're members of
                $builder->join('project_members', 'projects.id = project_members.project_id')
                    ->where('project_members.user_id', $userId);
                break;
        }

        $total = $builder->countAllResults(false);

        // Get active projects (status = 'active')
        $active = $builder->where('status', 'active')->countAllResults();

        return [
            'total' => $total,
            'active' => $active,
            'trend' => null // TODO: Calculate trend
        ];
    }

    /**
     * Get invoice statistics
     */
    private function getInvoiceStats(array $user): array
    {
        $role = $user['role'];
        $agencyId = $user['agency_id'] ?? null;

        $builder = $this->invoiceModel->builder();

        switch ($role) {
            case 'owner':
                // Owners see all invoices
                break;

            case 'agency':
                // Agencies see their invoices
                $builder->where('agency_id', $agencyId);
                break;

            case 'direct_client':
                // Direct clients see invoices for clients assigned to them
                $builder->join('client_users', 'invoices.client_id = client_users.client_id')
                    ->where('client_users.user_id', $user['id']);
                break;
        }

        $total = $builder->countAllResults(false);

        // Get pending invoices (status = 'pending')
        $pending = $builder->where('status', 'pending')->countAllResults();

        return [
            'total' => $total,
            'pending' => $pending,
            'trend' => null // TODO: Calculate trend
        ];
    }

    /**
     * Get revenue statistics
     */
    private function getRevenueStats(array $user): array
    {
        $role = $user['role'];
        $agencyId = $user['agency_id'] ?? null;

        $builder = $this->paymentModel->builder();

        // Get current month start and end dates
        $startOfMonth = date('Y-m-01 00:00:00');
        $endOfMonth = date('Y-m-t 23:59:59');

        switch ($role) {
            case 'owner':
                // Owners see all revenue
                break;

            case 'agency':
                // Agencies see their revenue
                $builder->join('invoices', 'payments.invoice_id = invoices.id')
                    ->where('invoices.agency_id', $agencyId);
                break;

            case 'direct_client':
                // Direct clients see revenue from their assigned clients
                $builder->join('invoices', 'payments.invoice_id = invoices.id')
                    ->join('client_users', 'invoices.client_id = client_users.client_id')
                    ->where('client_users.user_id', $user['id']);
                break;
        }

        // Get current month revenue
        $builder->where('payments.payment_date >=', $startOfMonth)
            ->where('payments.payment_date <=', $endOfMonth);

        $currentRevenue = $builder->selectSum('amount')->get()->getRow()->amount ?? 0;

        return [
            'current' => $currentRevenue,
            'trend' => null // TODO: Calculate trend
        ];
    }

    /**
     * Calculate client trend (comparison with previous period)
     */
    private function calculateClientTrend(?int $agencyId = null): ?array
    {
        // TODO: Implement trend calculation
        // Compare current month client count vs previous month
        return null;
    }

    /**
     * Get recent activity log entries
     */
    private function getRecentActivity(array $user): array
    {
        $role = $user['role'];
        $agencyId = $user['agency_id'] ?? null;
        $userId = $user['id'];

        $builder = $this->activityModel->builder();

        switch ($role) {
            case 'owner':
                // Owners see all activity
                break;

            case 'agency':
                // Agencies see their activity
                $builder->where('agency_id', $agencyId);
                break;

            case 'direct_client':
            case 'end_client':
                // Clients see activity related to them
                $builder->where('user_id', $userId)
                    ->orWhere('related_user_id', $userId);
                break;
        }

        $activities = $builder
            ->orderBy('created_at', 'DESC')
            ->limit(10)
            ->get()
            ->getResultArray();

        // Format activities for frontend
        return array_map(function ($activity) {
            return [
                'id' => $activity['id'],
                'type' => $activity['type'],
                'description' => $activity['description'],
                'user' => $activity['user_name'] ?? null,
                'created_at' => $activity['created_at']
            ];
        }, $activities);
    }
}
