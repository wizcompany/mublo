<?php $currentTab = 'realtime'; ?>
<?php include __DIR__ . '/_nav.php'; ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="card vs-metric">
            <div class="card-body text-center">
                <div class="text-muted small">최근 5분</div>
                <div class="vs-metric-val text-primary" id="rt-recent">-</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card vs-metric">
            <div class="card-body text-center">
                <div class="text-muted small">오늘 방문자</div>
                <div class="vs-metric-val" id="rt-today-visitors">-</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card vs-metric">
            <div class="card-body text-center">
                <div class="text-muted small">오늘 페이지뷰</div>
                <div class="vs-metric-val" id="rt-today-pv">-</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="vs-card-header px-3 pt-3 pb-0 d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-ul me-2 text-chart-indigo"></i>최근 방문 로그</span>
        <span class="badge text-bg-light" id="rt-refresh-timer">30초 후 새로고침</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 vs-table">
                <thead>
                    <tr>
                        <th>시간</th>
                        <th>IP</th>
                        <th>국가/도시</th>
                        <th>페이지</th>
                        <th>브라우저</th>
                        <th>디바이스</th>
                        <th>유입</th>
                    </tr>
                </thead>
                <tbody id="rt-log-body">
                    <tr><td colspan="7" class="text-center text-muted py-3">로딩 중...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.vs-metric { border: 1px solid #e9ecef; }
.vs-metric-val { font-size: 1.5rem; font-weight: 700; line-height: 1.3; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var refreshInterval = 30;
    var countdown = refreshInterval;
    var timerEl = document.getElementById('rt-refresh-timer');

    function loadRealtime() {
        MubloRequest.requestJson('/admin/visitor-stats/api/realtime', {}, { method: 'POST' })
            .then(function (res) {
                var d = res.data || {};
                document.getElementById('rt-recent').textContent = (d.recent5min || 0).toLocaleString('ko-KR');
                document.getElementById('rt-today-visitors').textContent = (d.todayVisitors || 0).toLocaleString('ko-KR');
                document.getElementById('rt-today-pv').textContent = (d.todayPageviews || 0).toLocaleString('ko-KR');
                renderLogs(d.recentLogs || []);
            });
    }

    function renderLogs(logs) {
        var tbody = document.getElementById('rt-log-body');
        if (logs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">오늘 방문 기록이 없습니다.</td></tr>';
            return;
        }

        var html = '';
        logs.forEach(function (log) {
            var time = (log.created_at || '').substring(11, 19);
            var ip = escapeHtml(log.ip_address || '');
            var page = escapeHtml(truncate(log.landing_url || '/', 40));
            var browser = escapeHtml(log.browser || '');
            var device = escapeHtml(log.device || '');
            var refType = escapeHtml(log.referer_type || 'direct');

            html += '<tr>';
            html += '<td class="text-nowrap">' + time + '</td>';
            var country = escapeHtml(log.country_code || '');
            var city = escapeHtml(log.city || '');
            var geo = country ? (country + (city ? ' / ' + city : '')) : '-';

            html += '<td class="text-nowrap"><code>' + ip + '</code></td>';
            html += '<td class="text-nowrap small">' + geo + '</td>';
            html += '<td title="' + escapeHtml(log.landing_url || '') + '">' + page + '</td>';
            html += '<td>' + browser + '</td>';
            html += '<td>' + deviceBadge(device) + '</td>';
            html += '<td>' + refTypeBadge(refType) + '</td>';
            html += '</tr>';
        });
        tbody.innerHTML = html;
    }

    function deviceBadge(device) {
        var cls = device === 'mobile' ? 'warning' : device === 'tablet' ? 'info' : 'secondary';
        return '<span class="badge text-bg-' + cls + '">' + device + '</span>';
    }

    function refTypeBadge(type) {
        var map = { direct: 'secondary', search: 'primary', social: 'danger', external: 'success' };
        var cls = map[type] || 'secondary';
        return '<span class="badge text-bg-' + cls + '">' + type + '</span>';
    }

    function truncate(str, len) {
        return str.length > len ? str.substring(0, len) + '...' : str;
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // 자동 새로고침
    loadRealtime();

    setInterval(function () {
        countdown--;
        if (countdown <= 0) {
            countdown = refreshInterval;
            loadRealtime();
        }
        timerEl.textContent = countdown + '초 후 새로고침';
    }, 1000);
});
</script>
