import React from 'react';
import { createRoot } from 'react-dom/client';
import { ChartAreaInteractive } from './components/ChartAreaInteractive';

function mountDashboardChart() {
  const el = document.getElementById('react-dashboard-chart');
  if (!el) {
    // Halaman ini tidak punya dashboard chart, normal
    return;
  }

  let chartData;
  try {
    chartData = JSON.parse(el.dataset.chartData || '[]');
  } catch (e) {
    console.error('[DashboardChart] JSON parse error:', e);
    el.innerHTML = '<div style="padding:1rem;color:var(--destructive)">Error: data chart tidak valid.</div>';
    return;
  }

  const title    = el.dataset.title    || 'Area Chart - Pendapatan';
  const desc     = el.dataset.description || 'Omzet dan transaksi operasional terakhir';
  const statsUrl = el.dataset.statsUrl || null;

  console.log('[DashboardChart] mounting — data points:', chartData.length);

  try {
    createRoot(el).render(
      <ChartAreaInteractive
        data={chartData}
        title={title}
        description={desc}
        statsUrl={statsUrl}
      />
    );
  } catch (e) {
    console.error('[DashboardChart] render error:', e);
    el.innerHTML = '<div style="padding:1rem;color:var(--destructive)">Chart render error: ' + e.message + '</div>';
  }
}

// Vite modules type="module" are deferred — DOM already ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mountDashboardChart);
} else {
  mountDashboardChart();
}
