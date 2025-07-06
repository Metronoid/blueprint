import React, { useState, useEffect } from 'react';
import { Link } from '@inertiajs/react';

export default function BlueprintDashboard() {
  const [dashboard, setDashboard] = useState(window.BlueprintDashboard?.dashboard || {});
  const [widgets, setWidgets] = useState(window.BlueprintDashboard?.widgets || {});
  const [loading, setLoading] = useState(false);
  const [activeNav, setActiveNav] = useState('overview');

  useEffect(() => {
    if (!window.BlueprintDashboard?.dashboard) {
      fetchDashboardData();
    }
  }, []);

  const fetchDashboardData = async () => {
    try {
      setLoading(true);
      const response = await fetch(window.BlueprintDashboard?.api?.dashboard || '/blueprint/dashboard', {
        headers: {
          'Accept': 'application/json',
        }
      });
      const data = await response.json();
      setDashboard(data.dashboard);
      setWidgets(data.widgets);
    } catch (error) {
      console.error('Failed to fetch dashboard data:', error);
    } finally {
      setLoading(false);
    }
  };

  const fetchWidgetData = async (widgetName) => {
    try {
      const response = await fetch(
        (window.BlueprintDashboard?.api?.widget || '/blueprint/dashboard/widgets/:widget/data').replace(':widget', widgetName),
        {
          headers: {
            'Accept': 'application/json',
          }
        }
      );
      const data = await response.json();
      setWidgets(prev => ({
        ...prev,
        [widgetName]: data
      }));
    } catch (error) {
      console.error(`Failed to fetch widget data for ${widgetName}:`, error);
    }
  };

  const renderWidget = (widgetName, widgetData) => {
    const { data, config } = widgetData;
    
    switch (config?.type) {
      case 'metric':
        return <MetricWidget title={config.title} data={data} config={config} />;
      case 'table':
        return <TableWidget title={config.title} data={data} config={config} />;
      case 'list':
        return <ListWidget title={config.title} data={data} config={config} />;
      case 'chart':
        return <ChartWidget title={config.title} data={data} config={config} />;
      default:
        return <div>Unknown widget type: {config?.type}</div>;
    }
  };

  if (loading) {
    return (
      <div className="dashboard-layout">
        <div className="dashboard-main">
          <div className="loading">Loading Blueprint Dashboard...</div>
        </div>
      </div>
    );
  }

  return (
    <div className="dashboard-layout">
      <nav className="dashboard-nav">
        <div className="nav-brand">
          <h1>Blueprint</h1>
          <p className="text-sm opacity-75">Dashboard</p>
        </div>
        
        <ul className="nav-menu">
          {dashboard.navigation?.map((item) => (
            <li key={item.name}>
              <Link
                href={item.route}
                className={activeNav === item.name ? 'active' : ''}
                onClick={() => setActiveNav(item.name)}
              >
                <span className="icon">{item.icon}</span>
                {item.title}
              </Link>
            </li>
          ))}
        </ul>
        
        <div className="nav-footer">
          <div className="user-info">
            <span>Blueprint Dashboard</span>
          </div>
        </div>
      </nav>
      
      <main className="dashboard-main">
        <div className="dashboard-header">
          <h1>{dashboard.title}</h1>
          <p>{dashboard.description}</p>
        </div>
        
        <div className="dashboard-grid">
          {Object.entries(widgets).map(([widgetName, widgetData]) => (
            <div key={widgetName} className="dashboard-widget">
              {renderWidget(widgetName, widgetData)}
            </div>
          ))}
        </div>
      </main>
    </div>
  );
}

// Widget Components
function MetricWidget({ title, data, config }) {
  const getValue = () => {
    if (data?.value !== undefined) return data.value;
    if (data?.status) return data.status;
    return '0';
  };

  const getColor = () => {
    if (data?.status === 'success') return 'text-green-600';
    if (data?.status === 'error') return 'text-red-600';
    if (data?.status === 'warning') return 'text-yellow-600';
    return 'text-blue-600';
  };

  return (
    <>
      <div className="widget-header">
        <h3>{title}</h3>
        <div className="widget-actions">
          <button onClick={() => window.location.reload()} className="refresh-btn">
            ↻
          </button>
        </div>
      </div>
      <div className="widget-content">
        <div className="metric-widget">
          <div className={`metric-value ${getColor()}`}>
            {getValue()}
          </div>
          <div className="metric-label">{title}</div>
        </div>
      </div>
    </>
  );
}

function TableWidget({ title, data, config }) {
  if (!data || data.length === 0) {
    return (
      <>
        <div className="widget-header">
          <h3>{title}</h3>
        </div>
        <div className="widget-content">
          <p className="text-gray-500">No data available</p>
        </div>
      </>
    );
  }

  const columns = Object.keys(data[0] || {});

  return (
    <>
      <div className="widget-header">
        <h3>{title}</h3>
        <div className="widget-actions">
          <button onClick={() => window.location.reload()} className="refresh-btn">
            ↻
          </button>
        </div>
      </div>
      <div className="widget-content">
        <div className="table-widget">
          <table>
            <thead>
              <tr>
                {columns.map(column => (
                  <th key={column}>{column.replace(/_/g, ' ').toUpperCase()}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {data.map((row, index) => (
                <tr key={index}>
                  {columns.map(column => (
                    <td key={column}>{row[column]}</td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </>
  );
}

function ListWidget({ title, data, config }) {
  if (!data || data.length === 0) {
    return (
      <>
        <div className="widget-header">
          <h3>{title}</h3>
        </div>
        <div className="widget-content">
          <p className="text-gray-500">No data available</p>
        </div>
      </>
    );
  }

  return (
    <>
      <div className="widget-header">
        <h3>{title}</h3>
        <div className="widget-actions">
          <button onClick={() => window.location.reload()} className="refresh-btn">
            ↻
          </button>
        </div>
      </div>
      <div className="widget-content">
        <div className="list-widget">
          <ul>
            {data.map((item, index) => (
              <li key={index}>
                {item.name || item.title || item.description || JSON.stringify(item)}
                {item.status && (
                  <span className={`status status-${item.status.toLowerCase()}`}>
                    {item.status}
                  </span>
                )}
              </li>
            ))}
          </ul>
        </div>
      </div>
    </>
  );
}

function ChartWidget({ title, data, config }) {
  return (
    <>
      <div className="widget-header">
        <h3>{title}</h3>
        <div className="widget-actions">
          <button onClick={() => window.location.reload()} className="refresh-btn">
            ↻
          </button>
        </div>
      </div>
      <div className="widget-content">
        <div className="chart-widget">
          <p>Chart widget - {data?.length || 0} data points</p>
          <p className="text-sm text-gray-500">
            Chart type: {config?.chart_type || 'line'}
          </p>
        </div>
      </div>
    </>
  );
} 