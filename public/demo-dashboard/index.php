<?php
/**
 * index.php — Demo page showing the sidebar in action.
 */
$pageTitle = 'Dashboard';
$activePage = 'dashboard';
require __DIR__ . '/header.php';
?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:18px;margin-bottom:24px">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(16,185,129,.10);color:#10B981">
      <i class="fa-solid fa-dollar-sign"></i>
    </div>
    <div>
      <div class="stat-value">$48,294</div>
      <div class="stat-label">Total Revenue</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(99,102,241,.10);color:#6366F1">
      <i class="fa-solid fa-arrow-trend-up"></i>
    </div>
    <div>
      <div class="stat-value">2,847</div>
      <div class="stat-label">Transactions</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(245,158,11,.10);color:#F59E0B">
      <i class="fa-solid fa-users"></i>
    </div>
    <div>
      <div class="stat-value">1,293</div>
      <div class="stat-label">Customers</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(239,68,68,.10);color:#EF4444">
      <i class="fa-solid fa-bolt"></i>
    </div>
    <div>
      <div class="stat-value">99.9%</div>
      <div class="stat-label">Uptime</div>
    </div>
  </div>
</div>

<div style="background:var(--bg-content);border:1px solid var(--border-light);border-radius:16px;padding:28px">
  <h2 style="font-size:1.15rem;font-weight:700;margin-bottom:16px">Recent Transactions</h2>
  <table style="width:100%;border-collapse:collapse;font-size:.9rem">
    <thead>
      <tr style="border-bottom:2px solid var(--border-light)">
        <th style="text-align:left;padding:12px 8px;color:var(--text-muted);font-weight:600">ID</th>
        <th style="text-align:left;padding:12px 8px;color:var(--text-muted);font-weight:600">Customer</th>
        <th style="text-align:left;padding:12px 8px;color:var(--text-muted);font-weight:600">Amount</th>
        <th style="text-align:left;padding:12px 8px;color:var(--text-muted);font-weight:600">Status</th>
        <th style="text-align:left;padding:12px 8px;color:var(--text-muted);font-weight:600">Date</th>
      </tr>
    </thead>
    <tbody>
      <tr style="border-bottom:1px solid var(--border-light)">
        <td style="padding:14px 8px;font-weight:600">#TXN-4821</td>
        <td style="padding:14px 8px">John Mwangi</td>
        <td style="padding:14px 8px;font-weight:600;color:#10B981">$120.00</td>
        <td style="padding:14px 8px"><span style="background:rgba(16,185,129,.10);color:#10B981;padding:4px 12px;border-radius:999px;font-size:.78rem;font-weight:600">Completed</span></td>
        <td style="padding:14px 8px;color:var(--text-muted)">Jul 15, 2026</td>
      </tr>
      <tr style="border-bottom:1px solid var(--border-light)">
        <td style="padding:14px 8px;font-weight:600">#TXN-4820</td>
        <td style="padding:14px 8px">Sarah Kimaro</td>
        <td style="padding:14px 8px;font-weight:600;color:#10B981">$85.50</td>
        <td style="padding:14px 8px"><span style="background:rgba(245,158,11,.10);color:#F59E0B;padding:4px 12px;border-radius:999px;font-size:.78rem;font-weight:600">Pending</span></td>
        <td style="padding:14px 8px;color:var(--text-muted)">Jul 15, 2026</td>
      </tr>
      <tr style="border-bottom:1px solid var(--border-light)">
        <td style="padding:14px 8px;font-weight:600">#TXN-4819</td>
        <td style="padding:14px 8px">David Ochieng</td>
        <td style="padding:14px 8px;font-weight:600;color:#10B981">$340.00</td>
        <td style="padding:14px 8px"><span style="background:rgba(16,185,129,.10);color:#10B981;padding:4px 12px;border-radius:999px;font-size:.78rem;font-weight:600">Completed</span></td>
        <td style="padding:14px 8px;color:var(--text-muted)">Jul 14, 2026</td>
      </tr>
      <tr>
        <td style="padding:14px 8px;font-weight:600">#TXN-4818</td>
        <td style="padding:14px 8px">Grace Mushi</td>
        <td style="padding:14px 8px;font-weight:600;color:#EF4444">$22.00</td>
        <td style="padding:14px 8px"><span style="background:rgba(239,68,68,.10);color:#EF4444;padding:4px 12px;border-radius:999px;font-size:.78rem;font-weight:600">Failed</span></td>
        <td style="padding:14px 8px;color:var(--text-muted)">Jul 14, 2026</td>
      </tr>
    </tbody>
  </table>
</div>

<style>
.stat-card {
  display: flex; align-items: center; gap: 16px;
  background: var(--bg-content); border: 1px solid var(--border-light);
  border-radius: 16px; padding: 22px;
  box-shadow: var(--shadow-card);
  transition: all .25s cubic-bezier(.22,1,.36,1);
}
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.06); }
.stat-icon {
  width: 52px; height: 52px; border-radius: 14px;
  display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
  flex-shrink: 0;
}
.stat-value { font-size: 1.5rem; font-weight: 800; line-height: 1.1; color: var(--text-dark); }
.stat-label { font-size: .85rem; color: var(--text-muted); margin-top: 2px; }
</style>

<?php require __DIR__ . '/footer.php'; ?>
