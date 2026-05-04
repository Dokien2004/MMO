<?php 

// --- DATA PREPARATION ---

// 1. Lấy dữ liệu từ Controller
$departments = $data['departments'] ?? [];
$employeeCounts = $data['employee_counts'] ?? [];

// 2. Xây dựng cấu trúc cây
$deptTree = [];
if (!empty($departments)) {
    foreach ($departments as $d) {
        $pid = $d->parent_id ? $d->parent_id : 0;
        $deptTree[$pid][] = $d;
    }
}

// 3. Hàm đệ quy tính tổng số nhân sự
function calculateCumulativeCounts(&$tree, $employeeCounts, $parentId = 0) {
    if (!isset($tree[$parentId])) {
        return 0;
    }
    
    $totalForThisLevel = 0;
    foreach ($tree[$parentId] as $dept) {
        $childCount = calculateCumulativeCounts($tree, $employeeCounts, $dept->id);
        $directCount = $employeeCounts[$dept->id] ?? 0;
        $dept->cumulative_user_count = $directCount + $childCount;
        $totalForThisLevel += $dept->cumulative_user_count;
    }
    return $totalForThisLevel;
}

// 4. Chạy hàm tính toán
calculateCumulativeCounts($deptTree, $employeeCounts, 0);

// 5. Chuẩn bị dữ liệu cho Google Chart
$chartData = [];
foreach ($departments as $dept) {
    // Xác định trạng thái để style
    $isInactive = ($dept->status !== 'active');
    $nodeClass = $isInactive ? 'node-inactive' : 'node-active';

    // HTML content for the chart node
    // Thiết kế Card tối ưu diện tích
    $htmlNode = '<div class="org-card ' . $nodeClass . '">';
    
    // Header: Tên phòng ban
    $htmlNode .= '<div class="org-header">';
    $htmlNode .= '<div class="org-name text-truncate" title="' . htmlspecialchars($dept->name) . '">' . htmlspecialchars($dept->name) . '</div>';
    $htmlNode .= '<div class="org-code badge bg-light text-secondary border">' . htmlspecialchars($dept->code) . '</div>';
    $htmlNode .= '</div>';
    
    // Body: Thông tin chi tiết (Có thể ẩn khi thu gọn)
    $htmlNode .= '<div class="org-body">';
    
    if (!empty($dept->manager_name)) {
        // Lấy tên ngắn gọn (Tên cuối)
        $parts = explode(' ', $dept->manager_name);
        $shortName = end($parts);
        $htmlNode .= '<div class="org-manager text-truncate" title="Quản lý: ' . htmlspecialchars($dept->manager_name) . '">';
        $htmlNode .= '<i class="fas fa-user-tie me-1 text-primary opacity-75"></i>' . htmlspecialchars($shortName);
        $htmlNode .= '</div>';
    }

    $employeeCount = $dept->cumulative_user_count ?? 0;
    if ($employeeCount > 0) {
        $htmlNode .= '<div class="org-count mt-1"><span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 rounded-pill"><i class="fas fa-users me-1"></i>' . $employeeCount . '</span></div>';
    }
    
    $htmlNode .= '</div>'; // End body
    $htmlNode .= '</div>';

    // Parent ID, must be a string. Root nodes have an empty string.
    $parentIdString = $dept->parent_id ? (string)$dept->parent_id : '';

    // Tooltip text
    $tooltip = htmlspecialchars($dept->name);

    $chartData[] = [
        ['v' => (string)$dept->id, 'f' => $htmlNode],
        $parentIdString,
        $tooltip
    ];
}

?>

<style>
    /* Custom Google OrgChart CSS Override */
    .google-visualization-orgchart-node {
        border: 0 !important;
        border-radius: 8px !important;
        box-shadow: none !important;
        background: transparent !important;
        padding: 0 !important;
    }

    /* Card Style */
    .org-card {
        background: #fff;
        border-radius: 6px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        padding: 8px 12px;
        min-width: 140px;
        max-width: 200px;
        transition: all 0.2s ease;
        border: 1px solid #e9ecef;
        cursor: pointer;
    }

    .org-card:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.12);
        transform: translateY(-2px);
        border-color: #0d6efd;
        z-index: 100;
    }
    
    /* Header */
    .org-name {
        font-weight: 700;
        color: #2c3e50;
        font-size: 0.95rem;
        margin-bottom: 3px;
        line-height: 1.3;
    }
    .org-code {
        font-size: 0.7rem;
        font-family: monospace;
        padding: 2px 6px;
    }

    /* Body */
    .org-body {
        margin-top: 6px;
        padding-top: 6px;
        border-top: 1px dashed #eee;
        font-size: 0.8rem;
    }
    .org-manager {
        color: #6c757d;
        margin-bottom: 4px;
        font-size: 0.8rem;
    }
    .org-count .badge {
        font-size: 0.75rem;
        padding: 3px 8px;
    }

    /* Selected Node */
    .google-visualization-orgchart-node-sel .org-card {
        border: 2px solid #0d6efd;
        background-color: #f0f7ff;
    }

    /* Connector Lines */
    .google-visualization-orgchart-lineleft, 
    .google-visualization-orgchart-lineright, 
    .google-visualization-orgchart-linebottom {
        border-color: #dee2e6 !important;
        border-width: 2px !important;
    }
    
    /* Inactive Node */
    .node-inactive { 
        opacity: 0.6; 
        background: #f8f9fa;
    }

    /* Compact Mode Styles */
    .chart-compact .org-body {
        display: none !important;
    }
    .chart-compact .org-card {
        padding: 6px 10px;
        min-width: 110px;
        max-width: 150px;
    }
    .chart-compact .org-name {
        font-size: 0.85rem;
        margin-bottom: 2px;
    }
    .chart-compact .org-code {
        font-size: 0.65rem;
    }
    
    /* Connector Lines - Thinner */
    .google-visualization-orgchart-lineleft, 
    .google-visualization-orgchart-lineright, 
    .google-visualization-orgchart-linebottom {
        border-width: 1px !important;
    }
