<?php

namespace Blueprint\Plugins\BlueprintAuditing;

use Blueprint\Contracts\DashboardPlugin;
use Blueprint\Models\Dashboard;

class AuditingDashboardPlugin implements DashboardPlugin
{
    public function getName(): string
    {
        return 'Blueprint Auditing';
    }

    public function getDescription(): string
    {
        return 'Provides auditing and versioning capabilities for Blueprint models';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function extendDashboard(Dashboard $dashboard): void
    {
        // Add auditing-specific widgets to the dashboard
        $dashboard->addWidget('AuditStats', [
            'type' => 'metric',
            'title' => 'Audit Records',
            'config' => [
                'format' => 'number',
                'color' => 'blue'
            ]
        ]);

        $dashboard->addWidget('RecentAudits', [
            'type' => 'table',
            'title' => 'Recent Audits',
            'config' => [
                'limit' => 10,
                'sort_by' => 'created_at',
                'sort_order' => 'desc'
            ]
        ]);

        $dashboard->addWidget('AuditChart', [
            'type' => 'chart',
            'title' => 'Audit Activity',
            'config' => [
                'chart_type' => 'line',
                'timeframe' => '7d'
            ]
        ]);
    }

    public function getWidgets(): array
    {
        return [
            'AuditStats' => [
                'type' => 'metric',
                'title' => 'Audit Records',
                'config' => [
                    'format' => 'number',
                    'color' => 'blue'
                ]
            ],
            'RecentAudits' => [
                'type' => 'table',
                'title' => 'Recent Audits',
                'config' => [
                    'limit' => 10,
                    'sort_by' => 'created_at',
                    'sort_order' => 'desc'
                ]
            ],
            'AuditChart' => [
                'type' => 'chart',
                'title' => 'Audit Activity',
                'config' => [
                    'chart_type' => 'line',
                    'timeframe' => '7d'
                ]
            ]
        ];
    }

    public function getNavigation(): array
    {
        return [
            [
                'name' => 'auditing',
                'title' => 'Auditing',
                'route' => '/blueprint/auditing',
                'icon' => 'shield-check'
            ],
            [
                'name' => 'audit-logs',
                'title' => 'Audit Logs',
                'route' => '/blueprint/auditing/logs',
                'icon' => 'document-text'
            ],
            [
                'name' => 'audit-settings',
                'title' => 'Audit Settings',
                'route' => '/blueprint/auditing/settings',
                'icon' => 'cog'
            ]
        ];
    }

    public function getPermissions(): array
    {
        return [
            'view-audit-logs',
            'manage-audit-settings',
            'export-audit-data'
        ];
    }

    public function getApiEndpoints(): array
    {
        return [
            'audit-stats' => '/api/auditing/stats',
            'audit-logs' => '/api/auditing/logs',
            'audit-export' => '/api/auditing/export'
        ];
    }

    public function getSettings(): array
    {
        return [
            'audit_enabled' => true,
            'audit_retention_days' => 365,
            'audit_models' => ['User', 'Post', 'Comment'],
            'audit_events' => ['created', 'updated', 'deleted']
        ];
    }

    public function isEnabled(): bool
    {
        return config('blueprint-auditing.enabled', true);
    }

    public function enable(): void
    {
        config(['blueprint-auditing.enabled' => true]);
    }

    public function disable(): void
    {
        config(['blueprint-auditing.enabled' => false]);
    }
} 