</style>

<div class="container-fluid px-4 mt-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-primary fw-bold mb-1"><i class="fas fa-sitemap me-2"></i> Sơ đồ Tổ chức</h4>
            <small class="text-muted">
                Sơ đồ cấu trúc phòng ban tại: 
                <span class="fw-bold text-dark">
                    <?php echo isset($_SESSION['site_name']) ? $_SESSION['site_name'] : 'N/A'; ?>
                </span>
            </small>
        </div>
        
        <a href="<?= url('/admin') ?>/departments" class="btn btn-outline-secondary fw-bold">
            <i class="fas fa-list me-1"></i> Danh sách
        </a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom-0 d-flex justify-content-between align-items-center">
            <!-- Info -->
            <div class="small text-muted">
                <i class="fas fa-info-circle me-1"></i>
                Click node để expand/collapse
            </div>
            
            <!-- Controls -->
            <div class="d-flex align-items-center gap-2">
                <div class="form-check form-switch m-0 d-flex align-items-center">
                    <input class="form-check-input me-2" type="checkbox" id="toggleCompact" onchange="toggleCompactMode()" checked>
                    <label class="form-check-label small fw-bold text-secondary mb-0" for="toggleCompact">Thu gọn</label>
                </div>
                <div class="btn-group shadow-sm" role="group">
                    <button type="button" class="btn btn-sm btn-light border" onclick="zoomChart(0.1)" title="Phóng to"><i class="fas fa-search-plus"></i></button>
                    <button type="button" class="btn btn-sm btn-light border" onclick="resetZoom()" title="Mặc định"><i class="fas fa-compress"></i></button>
                    <button type="button" class="btn btn-sm btn-light border" onclick="zoomChart(-0.1)" title="Thu nhỏ"><i class="fas fa-search-minus"></i></button>
                    <button type="button" class="btn btn-sm btn-light border" onclick="fitToScreen()" title="Fit màn hình"><i class="fas fa-expand-arrows-alt"></i></button>
                </div>
            </div>
        </div>
        <div class="card-body p-2">
            <?php if (!empty($chartData)): ?>
                <div class="overflow-auto text-center py-3" style="background-color: #f8f9fa; border-radius: 6px; height: calc(100vh - 240px); min-height: 550px;">
                    <div id="chart_wrapper" style="transition: transform 0.3s ease; display: inline-block; min-width: 100%;">
                        <div id="chart_div"></div>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center p-5 text-muted">
                    <i class="fas fa-folder-open fa-2x mb-2 opacity-50"></i><br>Chưa có dữ liệu phòng ban để vẽ sơ đồ.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($chartData)): ?>
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<script type="text/javascript">
    google.charts.load('current', {packages:['orgchart']});
    google.charts.setOnLoadCallback(drawChart);
    var chart;

    function drawChart() {
        var data = new google.visualization.DataTable();
        data.addColumn('string', 'Name');
        data.addColumn('string', 'Manager');
        data.addColumn('string', 'ToolTip');

        data.addRows(<?php echo json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);

        chart = new google.visualization.OrgChart(document.getElementById('chart_div'));
        
        var options = {
            'allowHtml': true,
            'allowCollapse': true,
            'size': 'medium',
            'nodeClass': 'google-visualization-orgchart-node',
            'selectedNodeClass': 'google-visualization-orgchart-node-sel'
        };

        chart.draw(data, options);
        
        // Mặc định bật compact mode
        setTimeout(function() {
            document.getElementById('chart_div').classList.add('chart-compact');
        }, 100);
    }

    // Zoom Logic
    var currentScale = 1;
    function zoomChart(delta) {
        currentScale += delta;
        if (currentScale < 0.5) currentScale = 0.5;
        if (currentScale > 2) currentScale = 2;
        document.getElementById('chart_wrapper').style.transform = 'scale(' + currentScale + ')';
    }
    
    function resetZoom() {
        currentScale = 1;
        document.getElementById('chart_wrapper').style.transform = 'scale(1)';
    }
    
    function fitToScreen() {
        currentScale = 0.9;
        document.getElementById('chart_wrapper').style.transform = 'scale(0.9)';
    }

    // Compact Mode Logic
    function toggleCompactMode() {
        var chartDiv = document.getElementById('chart_div');
        if (document.getElementById('toggleCompact').checked) {
            chartDiv.classList.add('chart-compact');
        } else {
            chartDiv.classList.remove('chart-compact');
        }
    }
</script>
<?php endif; ?>

